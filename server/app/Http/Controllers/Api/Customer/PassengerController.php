<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PassengerPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * G03 - Khách tự sửa danh sách hành khách, trong khoảng thời gian còn được phép.
 *
 * Trước hạn chốt danh sách thì khách sửa thoải mái. Sau hạn chốt, danh sách đã gửi cho khách
 * sạn và nhà xe nên chỉ điều hành sửa được. Sau khi đoàn lên đường thì không ai sửa.
 *
 * Xem docs/nghiep-vu/02-luong-dat-tour.md mục 3.1.
 */
class PassengerController extends Controller
{
    public function __construct(
        private PassengerPolicyService $passengerPolicy,
    ) {
    }

    /** Danh sách hiện tại kèm cho biết còn sửa được không, để giao diện ẩn nút đúng lúc. */
    public function index(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->timDon($request, $bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        $quyen = $this->passengerPolicy->editability($booking);

        return $this->success([
            'passengers' => $booking->passengers()->get(),
            'guests' => (int) $booking->guests,
            'can_edit' => $quyen['customer'],
            'locked_reason' => $quyen['customer'] ? null : $quyen['reason'],
            'warnings' => $this->passengerPolicy->manifestWarnings($booking),
        ], 'Lấy danh sách hành khách thành công');
    }

    /**
     * Ghi đè cả danh sách.
     *
     * Nhận cả danh sách chứ không sửa từng người: các luật kiểm tra là luật của cả đơn, ví dụ
     * trùng số giấy tờ, nên phải nhìn thấy toàn bộ mới kiểm được.
     */
    public function update(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate(PassengerPolicyService::validationRules());

        $booking = $this->timDon($request, $bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        $this->passengerPolicy->assertCustomerCanEdit($booking);
        $this->passengerPolicy->validateList($booking, $validated['passengers']);

        DB::transaction(function () use ($booking, $validated) {
            $this->passengerPolicy->replaceList($booking, $validated['passengers']);
        });

        return $this->success([
            'passengers' => $booking->passengers()->get(),
            'warnings' => $this->passengerPolicy->manifestWarnings($booking->fresh()),
        ], 'Đã cập nhật danh sách hành khách.');
    }

    private function timDon(Request $request, int $bookingId): ?Booking
    {
        return Booking::query()
            ->with('schedule')
            ->whereKey($bookingId)
            ->where('customer_id', $request->user()->id)
            ->first();
    }
}
