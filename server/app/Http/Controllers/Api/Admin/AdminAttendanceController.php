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

    /**
     * Admin xem báo cáo tổng hợp điểm danh toàn hệ thống.
     * Hỗ trợ bộ lọc từ ngày -> đến ngày, từ khóa, trạng thái và phân trang.
     */
    public function report(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = TourSchedule::query()
            ->with(['tour:id,title,number_of_days', 'guide:id,name,phone']);

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('start_date', '<=', $request->input('to_date'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('tour', function ($t) use ($search) {
                    $t->where('title', 'like', $search);
                })->orWhereHas('guide', function ($g) use ($search) {
                    $g->where('name', 'like', $search);
                });
            });
        }

        $allSchedules = (clone $query)->get();

        $totalCheckins = BookingCheckin::count();
        $totalPresent = BookingCheckin::where('present', true)->count();
        $totalAbsent = BookingCheckin::where('present', false)->count();

        $overallPresenceRate = $totalCheckins > 0
            ? round(($totalPresent / $totalCheckins) * 100, 1)
            : 100;

        $perPage = (int) $request->input('per_page', 10);
        $paginatedSchedules = $query->latest('start_date')->paginate($perPage);

        $scheduleReports = collect($paginatedSchedules->items())->map(function ($schedule) {
            $guests = Booking::query()
                ->where('tour_schedule_id', $schedule->id)
                ->where('status', 'confirmed')
                ->pluck('id');

            $checkins = BookingCheckin::query()
                ->whereIn('booking_id', $guests)
                ->get();

            $present = $checkins->where('present', true)->count();
            $absent = $checkins->where('present', false)->count();
            $total = $checkins->count();

            $rate = $total > 0 ? round(($present / $total) * 100, 1) : 100;
            $photoCount = CheckpointPhoto::where('tour_schedule_id', $schedule->id)->count();

            return [
                'id' => $schedule->id,
                'start_date' => $schedule->start_date,
                'status' => $schedule->status,
                'booked_people' => (int) $schedule->booked_people,
                'tour_id' => $schedule->tour->id ?? null,
                'tour_title' => $schedule->tour->title ?? 'N/A',
                'number_of_days' => (int) ($schedule->tour->number_of_days ?? 1),
                'guide' => $schedule->guide ? [
                    'id' => $schedule->guide->id,
                    'name' => $schedule->guide->name,
                    'phone' => $schedule->guide->phone,
                ] : null,
                'present_count' => $present,
                'absent_count' => $absent,
                'total_checkins' => $total,
                'presence_rate' => $rate,
                'photo_count' => $photoCount,
            ];
        });

        $missingPhotosCount = $allSchedules->filter(function ($schedule) {
            return CheckpointPhoto::where('tour_schedule_id', $schedule->id)->count() === 0 && $schedule->booked_people > 0;
        })->count();

        $absenceLogs = BookingCheckin::query()
            ->where('present', false)
            ->with([
                'booking:id,customer_name,customer_phone,tour_schedule_id',
                'tourItinerary:id,day_number,title,tour_id',
                'guide:id,name',
            ])
            ->latest('checked_at')
            ->take(20)
            ->get()
            ->map(function ($checkin) {
                return [
                    'id' => $checkin->id,
                    'booking_id' => $checkin->booking_id,
                    'customer_name' => $checkin->booking->customer_name ?? 'N/A',
                    'customer_phone' => $checkin->booking->customer_phone ?? 'N/A',
                    'day_number' => $checkin->tourItinerary->day_number ?? 1,
                    'itinerary_title' => $checkin->tourItinerary->title ?? 'N/A',
                    'checked_at' => $checkin->checked_at,
                    'guide_name' => $checkin->guide->name ?? 'N/A',
                ];
            });

        return $this->success([
            'kpis' => [
                'overall_presence_rate' => $overallPresenceRate,
                'total_checkins' => $totalCheckins,
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'missing_photos_count' => $missingPhotosCount,
            ],
            'schedules' => [
                'data' => $scheduleReports,
                'current_page' => $paginatedSchedules->currentPage(),
                'last_page' => $paginatedSchedules->lastPage(),
                'per_page' => $paginatedSchedules->perPage(),
                'total' => $paginatedSchedules->total(),
            ],
            'absence_logs' => $absenceLogs,
        ], 'Lấy báo cáo điểm danh thành công');
    }
}
