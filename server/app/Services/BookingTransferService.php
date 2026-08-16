<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingTransfer;
use App\Models\TourSchedule;
use App\Models\User;
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
    /** Khách phải báo trước ngần này ngày, tính từ ngày khởi hành của chuyến gốc. */
    public const CUSTOMER_NOTICE_DAYS = 7;

    /** Số lần chuyển được miễn phí. Từ lần sau thu phí đổi lịch. */
    public const FREE_TRANSFERS = 1;

    public function __construct(
        private ScheduleLifecycleService $lifecycle,
        private BookingAuditLogger $auditLogger,
    ) {
    }

    /**
     * Xem trước hậu quả của việc chuyển, không thay đổi gì.
     *
     * Điều hành cần biết chênh lệch giá và lý do bị chặn trước khi bấm, giống hệt lý do màn hủy
     * đơn phải có dự báo: người bấm nút chịu trách nhiệm thì phải được biết trước.
     *
     * @return array{can_transfer: bool, blocked_reason: string|null, price_difference: float, fee: float, new_total: float, transfer_count: int}
     */
    public function preview(
        Booking $booking,
        TourSchedule $toSchedule,
        string $initiatedBy = 'customer',
    ): array {
        $coThe = true;
        $lyDoChan = null;

        try {
            $this->assertCanTransfer($booking, $booking->schedule, $toSchedule, $initiatedBy);
        } catch (BusinessRuleException $e) {
            $coThe = false;
            $lyDoChan = $e->getMessage();
        }

        $tongMoi = $this->recalculateTotal($booking, $toSchedule);
        $phi = $this->transferFee($booking, $initiatedBy);

        return [
            'can_transfer' => $coThe,
            'blocked_reason' => $lyDoChan,
            'price_difference' => round($tongMoi - (float) $booking->total_amount, 2),
            'fee' => $phi,
            'new_total' => $tongMoi,
            'transfer_count' => (int) $booking->transfer_count,
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
    ): BookingTransfer {
        return DB::transaction(function () use ($booking, $toSchedule, $reason, $actor, $initiatedBy) {
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
            $this->assertCanTransfer($locked, $chuyenGoc, $chuyenDich, $initiatedBy);

            $tongCu = (float) $locked->total_amount;
            $tongMoi = $this->recalculateTotal($locked, $chuyenDich);
            $phi = $this->transferFee($locked, $initiatedBy);
            $soKhach = (int) $locked->guests;

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
            ])->save();

            $banGhi = BookingTransfer::query()->create([
                'booking_id' => $locked->getKey(),
                'from_schedule_id' => $chuyenGoc?->getKey(),
                'to_schedule_id' => $chuyenDich->getKey(),
                'from_tour_id' => $chuyenGoc?->tour_id,
                'to_tour_id' => $chuyenDich->tour_id,
                'initiated_by' => $initiatedBy,
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
    }

    /**
     * Năm điều kiện theo tài liệu 02 mục 4.2.
     */
    public function assertCanTransfer(
        Booking $booking,
        ?TourSchedule $fromSchedule,
        TourSchedule $toSchedule,
        string $initiatedBy = 'customer',
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

            if ($trangThaiGoc->isRunning() || $trangThaiGoc->isFinal()) {
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
             */
            $hanChot = $fromSchedule->booking_deadline ?? $fromSchedule->defaultBookingDeadline();

            if ($hanChot && now()->gte($hanChot)) {
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

        $conTrong = (int) $toSchedule->max_people - (int) $toSchedule->booked_people;

        if ($conTrong < (int) $booking->guests) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đích chỉ còn %d chỗ, không đủ cho %d khách của đơn này.',
                max(0, $conTrong),
                (int) $booking->guests,
            ));
        }

        // 5. Hãng khởi xướng thì bỏ qua hạn báo trước, vì lỗi không thuộc về khách.
        if ($initiatedBy === 'company') {
            return;
        }

        // 3. Khách phải báo trước, tính từ ngày khởi hành của chuyến gốc.
        if ($fromSchedule?->start_date) {
            $hanBaoTruoc = Carbon::parse($fromSchedule->start_date)
                ->subDays(self::CUSTOMER_NOTICE_DAYS);

            if (now()->gt($hanBaoTruoc)) {
                throw new BusinessRuleException(sprintf(
                    'Khách chỉ đổi được chuyến trước ngày khởi hành ít nhất %d ngày. '
                        . 'Sau mốc đó cần bộ phận điều hành thực hiện.',
                    self::CUSTOMER_NOTICE_DAYS,
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
     * Lần đầu miễn phí, từ lần thứ hai thu. Hãng khởi xướng thì không bao giờ thu.
     */
    public function transferFee(Booking $booking, string $initiatedBy): float
    {
        if ($initiatedBy === 'company') {
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
