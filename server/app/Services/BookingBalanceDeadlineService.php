<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Enums\ScheduleStatus;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\TourSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Loại đơn thiếu tiền tại hạn chốt, trước khi lập danh sách cuối cùng. */
class BookingBalanceDeadlineService
{
    public function __construct(
        private BookingPaymentService $payments,
        private BookingHoldService $holds,
        private BookingAuditLogger $audit,
        private CancellationPolicyService $cancellationPolicy,
    ) {}

    public function overdue(Booking $booking): bool
    {
        return !$booking->isGroup()
            && in_array($booking->status, BookingStatus::paidValues(), true)
            && $booking->balanceDueAt()?->lte(DemoClock::booking($booking))
            && $this->payments->paidForTour($booking) > 0
            && $this->payments->balanceDue($booking) > 0;
    }

    public function cancel(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $schedule = TourSchedule::query()->whereKey($booking->tour_schedule_id)->lockForUpdate()->first();
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
            if (!$schedule || !$locked || $locked->tour_schedule_id !== $schedule->id
                || in_array($schedule->status, [ScheduleStatus::Cancelled, ScheduleStatus::Completed, ScheduleStatus::InProgress], true)) {
                return false;
            }
            $locked->setRelation('schedule', $schedule);
            if (!$this->overdue($locked)) {
                return false;
            }
            $before = $locked->status;
            // Mất đúng tiền cọc; khoản trả thêm nhưng chưa đủ được ghi nghĩa vụ hoàn.
            $paid = $this->payments->paidForTour($locked);
            $forfeit = min($paid, $locked->depositAmount());
            $refund = max(0, $paid - $forfeit);
            $pendingRequest = BookingChangeRequest::query()->where('booking_id', $locked->id)
                ->where('type', ChangeRequestType::Cancel->value)->pending()->lockForUpdate()->first();
            $requestQuote = $pendingRequest
                ? $this->cancellationPolicy->quoteForRequest($locked, $pendingRequest) : null;
            if ($requestQuote) {
                // Khách đã xin hủy trước hạn không mất cọc vì điều hành chưa kịp duyệt.
                $forfeit = min($forfeit, $requestQuote['cancellation_fee']);
                $refund = max(0, $paid - $forfeit);
            }
            $reason = 'Không thanh toán đủ trước hạn chốt danh sách '
                . $locked->balanceDueAt()->format('H:i d/m/Y') . '. Đơn bị hủy và mất cọc 50%.';
            if ($pendingRequest) {
                $reason = 'Đơn được hủy tại hạn chốt danh sách. Tiền hoàn tính theo yêu cầu hủy đã gửi: '
                    . $pendingRequest->request_note;
            }
            $locked->forceFill([
                'status' => BookingStatus::Cancelled->value,
                'cancel_type' => $pendingRequest ? 'by_customer' : 'unpaid_balance',
                'cancel_reason' => $reason,
                'cancelled_at' => DemoClock::booking($locked),
                'refund_amount' => $this->payments->nghiaVuHoanGop($locked, $refund),
            ])->save();
            if ($pendingRequest) {
                $pendingRequest->update([
                    'status' => ChangeRequestStatus::Approved,
                    'estimated_refund' => $refund,
                    'estimated_refund_percent' => $requestQuote['refund_percent'],
                    'reviewed_at' => DemoClock::booking($locked),
                    'review_note' => 'Hệ thống xử lý yêu cầu khi đơn chưa thanh toán đủ tại hạn chốt; giữ mức phí tại lúc gửi.',
                ]);
            }
            $this->holds->releaseHold($locked, $schedule);
            $this->audit->logStatusChange($locked, BookingAuditAction::Cancelled, $before,
                BookingStatus::Cancelled->value, $reason, [
                    'deposit_forfeited' => $forfeit,
                    'refund_amount' => $refund,
                    'request_id' => $pendingRequest?->id,
                    'seats_released' => (bool) $locked->fresh()->seats_released,
                ]);
            DB::afterCommit(function () use ($locked) {
                app(Notifier::class)->toiDieuHanh(
                    \App\Notifications\Alert::CHUYEN_TRONG_CHO,
                    "Đơn #{$locked->id} bị hủy vì chưa trả đủ trước hạn chốt",
                    'Đơn đã hủy và ghi nhận tiền hoàn theo chính sách. Cập nhật danh sách cuối cùng và vẫn tổ chức chuyến cho khách đã trả đủ, kể cả dưới số khách mục tiêu.',
                    '/admin/schedules',
                );
                try {
                    if ($locked->customer_email) {
                        Mail::to($locked->customer_email)->send(new BookingCancelledMail($locked));
                    }
                } catch (Throwable $e) {
                    Log::warning('Không gửi được thư hủy đơn quá hạn.', ['booking_id' => $locked->id, 'error' => $e->getMessage()]);
                }
            });
            return true;
        });
    }

    public function settleSchedule(TourSchedule $schedule): void
    {
        foreach ($schedule->bookings()->whereIn('status', BookingStatus::paidValues())->get() as $booking) {
            $this->cancel($booking);
        }
    }
}
