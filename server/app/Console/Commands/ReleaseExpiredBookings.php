<?php

namespace App\Console\Commands;

use App\Services\BookingHoldService;
use Illuminate\Console\Command;

class ReleaseExpiredBookings extends Command
{
    protected $signature = 'bookings:release-expired';

    protected $description = 'Hủy các đơn giữ chỗ quá hạn thanh toán và trả lại chỗ cho khách khác';

    use \App\Console\Concerns\RunsWithDemoClock;

    public function handleForClock(): int
    {
        $released = app(BookingHoldService::class)->releaseAllOverdue();

        $this->info("Đã hủy {$released} đơn quá hạn thanh toán.");

        return self::SUCCESS;
    }
}
