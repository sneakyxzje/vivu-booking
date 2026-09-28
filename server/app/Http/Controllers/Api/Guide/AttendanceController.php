<?php

namespace App\Http\Controllers\Api\Guide;

use App\Enums\BookingStatus;
use App\Enums\PassengerCheckinStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\CheckpointPhoto;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\PassengerCheckinHistory;
use App\Models\TourSchedule;
use App\Services\AttendanceService;
use App\Services\CloudinaryService;
use App\Services\DemoClock;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AttendanceController extends Controller
{
    public function __construct(
        private CloudinaryService $cloudinaryService,
        private AttendanceService $attendanceService,
    ) {
    }

    /**
     * Lấy toàn bộ dữ liệu điểm danh của một lịch khởi hành.
     */
    public function show(Request $request, int $scheduleId): JsonResponse
    {
        $schedule = $this->findAssignedSchedule($request, $scheduleId);

        if (!$schedule) {
            return $this->error(
                'Không tìm thấy lịch khởi hành được phân công.',
                404
            );
        }

        $checkpoints = ItineraryCheckpoint::query()
            ->whereHas('tourItinerary', function ($query) use ($schedule) {
                $query->where('tour_id', $schedule->tour_id);
            })
            ->with('tourItinerary:id,day_number,title')
            ->orderBy('sequence')
            ->get();

        $bookings = Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->whereIn('status', BookingStatus::manifestValues())
            ->with('passengers:id,booking_id,name,type,note')
            ->orderBy('customer_name')
            ->get([
                'id',
                'customer_name',
                'customer_phone',
                'guests',
                'adult_count',
                'child_count',
                'infant_count',
            ]);

        $passengerIds = $bookings
            ->flatMap(fn ($booking) => $booking->passengers->pluck('id'))
            ->values();

        $checkins = PassengerCheckin::query()
            ->where('tour_schedule_id', $schedule->id)
            ->whereIn('booking_passenger_id', $passengerIds)
            ->with([
                'bookingPassenger:id,booking_id,name,type',
                'itineraryCheckpoint:id,tour_itinerary_id,name,sequence',
            ])
            ->orderBy('itinerary_checkpoint_id')
            ->orderBy('booking_passenger_id')
            ->get();
            $photos = CheckpointPhoto::query()
    ->where('tour_schedule_id', $schedule->id)
    ->with('checkpoint:id,name,latitude,longitude')
    ->latest()
    ->get([
        'id',
        'tour_itinerary_id',
        'itinerary_checkpoint_id',
        'image_path',
        'latitude',
        'longitude',
        'captured_at',
        'created_at',
    ]);

        $now = DemoClock::schedule($schedule)->setTimezone(AttendanceService::TIMEZONE);
        $status = app(ScheduleLifecycleService::class)->effectiveStatus($schedule, $now);
        foreach ($checkpoints as $checkpoint) {
            $checkpoint->setAttribute('attendance_date', $this->attendanceService->checkpointDate($schedule, $checkpoint)->toDateString());
        }

        return $this->success([
            'schedule' => [
                'demo_clock' => $schedule->demo_clock,
                'id' => $schedule->id,
                'start_date' => $schedule->start_date,
                'end_date' => $schedule->end_date,
                'status' => $status->value,
                'can_record' => $status->isRunning(),
                'server_now' => $now->toIso8601String(),
                'recording_ends_at' => ($schedule->end_date ?? $schedule->start_date->copy()->addDays(max(0, (int) $schedule->tour->number_of_days - 1))->endOfDay())->toIso8601String(),
                'max_people' => (int) $schedule->max_people,
                'booked_people' => (int) $schedule->booked_people,
            ],

            'tour' => [
                'id' => $schedule->tour->id,
                'title' => $schedule->tour->title,
                'number_of_days' => (int) $schedule->tour->number_of_days,
            ],

            'checkpoints' => $checkpoints,
            'bookings' => $bookings,
            'checkins' => $checkins,
            'photos' => $photos,
        ], 'Lấy dữ liệu điểm danh thành công');
    }

    /**
     * Lưu/cập nhật điểm danh của hành khách tại một điểm dừng.
     */
    public function update(
        Request $request,
        int $scheduleId,
        int $checkpointId
    ): JsonResponse {
        $schedule = $this->findAssignedSchedule($request, $scheduleId);

        if (!$schedule) {
            return $this->error(
                'Không tìm thấy lịch khởi hành được phân công.',
                404
            );
        }

        $checkpoint = $this->findCheckpointOfSchedule(
            $schedule,
            $checkpointId
        );

        if (!$checkpoint) {
            return $this->error(
                'Điểm dừng không thuộc tour của lịch khởi hành này.',
                404
            );
        }

        $this->attendanceService->assertCanRecord($request->user(), $schedule, $checkpoint);

        $validated = $request->validate([
            'checkins' => ['required', 'array', 'min:1'],

            'checkins.*.booking_passenger_id' => [
                'required',
                'integer',
                'distinct',
            ],

            'checkins.*.status' => [
                'required',
                'string',
                'in:' . implode(',', PassengerCheckinStatus::values()),
            ],

            'checkins.*.note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $validPassengerIds = BookingPassenger::query()
            ->whereHas('booking', function ($query) use ($schedule) {
                $query
                    ->where('tour_schedule_id', $schedule->id)
                    ->whereIn('status', BookingStatus::manifestValues());
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $saved = 0;
        $created = 0;
        $updated = 0;

        // Ghi qua AttendanceService chứ không tự thao tác trên model.
        //
        // Lớp dịch vụ giữ đủ chín quy tắc ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 5.3.
        // Viết lại logic ngay trong controller thì bốn quy tắc không có chỗ nào cài: chuyến
        // phải đang chạy, chỉ ghi đúng ngày hiện tại theo giờ Việt Nam, và điểm
        // dừng bắt buộc ảnh mới chốt được. Điểm danh là dữ liệu dùng để đối chiếu khi có
        // khiếu nại nên không được có đường ghi nào lách qua các quy tắc đó.
        DB::transaction(function () use (
            $validated,
            $schedule,
            $checkpoint,
            $validPassengerIds,
            $request,
            &$saved,
            &$created,
            &$updated
        ) {
            foreach ($validated['checkins'] as $entry) {
                $passengerId = (int) $entry['booking_passenger_id'];

                if (!in_array($passengerId, $validPassengerIds, true)) {
                    continue;
                }

                $passenger = BookingPassenger::query()->find($passengerId);

                if (!$passenger) {
                    continue;
                }

                $daTonTai = PassengerCheckin::query()
                    ->where('booking_passenger_id', $passengerId)
                    ->where('itinerary_checkpoint_id', $checkpoint->id)
                    ->exists();

                $this->attendanceService->record(
                    $request->user(),
                    $schedule,
                    $checkpoint,
                    $passenger,
                    PassengerCheckinStatus::from($entry['status']),
                    isset($entry['note']) ? trim((string) $entry['note']) : null,
                );

                $daTonTai ? $updated++ : $created++;
                $saved++;
            }

            /*
             * Điểm dừng bắt buộc ảnh: chốt xong cả điểm mà chưa có ảnh thì không cho ghi.
             *
             * Luật này (tài liệu 04 §5.3) đã có sẵn ở `AttendanceService::assertCheckpointCompletable()`
             * kèm bài kiểm thử xanh — nhưng **không đường ghi nào gọi nó**, nên trên thực tế hướng
             * dẫn viên vẫn điểm danh xong cả trạm dừng mà không cần tấm ảnh nào. Một luật có mã, có
             * test, và không có hiệu lực.
             *
             * Chỉ áp vào đúng lúc điểm dừng đã ghi đủ mọi hành khách — tức lúc "chốt". Áp cho từng
             * lần lưu thì hướng dẫn viên không lưu tạm được giữa chừng, mà ngoài hiện trường người
             * ta ghi dần theo từng nhóm khách xuống xe. `pendingPassengers()` trả về những người
             * còn thiếu; rỗng nghĩa là điểm dừng vừa hoàn tất.
             *
             * Ném ở đây kéo cả lô về, nên không có trạng thái nửa vời: hoặc ghi được cả, hoặc chưa
             * ghi gì và người dùng biết phải tải ảnh lên trước.
             */
            if ($this->attendanceService->pendingPassengers($schedule, $checkpoint)->isEmpty()) {
                $this->attendanceService->assertCheckpointCompletable($schedule, $checkpoint);
            }
        });

        $checkins = PassengerCheckin::query()
            ->where('tour_schedule_id', $schedule->id)
            ->where('itinerary_checkpoint_id', $checkpoint->id)
            ->whereIn('booking_passenger_id', $validPassengerIds)
            ->with([
                'bookingPassenger:id,booking_id,name,type',
            ])
            ->get();

        return $this->success([
            'saved' => $saved,
            'created' => $created,
            'updated' => $updated,
            'checkpoint' => [
                'id' => $checkpoint->id,
                'name' => $checkpoint->name,
            ],
            'checkins' => $checkins,
        ], "Đã lưu điểm danh cho {$saved} hành khách.");
    }

    /** Lưu ảnh tại điểm dừng, không yêu cầu hoặc thu thập tọa độ GPS. */
    public function uploadPhoto(
        Request $request,
        int $scheduleId,
        int $checkpointId
    ): JsonResponse {
        $schedule = $this->findAssignedSchedule($request, $scheduleId);
        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành được phân công.', 404);
        }

        $checkpoint = $this->findCheckpointOfSchedule($schedule, $checkpointId);
        if (!$checkpoint) {
            return $this->error('Điểm dừng không thuộc tour của lịch khởi hành này.', 404);
        }

        $this->attendanceService->assertCanRecord($request->user(), $schedule, $checkpoint);
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'photo.required' => 'Vui lòng chọn ảnh check-in.',
            'photo.image' => 'Tệp tải lên phải là hình ảnh.',
            'photo.max' => 'Ảnh không được vượt quá 5MB.',
        ]);

        $imagePath = $this->cloudinaryService->uploadImage(
            $request->file('photo'),
            'vivu-booking/checkins'
        );

        // Thời gian/quyền có thể đổi trong lúc gửi ảnh lên dịch vụ lưu trữ.
        $this->attendanceService->assertCanRecord($request->user(), $schedule, $checkpoint);
        $photo = CheckpointPhoto::create([
            'tour_schedule_id' => $schedule->id,
            'tour_itinerary_id' => $checkpoint->tour_itinerary_id,
            'itinerary_checkpoint_id' => $checkpoint->id,
            'guide_id' => $request->user()->id,
            'image_path' => $imagePath,
            'captured_at' => now(),
        ]);

        return $this->success([
            'photo' => $photo->load('checkpoint:id,name'),
        ], 'Đã lưu ảnh check-in thành công.');
    }

    private function findAssignedSchedule(
        Request $request,
        int $scheduleId
    ): ?TourSchedule {
        // Chuyến có thể được phân công nhiều hướng dẫn viên; ai trong số đó cũng vào được.
        return TourSchedule::query()
            ->with('tour:id,title,number_of_days')
            ->whereHas('guides', fn ($query) => $query->whereKey($request->user()->id))
            ->find($scheduleId);
    }

    private function findCheckpointOfSchedule(
        TourSchedule $schedule,
        int $checkpointId
    ): ?ItineraryCheckpoint {
        return ItineraryCheckpoint::query()
            ->whereKey($checkpointId)
            ->whereHas('tourItinerary', function ($query) use ($schedule) {
                $query->where('tour_id', $schedule->tour_id);
            })
            ->first();
    }
}
