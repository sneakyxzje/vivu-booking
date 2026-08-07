<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCheckin;
use App\Models\CheckpointPhoto;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use Illuminate\Http\JsonResponse;

class AdminAttendanceController extends Controller
{
    /**
     * Admin xem lại toàn bộ điểm danh + ảnh check-in của một lịch khởi hành.
     * Cùng cấu trúc dữ liệu với màn điểm danh của guide nhưng chỉ đọc.
     */
    public function show(int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::query()
            ->with(['tour:id,title,number_of_days', 'guide:id,name,phone'])
            ->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành.', 404);
        }

        $itineraries = TourItinerary::query()
            ->where('tour_id', $schedule->tour_id)
            ->orderBy('day_number')
            ->get(['id', 'day_number', 'title', 'start_point', 'end_point']);

        $guests = Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->orderBy('customer_name')
            ->with('passengers:id,booking_id,name,type')
            ->get(['id', 'customer_name', 'customer_phone', 'guests']);

        $checkins = BookingCheckin::query()
            ->whereIn('booking_id', $guests->pluck('id'))
            ->get(['booking_id', 'tour_itinerary_id', 'present', 'checked_at']);

        $photos = CheckpointPhoto::query()
            ->where('tour_schedule_id', $schedule->id)
            ->latest()
            ->get(['id', 'tour_itinerary_id', 'image_path', 'created_at']);

        return $this->success([
            'schedule' => [
                'id' => $schedule->id,
                'start_date' => $schedule->start_date,
                'max_people' => (int) $schedule->max_people,
                'booked_people' => (int) $schedule->booked_people,
                'guide' => $schedule->guide,
            ],
            'tour' => [
                'id' => $schedule->tour->id,
                'title' => $schedule->tour->title,
                'number_of_days' => (int) $schedule->tour->number_of_days,
            ],
            'itineraries' => $itineraries,
            'guests' => $guests,
            'checkins' => $checkins,
            'photos' => $photos,
        ], 'Lấy dữ liệu điểm danh thành công');
    }
}
