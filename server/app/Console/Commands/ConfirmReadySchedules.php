<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\ScheduleLifecycleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A06 - Chốt các chuyến sắp tới hạn chốt danh sách và đã đủ số khách tối thiểu.
 *
 * Hai điều kiện, thiếu một trong hai là sai nghiệp vụ:
 *
 * 1. Chỉ xét chuyến sắp tới hạn chốt danh sách, không xét mọi chuyến đang mở bán.
 *    Chốt sớm sẽ khóa luôn việc bán tiếp: chuyến ở trạng thái confirmed không nhận đặt mới,
 *    nên một chuyến mới bán được 3 trên 22 chỗ mà bị chốt là mất 19 chỗ còn lại.
 *
 * 2. Đếm khách của các đơn ĐÃ TRẢ TIỀN, không dùng booked_people. booked_people gồm cả
 *    đơn pending đang giữ chỗ tạm và sẽ tự hủy sau mười phút nếu không thanh toán.
 *
 * Tài liệu: docs/nghiep-vu/04-luong-dieu-hanh.md mục 1.2
 */
#[Signature('schedules:confirm-ready')]
#[Description('Chốt các chuyến sắp tới hạn chốt danh sách và đã đủ số khách tối thiểu')]
class ConfirmReadySchedules extends Command
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $window = now()->addHours((int) config('booking.confirm_window_hours', 24));

        $query = TourSchedule::query()
            ->whereIn('status', [
                ScheduleStatus::Open->value,
                ScheduleStatus::Closed->value,
            ])
            ->where('start_date', '>', now())
            // Chỉ những chuyến đã tới hoặc sắp tới hạn chốt danh sách.
            ->whereNotNull('booking_deadline')
            ->where('booking_deadline', '<=', $window);

        if ($query->clone()->doesntExist()) {
            $this->info('Không có chuyến nào tới hạn chốt danh sách.');

            return self::SUCCESS;
        }

        $confirmed = 0;
        $notEnough = 0;

        $query->orderBy('id')->chunkById(100, function ($schedules) use (&$confirmed, &$notEnough) {
            foreach ($schedules as $schedule) {
                $paidPeople = $this->paidPeople($schedule);
                $minPeople = max(1, (int) $schedule->min_people);

                if ($paidPeople < $minPeople) {
                    $this->warn(sprintf(
                        'Chuyến #%d chưa đủ khách đã thanh toán: %d trên %d, còn thiếu %d.',
                        $schedule->id,
                        $paidPeople,
                        $minPeople,
                        $minPeople - $paidPeople,
                    ));

                    $notEnough++;

                    continue;
                }

                try {
                    $this->lifecycle->transitionTo(
                        $schedule,
                        ScheduleStatus::Confirmed,
                        'Tự động chốt chuyến do đã đủ số khách tối thiểu tại hạn chốt danh sách.',
                    );
                } catch (BusinessRuleException $e) {
                    $this->warn("Chuyến #{$schedule->id} không chuyển được sang đã chốt: {$e->getMessage()}");

                    continue;
                }

                $this->info(sprintf(
                    'Chuyến #%d đã chốt với %d trên %d khách đã thanh toán.',
                    $schedule->id,
                    $paidPeople,
                    $minPeople,
                ));

                $this->notifyCustomers($schedule);

                $confirmed++;
            }
        });

        $this->newLine();
        $this->info("Đã chốt {$confirmed} chuyến, {$notEnough} chuyến chưa đủ khách.");

        return self::SUCCESS;
    }

    /** Tổng số khách của các đơn đã trả tiền trên chuyến này. */
    private function paidPeople(TourSchedule $schedule): int
    {
        return (int) Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->whereIn('status', BookingStatus::paidValues())
            ->sum('guests');
    }

    /**
     * Gửi thư báo chuyến chắc chắn khởi hành.
     *
     * Bọc try/catch từng thư: máy chủ thư lỗi thì chỉ mất thông báo của một khách,
     * không được làm chết cả lệnh khiến các chuyến còn lại không được xử lý.
     */
    private function notifyCustomers(TourSchedule $schedule): void
    {
        $bookings = Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->whereIn('status', BookingStatus::paidValues())
            ->with(['customer', 'tour', 'schedule'])
            ->get();

        foreach ($bookings as $booking) {
            $email = $booking->customer?->email ?: $booking->customer_email;

            if (!$email) {
                continue;
            }

            try {
                Mail::to($email)->send(new BookingConfirmedMail($booking));
                $this->line("  Đã gửi thư cho {$email}");
            } catch (Throwable $exception) {
                Log::warning('Không gửi được thư báo chốt chuyến.', [
                    'schedule_id' => $schedule->id,
                    'booking_id' => $booking->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);

                $this->warn("  Không gửi được thư cho {$email}");
            }
        }
    }
}
