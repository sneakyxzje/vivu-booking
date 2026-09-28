<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\BookingTransfer;
use App\Models\TourSchedule;
use Illuminate\Support\Carbon;

/** Một mốc hoàn hủy: hạn chốt danh sách của chuyến hiện tại. */
class CancellationPolicyService
{
    public const NAME = 'Hoàn hủy theo hạn chốt danh sách';
    public const VERSION = 'booking_deadline_v1';

    public function __construct(private readonly BookingPaymentService $payments) {}

    /** Các nhãn công khai dùng chung cho API, hợp đồng và chatbot. */
    public static function publicRules(): array
    {
        return [
            ['window' => 'Trước hạn chốt danh sách', 'refund_percent' => 100,
                'note' => 'Hoàn toàn bộ số tiền đã thanh toán.'],
            ['window' => 'Từ hạn chốt danh sách đến trước giờ khởi hành', 'refund_percent' => 50,
                'note' => 'Giữ cọc 50% giá trị đơn, tối đa bằng số đã thu; hoàn phần đã trả vượt cọc.'],
        ];
    }

    public function hoursBeforeDeparture(?TourSchedule $schedule, ?Carbon $now = null): ?float
    {
        return $schedule?->start_date
            ? ($now ?? DemoClock::schedule($schedule))->floatDiffInHours(Carbon::parse($schedule->start_date), false)
            : null;
    }

    public function quote(
        Booking $booking,
        ?TourSchedule $schedule = null,
        ?Carbon $now = null,
        bool $congTyHuy = false,
    ): array {
        $schedule ??= $booking->schedule;
        $now ??= DemoClock::schedule($schedule);
        $departure = $schedule?->start_date ?? $booking->departure_date;
        $departure = $departure ? Carbon::parse($departure) : null;
        $deadline = $schedule
            ? ($schedule->booking_deadline ?? $schedule->defaultBookingDeadline())
            : $booking->balanceDueAt();
        $beforeDeadline = $deadline !== null && $now->lt($deadline);
        $beforeDeparture = $departure !== null && $now->lt($departure);
        $movedByCompany = $this->congTyDaDoiNgay($booking);
        $waived = $movedByCompany || $congTyHuy;
        $total = round((float) $booking->total_amount);
        $paid = $this->payments->paidForTour($booking);

        // Đúng thời điểm chốt đã là mất cọc. Đã khởi hành thì không cho hủy;
        // quote trả 0 tiền hoàn, còn các cửa ghi chặn bằng BookingPolicyService.
        $fee = $waived || ($beforeDeparture && $beforeDeadline)
            ? 0.0 : ($beforeDeparture ? $booking->depositAmount() : $total);
        $percent = $waived || ($beforeDeparture && $beforeDeadline) ? 100 : ($beforeDeparture ? 50 : 0);

        return [
            'policy_version' => self::VERSION,
            'policy_name' => self::NAME,
            'hours_before' => $departure ? $now->floatDiffInHours($departure, false) : null,
            // Tỷ lệ trên giá trị đơn, giữ tên trường để tương thích các yêu cầu hủy đã lưu.
            'refund_percent' => $percent,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'cancellation_fee' => (float) $fee,
            'refund_amount' => max(0.0, $paid - $fee),
            'booking_deadline' => $deadline?->format('Y-m-d H:i:s'),
            'before_deadline' => $beforeDeadline,
            'moved_by_company' => $movedByCompany,
            'company_initiated' => $congTyHuy,
            'fee_waived' => $waived,
        ];
    }

    /** Giữ phí tại lúc khách gửi yêu cầu, kể cả khi duyệt sau hạn chốt. */
    public function quoteForRequest(Booking $booking, BookingChangeRequest $request): array
    {
        $payload = $request->payload ?? [];
        $quotedAt = isset($payload['quoted_at']) ? Carbon::parse($payload['quoted_at']) : $request->created_at;
        $quote = $this->quote($booking, now: $quotedAt);
        // Yêu cầu cũ còn chờ cũng dùng luật mới tại thời điểm gửi; không đọc bậc 20/15/12 ngày.
        if (($payload['policy_version'] ?? null) === self::VERSION && !$quote['fee_waived']) {
            $quote['cancellation_fee'] = (float) $payload['cancellation_fee'];
            $quote['refund_percent'] = (int) $request->estimated_refund_percent;
            $quote['booking_deadline'] = $payload['booking_deadline'] ?? null;
            $quote['refund_amount'] = max(0.0, $quote['paid_amount'] - $quote['cancellation_fee']);
        }

        return $quote;
    }

    /** Điều hành hủy hộ cũng phải tôn trọng yêu cầu khách đã gửi trước hạn. */
    public function quoteIncludingPendingRequest(Booking $booking, ?TourSchedule $schedule = null, bool $congTyHuy = false): array
    {
        if ($schedule) {
            $booking->setRelation('schedule', $schedule);
        }
        $request = $congTyHuy ? null : BookingChangeRequest::query()
            ->where('booking_id', $booking->id)->where('type', 'cancel')->pending()->first();

        return $request ? $this->quoteForRequest($booking, $request)
            : $this->quote($booking, $schedule, congTyHuy: $congTyHuy);
    }

    public function congTyDaDoiNgay(Booking $booking): bool
    {
        return $booking->tour_schedule_id && BookingTransfer::query()
            ->where('booking_id', $booking->getKey())
            ->where('initiated_by', 'company')
            ->where('to_schedule_id', $booking->tour_schedule_id)
            ->exists();
    }
}
