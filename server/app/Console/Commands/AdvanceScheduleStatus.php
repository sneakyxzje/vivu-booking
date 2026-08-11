<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\TourSchedule;
use App\Services\ScheduleLifecycleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schedules:advance-status')]
#[Description('Chuyển chuyến đã chốt sang đang chạy và chuyến đang chạy sang đã kết thúc theo thời gian')]
class AdvanceScheduleStatus extends Command
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $schedules = TourSchedule::query()
            ->with('tour:id,number_of_days')
            ->whereIn('status', [
                ScheduleStatus::Confirmed->value,
                ScheduleStatus::InProgress->value,
            ])
            ->get();

        $advanced = 0;

        foreach ($schedules as $schedule) {
            $nextStatus = $this->lifecycle->resolveStatusByTime($schedule);

            if ($nextStatus === null) {
                continue;
            }

            try {
                $this->lifecycle->transitionTo(
                    $schedule,
                    $nextStatus,
                    'Tự động chuyển trạng thái chuyến theo thời gian khởi hành/kết thúc.',
                );
            } catch (BusinessRuleException $e) {
                $this->warn(
                    "Chuyến #{$schedule->id} không thể chuyển sang {$nextStatus->value}: {$e->getMessage()}"
                );

                continue;
            }

            $advanced++;
            $this->line("Đã chuyển lịch #{$schedule->id} sang {$nextStatus->value}.");
        }

        $this->info("Đã chuyển trạng thái {$advanced} lịch khởi hành.");

        return self::SUCCESS;
    }
}