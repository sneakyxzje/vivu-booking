<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingBalanceDeadlineService;
use Illuminate\Console\Command;

class CancelUnpaidBalances extends Command
{
    use \App\Console\Concerns\RunsWithDemoClock;

    protected $signature = 'bookings:cancel-unpaid-balances {--dry-run : Chỉ liệt kê, không hủy gì}';
    protected $description = 'Hủy đơn chưa trả đủ tại hạn chốt danh sách và giữ lại tiền cọc';

    public function handleForClock(): int
    {
        $service = app(BookingBalanceDeadlineService::class);
        $count = 0;
        Booking::query()->forClock()->whereIn('status', BookingStatus::paidValues())
            ->whereNull('group_booking_request_id')->with(['schedule', 'payments'])
            ->chunkById(100, function ($bookings) use ($service, &$count) {
                foreach ($bookings as $booking) {
                    if (!$service->overdue($booking)) {
                        continue;
                    }
                    if ($this->option('dry-run')) {
                        $this->line("Đơn #{$booking->id} chưa trả đủ trước hạn chốt.");
                        $count++;
                    } elseif ($service->cancel($booking)) {
                        $count++;
                    }
                }
            });
        $this->info($this->option('dry-run') ? "Có {$count} đơn quá hạn." : "Đã hủy {$count} đơn quá hạn thanh toán.");
        return self::SUCCESS;
    }
}
