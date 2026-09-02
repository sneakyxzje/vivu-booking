<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\TourSchedule;
use App\Services\ScheduleLifecycleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schedules:close-expired')]
#[Description('Đóng bán các chuyến đã quá hạn chốt hoặc đã đủ chỗ')]
class CloseExpiredSchedules extends Command
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hanMacDinh = (int) config('booking.booking_deadline_days', 3);

        $schedules = TourSchedule::query()
            ->where('status', ScheduleStatus::Open->value)
            ->where(function ($query) use ($hanMacDinh) {
                $query
                    ->where(function ($query) {
                        $query
                            ->whereNotNull('booking_deadline')
                            ->where('booking_deadline', '<=', now());
                    })
                    /*
                     * Chuyến không đặt hạn chốt riêng vẫn có hạn mặc định, và vẫn phải đóng bán khi
                     * qua mốc ấy. Thiếu nhánh này thì chúng ở lại trạng thái `open` mãi mãi: khách
                     * không đặt được (`isBookable()` chặn) nhưng mọi màn hình lọc theo cột trạng
                     * thái vẫn mời họ vào.
                     */
                    ->orWhere(function ($query) use ($hanMacDinh) {
                        $query
                            ->whereNull('booking_deadline')
                            ->where('start_date', '<=', now()->addDays($hanMacDinh));
                    })
                    ->orWhereColumn('booked_people', '>=', 'max_people');
            })
            ->get();

        $closed = 0;

        foreach ($schedules as $schedule) {
            try {
                $this->lifecycle->transitionTo(
                    $schedule,
                    ScheduleStatus::Closed,
                    'Tự động đóng bán do quá hạn chốt hoặc đã đủ chỗ.',
                );
            } catch (BusinessRuleException $e) {
                $this->warn(
                    "Lịch #{$schedule->id} không thể đóng bán: {$e->getMessage()}"
                );

                continue;
            }

            $closed++;

            $this->line(
                "Đã đóng lịch #{$schedule->id}"
            );
        }

        $this->info("Đã đóng {$closed} lịch khởi hành.");

        return self::SUCCESS;
    }
}