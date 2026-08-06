<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dọn đơn giữ chỗ quá hạn thanh toán (cần `php artisan schedule:work` khi chạy local)
Schedule::command('bookings:release-expired')->everyMinute();
