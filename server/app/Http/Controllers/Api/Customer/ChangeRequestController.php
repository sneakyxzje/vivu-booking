<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\BookingChangeRequestService;
use App\Services\BookingHoldService;
use App\Services\CancellationPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F02 - Khách xem mức hoàn dự kiến rồi gửi yêu cầu hủy.
 *
 * Tách khỏi BookingController vì đây là luồng khác hẳn: đơn chưa thanh toán thì khách tự hủy
 * qua BookingController::cancelBooking, đơn đã thanh toán thì đi qua đây và phải chờ duyệt.
 */
class ChangeRequestController extends Controller
{
    public function __construct(
        private BookingChangeRequestService $changeRequests,
        private CancellationPolicyService $cancellationPolicy,
        private BookingHoldService $holdService,
    ) {
    }

    /**
     * Mức hoàn nếu hủy ngay bây giờ, để khách xem trước khi quyết định gửi.
     *
     * Bước bắt buộc theo tài liệu 03 mục 5.2: cho khách xác nhận con số trước khi gửi là cách
     * duy nhất tránh khiếu nại "tôi tưởng được hoàn nhiều hơn" về sau.
     */
    public function preview(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->timDonCuaKhach($request, $bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        $duBao = $this->cancellationPolicy->quote($booking, $booking->schedule);

        return $this->success($duBao + [
            'seats_will_be_released' => $this->holdService->shouldReleaseSeats($booking, $booking->schedule),
            'policy_name' => $booking->cancellationPolicy?->name,
            'pending_request' => $this->yeuCauDangCho($booking),
        ], 'Lấy mức hoàn dự kiến thành công');
    }

    /** Gửi yêu cầu hủy. Đơn không đổi trạng thái cho tới khi điều hành duyệt. */
    public function store(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Vui lòng cho biết lý do bạn muốn hủy.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự để chúng tôi hiểu và xử lý nhanh hơn.',
        ]);

        $booking = $this->timDonCuaKhach($request, $bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        $yeuCau = $this->changeRequests->requestCancellation(
            $booking,
            trim($validated['reason']),
            $request->user(),
        );

        return $this->success(
            $yeuCau,
            'Đã gửi yêu cầu hủy. Bộ phận điều hành sẽ xem xét và phản hồi bạn.',
            201,
        );
    }

    /** Danh sách yêu cầu của chính khách, để theo dõi trạng thái. */
    public function index(Request $request): JsonResponse
    {
        $yeuCau = BookingChangeRequest::query()
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $request->user()->id))
            ->with('booking:id,tour_id,departure_date,total_amount,status')
            ->latest()
            ->get();

        return $this->success($yeuCau, 'Lấy danh sách yêu cầu thành công');
    }

    /** Khách rút lại yêu cầu khi chưa ai duyệt. */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $yeuCau = BookingChangeRequest::query()
            ->whereKey($id)
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $request->user()->id))
            ->first();

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu của bạn.', 404);
        }

        return $this->success(
            $this->changeRequests->withdraw($yeuCau),
            'Đã rút lại yêu cầu hủy.',
        );
    }

    private function timDonCuaKhach(Request $request, int $bookingId): ?Booking
    {
        return Booking::query()
            ->with(['schedule', 'cancellationPolicy.rules'])
            ->whereKey($bookingId)
            ->where('customer_id', $request->user()->id)
            ->first();
    }

    private function yeuCauDangCho(Booking $booking): ?BookingChangeRequest
    {
        return BookingChangeRequest::query()
            ->where('booking_id', $booking->getKey())
            ->pending()
            ->first();
    }
}
