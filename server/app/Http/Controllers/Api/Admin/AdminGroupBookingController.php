<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GroupBookingRequest;
use App\Services\GroupBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phía điều hành của luồng booking đoàn: báo giá, chốt, ghi sổ thu tiền, giảm số khách.
 */
class AdminGroupBookingController extends Controller
{
    public function __construct(private GroupBookingService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $ds = GroupBookingRequest::query()
            ->with([
                'tour:id,title',
                'schedule:id,start_date,booking_deadline,max_people,booked_people',
                'booking:id,group_booking_request_id,guests,total_amount,status,paid_at',
                'quotedBy:id,name',
            ])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15);

        $ds->getCollection()->transform(fn (GroupBookingRequest $yc) => $this->dong($yc));

        return $this->success($ds, 'Lấy danh sách yêu cầu đoàn thành công');
    }

    public function quote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'price_per_person' => ['required', 'numeric', 'min:1'],
            'free_slots' => ['required', 'integer', 'min:0'],
            'expires_at' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'expires_at.after' => 'Hạn hiệu lực báo giá phải ở tương lai.',
        ]);

        $yeuCau = GroupBookingRequest::query()->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu', 404);
        }

        $this->service->quote(
            $yeuCau,
            (float) $data['price_per_person'],
            (int) $data['free_slots'],
            Carbon::parse($data['expires_at']),
            $data['note'] ?? null,
            $request->user(),
        );

        return $this->success($this->dong($yeuCau->fresh(['tour:id,title', 'schedule', 'booking', 'quotedBy:id,name'])), 'Đã lưu báo giá. Báo cho khách qua điện thoại hoặc email của họ.');
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'final_guests' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $yeuCau = GroupBookingRequest::query()->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu', 404);
        }

        $booking = $this->service->confirm($yeuCau, (int) $data['final_guests'], $request->user());

        return $this->success([
            'booking_id' => $booking->id,
            'public_token' => $booking->public_token,
            'guests' => $booking->guests,
            'total_amount' => (float) $booking->total_amount,
        ], sprintf(
            'Đã chốt đoàn %d người, tổng %s đ. Nhớ ghi khoản cọc vào sổ khi tiền về.',
            $booking->guests,
            number_format((float) $booking->total_amount, 0, ',', '.'),
        ));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Phải ghi lý do từ chối - khách cần biết vì sao để còn tính phương án khác.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự.',
        ]);

        $yeuCau = GroupBookingRequest::query()->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu', 404);
        }

        $this->service->reject($yeuCau, $data['reason'], $request->user());

        return $this->success(null, 'Đã từ chối yêu cầu.');
    }

    /*
     * ĐÃ CHUYỂN: sổ giao dịch (`payments`, `storePayment`).
     *
     * Nay ở `AdminBookingPaymentController`, cùng đường dẫn cũ. Sổ ra đời cùng booking đoàn nên
     * từng nằm ở đây, nhưng từ khi đơn lẻ cũng trả nhiều đợt thì nó không còn là chuyện riêng
     * của đoàn. Cái còn lại đúng chỗ ở đây là `reduceGuests` bên dưới: giảm số khách là luật chỉ
     * đoàn mới có.
     */

    public function reduceGuests(Request $request, int $bookingId): JsonResponse
    {
        $data = $request->validate([
            'new_guests' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::query()->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn hàng', 404);
        }

        $daSua = $this->service->reduceGuests($booking, (int) $data['new_guests'], $request->user(), $data['reason'] ?? null);

        $daThu = $this->service->netPaid($daSua);
        $chenh = max(0, $daThu - round((float) $daSua->total_amount));

        return $this->success([
            'guests' => $daSua->guests,
            'total_amount' => (float) $daSua->total_amount,
            'net_paid' => $daThu,
            'overpaid' => $chenh,
        ], $chenh > 0
            ? sprintf('Đã giảm còn %d khách. Đơn đang thừa %s đ so với tổng mới - thống nhất với khách rồi ghi một khoản hoàn vào sổ.', $daSua->guests, number_format($chenh, 0, ',', '.'))
            : sprintf('Đã giảm còn %d khách, chỗ dư đã trả về chuyến.', $daSua->guests));
    }

    /** @return array<string, mixed> */
    private function dong(GroupBookingRequest $yc): array
    {
        return [
            'id' => $yc->id,
            'status' => $yc->status->value,
            'status_label' => $yc->status->label(),
            'tour_title' => $yc->tour?->title,
            'schedule_id' => $yc->tour_schedule_id,
            'start_date' => $yc->schedule?->start_date,
            // Chỗ còn trống hiện ngay trên danh sách: yêu cầu 40 người vào chuyến còn 5 chỗ là
            // thứ điều hành cần thấy TRƯỚC khi nhấc máy báo giá, không phải lúc bấm chốt.
            'remaining_seats' => $yc->schedule
                ? max(0, (int) $yc->schedule->max_people - (int) $yc->schedule->booked_people)
                : null,
            'booking_deadline' => $yc->schedule?->booking_deadline,
            'contact_name' => $yc->contact_name,
            'contact_email' => $yc->contact_email,
            'contact_phone' => $yc->contact_phone,
            'estimated_guests' => $yc->estimated_guests,
            'company_name' => $yc->company_name,
            'tax_code' => $yc->tax_code,
            'invoice_address' => $yc->invoice_address,
            'note' => $yc->note,
            'quote' => $yc->quoted_price_per_person === null ? null : [
                'price_per_person' => (float) $yc->quoted_price_per_person,
                'free_slots' => $yc->quoted_free_slots,
                'note' => $yc->quote_note,
                'expires_at' => $yc->quote_expires_at,
                'expired' => $yc->quoteExpired(),
                'quoted_by' => $yc->quotedBy?->name,
            ],
            'rejected_reason' => $yc->rejected_reason,
            'booking' => $yc->booking === null ? null : [
                'id' => $yc->booking->id,
                'guests' => $yc->booking->guests,
                'total_amount' => (float) $yc->booking->total_amount,
                'status' => $yc->booking->status,
                'paid_in_full' => $yc->booking->paid_at !== null,
            ],
            'created_at' => $yc->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
