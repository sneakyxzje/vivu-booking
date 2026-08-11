<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dọn đơn giữ chỗ quá hạn thanh toán
Schedule::command('bookings:release-expired')
    ->everyMinute();

// A05: Đóng bán các chuyến đã quá hạn chốt hoặc đã đủ chỗ
Schedule::command('schedules:close-expired')
    ->everyMinute();

// A06: Chốt các chuyến đủ số khách tối thiểu và gửi email xác nhận
Schedule::command('schedules:confirm-ready')
    ->everyMinute();

// A07: Chuyển chuyến đã chốt sang đang chạy và đã kết thúc theo thời gian
Schedule::command('schedules:advance-status')
    ->everyMinute();
