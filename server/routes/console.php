<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A05: Hủy các đơn giữ chỗ quá hạn thanh toán
Schedule::command('bookings:release-expired')
    ->everyMinute();

// A06: Chốt các chuyến đủ số khách tối thiểu và gửi email xác nhận
Schedule::command('schedules:confirm-ready')
    ->everyMinute();

// A07: Đóng bán các chuyến đã quá hạn chốt hoặc đã đủ chỗ
Schedule::command('schedules:close-expired')
    ->everyMinute();