<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
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
     */
    public function sumCollectedBetween(?Carbon $tu, ?Carbon $den): float
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

        $thu = (float) $trongKhoang(
            BookingPayment::query()->whereIn('kind', BookingPayment::THU),
            'paid_at',
        )->sum('amount');

        $hoan = (float) $trongKhoang(
            BookingPayment::query()->where('kind', BookingPayment::HOAN),
            'paid_at',
        )->sum('amount');

        $donCu = (float) $trongKhoang(
            Booking::query()
                ->whereNotNull('paid_at')
                ->whereDoesntHave('payments', fn ($p) => $p->whereIn(
                    'kind',
                    [...BookingPayment::THU, BookingPayment::HOAN],
                )),
            'paid_at',
        )->sum('total_amount');

        return round($thu - $hoan + $donCu);
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

    /** Còn thiếu bao nhiêu so với tổng giá trị đơn. */
    public function balanceDue(Booking $booking): float
    {
        return max(0.0, round((float) $booking->total_amount) - $this->netPaid($booking));
    }
}
