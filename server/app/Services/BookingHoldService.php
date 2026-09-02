<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\TourSchedule;
use Illuminate\Support\Facades\DB;

class BookingHoldService
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
    ) {
    }

    public const EXPIRED_REASON = 'Quá hạn thanh toán, hệ thống tự hủy để nhường chỗ';

    public function holdMinutes(): int
    {
        return max(1, (int) config('booking.payment_ttl_minutes', 10));
    }

    /**
     * Hủy các đơn pending quá hạn của một lịch khởi hành và trả lại chỗ.
     * Gọi trước khi kiểm tra chỗ trống để khách mới dùng được ngay slot vừa được trả lại.
     */
    public function releaseOverdueForSchedule(int $scheduleId): int
    {
        return DB::transaction(function () use ($scheduleId) {
            $schedule = TourSchedule::query()
                ->whereKey($scheduleId)
                ->lockForUpdate()
                ->first();

            if (!$schedule) {
                return 0;
            }

            $overdue = Booking::query()
                ->where('tour_schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            foreach ($overdue as $booking) {
                $this->expireLocked($booking, $schedule);
            }

            return $overdue->count();
        });
    }

    /**
     * Hủy một đơn nếu đúng là đã quá hạn. Trả về true nếu đơn vừa bị hủy.
     */
    public function releaseIfOverdue(Booking $booking): bool
    {
        if (!$booking->isOverdue()) {
            return false;
        }

        $released = DB::transaction(function () use ($booking) {
            $schedule = $booking->tour_schedule_id
                ? TourSchedule::query()
                    ->whereKey($booking->tour_schedule_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if (!$fresh || !$fresh->isOverdue()) {
                return false;
            }

            $this->expireLocked($fresh, $schedule);

            return true;
        });

        if ($released) {
            $booking->refresh();
        }

        return $released;
    }

    /**
     * Nhả chỗ quá hạn cho mọi lịch khởi hành của một tour.
     */
    public function releaseOverdueForTour(int $tourId): int
    {
        $scheduleIds = Booking::query()
            ->where('tour_id', $tourId)
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotNull('tour_schedule_id')
            ->distinct()
            ->pluck('tour_schedule_id');

        $released = 0;

        foreach ($scheduleIds as $scheduleId) {
            $released += $this->releaseOverdueForSchedule((int) $scheduleId);
        }

        return $released;
    }

    /**
     * Quét toàn bộ đơn quá hạn (dùng cho scheduled command).
     */
    public function releaseAllOverdue(): int
    {
        $scheduleIds = Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotNull('tour_schedule_id')
            ->distinct()
            ->pluck('tour_schedule_id');

        $released = 0;

        foreach ($scheduleIds as $scheduleId) {
            $released += $this->releaseOverdueForSchedule((int) $scheduleId);
        }

        $orphans = Booking::query()
            ->whereNull('tour_schedule_id')
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($orphans as $orphan) {
            $released += DB::transaction(function () use ($orphan) {
                $fresh = Booking::query()->whereKey($orphan->id)->lockForUpdate()->first();

                if (!$fresh || !$fresh->isOverdue()) {
                    return 0;
                }

                $this->expireLocked($fresh, null);

                return 1;
            });
        }

        return $released;
    }

    /**
     * Hủy đơn này thì có trả chỗ về kho để bán lại không.
     *
     * Câu trả lời cho câu hỏi số 8 của hội đồng. Lý do đầy đủ ở
     * docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 3.
     *
     * Hai tình huống khác nhau, không được áp chung một luật:
     *
     * 1. Đơn CHƯA vào danh sách đoàn (giữ chỗ quá hạn thanh toán) thì luôn trả chỗ. Chỗ đó
     *    chưa bao giờ nằm trong danh sách gửi nhà cung cấp. Nếu giữ lại thì một người vào giữ
     *    chỗ lúc hai giờ sáng rồi bỏ đi cũng làm mất vĩnh viễn một chỗ bán được.
     *
     * 2. Đơn ĐÃ vào danh sách đoàn mà hủy sau hạn chốt thì KHÔNG trả chỗ. Phòng, ghế và suất ăn
     *    đã chốt theo danh sách này. Trả về kho là bán ra một chỗ không có dịch vụ đi kèm.
     *    Chỗ đó thành ghế chết: hãng đã trả tiền cho nó nhưng không có khách.
     *
     * Ghế chết ở lại như thế cho tới khi chuyến kết thúc. Từng có màn hình cho điều hành mở lại
     * chỗ ấy khi xin thêm được suất từ nhà cung cấp; đã bỏ, vì phí hủy đã bù phần chi phí đã cam
     * kết nên chuyện còn lại thuần túy là **đừng bán ra thứ không giao được** - và đó là việc của
     * luật này, không cần thêm một thao tác thủ công nào. Điều hành muốn bán tiếp thì tăng sức
     * chứa của chuyến, một quyết định nhìn thấy rõ hơn.
     */
    public function shouldReleaseSeats(Booking $booking, ?TourSchedule $schedule): bool
    {
        if (!$schedule) {
            return false;
        }

        if (!$this->hasEnteredManifest($booking)) {
            return true;
        }

        $deadline = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

        if (!$deadline) {
            return true;
        }

        return now()->lt($deadline);
    }

    /**
     * Đơn đã từng được đưa vào danh sách đoàn gửi nhà cung cấp chưa.
     *
     * Xét paid_at và confirmed_at chứ không xét status, vì tại thời điểm hàm này chạy thì
     * status đã bị đổi sang cancelled rồi. confirmed_at được đặt ở cả ba đường vào danh sách:
     * thanh toán thành công, quản trị xác nhận tay, và hướng dẫn viên xác nhận.
     */
    private function hasEnteredManifest(Booking $booking): bool
    {
        return $booking->paid_at !== null || $booking->confirmed_at !== null;
    }

    /** Chuyến còn nhận đặt mới hay đã qua hạn chốt danh sách. */
    private function conTrongHanChot(TourSchedule $schedule): bool
    {
        $deadline = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

        return !$deadline || now()->lt($deadline);
    }

    /**
     * Trả chỗ + mở lại lịch/tour + hoàn lượt mã giảm giá.
     * Phải gọi bên trong transaction đã lock schedule tương ứng.
     */
    public function releaseHold(Booking $booking, ?TourSchedule $schedule): void
    {
        // Lượt mã giảm giá luôn được trả lại, kể cả khi chỗ bị giữ. Mã giảm giá không liên quan
        // gì tới cam kết với nhà cung cấp.
        $this->releaseDiscountUsage($booking);

        if (!$schedule) {
            return;
        }

        if (!$this->shouldReleaseSeats($booking, $schedule)) {
            // Ghế chết: giữ nguyên booked_people và đánh dấu để điều hành thấy trên màn hình
            // chỗ chưa mở bán lại. Không trừ ở đây thì số chỗ đã bán mới phản ánh đúng số suất
            // đã cam kết với nhà cung cấp.
            $booking->forceFill([
                'seats_released' => false,
                'seats_released_at' => null,
            ])->save();

            return;
        }

        $booking->forceFill([
            'seats_released' => true,
            'seats_released_at' => now(),
        ])->save();

        // Trả đúng số GHẾ đã chiếm, không phải số người: em bé đi cùng chưa từng ăn chỗ nào.
        $schedule->decrement('booked_people', min($booking->seatsTaken(), (int) $schedule->booked_people));
        $schedule->refresh();

        // Còn chỗ trống không phải lý do đủ để bán tiếp. Đơn chưa thanh toán luôn được trả chỗ,
        // kể cả khi hết hạn giữ chỗ sau hạn chốt danh sách - và khi đó mở bán lại là sai: khách
        // vào vẫn không đặt được, còn tác vụ đóng bán chạy sau lại đóng về ngay, làm trạng thái
        // chuyến nhấp nháy. Điều kiện này đã có ở releaseHeldSeats, thiếu ở đây.
        if ($schedule->status === ScheduleStatus::Closed
            && $schedule->booked_people < $schedule->max_people
            && $this->conTrongHanChot($schedule)) {
            $this->lifecycle->transitionTo(
                $schedule,
                ScheduleStatus::Open,
                'Tự động mở bán lại do đơn giữ chỗ quá hạn được nhả.',
            );
        }

        $this->refreshTourAvailability($schedule);
    }

    public function releaseDiscountUsage(Booking $booking): void
    {
        $booking->loadMissing('discountCode');

        if (!$booking->discountCode || $booking->discountCode->used_count <= 0) {
            return;
        }

        $booking->discountCode->decrement('used_count');
    }

    /**
     * X12 - Dọn một đơn giữ chỗ còn treo của chuyến đã kết thúc.
     *
     * Đơn pending bình thường tự hủy khi qua expires_at. Đơn lọt lưới - tác vụ nền chết một
     * hôm, hoặc expires_at rỗng vì được tạo bằng tay - thì nằm mãi ở "chờ thanh toán". Chuyến
     * đi xong rồi mà đơn vẫn treo, và nó vẫn đang tính vào booked_people.
     *
     * Lệnh chốt đơn sau chuyến ở D03 cố ý bỏ qua nhóm này, vì chưa trả tiền thì không kết luận
     * được là đã đi hay không có mặt. Đây là chỗ dọn phần còn lại.
     *
     * Trả về true khi vừa dọn được đơn này.
     */
    public function expireStaleHold(Booking $booking, string $reason): bool
    {
        return DB::transaction(function () use ($booking, $reason) {
            $schedule = $booking->tour_schedule_id
                ? TourSchedule::query()
                    ->whereKey($booking->tour_schedule_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            // Đọc lại sau khi khóa: khách hoàn toàn có thể vừa thanh toán xong trong lúc lệnh
            // chạy, và hủy một đơn vừa trả tiền là mất tiền của người thật.
            if (!$fresh || $fresh->status !== 'pending') {
                return false;
            }

            $this->expireLocked($fresh, $schedule, $reason, 'stale_hold');

            return true;
        });
    }

    private function expireLocked(
        Booking $booking,
        ?TourSchedule $schedule,
        ?string $reason = null,
        string $cancelType = 'hold_expired',
    ): void {
        $booking->forceFill([
            'cancel_type' => $cancelType,
            'cancelled_at' => now(),
        ])->save();

        $booking->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason ?? self::EXPIRED_REASON,
        ]);

        $this->releaseHold($booking, $schedule);
    }

    public function refreshTourAvailability(TourSchedule $schedule): void
    {
        $tour = $schedule->tour()->with('schedules')->first();

        if (!$tour || $tour->status === 'inactive') {
            return;
        }

        $hasAvailableSchedule = $tour->schedules->contains(function (TourSchedule $item) {
            return $item->status === ScheduleStatus::Open
                && (int) $item->booked_people < (int) $item->max_people;
        });

        $tour->update(['status' => $hasAvailableSchedule ? 'active' : 'full']);
    }
}
