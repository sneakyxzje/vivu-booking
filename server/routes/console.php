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

// D03: Chốt đơn của chuyến đã kết thúc. Đặt sau advance-status vì nó ăn theo trạng thái
// chuyến; chạy trước cũng không sai, chỉ là phải đợi thêm một phút mới chốt được.
Schedule::command('bookings:finalize-completed')
    ->everyMinute()
    ->withoutOverlapping();

// X12: Dọn đơn giữ chỗ còn treo của chuyến đã kết thúc. Chạy thưa vì đây là nhóm lọt lưới,
// không phải luồng thường: đơn quá hạn bình thường đã có bookings:release-expired lo mỗi phút.
Schedule::command('bookings:expire-stale-holds')
    ->hourly()
    ->withoutOverlapping();

// C05: Đối chiếu số chỗ đã bán với số chỗ thực tế đang bị chiếm.
// Chỉ báo cáo, không tự nắn số liệu: lệch số chỗ là dấu hiệu có lỗi nghiệp vụ ở đâu đó,
// tự sửa sẽ che mất nguyên nhân.
Schedule::command('bookings:check-seat-consistency')
    ->hourly()
    ->withoutOverlapping();

/*
 * Nhắc khách trước ngày khởi hành.
 *
 * Chạy 8 giờ sáng, một lần mỗi ngày: thư nhắc gửi lúc 3 giờ sáng thì nằm dưới cùng hộp thư khi
 * người ta mở máy. Cửa sổ quét trải cả khoảng ngày nên một lần lỡ chạy vẫn bắt lại được, xem
 * SendDepartureReminders.
 */
Schedule::command('bookings:send-departure-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();

/*
 * Đẩy hàng đợi.
 *
 * Thư gửi đi đều là `ShouldQueue`, nên với `QUEUE_CONNECTION=database` chúng nằm trong bảng `jobs`
 * cho tới khi có ai đó chạy. Bảy lệnh ở trên đã buộc phải có `schedule:run` mỗi phút rồi, nên gắn
 * việc đẩy hàng đợi vào đó là không phải nhớ bật thêm một tiến trình nữa — và quên bật tiến trình
 * ấy nghĩa là thư im lặng không bao giờ tới, kiểu hỏng khó nhận ra nhất.
 *
 * `--stop-when-empty` để tiến trình tự thoát thay vì chạy mãi; `--max-time=50` để nó không lấn
 * sang phút sau, vì `withoutOverlapping` chỉ chặn được lần chạy chồng chứ không rút ngắn lần đang
 * chạy.
 *
 * Khi lượng thư lớn hơn, chạy `php artisan queue:work` như một dịch vụ riêng vẫn tốt hơn: ở đây
 * thư chỉ được gửi mỗi phút một đợt.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
