<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Mail\DepartureReminderMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Nhắc khách trước ngày khởi hành.
 *
 * ## Vì sao cần
 *
 * Thư đặt tour gửi lúc khách trả tiền, có khi hàng tháng trước ngày đi. Tới sát ngày, bốn câu
 * khách cần lại nằm trong lá thư ấy: mấy giờ tập trung, đón ở đâu, ai dẫn đoàn, gọi ai khi có
 * chuyện. Không nhắc thì họ gọi lên tổng đài hỏi từng câu một.
 *
 * Hướng dẫn viên cũng chỉ được phân công gần ngày đi, nên tên và số điện thoại của người dẫn
 * KHÔNG thể có trong thư đặt tour — đây là thư đầu tiên nói được điều đó.
 *
 * ## Ai được nhận
 *
 * Đơn đã thu tiền, của chuyến chưa hủy, và chưa từng nhận thư này. Đơn chưa trả tiền không nhận:
 * chỗ của họ có thể đã bị nhả từ lâu, nhắc họ ra bến là mời họ tới một chuyến không có tên mình.
 */
class SendDepartureReminders extends Command
{
    protected $signature = 'bookings:send-departure-reminders';

    protected $description = 'Gửi thư nhắc khách trước ngày khởi hành';

    public function handle(): int
    {
        $soNgay = (int) config('booking.departure_reminder_days', 3);

        /*
         * Cửa sổ quét: từ bây giờ tới mốc N ngày nữa.
         *
         * Cố ý KHÔNG chỉ lấy đúng ngày thứ N. Lệnh chạy mỗi ngày một lần, và một lần không chạy
         * được — máy bảo trì, tác vụ nền chết — sẽ làm cả nhóm khách của ngày ấy không bao giờ
         * nhận thư, vì hôm sau họ đã rơi ra ngoài khung. Quét cả khoảng thì lần chạy kế tiếp bắt
         * lại được họ, và cột `departure_reminder_sent_at` lo việc không gửi trùng.
         */
        $den = now()->addDays($soNgay)->endOfDay();

        $bookings = Booking::query()
            ->with(['tour', 'schedule.guides:id,name,phone', 'customer:id,email'])
            ->whereIn('status', BookingStatus::paidValues())
            ->whereNull('departure_reminder_sent_at')
            ->whereHas('schedule', fn ($q) => $q
                ->whereNotIn('status', [ScheduleStatus::Cancelled->value, ScheduleStatus::Completed->value])
                ->where('start_date', '>', now())
                ->where('start_date', '<=', $den))
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('Không có đơn nào tới hạn nhắc.');

            return self::SUCCESS;
        }

        $daGui = 0;

        foreach ($bookings as $booking) {
            $email = $booking->customer?->email ?: $booking->customer_email;

            if (!$email) {
                continue;
            }

            try {
                Mail::to($email)->send(new DepartureReminderMail($booking));

                /*
                 * Đóng mốc SAU khi gửi thành công.
                 *
                 * Đóng trước thì một lỗi máy chủ thư biến thành "đã nhắc rồi" vĩnh viễn, và khách
                 * ấy im lặng không bao giờ nhận được gì. Rủi ro ngược lại — gửi được nhưng chưa
                 * kịp ghi mốc rồi tiến trình chết — chỉ dẫn tới một thư trùng, phiền hơn nhưng
                 * không ai lỡ chuyến vì nó.
                 */
                $booking->forceFill(['departure_reminder_sent_at' => now()])->save();
                $daGui++;

                $this->line("  Đã nhắc {$email} (đơn #{$booking->id})");
            } catch (Throwable $exception) {
                Log::warning('Không gửi được thư nhắc khởi hành.', [
                    'booking_id' => $booking->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);

                $this->warn("  Không gửi được cho {$email}");
            }
        }

        $this->info("Đã gửi {$daGui} thư nhắc khởi hành.");

        return self::SUCCESS;
    }
}
