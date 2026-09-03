<?php

namespace App\Http\Controllers\Api\Guide;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\BookingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function __construct(private BookingPaymentService $payments)
    {
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;
        $schedules = TourSchedule::whereHas('guides', fn ($query) => $query->whereKey($guideId));
        $scheduleIds = (clone $schedules)->pluck('id');
        $bookings = Booking::whereIn('tour_schedule_id', $scheduleIds);

        return response()->json([
            'success' => true,
            'message' => 'Lấy dữ liệu tổng quan hướng dẫn viên thành công',
            'data' => [
                'total_tours' => (clone $schedules)->distinct()->count('tour_id'),
                'active_tours' => (clone $schedules)->whereIn('status', [ScheduleStatus::Open->value, ScheduleStatus::Confirmed->value, ScheduleStatus::InProgress->value])->distinct()->count('tour_id'),
                'full_tours' => (clone $schedules)->where('status', ScheduleStatus::Closed->value)->distinct()->count('tour_id'),
                'total_bookings' => (clone $bookings)->count(),
                'pending_bookings' => (clone $bookings)->where('status', 'pending')->count(),
                /*
                 * Doanh thu là tiền ĐÃ VỀ, cộng từ sổ giao dịch — giống hệt bảng điều khiển của
                 * điều hành.
                 *
                 * Trước đây chỗ này cộng `total_amount`, tức giá trị đơn hàng. Cùng một chữ "doanh
                 * thu" mà hai màn hình trong cùng hệ thống trả về hai con số khác nhau: bảng của
                 * điều hành đã sửa sang tiền thực thu, bảng của hướng dẫn viên thì chưa. Người đọc
                 * so hai màn rồi mất lòng tin vào cả hai, mà không biết bên nào đúng.
                 */
                'revenue' => $this->payments->sumPaidForTour(
                    (clone $bookings)
                        ->whereIn('status', BookingStatus::revenueValues())
                        ->get(['id', 'total_amount', 'paid_at']),
                ),
            ],
        ]);
    }
}