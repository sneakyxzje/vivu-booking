<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PassengerPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * G03, G05 - Điều hành sửa danh sách hành khách sau hạn chốt, và xem đơn nào khai còn thiếu.
 *
 * Điều hành sửa được cả sau hạn chốt vì họ là người gọi cho nhà cung cấp báo đổi tên. Nhưng sau
 * khi đoàn lên đường thì cũng dừng: danh sách lúc đó là dữ liệu đang dùng để điểm danh.
 */
class AdminPassengerController extends Controller
{
    public function __construct(
        private PassengerPolicyService $passengerPolicy,
    ) {
    }

    public function index(int $bookingId): JsonResponse
    {
        $booking = Booking::query()->with('schedule')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $quyen = $this->passengerPolicy->editability($booking);

        return $this->success([
            'passengers' => $booking->passengers()->get(),
            'guests' => (int) $booking->guests,
            'can_edit' => $quyen['admin'],
            'locked_reason' => $quyen['admin'] ? null : $quyen['reason'],
            'warnings' => $this->passengerPolicy->manifestWarnings($booking),
        ], 'Lấy danh sách hành khách thành công');
    }

    public function update(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate(PassengerPolicyService::validationRules());

        $booking = Booking::query()->with('schedule')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $this->passengerPolicy->assertAdminCanEdit($booking);
        $this->passengerPolicy->validateList($booking, $validated['passengers']);

        DB::transaction(function () use ($booking, $validated) {
            $this->passengerPolicy->replaceList($booking, $validated['passengers']);
        });

        return $this->success([
            'passengers' => $booking->passengers()->get(),
            'warnings' => $this->passengerPolicy->manifestWarnings($booking->fresh()),
        ], 'Đã cập nhật danh sách hành khách.');
    }

    /**
     * G05 - Các đơn của một chuyến còn khai thiếu hành khách.
     *
     * Điều hành cần danh sách này trước khi gửi danh sách đoàn cho nhà cung cấp, chứ không phải
     * mở từng đơn ra đếm.
     */
    public function incomplete(int $scheduleId): JsonResponse
    {
        $bookings = Booking::query()
            ->where('tour_schedule_id', $scheduleId)
            ->whereNotIn('status', ['cancelled', 'transferred'])
            ->with('passengers:id,booking_id,name,identity_number,is_contact')
            ->get(['id', 'customer_name', 'customer_phone', 'guests', 'status']);

        $thieu = $bookings
            ->map(function (Booking $booking) {
                $canhBao = $this->passengerPolicy->manifestWarnings($booking);

                return [
                    'booking_id' => $booking->id,
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'guests' => (int) $booking->guests,
                    'declared' => $booking->passengers->count(),
                    'missing' => $this->passengerPolicy->missingCount($booking),
                    'warnings' => $canhBao,
                ];
            })
            ->filter(fn (array $row) => $row['warnings'] !== [])
            ->values();

        return $this->success([
            'bookings' => $thieu,
            'total_missing' => $thieu->sum('missing'),
            // Danh sách đoàn chỉ xuất được khi mọi đơn đã khai đủ người.
            'can_export_manifest' => $thieu->sum('missing') === 0,
        ], 'Lấy danh sách đơn khai thiếu thành công');
    }
}
