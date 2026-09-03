<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sổ giao dịch của một đơn hàng: mọi khoản thu và mọi khoản hoàn, cho MỌI loại đơn.
 *
 * ## Vì sao tách ra khỏi `GroupBookingService`
 *
 * Sổ này ra đời cùng booking đoàn nên nằm nhờ trong dịch vụ của đoàn, và ở đó có một câu chặn:
 * đơn lẻ không được ghi sổ, "đơn lẻ thanh toán một lần qua cổng". Lý do khi ấy đúng — mở sổ cho
 * đơn lẻ là hai nguồn sự thật về cùng một khoản tiền.
 *
 * Lý do đó hết hiệu lực từ khi đơn lẻ cũng trả nhiều lần: cọc trước, phần còn lại sau, và có thể
 * bằng chuyển khoản hay tiền mặt tại văn phòng chứ không qua cổng nào cả. Lúc đó câu hỏi "đơn này
 * đã thu bao nhiêu" không còn trả lời được bằng một cột `paid_at`.
 *
 * Nên sổ trở thành nguồn duy nhất cho mọi đơn, và `GroupBookingService` gọi sang đây.
 *
 * ## Bốn quy tắc, giữ nguyên từ bản cũ
 *
 * 1. Chỉ thêm dòng, không sửa dòng cũ. Ghi nhầm thì ghi một dòng ngược lại.
 * 2. Số tiền luôn dương; `kind` quyết định dấu.
 * 3. Thu thì đơn phải còn sống; hoàn thì được cả sau khi hủy — đó mới là lúc thường phải hoàn.
 * 4. Không bao giờ hoàn quá số đã thu.
 */
class BookingPaymentService
{
    public function __construct(private readonly BookingAuditLogger $auditLogger)
    {
    }

    /**
     * Ghi một khoản thu hoặc hoàn vào sổ.
     *
     * `$actor` nhận null cho các khoản do hệ thống ghi — cổng thanh toán báo tiền về chẳng hạn.
     * Ở đó không có con người nào bấm nút, và ghi bừa một người vào cột `recorded_by` là nói dối
     * đúng cái cột dùng để quy trách nhiệm.
     */
    public function record(
        Booking $booking,
        string $kind,
        float $amount,
        ?string $method = null,
        ?string $reference = null,
        ?string $note = null,
        ?User $actor = null,
    ): BookingPayment {
        if (!in_array($kind, [...BookingPayment::THU, BookingPayment::HOAN], true)) {
            throw new BusinessRuleException('Loại bút toán không hợp lệ.');
        }

        if ($amount <= 0) {
            throw new BusinessRuleException(
                'Số tiền phải lớn hơn 0. Muốn ghi hoàn thì chọn loại "hoàn tiền", không ghi số âm.',
            );
        }

        return DB::transaction(function () use ($booking, $kind, $amount, $method, $reference, $note, $actor) {
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (in_array($kind, BookingPayment::THU, true)
                && in_array($fresh->status, ['cancelled', 'transferred'], true)) {
                throw new BusinessRuleException(
                    'Đơn đã ' . ($fresh->status === 'cancelled' ? 'hủy' : 'chuyển đi')
                    . ', không ghi thêm khoản thu được nữa. Ghi hoàn thì vẫn được.',
                );
            }

            $daThu = $this->netPaid($fresh);

            if ($kind === BookingPayment::HOAN && round($amount) > $daThu) {
                throw new BusinessRuleException(sprintf(
                    'Không hoàn quá số đã thu: đơn này mới thu thực %s đ.',
                    number_format($daThu, 0, ',', '.'),
                ));
            }

            /*
             * Không thu quá số đơn còn thiếu — luật đặt ở ĐÂY, không ở từng nơi gọi.
             *
             * Phép chặn này vốn chỉ có ở `recordManualCollection()`, tức chỉ bảo vệ đúng nút "xác
             * nhận đơn". Màn sổ giao dịch (`POST /admin/bookings/{id}/payments`) gọi thẳng hàm này
             * nên đi vòng qua được: gõ nhầm 20.000.000 cho một đơn 2.000.000 thì hệ thống nhận, và
             * 18 triệu thừa không hiện ở màn hình nào — `balance_due` về 0 nên nó rời khỏi danh sách
             * phải thu, còn `refund_amount` vẫn rỗng nên nó không bao giờ vào danh sách phải trả.
             *
             * Nói cách khác: tiền thừa của khách biến mất khỏi mọi báo cáo. Đó là lý do luật phải
             * nằm ở cửa duy nhất mà mọi bút toán đều đi qua.
             */
            if (in_array($kind, BookingPayment::THU, true)) {
                $conThieu = max(0.0, round((float) $fresh->total_amount) - $daThu);

                if (round($amount) > $conThieu) {
                    throw new BusinessRuleException(sprintf(
                        'Đơn này chỉ còn thiếu %s đ, không ghi thu quá số đó được. Kiểm lại số tiền '
                            . 'vừa nhập; nếu khách thật sự chuyển thừa thì ghi đúng phần của đơn rồi '
                            . 'hoàn lại phần dư.',
                        number_format($conThieu, 0, ',', '.'),
                    ));
                }
            }

            $payment = BookingPayment::query()->create([
                'booking_id' => $fresh->getKey(),
                'kind' => $kind,
                'amount' => round($amount, 2),
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'paid_at' => now(),
                'recorded_by' => $actor?->getKey(),
            ]);

            /*
             * Thu đủ thì đóng mốc `paid_at` — một lần, không lùi.
             *
             * Mốc này nghĩa là "đã từng thu đủ giá tour". Các luồng sẵn có đọc nó như với đơn trả
             * một lần: chặn khách tự hủy, bắt yêu cầu hủy phải qua duyệt. Hoàn tiền về sau không
             * xóa mốc — số thực còn giữ nằm ở sổ, không ở cột này.
             */
            if ($fresh->paid_at === null && $this->netPaid($fresh) >= round((float) $fresh->total_amount)) {
                $fresh->forceFill(['paid_at' => now()])->save();
            }

            $this->auditLogger->log($fresh, BookingAuditAction::PaymentRecorded, null, [
                'kind' => $kind,
                'amount' => round($amount),
                'method' => $method,
                'net_paid_after' => $this->netPaid($fresh),
            ], $note);

            return $payment;
        });
    }

    /**
     * Số đã thu thực cho GIÁ TOUR: tổng các khoản thu trừ tổng các khoản hoàn.
     *
     * Cộng trên quan hệ đã nạp nếu có, để một màn hình liệt kê nhiều đơn không sinh hai truy vấn
     * cho mỗi đơn. Đường GHI không bao giờ rơi vào nhánh ấy: `record()` luôn đọc lại đơn dưới
     * khóa dòng, và bản đọc lại đó chưa nạp quan hệ nào — nên nó không thể cộng nhầm trên một
     * danh sách cũ.
     */
    public function netPaid(Booking $booking): float
    {
        if ($booking->relationLoaded('payments')) {
            $rows = $booking->getRelation('payments');

            return round(
                (float) $rows->whereIn('kind', BookingPayment::THU)->sum('amount')
                - (float) $rows->where('kind', BookingPayment::HOAN)->sum('amount'),
            );
        }

        $thu = (float) $booking->payments()->whereIn('kind', BookingPayment::THU)->sum('amount');
        $hoan = (float) $booking->payments()->where('kind', BookingPayment::HOAN)->sum('amount');

        return round($thu - $hoan);
    }

    /**
     * Ghi khoản tiền thu được lúc XÁC NHẬN TAY một đơn đang chờ.
     *
     * Dùng chung cho quản trị và hướng dẫn viên: hai màn hình khác nhau nhưng cùng một nghiệp vụ -
     * người của công ty cầm tiền của khách rồi khẳng định đơn này đã trả. Viết riêng ở hai chỗ là
     * đúng khuôn lỗi mà dự án này đã gặp nhiều lần.
     *
     * Chặn thu quá số còn thiếu: đơn 4 triệu mà gõ nhầm thành 40 triệu thì tiền thừa thành một
     * khoản nợ ngược mà không luồng nào phát hiện, vì hàng đợi hoàn tiền chỉ đọc `refund_amount`.
     */
    public function recordManualCollection(
        Booking $booking,
        float $amount,
        string $method,
        ?string $reference = null,
        ?string $note = null,
        ?User $actor = null,
    ): BookingPayment {
        $conThieu = $this->balanceDue($booking);

        if ($conThieu <= 0) {
            throw new BusinessRuleException(
                'Đơn này đã thu đủ rồi, không ghi thêm khoản thu nào được nữa.',
            );
        }

        if (round($amount) > $conThieu) {
            throw new BusinessRuleException(sprintf(
                'Đơn này chỉ còn thiếu %s đ, không thu quá số đó được. Kiểm lại số tiền vừa nhập.',
                number_format($conThieu, 0, ',', '.'),
            ));
        }

        return $this->record(
            $booking,
            // Thu một lần đủ hay thu trước một phần đều là tiền của giá tour; `balance` là nhãn
            // chung của nhóm THU nên mọi phép cộng đối xử với chúng như nhau.
            'balance',
            $amount,
            $method,
            $reference,
            $note ?? 'Thu tay khi xác nhận đơn',
            $actor,
        );
    }

    /**
     * Số đã thu thực cho GIÁ TOUR, kể cả với đơn chưa từng dùng sổ.
     *
     * Đây là nguồn DUY NHẤT cho câu hỏi "đơn này đã thu bao nhiêu". Trước đây câu hỏi ấy được trả
     * lời ở ba chỗ: `CancellationPolicyService::paidAmount()`, `ContractPrintController::daThu()`
     * và `ScheduleCancellationService::soTienDaTra()`. Hai chỗ đầu đọc sổ; chỗ thứ ba đọc `paid_at`
     * rồi nhân với giá đơn — và vì `paid_at` chỉ đóng khi đã thu ĐỦ, một đơn mới trả cọc bị nó trả
     * về 0. Hủy cả chuyến theo con số đó nghĩa là hoàn 0 đồng cho người đã đưa tiền cọc.
     *
     * Hai nguồn, chọn theo đơn:
     *
     *   - Sổ có dòng của giá tour: tổng thu trừ tổng hoàn. Nhánh này làm cho phép tính "mất cọc"
     *     chạy đúng, và phản ánh được cả những khoản đã hoàn một phần.
     *   - Sổ trống: đọc mốc `paid_at` như cũ, cho các đơn tạo trước khi sổ mở cho đơn lẻ.
     *
     * Câu hỏi "sổ có dòng chưa" và phép cộng phải lọc trên CÙNG một tập loại bút toán. Lệch nhau là
     * cách một đơn lẻ đã trả đủ, có thêm một dòng phụ thu sự cố, bỗng báo đã thu 0 đồng.
     */
    public function paidForTour(Booking $booking): float
    {
        $loaiGiaTour = [...BookingPayment::THU, BookingPayment::HOAN];

        $coSo = $booking->relationLoaded('payments')
            ? $booking->getRelation('payments')->whereIn('kind', $loaiGiaTour)->isNotEmpty()
            : $booking->payments()->whereIn('kind', $loaiGiaTour)->exists();

        if ($coSo) {
            return $this->netPaid($booking);
        }

        return $booking->paid_at ? round((float) $booking->total_amount) : 0.0;
    }

    /** Tổng đã hoàn cho giá tour. Dùng để biết một khoản hoàn đã trả xong chưa. */
    public function refunded(Booking $booking): float
    {
        if ($booking->relationLoaded('payments')) {
            return round(
                (float) $booking->getRelation('payments')->where('kind', BookingPayment::HOAN)->sum('amount'),
            );
        }

        return round((float) $booking->payments()->where('kind', BookingPayment::HOAN)->sum('amount'));
    }

    /**
     * Số tiền còn nợ khách sau khi hủy.
     *
     * `refund_amount` là nghĩa vụ chốt tại thời điểm hủy; các dòng `refund` trong sổ là những gì
     * đã thực trả. Hiệu số là thứ kế toán còn phải chi, và là thứ màn hình "Chờ hoàn tiền" đọc.
     *
     * Đơn chưa hủy thì `refund_amount` là null và hàm này trả 0 — không có nghĩa vụ nào.
     */
    public function refundOutstanding(Booking $booking): float
    {
        return max(0.0, round((float) ($booking->refund_amount ?? 0)) - $this->refunded($booking));
    }

    /**
     * Quy một mức hoàn do bảng phí quyết về đúng quy ước GỘP của cột `refund_amount`.
     *
     * Cột ấy mang nghĩa "tổng nghĩa vụ từ đầu tới giờ", vì `refundOutstanding()` luôn trừ đi các
     * dòng đã hoàn. `syncRefundDueAfterPriceDrop()` viết đúng quy ước ấy; các đường HỦY thì không —
     * chúng ghi thẳng số từ `CancellationPolicyService::quote()`, mà số đó tính trên `netPaid` nên
     * đã trừ khoản hoàn trước đó rồi. Trừ hai lần, và lần nào cũng là tiền thật.
     *
     * Cảnh lộ ra: đơn 10 triệu, khách cọc 5 triệu, được chuyển sang chuyến rẻ hơn nên đơn còn 4
     * triệu và kế toán đã chi lại 1 triệu. Khách hủy ở mốc còn hoàn đủ. `quote()` đọc `netPaid` = 4
     * triệu; cột ghi 4 triệu; `refundOutstanding()` trừ tiếp 1 triệu đã chi thành 3 triệu — trong
     * khi công ty đang giữ 4 triệu của họ. Chi thiếu đúng bằng phần đã hoàn, và không màn hình nào
     * phát hiện vì đơn rời khỏi hàng đợi ngay khi chi nốt con số sai ấy.
     */
    public function nghiaVuHoanGop(Booking $booking, float $hoanTheoBangPhi): float
    {
        return round($this->refunded($booking) + $hoanTheoBangPhi);
    }

    /**
     * Tổng tiền THỰC THU của một tập đơn, dùng cho các con số tổng kết.
     *
     * Các màn hình thống kê vẫn cộng `total_amount` và gọi kết quả là doanh thu. Đó là giá trị đơn
     * hàng, không phải tiền đã về: một đơn vừa xác nhận mà khách còn nợ vẫn cộng đủ, nên con số ấy
     * luôn cao hơn số dư tài khoản thật và không đối chiếu được với bất cứ thứ gì.
     *
     * Tính theo lô chứ không gọi `paidForTour()` cho từng đơn: một trang danh sách vài trăm đơn sẽ
     * sinh vài trăm truy vấn cho một ô thống kê.
     *
     * @param  \Illuminate\Support\Collection<int, Booking>  $bookings  cần có sẵn `total_amount` và `paid_at`
     */
    public function sumPaidForTour($bookings): float
    {
        if ($bookings->isEmpty()) {
            return 0.0;
        }

        $ids = $bookings->pluck('id');

        $tongTheoLoai = fn (array|string $kind) => BookingPayment::query()
            ->whereIn('booking_id', $ids)
            ->when(is_array($kind), fn ($q) => $q->whereIn('kind', $kind), fn ($q) => $q->where('kind', $kind))
            ->groupBy('booking_id')
            ->selectRaw('booking_id, SUM(amount) as tong')
            ->pluck('tong', 'booking_id');

        $thu = $tongTheoLoai(BookingPayment::THU);
        $hoan = $tongTheoLoai(BookingPayment::HOAN);

        return round($bookings->sum(function (Booking $booking) use ($thu, $hoan) {
            $coSo = $thu->has($booking->id) || $hoan->has($booking->id);

            if ($coSo) {
                return (float) ($thu[$booking->id] ?? 0) - (float) ($hoan[$booking->id] ?? 0);
            }

            // Đơn tạo trước khi sổ mở cho đơn lẻ: đọc mốc `paid_at`, đúng như `paidForTour()`.
            return $booking->paid_at ? round((float) $booking->total_amount) : 0.0;
        }));
    }

    /**
     * Tiền thực thu cho GIÁ TOUR trong một khoảng thời gian, tính theo NGÀY TIỀN VỀ.
     *
     * ## Vì sao cần, dù đã có `sumPaidForTour()`
     *
     * Hàm kia trả lời "tập đơn này đã thu bao nhiêu" — không có chiều thời gian. Bảng điều khiển
     * lại hỏi "tháng này thu được bao nhiêu", và trước đây nó trả lời bằng cách lọc đơn theo
     * `bookings.created_at` rồi cộng số đã thu của chúng. Hai chuyện khác hẳn nhau: một đơn đặt
     * cuối tháng trước, trả tiền đầu tháng này, sẽ được cộng vào **tháng trước** — tức doanh thu
     * gắn với ngày khách bấm đặt chứ không phải ngày tiền vào tài khoản.
     *
     * Với tour thì độ lệch ấy không nhỏ: đơn đoàn trả cọc rồi trả nốt cách nhau hàng tuần, và mỗi
     * đợt tiền lại bị quy về đúng cái tháng đơn được tạo. Con số ấy không đối chiếu được với sao kê
     * ngân hàng, mà đó là việc duy nhất người ta dùng nó.
     *
     * ## Đơn cũ chưa có sổ
     *
     * Các đơn tạo trước khi sổ mở cho đơn lẻ không có bút toán nào; với chúng, mốc `bookings.paid_at`
     * là bằng chứng duy nhất còn lại về thời điểm tiền về. Cộng riêng nhóm ấy, và chỉ nhóm KHÔNG có
     * dòng nào trong sổ — nếu không thì một đơn vừa có sổ vừa có mốc sẽ bị cộng hai lần.
     *
     * ## Lọc theo trạng thái đơn
     *
     * `$trangThaiDon` để hỏi "doanh thu của nhóm đơn tính vào doanh thu" mà **không phải nạp từng
     * đơn về bộ nhớ** như `sumPaidForTour()`. Cùng một định nghĩa, chỉ khác là phép cộng chạy
     * trong cơ sở dữ liệu — đó là thứ giữ cho bảng điều khiển không chậm dần theo số đơn bán được.
     *
     * @param  array<int, string>|null  $trangThaiDon
     */
    public function sumCollectedBetween(?Carbon $tu, ?Carbon $den, ?array $trangThaiDon = null): float
    {
        $trongKhoang = static function ($query, string $cot) use ($tu, $den) {
            if ($tu) {
                $query->where($cot, '>=', $tu);
            }

            if ($den) {
                $query->where($cot, '<=', $den);
            }

            return $query;
        };

        $locTheoDon = static fn ($query) => $trangThaiDon === null
            ? $query
            : $query->whereHas('booking', fn ($b) => $b->whereIn('status', $trangThaiDon));

        $thu = (float) $trongKhoang(
            $locTheoDon(BookingPayment::query()->whereIn('kind', BookingPayment::THU)),
            'paid_at',
        )->sum('amount');

        $hoan = (float) $trongKhoang(
            $locTheoDon(BookingPayment::query()->where('kind', BookingPayment::HOAN)),
            'paid_at',
        )->sum('amount');

        $donCu = (float) $trongKhoang(
            Booking::query()
                ->when($trangThaiDon !== null, fn ($q) => $q->whereIn('status', $trangThaiDon))
                ->whereNotNull('paid_at')
                ->whereDoesntHave('payments', fn ($p) => $p->whereIn(
                    'kind',
                    [...BookingPayment::THU, BookingPayment::HOAN],
                )),
            'paid_at',
        )->sum('total_amount');

        return round($thu - $hoan + $donCu);
    }

    /**
     * Tiền thực thu, gộp sẵn theo từng ngày hoặc từng tháng.
     *
     * ## Vì sao không gọi `sumCollectedBetween()` cho từng mốc
     *
     * Biểu đồ mười hai tháng thì mười hai lần gọi cũng chịu được. Nhưng khi bảng điều khiển nhận
     * được một khoảng ngày và vẽ theo NGÀY, một khoảng hai tháng thành sáu mươi hai lần gọi, mỗi
     * lần ba câu truy vấn — gần hai trăm câu cho một lần mở trang. Hàm này gộp ở cơ sở dữ liệu và
     * mang về đúng số dòng có dữ liệu.
     *
     * ## Cắt mốc bằng `substr`, không dùng hàm ngày tháng
     *
     * `MONTH()` không có ở SQLite, `strftime()` không có ở MySQL. Cột lưu chuỗi "Y-m-d H:i:s" nên
     * mười ký tự đầu là ngày và bảy ký tự đầu là tháng — cách duy nhất chạy giống nhau ở cả hai hệ,
     * và cũng là cách `dashboardData()` đang dùng để gom số đơn theo tháng.
     *
     * Giữ nguyên ba nguồn của `sumCollectedBetween()`: các khoản THU, trừ đi các khoản HOÀN, cộng
     * nhóm đơn cũ chỉ còn mốc `paid_at` mà không có dòng nào trong sổ.
     *
     * @param  array<int, string>|null  $trangThaiDon
     * @return \Illuminate\Support\Collection<string, float>  Khóa là "Y-m-d" hoặc "Y-m".
     */
    public function sumCollectedGrouped(
        ?Carbon $tu,
        ?Carbon $den,
        string $donVi = 'month',
        ?array $trangThaiDon = null,
    ): Collection {
        $soKyTu = $donVi === 'day' ? 10 : 7;

        $trongKhoang = static function ($query, string $cot) use ($tu, $den) {
            if ($tu) {
                $query->where($cot, '>=', $tu);
            }

            if ($den) {
                $query->where($cot, '<=', $den);
            }

            return $query;
        };

        $gop = static fn ($query, string $cot, string $cotTien) => $query
            ->selectRaw("substr({$cot}, 1, {$soKyTu}) as moc, sum({$cotTien}) as tong")
            ->groupBy('moc')
            ->pluck('tong', 'moc');

        $locTheoDon = static fn ($query) => $trangThaiDon === null
            ? $query
            : $query->whereHas('booking', fn ($b) => $b->whereIn('status', $trangThaiDon));

        $thu = $gop(
            $trongKhoang(
                $locTheoDon(BookingPayment::query()->whereIn('kind', BookingPayment::THU)),
                'paid_at',
            ),
            'paid_at',
            'amount',
        );

        $hoan = $gop(
            $trongKhoang(
                $locTheoDon(BookingPayment::query()->where('kind', BookingPayment::HOAN)),
                'paid_at',
            ),
            'paid_at',
            'amount',
        );

        $donCu = $gop(
            $trongKhoang(
                Booking::query()
                    ->when($trangThaiDon !== null, fn ($q) => $q->whereIn('status', $trangThaiDon))
                    ->whereNotNull('paid_at')
                    ->whereDoesntHave('payments', fn ($p) => $p->whereIn(
                        'kind',
                        [...BookingPayment::THU, BookingPayment::HOAN],
                    )),
                'paid_at',
            ),
            'paid_at',
            'total_amount',
        );

        return collect($thu->keys())
            ->merge($hoan->keys())
            ->merge($donCu->keys())
            ->unique()
            ->mapWithKeys(fn (string $moc) => [
                $moc => round(
                    (float) ($thu[$moc] ?? 0)
                        - (float) ($hoan[$moc] ?? 0)
                        + (float) ($donCu[$moc] ?? 0),
                ),
            ]);
    }

    /** Tổng các khoản THU của giá tour, chưa trừ khoản hoàn nào. */
    public function collected(Booking $booking): float
    {
        if ($booking->relationLoaded('payments')) {
            return round(
                (float) $booking->getRelation('payments')->whereIn('kind', BookingPayment::THU)->sum('amount'),
            );
        }

        return round((float) $booking->payments()->whereIn('kind', BookingPayment::THU)->sum('amount'));
    }

    /**
     * Giá đơn vừa giảm xuống dưới số đã thu thì phần thừa thành nghĩa vụ hoàn.
     *
     * Chuyển đơn sang một chuyến rẻ hơn, hay đoàn bớt người, đều ghi đè `total_amount` mà không
     * đụng tới đồng nào đã thu. Trước đây phần chênh chỉ nằm im trong sổ: hàng đợi hoàn tiền đọc
     * `refund_amount`, mà cột ấy chỉ được ghi khi HỦY đơn - nên không màn hình nào biết công ty
     * đang giữ tiền của một khách vẫn đang đi tour. Nó chỉ lộ ra khi chính khách gọi lên đòi.
     *
     * Nghĩa vụ tính bằng `tổng đã THU trừ giá đơn`, không phải `netPaid trừ giá đơn`. Hai cách ra
     * cùng một kết quả ở lần đầu, nhưng chỉ cách thứ nhất còn đúng sau khi kế toán đã trả một phần:
     * `refundOutstanding()` lấy `refund_amount` trừ các dòng đã hoàn, nên `refund_amount` phải là
     * TỔNG nghĩa vụ từ đầu tới giờ chứ không phải phần còn lại. Nhờ vậy gọi lại bao nhiêu lần cũng
     * ra cùng con số, và chuyển chuyến nhiều lần liên tiếp vẫn cộng dồn đúng.
     *
     * Không đụng tới đơn đã ở trạng thái cuối: ở đó `refund_amount` là nghĩa vụ do bảng phí hủy
     * quyết, và đè lên nó bằng phép trừ này là xóa mất kết quả của một lần hủy.
     */
    public function syncRefundDueAfterPriceDrop(Booking $booking): float
    {
        $status = BookingStatus::tryFrom((string) $booking->status);

        if ($status?->isTerminal()) {
            return 0.0;
        }

        $thua = round($this->collected($booking) - round((float) $booking->total_amount));

        if ($thua <= 0) {
            return 0.0;
        }

        $booking->forceFill(['refund_amount' => $thua])->save();

        return $thua;
    }

    /**
     * Số tiền của LẦN TRẢ SẮP TỚI — khác với số còn thiếu.
     *
     * Bán theo cọc nghĩa là một đơn có hai lần trả, và hai lần ấy khác số nhau:
     *
     *   - Chưa trả đồng nào → lần này là TIỀN CỌC, không phải cả giá tour.
     *   - Đã cọc rồi → lần này là toàn bộ phần còn thiếu.
     *
     * Thiếu phép phân biệt này thì trang tra cứu dựng liên kết thanh toán bằng số còn thiếu, và với
     * đơn vừa đặt xong thì số đó đúng bằng giá tour — khách bấm vào và bị đòi trả đủ, trong khi thư
     * xác nhận và trang đặt tour vừa nói với họ là chỉ cần cọc.
     *
     * Kẹp bằng số còn thiếu để không bao giờ đòi quá: đơn có giá trị tụt xuống sau khi chuyển sang
     * chuyến rẻ hơn vẫn ra con số đúng.
     */
    public function nextPaymentAmount(Booking $booking): float
    {
        $conThieu = $this->balanceDue($booking);

        if ($conThieu <= 0) {
            return 0.0;
        }

        if ($this->netPaid($booking) > 0) {
            return $conThieu;
        }

        /*
         * Đặt sát ngày khởi hành thì KHÔNG có hai đợt — thu đủ ngay.
         *
         * Hạn trả nốt là ngày khởi hành trừ mười ngày, nên khách đặt tour đi trong tuần tới có hạn
         * ấy nằm ở quá khứ. Cho họ cọc nghĩa là tạo ra một đơn quá hạn ngay lúc vừa sinh: trang tra
         * cứu báo đỏ "đã quá hạn thanh toán" trước cả khi họ đóng tab, và sáng hôm sau lệnh hủy
         * quét đơn ấy — khách mất cọc vì một cái hạn không ai kịp làm gì.
         *
         * Đây cũng là cách các hãng vẫn bán: cọc là ưu đãi cho người đặt sớm, đổi lại công ty có
         * thời gian xoay xở. Không còn thời gian thì không còn cọc.
         */
        $hanTraNot = $booking->balanceDueAt();

        if ($hanTraNot && now()->gte($hanTraNot)) {
            return $conThieu;
        }

        return min($conThieu, $booking->depositAmount());
    }

    /**
     * Đơn này còn nợ tiền, mà quy trình thu nốt tự động KHÔNG còn kịp chạy hết trước hạn chốt.
     *
     * Việc thu nốt bình thường do hai tác vụ nền lo, và chúng cần thời gian thật:
     *
     *   1. Lệnh nhắc chạy mỗi ngày một lần, nên thư sớm nhất cũng phải sang hôm sau mới đi.
     *   2. Lệnh hủy chỉ đụng tới đơn sau khi đã qua `balance_final_notice_days` ngày kể từ lá thư ấy.
     *
     * Cộng lại là `ân hạn + 1` ngày trước khi một lượt hủy có thể xảy ra.
     *
     * ## Vì sao đo tới HẠN CHỐT DANH SÁCH chứ không phải ngày khởi hành
     *
     * Cả dây chuyền này chỉ có ích khi lượt hủy còn kịp **trả chỗ về kho để bán lại**. Mà chỗ chỉ về
     * kho khi hủy trước hạn chốt: sau mốc đó phòng, ghế và suất ăn đã chốt theo danh sách gửi nhà
     * cung cấp, nên `BookingHoldService::shouldReleaseSeats()` giữ nguyên số chỗ và đơn thành ghế
     * chết — công ty đã trả tiền cho một chỗ không có khách ngồi.
     *
     * Đo tới ngày khởi hành thì bỏ lọt đúng khoảng nguy hiểm ấy, và bỏ lọt theo một cách khó chịu:
     * hạn chốt mang đúng giờ khởi hành của chuyến, còn lệnh hủy chạy 09:30 mỗi sáng. Nên tour đi
     * buổi tối thì chỗ kịp về kho, tour đi 5 giờ sáng thì thành ghế chết — cùng một tình huống
     * nghiệp vụ, hai kết cục, và thứ quyết định là giờ xe lăn bánh. Không luật nào nên phụ thuộc
     * vào một sự trùng hợp như thế.
     *
     * Chỉ xảy ra khi đơn bị ĐỔI NGÀY sau lúc đặt — ghép chuyến, chuyển chuyến. Đơn đặt thẳng vào
     * chuyến sát ngày đã bị thu đủ tiền ngay từ đầu, xem `nextPaymentAmount()`.
     *
     * Hàm này không quyết định gì cả, nó chỉ trả lời "có phải gọi người không". Câu trả lời đúng cho
     * tình huống ấy luôn là có: hủy đơn của người vừa bị công ty dời ngày, vào lúc đã muộn để bán
     * lại chỗ, là thiệt cho cả hai bên.
     */
    public function tuDongThuNotKhongKip(Booking $booking): bool
    {
        if ($this->balanceDue($booking) <= 0) {
            return false;
        }

        $schedule = $booking->schedule;

        $mocCuoi = $schedule
            ? ($schedule->booking_deadline ?? $schedule->defaultBookingDeadline())
            : $booking->departure_date;

        if (!$mocCuoi) {
            return false;
        }

        $canToiThieu = (int) config('booking.balance_final_notice_days', 2) + 1;

        return now()->addDays($canToiThieu)->gte(\Illuminate\Support\Carbon::parse($mocCuoi));
    }

    /** Còn thiếu bao nhiêu so với tổng giá trị đơn. */
    public function balanceDue(Booking $booking): float
    {
        return max(0.0, round((float) $booking->total_amount) - $this->netPaid($booking));
    }
}
