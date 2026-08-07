<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCheckin;
use App\Models\CheckpointPhoto;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private CloudinaryService $cloudinaryService)
    {
    }

    /**
     * Toàn bộ dữ liệu điểm danh của một lịch khởi hành: các chặng (ngày),
     * danh sách khách đã xác nhận, trạng thái điểm danh và ảnh check-in.
     */
    public function show(Request $request, int $scheduleId): JsonResponse
    {
        $schedule = $this->findAssignedSchedule($request, $scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành được phân công.', 404);
        }

        $itineraries = TourItinerary::query()
            ->where('tour_id', $schedule->tour_id)
            ->orderBy('day_number')
            ->get(['id', 'day_number', 'title', 'start_point', 'end_point', 'route_points']);

        $guests = Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->orderBy('customer_name')
            ->with('passengers:id,booking_id,name,type,note')
            ->get(['id', 'customer_name', 'customer_phone', 'guests', 'adult_count', 'child_count', 'infant_count']);

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
     * Lưu điểm danh của một chặng: danh sách booking kèm có mặt / vắng.
     */
    public function update(Request $request, int $scheduleId, int $itineraryId): JsonResponse
    {
        $schedule = $this->findAssignedSchedule($request, $scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành được phân công.', 404);
        }

        $itinerary = $this->findItineraryOfSchedule($schedule, $itineraryId);

        if (!$itinerary) {
            return $this->error('Chặng không thuộc tour của lịch khởi hành này.', 404);
        }

        $validated = $request->validate([
            'checkins' => ['required', 'array', 'min:1'],
            'checkins.*.booking_id' => ['required', 'integer'],
            'checkins.*.present' => ['required', 'boolean'],
        ]);

        $validBookingIds = Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->pluck('id')
            ->all();

        $saved = 0;

        foreach ($validated['checkins'] as $entry) {
            if (!in_array((int) $entry['booking_id'], $validBookingIds, true)) {
                continue;
            }

            BookingCheckin::query()->updateOrCreate(
                [
                    'booking_id' => (int) $entry['booking_id'],
                    'tour_itinerary_id' => $itinerary->id,
                ],
                [
                    'present' => (bool) $entry['present'],
                    'checked_at' => now(),
                    'guide_id' => $request->user()->id,
                ],
            );

            $saved++;
        }

        return $this->success([
            'saved' => $saved,
            'checkins' => BookingCheckin::query()
                ->where('tour_itinerary_id', $itinerary->id)
                ->whereIn('booking_id', $validBookingIds)
                ->get(['booking_id', 'tour_itinerary_id', 'present', 'checked_at']),
        ], "Đã lưu điểm danh cho {$saved} đơn.");
    }

    /**
     * Upload ảnh check-in đoàn tại một chặng.
     */
    public function uploadPhoto(Request $request, int $scheduleId, int $itineraryId): JsonResponse
    {
        $schedule = $this->findAssignedSchedule($request, $scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành được phân công.', 404);
        }

        $itinerary = $this->findItineraryOfSchedule($schedule, $itineraryId);

        if (!$itinerary) {
            return $this->error('Chặng không thuộc tour của lịch khởi hành này.', 404);
        }

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'photo.required' => 'Vui lòng chọn ảnh check-in.',
            'photo.image' => 'Tệp tải lên phải là hình ảnh.',
        ]);

        $imagePath = $this->cloudinaryService->uploadImage(
            $request->file('photo'),
            'vivu-booking/checkins'
        );

        $photo = CheckpointPhoto::create([
            'tour_schedule_id' => $schedule->id,
            'tour_itinerary_id' => $itinerary->id,
            'guide_id' => $request->user()->id,
            'image_path' => $imagePath,
        ]);

        return $this->success($photo, 'Đã lưu ảnh check-in.');
    }

    private function findAssignedSchedule(Request $request, int $scheduleId): ?TourSchedule
    {
        return TourSchedule::query()
            ->with('tour:id,title,number_of_days')
            ->where('guide_id', $request->user()->id)
            ->find($scheduleId);
    }

    private function findItineraryOfSchedule(TourSchedule $schedule, int $itineraryId): ?TourItinerary
    {
        return TourItinerary::query()
            ->where('tour_id', $schedule->tour_id)
            ->find($itineraryId);
    }
}
