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
        // phải đang chạy, không tick trước cho ngày chưa tới, đánh dấu ghi bù muộn, và điểm
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

   /**
 * Upload ảnh check-in tại một điểm dừng.
 *
 * Lưu:
 * - điểm dừng
 * - tọa độ GPS
 * - thời gian chụp
 * - khoảng cách tới điểm dừng
 *
 * Nếu khoảng cách vượt quá 200m thì vẫn lưu ảnh
 * nhưng trả về cảnh báo.
 */
public function uploadPhoto(
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

    $validated = $request->validate([
        'photo' => [
            'required',
            'image',
            'max:5120',
        ],

        'latitude' => [
            'required',
            'numeric',
            'between:-90,90',
        ],

        'longitude' => [
            'required',
            'numeric',
            'between:-180,180',
        ],
    ], [
        'photo.required' => 'Vui lòng chọn ảnh check-in.',
        'photo.image' => 'Tệp tải lên phải là hình ảnh.',
        'photo.max' => 'Ảnh không được vượt quá 5MB.',

        'latitude.required' => 'Vui lòng cung cấp vĩ độ GPS.',
        'latitude.numeric' => 'Vĩ độ GPS không hợp lệ.',

        'longitude.required' => 'Vui lòng cung cấp kinh độ GPS.',
        'longitude.numeric' => 'Kinh độ GPS không hợp lệ.',
    ]);

    $latitude = (float) $validated['latitude'];
    $longitude = (float) $validated['longitude'];

    /*
     * Tính khoảng cách từ vị trí chụp đến điểm dừng.
     */
    if ($checkpoint->latitude === null || $checkpoint->longitude === null) {
    return $this->error(
        'Điểm dừng chưa được cấu hình tọa độ GPS.',
        422
    );
}
    $distanceMeters = $this->calculateDistanceInMeters(
        $latitude,
        $longitude,
        (float) $checkpoint->latitude,
        (float) $checkpoint->longitude
    );

    /*
     * Ngưỡng cảnh báo: 200 mét.
     */
    $warningDistance = 200;

    $isFar = $distanceMeters > $warningDistance;

    /*
     * Upload ảnh lên Cloudinary.
     */
    $imagePath = $this->cloudinaryService->uploadImage(
        $request->file('photo'),
        'vivu-booking/checkins'
    );

    /*
     * Lưu thông tin ảnh.
     */
    $photo = CheckpointPhoto::create([
        'tour_schedule_id' => $schedule->id,
        'tour_itinerary_id' => $checkpoint->tour_itinerary_id,
        'itinerary_checkpoint_id' => $checkpoint->id,
        'guide_id' => $request->user()->id,
        'image_path' => $imagePath,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'captured_at' => now(),
    ]);

    return $this->success([
        'photo' => $photo->load('checkpoint:id,name,latitude,longitude'),

        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ],

        'checkpoint' => [
            'id' => $checkpoint->id,
            'name' => $checkpoint->name,
            'latitude' => (float) $checkpoint->latitude,
            'longitude' => (float) $checkpoint->longitude,
        ],

        'distance_meters' => round($distanceMeters, 2),

        'warning' => $isFar,

        'warning_message' => $isFar
            ? "Vị trí chụp ảnh cách điểm dừng {$checkpoint->name} "
                . round($distanceMeters, 2)
                . "m, vượt quá ngưỡng cho phép {$warningDistance}m."
            : null,
    ], $isFar
        ? 'Đã lưu ảnh check-in nhưng vị trí chụp ở xa điểm dừng.'
        : 'Đã lưu ảnh check-in thành công.');
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
    /**
 * Tính khoảng cách giữa hai tọa độ GPS bằng công thức Haversine.
 *
 * Kết quả trả về theo mét.
 */
private function calculateDistanceInMeters(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
): float {
    $earthRadius = 6371000;

    $latitudeDifference = deg2rad(
        $latitude2 - $latitude1
    );

    $longitudeDifference = deg2rad(
        $longitude2 - $longitude1
    );

    $a = sin($latitudeDifference / 2) ** 2
        + cos(deg2rad($latitude1))
        * cos(deg2rad($latitude2))
        * sin($longitudeDifference / 2) ** 2;

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}
}