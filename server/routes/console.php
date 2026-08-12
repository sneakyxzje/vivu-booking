<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cả bốn lệnh đều quét bảng rồi khóa dòng để đổi trạng thái. withoutOverlapping bắt buộc
// vì nếu một lần chạy lâu hơn chu kỳ, hai tiến trình sẽ tranh khóa trên cùng các bản ghi.

// Dọn đơn giữ chỗ quá hạn thanh toán
Schedule::command('bookings:release-expired')
    ->everyMinute()
    ->withoutOverlapping();

// A05: Đóng bán các chuyến đã quá hạn chốt hoặc đã đủ chỗ
Schedule::command('schedules:close-expired')
    ->everyMinute()
    ->withoutOverlapping();

// A06: Chốt các chuyến sắp tới hạn chốt danh sách và đã đủ số khách tối thiểu
Schedule::command('schedules:confirm-ready')
    ->everyMinute()
    ->withoutOverlapping();

// A07: Chuyển chuyến đã chốt sang đang chạy và đã kết thúc theo thời gian
Schedule::command('schedules:advance-status')
    ->everyMinute()
    ->withoutOverlapping();
