<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChangeRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\BookingChangeRequest;
use App\Services\BookingChangeRequestService;
use App\Services\BookingHoldService;
use App\Services\BookingPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F03 - Điều hành duyệt hoặc từ chối yêu cầu của khách.
 *
 * Duyệt là thao tác chi tiền, nên màn này phải nói đủ để người bấm quyết định được: mức hoàn
 * chốt lúc khách gửi, mức hoàn nếu tính lại bây giờ, chỗ có về kho không, và yêu cầu này còn
 * duyệt được hay chuyến đã khởi hành mất rồi.
 */
class AdminChangeRequestController extends Controller
{
    public function __construct(
        private BookingChangeRequestService $changeRequests,
        private BookingPolicyService $bookingPolicy,
        private BookingHoldService $holdService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', ChangeRequestStatus::Pending->value);

        $query = BookingChangeRequest::query()
            ->with([
                'booking:id,tour_id,tour_schedule_id,customer_name,customer_email,guests,total_amount,status,paid_at',
                'booking.tour:id,title',
                'booking.schedule:id,start_date,booking_deadline,status',
                'requester:id,name,email',
                'reviewer:id,name',
            ])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15);

        return $this->success([
            'requests' => $requests,
            'pending_count' => BookingChangeRequest::query()->pending()->count(),
        ], 'Lấy danh sách yêu cầu thành công');
    }

    /**
     * Chi tiết một yêu cầu, chỉ gồm những gì thực sự dùng để quyết định.
     *
     * Không trả về mức hoàn tính lại tại thời điểm xem. Mức hoàn đã chốt lúc khách gửi và không
     * có đường nào đổi được, nên một con số thứ hai chỉ làm người duyệt phân vân giữa hai số
     * trong khi chỉ một số được chi.
     */
    public function show(int $id): JsonResponse
    {
        $yeuCau = BookingChangeRequest::query()
            ->with(['booking.tour:id,title', 'booking.schedule', 'requester:id,name,email', 'reviewer:id,name'])
            ->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu.', 404);
        }

        $booking = $yeuCau->booking;
        $schedule = $booking?->schedule;

        $conDuyetDuoc = true;
        $lyDoChan = null;

        try {
            $this->bookingPolicy->assertCancellable($booking, $schedule);
        } catch (BusinessRuleException $e) {
            $conDuyetDuoc = false;
            $lyDoChan = $e->getMessage();
        }

        return $this->success([
            'request' => $yeuCau,
            'seats_will_be_released' => $this->holdService->shouldReleaseSeats($booking, $schedule),
            'can_approve' => $conDuyetDuoc && !$yeuCau->status->isClosed(),
            'blocked_reason' => $lyDoChan,
        ], 'Lấy chi tiết yêu cầu thành công');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $yeuCau = BookingChangeRequest::query()->with('booking')->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu.', 404);
        }

        $daDuyet = $this->changeRequests->approve(
            $yeuCau,
            $request->user(),
            $validated['review_note'] ?? null,
        );

        $daTraCho = (bool) $daDuyet->booking?->seats_released;

        return $this->success(
            $daDuyet,
            $daTraCho
                ? 'Đã duyệt yêu cầu, hủy đơn và trả lại chỗ cho lịch khởi hành.'
                : 'Đã duyệt yêu cầu và hủy đơn. Đơn hủy sau hạn chốt danh sách nên chỗ chưa được '
                    . 'trả về kho, xem mục Chỗ đã hủy chưa mở bán lại.',
        );
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'review_note.required' => 'Vui lòng nêu lý do từ chối để khách hiểu.',
            'review_note.min' => 'Lý do từ chối cần ít nhất 10 ký tự.',
        ]);

        $yeuCau = BookingChangeRequest::query()->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu.', 404);
        }

        return $this->success(
            $this->changeRequests->reject($yeuCau, $request->user(), trim($validated['review_note'])),
            'Đã từ chối yêu cầu.',
        );
    }
}
