<?php

namespace App\Http\Controllers\Api\Guide;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TourSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function dashboardData(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;
        $schedules = TourSchedule::where('guide_id', $guideId);
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
                // Gồm cả đơn đã chốt sau chuyến, xem chú thích cùng vấn đề ở AdminController.
                'revenue' => (float) (clone $bookings)
                    ->whereIn('status', BookingStatus::revenueValues())
                    ->sum('total_amount'),
            ],
        ]);
    }
}