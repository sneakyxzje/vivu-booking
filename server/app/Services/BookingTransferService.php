<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Enums\TransferReasonCategory;
use App\Models\Booking;
use App\Models\BookingTransfer;
use App\Models\CustomerContactLog;
use App\Models\TourSchedule;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * I02, I03 - Chuyển đơn sang chuyến khác.
 *
 * Câu hỏi hội đồng nêu đầu tiên. Luật ở docs/nghiep-vu/02-luong-dat-tour.md mục 4.
 *
 * Đây là chỗ khó nhất của cả hệ thống, vì phải khóa **hai** chuyến cùng lúc: chuyến gốc để trả
 * chỗ và chuyến đích để lấy chỗ. Hai tài nguyên khóa cùng lúc là chỗ sinh ra khóa chết, và cách
 * chống nằm ở mục thứ tự khóa bên dưới.
 */
class BookingTransferService
{
    /** Số lần chuyển được miễn phí. Từ lần sau thu phí đổi lịch. */
    public const FREE_TRANSFERS = 1;

    public function __construct(
        private ScheduleLifecycleService $lifecycle,
        private BookingAuditLogger $auditLogger,
        private BookingPaymentService $payments,
        private Notifier $notifier,
    ) {
    }

    /**
     * Báo điều hành khi đơn còn nợ vừa hạ cánh xuống một chuyến quá sát ngày.
     *
     * Gọi SAU khi giao dịch đã ghi xong, cùng lý do với mọi chỗ gửi thư trong hệ thống: thông báo
     * đã bay đi thì không gọi về được, còn giao dịch thì vẫn có thể quay lại.
     *
     * Luật "thế nào là quá sát" nằm ở `BookingPaymentService::tuDongThuNotKhongKip()`, dùng chung
     * với luồng ghép chuyến. Chép nó về đây là tạo ra bản thứ hai của một luật, đúng thứ dự án này
     * đã vấp nhiều lần.
     */
    private function canhBaoNeuKhongKipThuNot(Booking $booking): void
    {
        // `booking_deadline` bắt buộc phải có: `tuDongThuNotKhongKip()` đo tới hạn chốt, mà cột
        // thiếu thì nó đọc null rồi lùi về mặc định — và màn xem trước (dùng bản ghi đầy đủ) sẽ nói
        // một đằng còn cảnh báo sau khi bấm nói một nẻo, cách nhau vài giây.
        $moi = Booking::query()
            ->with(['schedule:id,start_date,booking_deadline', 'tour:id,title'])
            ->find($booking->getKey());

        if (!$moi || !$this->payments->tuDongThuNotKhongKip($moi)) {
            return;
        }

        $this->notifier->toiDieuHanh(
            Alert::CON_NO_SAT_NGAY,
            sprintf(
                'Đơn #%d còn thiếu %s đ mà chuyến khởi hành %s',
                $moi->id,
                number_format($this->payments->balanceDue($moi), 0, ',', '.'),
                $moi->schedule?->start_date?->format('d/m') ?? 'rất gần',
            ),
            sprintf(
                '%s · vừa đổi ngày nên quy trình nhắc và hủy tự động không còn kịp chạy. '
                    . 'Gọi khách thu nốt, hoặc duyệt cho đi rồi thu sau.',
                $moi->tour?->title ?? 'Tour',
            ),
            '/admin/bookings',
        );
    }

    /**
     * Xem trước hậu quả của việc chuyển, không thay đổi gì.
     *
     * Điều hành cần biết chênh lệch giá và lý do bị chặn trước khi bấm, giống hệt lý do màn hủy
     * đơn phải có dự báo: người bấm nút chịu trách nhiệm thì phải được biết trước.
     *
     * ## Vì sao có thêm cảnh báo về hạn trả nốt
     *
     * Hạn trả nốt suy ra từ NGÀY KHỞI HÀNH, nên chuyển đơn sang chuyến sớm hơn là kéo cái hạn ấy
     * lùi lại — có khi lùi vào quá khứ. Đơn đang yên lành bỗng thành đơn quá hạn, không phải vì
     * khách chậm trễ mà vì người vừa bấm nút này.
     *
     * Điều hành không có cách nào tự nhìn ra điều đó: màn hình cho họ chọn chuyến đích theo chỗ
     * trống và ngày đi, không ai nhẩm trong đầu "ngày đi trừ mười". Nên họ bấm chuyển, rồi khách
     * nhận thư đòi trả nốt trong hai ngày, rồi tổng đài nhận cuộc gọi hỏi vì sao.
     *
     * @return array{can_transfer: bool, blocked_reason: string|null, price_difference: float, fee: float, new_total: float, transfer_count: int, balance_due: float, balance_due_at: string|null, balance_overdue_after: bool, auto_collect_too_late: bool}
     */
    public function preview(
        Booking $booking,
        TourSchedule $toSchedule,
        string $initiatedBy = 'customer',
        bool $nguonBiHuy = false,
        ?TransferReasonCategory $nhomLyDo = null,
    ): array {
        $coThe = true;
        $lyDoChan = null;

        try {
            $this->assertCanTransfer($booking, $booking->schedule, $toSchedule, $initiatedBy, $nguonBiHuy);
        } catch (BusinessRuleException $e) {
            $coThe = false;
            $lyDoChan = $e->getMessage();
        }

        $tongMoi = $this->recalculateTotal($booking, $toSchedule);
        $phi = $this->transferFee($booking, $initiatedBy, $nhomLyDo);

        /*
         * Dự báo tình trạng nợ SAU khi chuyển, tính trên chuyến đích chứ không phải chuyến hiện tại.
         *
         * Dựng một bản sao trong bộ nhớ để hỏi, không đụng vào đơn thật: đây là màn xem trước, và
         * xem trước mà ghi vào cơ sở dữ liệu thì người bấm "hủy" vẫn để lại dấu vết.
         */
        $thu = $booking->replicate();
        $thu->setRelation('schedule', $toSchedule);
        $thu->departure_date = $toSchedule->start_date;
        $thu->total_amount = $tongMoi + $phi;
        $thu->exists = true;
        $thu->id = $booking->getKey();

        $hanTraNotMoi = $thu->balanceDueAt();

        return [
            'can_transfer' => $coThe,
            'blocked_reason' => $lyDoChan,
            'price_difference' => round($tongMoi - (float) $booking->total_amount, 2),
            'fee' => $phi,
            'new_total' => $tongMoi,
            'transfer_count' => (int) $booking->transfer_count,

            // Còn thiếu bao nhiêu sau khi tính lại theo giá chuyến đích.
            'balance_due' => $this->payments->balanceDue($thu),
            'balance_due_at' => $hanTraNotMoi?->toDateTimeString(),
            // Chuyển xong là quá hạn ngay: khách sẽ nhận thư đòi trả nốt trong vài ngày.
            'balance_overdue_after' => $this->payments->balanceDue($thu) > 0
                && $hanTraNotMoi !== null
                && now()->gte($hanTraNotMoi),
            // Nặng hơn một bậc: quá sát để cả quy trình nhắc rồi hủy kịp chạy, phải thu tay.
            'auto_collect_too_late' => $this->payments->tuDongThuNotKhongKip($thu),
        ];
    }

    /**
     * Chuyển đơn sang chuyến khác.
     *
     * Khóa hai chuyến theo thứ tự khóa chính tăng dần. Nếu luồng A khóa chuyến 5 rồi chờ chuyến
     * 9, trong khi luồng B khóa chuyến 9 rồi chờ chuyến 5, cả hai chờ nhau vô hạn. Sắp xếp id
     * trước khi khóa triệt tiêu khả năng đó, vì mọi luồng đều xin khóa theo cùng một thứ tự.
     *
     * Xem docs/nghiep-vu/02-luong-dat-tour.md mục 4.4.
     */
    public function transfer(
        Booking $booking,
        TourSchedule $toSchedule,
        string $reason,
        ?User $actor = null,
        string $initiatedBy = 'customer',
        bool $nguonBiHuy = false,
        ?CustomerContactLog $canCu = null,
        ?TransferReasonCategory $nhomLyDo = null,
    ): BookingTransfer {
        $this->assertDaHoiKhach($booking, $canCu, $nguonBiHuy);

        $banGhi = DB::transaction(function () use ($booking, $toSchedule, $reason, $actor, $initiatedBy, $nguonBiHuy, $canCu, $nhomLyDo) {
            $ids = collect([$booking->tour_schedule_id, $toSchedule->getKey()])
                ->filter()
                ->unique()
                ->sort()
                ->values();

            $schedules = TourSchedule::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (!$locked) {
                throw new BusinessRuleException('Không tìm thấy đơn đặt tour.', 404);
            }

            $chuyenGoc = $schedules->get($locked->tour_schedule_id);
            $chuyenDich = $schedules->get($toSchedule->getKey());

            if (!$chuyenDich) {
                throw new BusinessRuleException('Không tìm thấy chuyến đích.', 404);
            }

            // Kiểm tra lại trên bản ghi đã khóa. Chuyến đích có thể vừa đầy chỗ, hoặc đơn vừa bị
            // hủy, trong khoảng giữa lúc xem trước và lúc bấm xác nhận.
            $this->assertCanTransfer($locked, $chuyenGoc, $chuyenDich, $initiatedBy, $nguonBiHuy);

            $tongCu = (float) $locked->total_amount;
            $tongMoi = $this->recalculateTotal($locked, $chuyenDich);
            $phi = $this->transferFee($locked, $initiatedBy, $nhomLyDo);
            // Số GHẾ chuyển theo đơn, không phải số người: em bé đi cùng không chiếm chỗ ở đầu nào.
            $soKhach = $locked->seatsTaken();

            // Trả chỗ ở chuyến gốc trước rồi mới lấy chỗ ở chuyến đích. Ngược lại thì có lúc
            // cùng một đơn đang chiếm chỗ ở cả hai chuyến, và nếu giao dịch hỏng giữa chừng thì
            // số chỗ sai ở cả hai đầu.
            if ($chuyenGoc) {
                $chuyenGoc->decrement('booked_people', min($soKhach, (int) $chuyenGoc->booked_people));
                $chuyenGoc->refresh();
                $this->moBanLaiNeuCon($chuyenGoc);
            }

            $chuyenDich->increment('booked_people', $soKhach);
            $chuyenDich->refresh();
            $this->dongBanNeuDay($chuyenDich);

            $locked->forceFill([
                'tour_schedule_id' => $chuyenDich->getKey(),
                'tour_id' => $chuyenDich->tour_id,
                'departure_date' => $chuyenDich->start_date,
                'total_amount' => $tongMoi + $phi,
                'transfer_count' => (int) $locked->transfer_count + 1,
                // Đơn giá chép lại theo tour ĐÍCH, vì tổng tiền vừa được tính lại theo bảng giá của
                // nó. Giữ nguyên giá cũ thì hợp đồng in ra có đơn giá của một tour khác.
                'adult_price' => $chuyenDich->tour?->adult_price ?? $locked->adult_price,
                'child_price' => $chuyenDich->tour?->child_price ?? $locked->child_price,
                'infant_price' => $chuyenDich->tour?->infant_price ?? $locked->infant_price,
            ])->save();

            /*
             * Chuyến đích rẻ hơn thì khách đang thừa tiền ở chỗ công ty.
             *
             * Ghi thành nghĩa vụ ngay tại đây, nếu không thì phần chênh chỉ nằm trong sổ và không
             * màn hình nào đọc ra: hàng đợi hoàn tiền lọc theo `refund_amount`, mà cột ấy vốn chỉ
             * được ghi khi hủy đơn.
             */
            $this->payments->syncRefundDueAfterPriceDrop($locked);

            $banGhi = BookingTransfer::query()->create([
                'booking_id' => $locked->getKey(),
                'from_schedule_id' => $chuyenGoc?->getKey(),
                'to_schedule_id' => $chuyenDich->getKey(),
                'from_tour_id' => $chuyenGoc?->tour_id,
                'to_tour_id' => $chuyenDich->tour_id,
                'initiated_by' => $initiatedBy,
                'contact_log_id' => $canCu?->getKey(),
                'reason_category' => $nhomLyDo?->value,
                'price_difference' => round($tongMoi - $tongCu, 2),
                'fee' => $phi,
                'reason' => $reason,
                'approved_by' => $actor?->getKey(),
                'approved_at' => now(),
            ]);

            $this->auditLogger->log(
                $locked,
                BookingAuditAction::Transferred,
                [
                    'tour_schedule_id' => $chuyenGoc?->getKey(),
                    'total_amount' => $tongCu,
                ],
                [
                    'tour_schedule_id' => $chuyenDich->getKey(),
                    'total_amount' => $tongMoi + $phi,
                    'price_difference' => round($tongMoi - $tongCu, 2),
                    'fee' => $phi,
                    'initiated_by' => $initiatedBy,
                ],
                $reason,
            );

            return $banGhi;
        });

        $this->canhBaoNeuKhongKipThuNot($booking);

        return $banGhi;
    }

    /**
     * Chưa hỏi khách thì chưa chuyển được.
     *
     * ## Vì sao đây là một luật chứ không phải một lời nhắc trên giao diện
     *
     * Chuyển chuyến đổi ngày đi của người khác. Khách đã xin nghỉ phép, đã đặt vé tàu tới điểm tập
     * kết, đã hẹn người nhà. Điều hành thấy chuyến ngày 12 trống chỗ nên dời sang - việc đó hợp lý
     * với bảng xếp chuyến và vô lý với người phải đi.
     *
     * Nên luật nằm ở đây, cùng chỗ với luật số chỗ và luật hạn chốt, chứ không nằm ở một dấu tích
     * trên màn hình. Đường ghi nào cũng đi qua hàm này.
     *
     * ## Vì sao trỏ tới một bản ghi cụ thể chứ không chỉ hỏi "đã có ai đồng ý chưa"
     *
     * Khách gật đầu cho **một** phương án. Nếu chỉ kiểm sự tồn tại của một bản ghi đồng ý nào đó
     * trên đơn, thì lần chuyển thứ hai - sang một chuyến khác hẳn, vì một lý do khác hẳn - vẫn
     * mượn được cái gật đầu của lần trước. Nên mỗi lần chuyển tiêu đúng một bản ghi, và bản ghi đã
     * dùng rồi thì không dùng lại.
     *
     * ## Ngoại lệ duy nhất, và nó không phải cửa sau
     *
     * `$nguonBiHuy` là lúc **cả chuyến gốc bị hủy**, khách được dời đi vì chuyến của họ không còn
     * tồn tại nữa. Ở đó không có phương án nào để hỏi ý - luồng hủy chuyến gửi thư báo kèm lựa
     * chọn hoàn tiền, và khách trả lời bằng cách nhận tiền hoặc đi chuyến mới. Cờ này đã có sẵn
     * cho luật hạn chốt từ trước; tôi không thêm cờ mới nào để đi vòng qua luật này.
     */
    public function assertDaHoiKhach(
        Booking $booking,
        ?CustomerContactLog $canCu,
        bool $nguonBiHuy = false,
    ): void {
        if ($nguonBiHuy) {
            return;
        }

        if (!$canCu) {
            throw new BusinessRuleException(
                'Phải trao đổi với khách và ghi lại nội dung trước khi chuyển chuyến. Vào đơn, ghi '
                . 'nhận cuộc liên hệ, rồi quay lại bước chuyển chuyến.',
            );
        }

        if ((int) $canCu->booking_id !== (int) $booking->getKey()) {
            throw new BusinessRuleException('Bản ghi liên hệ này thuộc về một đơn khác.');
        }

        if (!$canCu->laSuDongYChuyenChuyen()) {
            throw new BusinessRuleException(sprintf(
                'Cuộc liên hệ được chọn có kết quả "%s" nên không dùng làm căn cứ chuyển chuyến '
                    . 'được. Khách không đồng ý hoặc không liên lạc được thì xử lý theo luồng hủy '
                    . 'đơn, đừng dời họ sang chuyến khác.',
                $canCu->outcome->label(),
            ));
        }

        if ($canCu->transfers()->exists()) {
            throw new BusinessRuleException(
                'Cuộc liên hệ này đã dùng làm căn cứ cho một lần chuyển trước rồi. Muốn chuyển '
                . 'tiếp thì phải hỏi lại khách về phương án mới.',
            );
        }
    }

    /**
     * Năm điều kiện theo tài liệu 02 mục 4.2.
     */
    /**
     * @param  bool  $nguonBiHuy  Chuyến nguồn đang bị hủy cả chuyến, xem chú thích ở luật hạn chốt.
     */
    public function assertCanTransfer(
        Booking $booking,
        ?TourSchedule $fromSchedule,
        TourSchedule $toSchedule,
        string $initiatedBy = 'customer',
        bool $nguonBiHuy = false,
    ): void {
        // 1. Đơn chưa thanh toán thì hủy rồi đặt lại đơn giản hơn nhiều, không cần luồng này.
        if (!in_array((string) $booking->status, BookingStatus::paidValues(), true)) {
            throw new BusinessRuleException(
                'Chỉ chuyển được đơn đã thanh toán. Đơn chưa thanh toán thì hủy và đặt lại đơn giản hơn.',
            );
        }

        if ($fromSchedule && (int) $fromSchedule->getKey() === (int) $toSchedule->getKey()) {
            throw new BusinessRuleException('Chuyến đích trùng với chuyến hiện tại.');
        }

        // Chuyến gốc đã lăn bánh thì không còn gì để chuyển: khách đã đi hoặc đã bỏ chuyến.
        if ($fromSchedule) {
            $trangThaiGoc = $this->lifecycle->effectiveStatus($fromSchedule);

            if (!$nguonBiHuy && ($trangThaiGoc->isRunning() || $trangThaiGoc->isFinal())) {
                throw new BusinessRuleException(sprintf(
                    'Chuyến hiện tại đang ở trạng thái "%s" nên không chuyển được nữa.',
                    $trangThaiGoc->label(),
                ));
            }

            /*
             * Qua hạn chốt danh sách của chuyến gốc thì không chuyển được nữa.
             *
             * Đây là cùng một mốc mà nhóm C dùng để quyết định trả chỗ, và vì đúng một lý do:
             * sau mốc đó suất ở chuyến gốc đã trả tiền cho nhà cung cấp và không rút lại được.
             *
             * Nếu vẫn cho chuyển, công ty trả tiền hai suất mà chỉ thu của khách một suất -
             * phòng bỏ trống ở chuyến gốc, phải mua thêm một suất ở chuyến đích, giá thu không
             * đổi. Khác hẳn hủy muộn, vì hủy thì công ty còn giữ lại phần lớn tiền theo bảng phí.
             *
             * Áp cho cả khách lẫn hãng. Hãng khởi xướng được miễn hạn báo trước và miễn phí đổi
             * lịch, nhưng không miễn được sự thật là suất kia đã trả tiền rồi.
             *
             * Ghép chuyến đi đường riêng và không bị luật này chặn: ở đó chuyến nguồn bị hủy
             * hẳn, nên chỗ trống của nó không còn ý nghĩa tồn kho nữa.
             *
             * Hủy cả chuyến (nhóm K) rơi vào đúng trường hợp ấy, nên truyền $nguonBiHuy để bỏ
             * qua. Chỗ ở chuyến nguồn không "bỏ trống" theo nghĩa lãng phí: cả chuyến không chạy,
             * công ty hủy với nhà cung cấp chứ không giữ suất đó cho ai. Luật ở chuyến ĐÍCH thì
             * vẫn nguyên - nhận thêm khách vào chuyến đã quá hạn chốt vẫn là vượt cam kết.
             */
            $hanChot = $fromSchedule->booking_deadline ?? $fromSchedule->defaultBookingDeadline();

            if (!$nguonBiHuy && $hanChot && now()->gte($hanChot)) {
                throw new BusinessRuleException(sprintf(
                    'Chuyến hiện tại đã qua hạn chốt danh sách ngày %s. Suất ở chuyến này đã cam kết '
                        . 'với nhà cung cấp nên không chuyển đi được; nếu khách không đi được, xử lý '
                        . 'theo luồng hủy đơn.',
                    Carbon::parse($hanChot)->format('d/m/Y H:i'),
                ));
            }
        }

        // 2. Chuyến đích phải đang mở bán và còn đủ chỗ cho toàn bộ số khách của đơn.
        if ($toSchedule->tour?->status !== 'active') {
            throw new BusinessRuleException('Tour của chuyến đích không còn hoạt động.');
        }

        if ($this->lifecycle->effectiveStatus($toSchedule) !== ScheduleStatus::Open) {
            throw new BusinessRuleException('Chuyến đích không ở trạng thái đang mở bán.');
        }

        /*
         * Chuyến đích cũng phải còn trước hạn chốt danh sách của chính nó.
         *
         * Trạng thái không thay được cho phép kiểm này. `CloseExpiredSchedules` chạy theo lịch, nên
         * giữa lúc hạn chốt trôi qua - hoặc lúc điều hành rút ngắn nó - và lúc lệnh nền chạy, chuyến
         * vẫn còn `open` trong cơ sở dữ liệu. Nhận thêm một đơn vào khoảng ấy làm `booked_people`
         * vượt số suất đã chốt với nhà cung cấp, đúng thứ mà ghép chuyến chặn ở cả hai đầu.
         *
         * Luật này đứng TRƯỚC nhánh miễn trừ cho hãng bên dưới, và đó là chủ ý: hãng khởi xướng thì
         * được miễn hạn báo trước của khách, không được miễn cam kết với nhà cung cấp.
         */
        $hanChotDich = $toSchedule->booking_deadline ?? $toSchedule->defaultBookingDeadline();

        if ($hanChotDich && now()->gte($hanChotDich)) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đích đã qua hạn chốt danh sách ngày %s nên không nhận thêm khách được. '
                    . 'Chọn chuyến khác, hoặc dời hạn chốt của chuyến đích nếu nhà cung cấp còn nhận.',
                Carbon::parse($hanChotDich)->format('d/m/Y H:i'),
            ));
        }

        $conTrong = (int) $toSchedule->max_people - (int) $toSchedule->booked_people;

        if ($conTrong < $booking->seatsTaken()) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đích chỉ còn %d chỗ, không đủ cho %d khách của đơn này.',
                max(0, $conTrong),
                $booking->seatsTaken(),
            ));
        }

        // 5. Hãng khởi xướng thì bỏ qua hạn báo trước, vì lỗi không thuộc về khách.
        if ($initiatedBy === 'company') {
            return;
        }

        /*
         * 3. Hạn báo trước của khách — mặc định KHÔNG có.
         *
         * Trước đây đây là một mốc cứng bảy ngày. Nhưng trước hạn chốt thì chưa có gì gửi đi nhà
         * cung cấp: chỗ khách rời khỏi quay lại kho và bán tiếp được (xem `moBanLaiNeuCon`), danh
         * sách chưa in, phòng chưa đặt. Đổi chuyến lúc ấy không tốn của công ty đồng nào, nên một
         * cái vạch thứ hai đứng trước hạn chốt chỉ chặn một việc vô hại - và chặn nó bằng một con
         * số do lập trình viên chọn, đúng thứ tài liệu 16 đã bác khi bàn về hạn chốt mặc định.
         *
         * Hạn chốt mới là mốc thay đổi bắt đầu có giá, và nó chặn cả hai phía.
         *
         * Công ty nào có chính sách báo trước riêng thì đặt `booking.transfer_notice_days`.
         */
        $soNgayBaoTruoc = max(0, (int) config('booking.transfer_notice_days', 0));

        if ($soNgayBaoTruoc > 0 && $fromSchedule?->start_date) {
            $hanBaoTruoc = Carbon::parse($fromSchedule->start_date)->subDays($soNgayBaoTruoc);

            if (now()->gt($hanBaoTruoc)) {
                throw new BusinessRuleException(sprintf(
                    'Khách chỉ đổi được chuyến trước ngày khởi hành ít nhất %d ngày. '
                        . 'Sau mốc đó cần bộ phận điều hành thực hiện.',
                    $soNgayBaoTruoc,
                ));
            }
        }
    }

    /**
     * Tổng tiền nếu đơn này nằm ở chuyến đích.
     *
     * Tính lại từ giá của tour đích theo đúng cơ cấu khách, chứ không giữ nguyên tổng cũ: đổi
     * sang tour khác thì giá người lớn, trẻ em, em bé đều khác. Giảm giá đã áp giữ nguyên, vì
     * khách đã dùng mã đó rồi.
     */
    public function recalculateTotal(Booking $booking, TourSchedule $toSchedule): float
    {
        $tour = $toSchedule->tour;

        if (!$tour) {
            return (float) $booking->total_amount;
        }

        $tamTinh = ((int) $booking->adult_count) * (float) $tour->adult_price
            + ((int) $booking->child_count) * (float) $tour->child_price
            + ((int) $booking->infant_count) * (float) $tour->infant_price;

        return round(max(0, $tamTinh - (float) $booking->discount_amount), 2);
    }

    /**
     * Phí đổi lịch.
     *
     * Lần đầu miễn phí, từ lần thứ hai thu. Hai trường hợp không bao giờ thu:
     *
     *   - Hãng khởi xướng - lỗi không thuộc về khách.
     *   - Lý do là bất khả kháng: bão, quyết định của cơ quan nhà nước, nhà cung cấp hỏng việc.
     *     Thu phí đổi lịch của một người phải dời chuyến vì bão là bắt họ trả cho một việc không
     *     ai gây ra. Chỉ nhóm "khách xin đổi vì việc riêng" mới chịu quy tắc phí.
     */
    public function transferFee(
        Booking $booking,
        string $initiatedBy,
        ?TransferReasonCategory $nhomLyDo = null,
    ): float {
        if ($initiatedBy === 'company') {
            return 0.0;
        }

        if ($nhomLyDo?->batKhaKhang()) {
            return 0.0;
        }

        if ((int) $booking->transfer_count < self::FREE_TRANSFERS) {
            return 0.0;
        }

        return (float) config('booking.transfer_fee', 200_000);
    }

    /** Chuyến gốc vừa trả chỗ thì có thể bán lại được, nếu vẫn còn trong hạn chốt. */
    private function moBanLaiNeuCon(TourSchedule $schedule): void
    {
        if ($this->lifecycle->currentStatus($schedule) !== ScheduleStatus::Closed) {
            return;
        }

        if ($schedule->booked_people >= $schedule->max_people) {
            return;
        }

        $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

        // Qua hạn chốt thì chỗ về kho nhưng chuyến vẫn không nhận đặt mới, giống hệt lý do ở
        // BookingHoldService::releaseHeldSeats.
        if ($hanChot && now()->gte($hanChot)) {
            return;
        }

        $this->lifecycle->transitionTo(
            $schedule,
            ScheduleStatus::Open,
            'Mở bán lại do một đơn vừa chuyển sang chuyến khác.',
        );
    }

    private function dongBanNeuDay(TourSchedule $schedule): void
    {
        if ($this->lifecycle->currentStatus($schedule) !== ScheduleStatus::Open) {
            return;
        }

        if ($schedule->booked_people < $schedule->max_people) {
            return;
        }

        $this->lifecycle->transitionTo(
            $schedule,
            ScheduleStatus::Closed,
            'Tự động đóng bán do một đơn vừa chuyển sang khiến chuyến đầy chỗ.',
        );
    }
}
