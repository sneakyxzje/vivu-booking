<?php

namespace App\Console\Commands;

use App\Services\BookingHoldService;
use Illuminate\Console\Command;

class ReleaseExpiredBookings extends Command
{
    protected $signature = 'bookings:release-expired';

    protected $description = 'Hủy các đơn giữ chỗ quá hạn thanh toán và trả lại chỗ cho khách khác';

    public function handle(BookingHoldService $holdService): int
    {
        $released = $holdService->releaseAllOverdue();

        $this->info("Đã hủy {$released} đơn quá hạn thanh toán.");

        return self::SUCCESS;
    }
}
