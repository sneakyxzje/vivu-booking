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

#[Signature('schedules:confirm-ready')]
#[Description('Chốt chuyến có khách đã thanh toán tại hạn chốt danh sách')]
class ConfirmReadySchedules extends Command
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    use \App\Console\Concerns\RunsWithDemoClock;

    public function handleForClock(): int
    {
        $window = \App\Services\DemoClock::commandNow()->addHours((int) config('booking.confirm_window_hours', 72));
        $hanMacDinh = (int) config('booking.booking_deadline_days', 3);

        $query = TourSchedule::query()->forClock()
            ->whereIn('status', [
                ScheduleStatus::Open->value,
            ])
            ->where('start_date', '>', \App\Services\DemoClock::commandNow())

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
        $chuaToiHan = 0;

        $query->orderBy('id')->chunkById(100, function ($schedules) use (&$confirmed, &$notEnough, &$chuaToiHan) {
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
                }

                $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

                if ($hanChot && \App\Services\DemoClock::commandNow()->lt($hanChot)) {
                    $this->line(sprintf(
                        'Chuyến #%d còn bán tới %s, chưa chốt.',
                        $schedule->id,
                        $hanChot->format('d/m/Y H:i'),
                    ));

                    $chuaToiHan++;

                    continue;
                }

                // Xử lý nợ trước khi chốt, kể cả khi chạy lệnh riêng.
                app(\App\Services\BookingBalanceDeadlineService::class)->settleSchedule($schedule);
                $paidPeople = $this->paidPeople($schedule);
                if ($paidPeople === 0) {
                    continue;
                }
                try {
                    $this->lifecycle->transitionTo(
                        $schedule,
                        ScheduleStatus::Confirmed,
                        'Chốt khách đã thanh toán; cam kết chạy kể cả dưới số khách mục tiêu.',
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
        $this->info(
            "Đã chốt {$confirmed} chuyến, {$notEnough} chuyến chưa đủ khách, "
            . "{$chuaToiHan} chuyến chưa tới hạn chốt."
        );

        return self::SUCCESS;
    }

    private function baoThieuKhach(TourSchedule $schedule, int $daCo, int $canCo): void
    {
        if ($schedule->understaffed_alert_sent_at !== null) {
            return;
        }

        $schedule->forceFill(['understaffed_alert_sent_at' => \App\Services\DemoClock::commandNow()])->save();

        $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

        app(Notifier::class)->toiDieuHanh(
            Alert::CHUYEN_THIEU_KHACH,
            sprintf('Chuyến #%d chưa đạt số khách mục tiêu', $schedule->id),
            sprintf(
                '%s · khởi hành %s · mới có %d trên %d khách đã thanh toán%s. Ưu tiên '
                    . 'ghép chuyến phù hợp; không ghép được vẫn tổ chức chuyến.',
                $schedule->tour?->title ?? 'Tour',
                $schedule->start_date?->format('d/m/Y') ?? 'chưa rõ',
                $daCo,
                $canCo,
                $hanChot ? ', hạn chốt ' . $hanChot->format('d/m/Y H:i') : '',
            ),

            '/admin/schedules',
        );
    }

    private function paidPeople(TourSchedule $schedule): int
    {
        return (int) Booking::query()->forClock()
            ->where('tour_schedule_id', $schedule->id)
            ->whereIn('status', BookingStatus::paidValues())
            ->with('payments')->get()
            ->filter(fn (Booking $don) => \App\Services\DemoClock::commandNow()->lt($schedule->booking_deadline ?? $schedule->defaultBookingDeadline())
                || app(\App\Services\BookingPaymentService::class)->balanceDue($don) <= 0)
            ->sum(fn (Booking $don): int => $don->seatsTaken());
    }

    private function notifyCustomers(TourSchedule $schedule): void
    {
        $bookings = Booking::query()->forClock()
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
