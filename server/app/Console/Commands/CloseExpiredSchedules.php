<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
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
        $schedules = TourSchedule::query()
            ->where('status', ScheduleStatus::Open)
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->whereNotNull('booking_deadline')
                            ->where('booking_deadline', '<=', now());
                    })
                    ->orWhereColumn('booked_people', '>=', 'max_people');
            })
            ->get();

        $closed = 0;

        foreach ($schedules as $schedule) {
            $this->lifecycle->transitionTo(
                $schedule,
                ScheduleStatus::Closed,
                'Tự động đóng bán do quá hạn chốt hoặc đã đủ chỗ.',
            );

            $closed++;

            $this->line(
                "Đã đóng lịch #{$schedule->id}"
            );
        }

        $this->info("Đã đóng {$closed} lịch khởi hành.");

        return self::SUCCESS;
    }
}