<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingConfirmedMail;
use App\Models\TourSchedule;
use App\Services\ScheduleLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ConfirmReadySchedules extends Command
{
    protected $signature = 'schedules:confirm-ready';

    protected $description = 'Chốt các chuyến đủ số khách tối thiểu và gửi email xác nhận';

    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $schedules = TourSchedule::query()
            ->whereIn('status', [
                ScheduleStatus::Open->value,
                ScheduleStatus::Closed->value,
            ])
            ->where('start_date', '>', now())
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('Không có chuyến nào cần kiểm tra.');

            return self::SUCCESS;
        }

        $confirmedCount = 0;
        $warningCount = 0;

        foreach ($schedules as $schedule) {
            $bookedPeople = (int) $schedule->booked_people;
            $minPeople = (int) $schedule->min_people;

            if ($bookedPeople >= $minPeople) {
                try {
                    $this->lifecycle->transitionTo(
                        $schedule,
                        ScheduleStatus::Confirmed,
                        'Tự động chốt chuyến do đã đủ số khách tối thiểu.',
                    );
                } catch (BusinessRuleException $e) {
                    $this->warn(
                        "Chuyến #{$schedule->id} không thể chuyển sang confirmed: {$e->getMessage()}"
                    );

                    continue;
                }

                $this->info(
                    "✓ Chuyến #{$schedule->id} đã đủ khách: "
                    . "{$bookedPeople}/{$minPeople} → ĐÃ CHỐT"
                );

                $bookings = $schedule->bookings()
                    ->with(['customer'])
                    ->where('status', 'confirmed')
                    ->get();

                foreach ($bookings as $booking) {
                    $email = $booking->customer?->email ?: $booking->customer_email;

                    if ($email) {
                        Mail::to($email)
                            ->send(new BookingConfirmedMail($booking));

                        $this->line(
                            "  → Đã gửi email cho {$email}"
                        );
                    }
                }

                $confirmedCount++;

            } else {

                $remaining = $minPeople - $bookedPeople;

                $this->warn(
                    "! Chuyến #{$schedule->id} chưa đủ khách: "
                    . "{$bookedPeople}/{$minPeople} "
                    . "(thiếu {$remaining} khách)"
                );

                $warningCount++;
            }
        }

        $this->newLine();

        $this->info("Đã chốt: {$confirmedCount} chuyến.");
        $this->warn("Chưa đủ khách: {$warningCount} chuyến.");

        return self::SUCCESS;
    }
}