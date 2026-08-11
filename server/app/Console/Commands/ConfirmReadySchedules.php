<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\TourSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ConfirmReadySchedules extends Command
{
    protected $signature = 'schedules:confirm-ready';

    protected $description = 'Chốt các chuyến đủ số khách tối thiểu và gửi email xác nhận';

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

            // Đủ số khách tối thiểu
            if ($bookedPeople >= $minPeople) {

                $currentStatus = $schedule->status instanceof ScheduleStatus
                    ? $schedule->status
                    : ScheduleStatus::tryFrom((string) $schedule->status);

                // Kiểm tra transition hợp lệ
                if (
                    $currentStatus === null ||
                    !$currentStatus->canTransitionTo(ScheduleStatus::Confirmed)
                ) {
                    $this->warn(
                        "Chuyến #{$schedule->id} không thể chuyển sang confirmed."
                    );

                    continue;
                }

                // Chốt chuyến
                $schedule->status = ScheduleStatus::Confirmed;
                $schedule->confirmed_at = now();
                $schedule->save();

                $this->info(
                    "✓ Chuyến #{$schedule->id} đã đủ khách: "
                    . "{$bookedPeople}/{$minPeople} → ĐÃ CHỐT"
                );

                // Lấy các booking của chuyến
                $bookings = $schedule->bookings()
                    ->with(['customer'])
                    ->get();

                // Gửi email cho từng khách
                foreach ($bookings as $booking) {
                    if ($booking->customer?->email) {
                        Mail::to($booking->customer->email)
                            ->send(new BookingConfirmedMail($booking));

                        $this->line(
                            "  → Đã gửi email cho {$booking->customer->email}"
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