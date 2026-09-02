<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Notifications\Alert;
use App\Services\Notifier;
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
        $hanMacDinh = (int) config('booking.booking_deadline_days', 3);

        $query = TourSchedule::query()
            ->whereIn('status', [
                ScheduleStatus::Open->value,
                ScheduleStatus::Closed->value,
            ])
            ->where('start_date', '>', now())
            /*
             * Chỉ những chuyến đã tới hoặc sắp tới hạn chốt danh sách — kể cả chuyến KHÔNG đặt hạn
             * chốt riêng.
             *
             * Bộ lọc cũ là `whereNotNull('booking_deadline')`, nên chuyến nào để trống cột ấy không
             * bao giờ được xét. Cả hệ thống coi những chuyến đó có hạn chốt mặc định (xem
             * `defaultBookingDeadline`), nên riêng ở đây chúng rơi vào một lỗ: không bao giờ được
             * chốt, và cũng không bao giờ bị hỏi đã đủ khách tối thiểu chưa.
             *
             * Hạn mặc định là `start_date` trừ N ngày, nên "hạn mặc định đã tới cửa sổ xét" tương
             * đương `start_date` sớm hơn cửa sổ cộng N ngày — viết được thành điều kiện SQL thường.
             */
            ->where(function ($q) use ($window, $hanMacDinh) {
                $q->where(function ($co) use ($window) {
                    $co->whereNotNull('booking_deadline')->where('booking_deadline', '<=', $window);
                })->orWhere(function ($khong) use ($window, $hanMacDinh) {
                    $khong->whereNull('booking_deadline')
                        ->where('start_date', '<=', $window->copy()->addDays($hanMacDinh));
                });
            });

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

                    $this->baoThieuKhach($schedule, $paidPeople, $minPeople);

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

    /**
     * Báo cho điều hành rằng chuyến này tới hạn chốt mà chưa đủ khách.
     *
     * Trước đây chỗ này chỉ `warn()` ra console rồi `continue`. Không ai ngồi đọc console của một
     * lệnh chạy mỗi phút, nên trên thực tế `min_people` chưa bao giờ được thực thi: chuyến hai
     * khách vẫn lăn bánh, và không có bước nào bắt ai đó quyết định chạy hay hủy.
     *
     * Hệ thống cố ý KHÔNG tự hủy chuyến. Chạy lỗ một chuyến nhỏ để giữ khách quen, hay hủy và đền
     * bù, là quyết định kinh doanh của con người — việc của lệnh này là đảm bảo con người ấy biết
     * mà quyết, đúng lúc còn kịp.
     *
     * Đánh dấu đã báo để mỗi chuyến chỉ nhận một thông báo. Lệnh chạy mỗi phút, và một thông báo
     * mỗi phút cho tới ngày khởi hành là cách chắc chắn nhất để người ta bỏ qua mọi thông báo.
     */
    private function baoThieuKhach(TourSchedule $schedule, int $daCo, int $canCo): void
    {
        if ($schedule->understaffed_alert_sent_at !== null) {
            return;
        }

        $schedule->forceFill(['understaffed_alert_sent_at' => now()])->save();

        $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

        app(Notifier::class)->toiDieuHanh(
            Alert::CHUYEN_THIEU_KHACH,
            sprintf('Chuyến #%d chưa đủ khách tối thiểu', $schedule->id),
            sprintf(
                '%s · khởi hành %s · mới có %d trên %d khách đã thanh toán%s. Cần quyết định cho '
                    . 'chạy hay hủy chuyến và đền bù.',
                $schedule->tour?->title ?? 'Tour',
                $schedule->start_date?->format('d/m/Y') ?? 'chưa rõ',
                $daCo,
                $canCo,
                $hanChot ? ', hạn chốt ' . $hanChot->format('d/m/Y H:i') : '',
            ),
            '/admin/schedules/' . $schedule->id,
        );
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
