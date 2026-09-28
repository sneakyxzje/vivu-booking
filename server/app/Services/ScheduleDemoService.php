<?php

namespace App\Services;

use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ScheduleDemoService
{
    public const COMMANDS = [
        'bookings:expire-proposals',
        'bookings:release-expired',
        'bookings:send-balance-reminders',
        'bookings:cancel-unpaid-balances',
        'schedules:confirm-ready',
        'bookings:send-departure-reminders',
        'schedules:advance-status',
        'bookings:finalize-completed',
        'bookings:expire-stale-holds',
    ];

    public function assertEnabled(): void
    {
        abort_unless(DemoClock::enabled(), 404);
    }

    /** One click on a departure: advance its clock and run the existing lifecycle rules. */
    public function moveToStatus(int $id, string $status, User $actor): array
    {
        $this->assertEnabled();
        return DB::transaction(function () use ($id, $status, $actor) {
            $schedule = TourSchedule::query()->lockForUpdate()->findOrFail($id);
            [$allowed, $milestone] = match ($status) {
                'confirmed' => [[ScheduleStatus::Open], 'booking_deadline'],
                'in_progress' => [[ScheduleStatus::Confirmed], 'departure'],
                'completed' => [[ScheduleStatus::InProgress], 'completion'],
                default => throw new BusinessRuleException('Trạng thái đích không hợp lệ.'),
            };
            if (!in_array($schedule->status, $allowed, true)) {
                throw new BusinessRuleException('Chuyến không thể chuyển sang trạng thái này từ bước hiện tại.');
            }
            return $this->moveToMilestone($id, $milestone, $actor);
        });
    }

    public function moveToMilestone(int $id, string $milestone, User $actor): array
    {
        $this->assertEnabled();
        return DB::transaction(function () use ($id, $milestone, $actor) {
            $schedule = TourSchedule::query()->lockForUpdate()->findOrFail($id);
            if ($schedule->status->isFinal() && !($schedule->merged_into_schedule_id && in_array($milestone, ['proposal_expired', 'process'], true))) {
                throw new BusinessRuleException('Chuyến đã kết thúc hoặc đã hủy.');
            }
            if ($milestone === 'departure' && $schedule->status !== ScheduleStatus::Confirmed) {
                throw new BusinessRuleException('Cần chốt chuyến trước khi khởi hành.');
            }
            if (($milestone === 'completion' || str_starts_with($milestone, 'day_')) && $schedule->status !== ScheduleStatus::InProgress) {
                throw new BusinessRuleException('Chuyến cần đang di chuyển để thực hiện bước này.');
            }
            $choice = collect($this->snapshot($schedule)['milestones'])->firstWhere('key', $milestone);
            if ($milestone !== 'process' && !$choice) {
                throw new BusinessRuleException('Mốc thời gian không hợp lệ.');
            }
            if ($schedule->demo_time === null) {
                $schedule->forceFill(['demo_time' => now(), 'demo_time_set_at' => now()])->save();
            }
            // A delayed scheduler may already have passed this milestone. Never rewind it.
            if ($choice && Carbon::parse($choice['at'])->lte(DemoClock::schedule($schedule))) {
                $milestone = 'process';
            }
            return $this->advance($id, $milestone, $actor);
        });
    }

    public function snapshot(TourSchedule $schedule): array
    {
        $current = DemoClock::schedule($schedule);
        $due = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();
        $end = $schedule->end_date ?? $schedule->start_date->copy()
            ->addDays(max(0, (int) $schedule->tour?->number_of_days - 1))->endOfDay();
        $moments = [
            'reminder' => ['Đến ngày nhắc trả nốt', $due->copy()->subDays((int) config('booking.balance_reminder_days', 7))],
            'final_notice' => ['Đến cảnh báo cuối', $due->copy()->subDays((int) config('booking.balance_final_notice_days', 2))],
            'booking_deadline' => ['Đến hạn chốt và trả nốt', $schedule->booking_deadline ?? $schedule->defaultBookingDeadline()],
            'departure' => ['Đến giờ khởi hành', $schedule->start_date],
            'completion' => ['Qua giờ kết thúc', $end->copy()->addSecond()],
        ];
        $proposalDeadline = \App\Models\BookingChangeProposal::query()->pending()
            ->where('from_schedule_id', $schedule->id)->min('response_deadline');
        if ($proposalDeadline && $schedule->merged_into_schedule_id) {
            $moments['proposal_expired'] = ['Qua hạn phản hồi ghép chuyến', Carbon::parse($proposalDeadline)->addSecond()];
        }
        $holdExpiry = $schedule->bookings()->where('status', 'pending')->whereNotNull('expires_at')->min('expires_at');
        if ($holdExpiry) {
            $moments['hold_expired'] = ['Qua hạn giữ chỗ chưa thanh toán', Carbon::parse($holdExpiry)->addSecond()];
        }
        for ($day = 2; $day <= (int) $schedule->tour?->number_of_days; $day++) {
            $at = $schedule->start_date->copy()->addDays($day - 1)->startOfDay();
            if ($at->lt($end)) {
                $moments['day_' . $day] = ['Đến ngày ' . $day . ' để điểm danh', $at];
            }
        }
        uasort($moments, fn ($a, $b) => $a[1]->getTimestamp() <=> $b[1]->getTimestamp());
        $terminal = in_array($schedule->status, [ScheduleStatus::Completed, ScheduleStatus::Cancelled], true);
        $milestones = [];
        foreach ($moments as $key => [$label, $at]) {
            $reason = null;
            if ($terminal && $key !== 'proposal_expired') {
                $reason = 'Chuyến đã kết thúc hoặc đã hủy.';
            } elseif ($at->lte($current)) {
                $reason = 'Đã qua mốc này. Có thể chạy lại xử lý tại mốc hiện tại.';
            } elseif ($key === 'departure' && $schedule->status !== ScheduleStatus::Confirmed) {
                $reason = 'Chuyến cần được chốt trước khi khởi hành.';
            } elseif (($key === 'completion' || str_starts_with($key, 'day_')) && $schedule->status !== ScheduleStatus::InProgress) {
                $reason = 'Cần khởi hành và thực hiện điểm danh trước khi kết thúc.';

            }
            $milestones[] = ['key' => $key, 'label' => $label, 'at' => $at->toIso8601String(), 'blocked_reason' => $reason];
        }

        return [
            'schedule_id' => $schedule->id,
            'active' => $schedule->demo_time !== null,
            'clock' => ['now' => $current->toIso8601String(), 'real_now' => now()->toIso8601String()],
            'status' => $schedule->status->value,
            'milestones' => $milestones,
        ];
    }

    public function enable(int $id, User $actor): array
    {
        $this->assertEnabled();
        return DB::transaction(function () use ($id, $actor) {
            $schedule = TourSchedule::query()->lockForUpdate()->findOrFail($id);
            if ($schedule->demo_time === null) {
                if ($schedule->status !== ScheduleStatus::Open || $schedule->start_date->lte(now())) {
                    throw new BusinessRuleException('Chỉ bật demo cho chuyến tương lai đang mở bán.');
                }
                $schedule->forceFill(['demo_time' => now(), 'demo_time_set_at' => now()])->save();
                app(ScheduleAuditLogger::class)->log($schedule, ScheduleAuditAction::DemoClockChanged,
                    null, ['demo_time' => $schedule->demo_time], 'Bật trình diễn cho chuyến được chọn.', $actor);
            }
            return $this->snapshot($schedule);
        });
    }

    public function advance(int $id, string $milestone, User $actor): array
    {
        $this->assertEnabled();
        return DB::transaction(function () use ($id, $milestone, $actor) {
            $schedule = TourSchedule::query()->lockForUpdate()->findOrFail($id);
            if ($schedule->demo_time === null) {
                throw new BusinessRuleException('Hãy bật trình diễn cho chuyến này trước.');
            }
            $before = DemoClock::schedule($schedule);
            if ($milestone !== 'process') {
                $choice = collect($this->snapshot($schedule)['milestones'])->firstWhere('key', $milestone);
                if (!$choice || $choice['blocked_reason']) {
                    throw new BusinessRuleException($choice['blocked_reason'] ?? 'Mốc thời gian không hợp lệ.');
                }
                $schedule->forceFill([
                    'demo_time' => Carbon::parse($choice['at'])->setTimezone(config('app.timezone')),
                    'demo_time_set_at' => now(),
                ])->save();
            }
            $output = app(DemoClock::class)->within($schedule, function () {
                $lines = [];
                foreach (self::COMMANDS as $command) {
                    if (Artisan::call($command) !== 0) {
                        throw new BusinessRuleException('Không chạy được tác vụ: ' . $command);
                    }
                    $lines[] = trim(Artisan::output());
                }
                return implode("\n", $lines);
            });
            $schedule->refresh();
            app(ScheduleAuditLogger::class)->log($schedule, ScheduleAuditAction::DemoClockChanged,
                ['demo_time' => $before->toIso8601String()],
                ['demo_time' => DemoClock::schedule($schedule)->toIso8601String(), 'milestone' => $milestone],
                'Chạy nghiệp vụ theo đồng hồ demo của riêng chuyến.', $actor);

            return $this->snapshot($schedule) + ['output' => $output];
        });
    }
}
