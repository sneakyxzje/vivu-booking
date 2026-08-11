<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\TourSchedule;
use Illuminate\Support\Facades\DB;

class BookingHoldService
{
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
     * Trả chỗ + mở lại lịch/tour + hoàn lượt mã giảm giá.
     * Phải gọi bên trong transaction đã lock schedule tương ứng.
     */
    public function releaseHold(Booking $booking, ?TourSchedule $schedule): void
    {
        $this->releaseDiscountUsage($booking);

        if (!$schedule) {
            return;
        }

        $schedule->decrement('booked_people', min($booking->guests, (int) $schedule->booked_people));
        $schedule->refresh();

        if ($this->scheduleStatusValue($schedule) === ScheduleStatus::Closed->value
            && $schedule->booked_people < $schedule->max_people) {
            $schedule->update(['status' => ScheduleStatus::Open->value]);
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

    private function expireLocked(Booking $booking, ?TourSchedule $schedule): void
    {
        $booking->update([
            'status' => 'cancelled',
            'cancel_reason' => self::EXPIRED_REASON,
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
            return $this->scheduleStatusValue($item) === ScheduleStatus::Open->value
                && (int) $item->booked_people < (int) $item->max_people;
        });

        $tour->update(['status' => $hasAvailableSchedule ? 'active' : 'full']);
    }

    private function scheduleStatusValue(TourSchedule $schedule): string
    {
        return $schedule->status instanceof ScheduleStatus
            ? $schedule->status->value
            : (string) $schedule->status;
    }
}
