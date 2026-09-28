<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPassengerSupplement;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PassengerSupplementService
{
    public function __construct(
        private PassengerPolicyService $policy,
        private ScheduleLifecycleService $lifecycle,
        private BookingPaymentService $payments,
        private BookingAuditLogger $audit,
    ) {}

    public function unavailableReason(Booking $booking): ?string
    {
        $schedule = $booking->schedule;
        if (!$schedule || $this->lifecycle->effectiveStatus($schedule)->isFinal()) {
            return 'Chuyến đã kết thúc hoặc đã hủy, không thể bổ sung hành khách.';
        }
        if ($booking->status !== 'confirmed') {
            return 'Chỉ bổ sung hành khách cho đơn đã xác nhận còn hiệu lực.';
        }
        $deadline = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();
        if (DemoClock::schedule($schedule)->gte($deadline) && $this->payments->balanceDue($booking) > 0) {
            return 'Đơn chưa thanh toán đủ trước hạn chốt. Vui lòng kiểm tra thanh toán.';
        }
        if ($this->policy->missingCount($booking) === 0) {
            return 'Đơn đã khai đủ số hành khách đã đặt.';
        }
        return null;
    }

    public function append(int $bookingId, array $data, User $actor): BookingPassengerSupplement
    {
        return DB::transaction(function () use ($bookingId, $data, $actor) {
            $booking = Booking::findOrFail($bookingId);
            // Same lock order as transfer/cancellation: schedule, then booking.
            $schedule = TourSchedule::whereKey($booking->tour_schedule_id)->lockForUpdate()->first();
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            if (!$schedule || (int) $booking->tour_schedule_id !== (int) $schedule->id) {
                throw new BusinessRuleException('Đơn vừa đổi chuyến. Vui lòng tải lại danh sách.', 409);
            }
            $booking->setRelation('schedule', $schedule);
            $fingerprint = hash('sha256', json_encode([
                $data['passengers'], $data['reported_by'], $data['reason'],
            ], JSON_THROW_ON_ERROR));
            $previous = BookingPassengerSupplement::where('booking_id', $bookingId)
                ->where('request_key', $data['request_key'])->first();
            if ($previous) {
                if (!hash_equals($previous->payload_hash, $fingerprint)) {
                    throw new BusinessRuleException('Yêu cầu này đã được lưu với nội dung khác. Vui lòng tải lại.', 409);
                }
                return $previous;
            }
            if ($reason = $this->unavailableReason($booking)) {
                throw new BusinessRuleException($reason);
            }
            $existing = $booking->passengers()->orderBy('id')->get();
            $combined = [...$existing->toArray(), ...$data['passengers']];
            $this->policy->validateList($booking, $combined);
            if (count(array_filter($combined, fn ($p) => !empty($p['is_contact']))) > 1) {
                throw new BusinessRuleException('Đơn đã có người liên hệ. Không thể chọn thêm người liên hệ khác.');
            }
            $added = [];
            foreach ($data['passengers'] as $passenger) {
                // Never replace existing rows: their IDs are referenced by attendance.
                $added[] = $booking->passengers()->create($passenger)->toArray();
            }
            $supplement = BookingPassengerSupplement::create([
                'booking_id' => $bookingId, 'tour_schedule_id' => $schedule->id,
                'request_key' => $data['request_key'], 'payload_hash' => $fingerprint,
                'passengers' => $added, 'reported_by' => $data['reported_by'], 'reason' => $data['reason'],
                'created_by' => $actor->id, 'created_by_name' => $actor->name,
                'recorded_at' => DemoClock::schedule($schedule),
            ]);
            $this->audit->log($booking, BookingAuditAction::PassengersSupplemented,
                ['declared' => $existing->count()],
                ['supplement_id' => $supplement->id, 'passenger_ids' => array_column($added, 'id'), 'reported_by' => $data['reported_by']],
                $data['reason']);
            DB::afterCommit(function () use ($schedule, $bookingId, $added) {
                foreach ($schedule->guides as $guide) {
                    app(Notifier::class)->toiNguoiDung($guide, 'passengers_supplemented',
                        "Đã bổ sung hành khách cho BK{$bookingId}",
                        count($added).' hành khách đã được bổ sung. Tải lại danh sách để điểm danh trong ngày.',
                        '/guide/attendance/'.$schedule->id);
                }
            });
            return $supplement;
        });
    }

    public function markSent(int $bookingId, int $supplementId, array $data, User $actor): BookingPassengerSupplement
    {
        return DB::transaction(function () use ($bookingId, $supplementId, $data, $actor) {
            $supplement = BookingPassengerSupplement::where('booking_id', $bookingId)
                ->whereKey($supplementId)->lockForUpdate()->firstOrFail();
            if ($supplement->sent_at) {
                return $supplement; // Retrying must not overwrite who sent the original update.
            }
            $supplement->update([
                'sent_at' => now(), 'sent_by' => $actor->id, 'sent_by_name' => $actor->name,
                'sent_to' => $data['sent_to'], 'sent_note' => $data['sent_note'] ?? null,
            ]);
            $this->audit->log(Booking::findOrFail($bookingId), BookingAuditAction::PassengerSupplementSent,
                null, ['supplement_id' => $supplementId, 'sent_to' => $data['sent_to']], $data['sent_note'] ?? null);
            return $supplement;
        });
    }
}
