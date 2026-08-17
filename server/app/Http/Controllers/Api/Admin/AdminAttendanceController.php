<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PassengerCheckinStatus;
use App\Http\Controllers\Controller;
use App\Models\CheckpointPhoto;
use App\Models\ItineraryCheckpoint;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\PassengerCheckin;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAttendanceController extends Controller
{
    /**
     * Admin xem lại toàn bộ điểm danh + ảnh check-in của một lịch khởi hành.
     * Cùng cấu trúc dữ liệu với màn điểm danh của guide nhưng chỉ đọc.
     */
    public function show(int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::query()
            ->with(['tour:id,title,number_of_days', 'guides:id,name,phone'])
            ->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành.', 404);
        }

        $itineraries = TourItinerary::query()
            ->where('tour_id', $schedule->tour_id)
            ->orderBy('day_number')
            ->get(['id', 'day_number', 'title', 'start_point', 'end_point']);

        // Điểm dừng là đơn vị điểm danh thật, phải trả về đủ kể cả điểm chưa ai ghi gì. Suy
        // ngược danh sách từ các bản ghi điểm danh thì điểm nào bị bỏ quên hoàn toàn cũng biến
        // mất khỏi báo cáo, đúng cái mà điều hành cần nhìn thấy nhất.
        $checkpoints = ItineraryCheckpoint::query()
            ->whereHas('tourItinerary', fn ($q) => $q->where('tour_id', $schedule->tour_id))
            ->with('tourItinerary:id,day_number,title')
            ->orderBy('sequence')
            ->get();

        // Danh sách đoàn kèm từng người, để đối chiếu ai chưa được ghi nhận.
        $bookings = Booking::query()
            ->where('tour_schedule_id', $schedule->id)
            ->whereNotIn('status', ['cancelled', 'transferred'])
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
                'status',
            ]);

        $passengerIds = $bookings
            ->flatMap(fn ($booking) => $booking->passengers->pluck('id'))
            ->values();

        $checkins = PassengerCheckin::query()
            ->where('tour_schedule_id', $schedule->id)
            ->with([
                'bookingPassenger:id,name,booking_id,type',
                'itineraryCheckpoint:id,name,tour_itinerary_id',
                'checkedBy:id,name',
            ])
            ->get();

        $photos = CheckpointPhoto::query()
            ->where('tour_schedule_id', $schedule->id)
            ->with('checkpoint:id,name')
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
                'id'           => $schedule->id,
                'start_date'   => $schedule->start_date,
                'max_people'   => (int) $schedule->max_people,
                'booked_people' => (int) $schedule->booked_people,
                'guides'       => $schedule->guides,
            ],
            'tour' => [
                'id'             => $schedule->tour->id,
                'title'          => $schedule->tour->title,
                'number_of_days' => (int) $schedule->tour->number_of_days,
            ],
            'itineraries'       => $itineraries,
            'checkpoints'       => $checkpoints,
            'bookings'          => $bookings,
            'total_passengers'  => $passengerIds->count(),
            'checkins'          => $checkins,
            'photos'            => $photos,
        ], 'Lấy dữ liệu điểm danh thành công');
    }

    /**
     * H13a — Báo cáo tổng hợp điểm danh toàn hệ thống.
     *
     * Đọc từ passenger_checkins (model mới sau H01-H09), không còn dùng BookingCheckin cũ.
     * Hỗ trợ bộ lọc từ ngày → đến ngày, từ khóa, trạng thái và phân trang.
     *
     * GET /api/admin/attendance-reports
     */
    public function report(Request $request): JsonResponse
    {
        $query = TourSchedule::query()
            ->with(['tour:id,title,number_of_days', 'guides:id,name,phone']);

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
                $q->whereHas('tour', fn ($t) => $t->where('title', 'like', $search))
                  ->orWhereHas('guides', fn ($g) => $g->where('name', 'like', $search));
            });
        }

        // KPI toàn hệ thống — đọc từ passenger_checkins
        $totalCheckins  = PassengerCheckin::count();
        $totalPresent   = PassengerCheckin::where('status', PassengerCheckinStatus::Present->value)->count();
        $totalAbsent    = PassengerCheckin::whereNotIn('status', [PassengerCheckinStatus::Present->value])->count();
        $totalLateEntry = PassengerCheckin::where('is_late_entry', true)->count();

        $overallPresenceRate = $totalCheckins > 0
            ? round(($totalPresent / $totalCheckins) * 100, 1)
            : 100;

        $perPage           = (int) $request->input('per_page', 10);
        $paginatedSchedules = $query->latest('start_date')->paginate($perPage);

        $scheduleReports = collect($paginatedSchedules->items())->map(function ($schedule) {
            $checkins = PassengerCheckin::query()
                ->where('tour_schedule_id', $schedule->id)
                ->get(['status', 'is_late_entry']);

            $present    = $checkins->where('status', PassengerCheckinStatus::Present->value)->count();
            $absent     = $checkins->whereNotIn('status', [PassengerCheckinStatus::Present->value])->count();
            $total      = $checkins->count();
            $lateEntry  = $checkins->where('is_late_entry', true)->count();
            $rate       = $total > 0 ? round(($present / $total) * 100, 1) : 100;

            $photoCount = CheckpointPhoto::where('tour_schedule_id', $schedule->id)->count();

            return [
                'id'              => $schedule->id,
                'start_date'      => $schedule->start_date,
                'status'          => $schedule->status instanceof \App\Enums\ScheduleStatus
                    ? $schedule->status->value
                    : $schedule->status,
                'booked_people'   => (int) $schedule->booked_people,
                'tour_id'         => $schedule->tour->id ?? null,
                'tour_title'      => $schedule->tour->title ?? 'N/A',
                'number_of_days'  => (int) ($schedule->tour->number_of_days ?? 1),
                'guides'          => $schedule->guides->map(fn ($guide) => [
                    'id'    => $guide->id,
                    'name'  => $guide->name,
                    'phone' => $guide->phone,
                ])->values(),
                'present_count'   => $present,
                'absent_count'    => $absent,
                'total_checkins'  => $total,
                'presence_rate'   => $rate,
                'late_entry_count' => $lateEntry,
                'photo_count'     => $photoCount,
            ];
        });

        // Đếm chuyến còn thiếu ảnh (có khách nhưng chưa có ảnh nào)
        $allScheduleIds       = (clone $query)->pluck('id');
        $missingPhotosCount   = $allScheduleIds->filter(function ($id) {
            return !CheckpointPhoto::where('tour_schedule_id', $id)->exists()
                && PassengerCheckin::where('tour_schedule_id', $id)->exists();
        })->count();

        return $this->success([
            'kpis' => [
                'overall_presence_rate' => $overallPresenceRate,
                'total_checkins'        => $totalCheckins,
                'total_present'         => $totalPresent,
                'total_absent'          => $totalAbsent,
                'late_entry_count'      => $totalLateEntry,
                'missing_photos_count'  => $missingPhotosCount,
            ],
            'schedules' => [
                'data'         => $scheduleReports,
                'current_page' => $paginatedSchedules->currentPage(),
                'last_page'    => $paginatedSchedules->lastPage(),
                'per_page'     => $paginatedSchedules->perPage(),
                'total'        => $paginatedSchedules->total(),
            ],
            'absence_logs' => $this->absenceLogs($allScheduleIds),
        ], 'Lấy báo cáo điểm danh thành công');
    }

    /**
     * Nhật ký các lần khách không có mặt, gộp qua mọi chuyến trong bộ lọc.
     *
     * Điều hành cần một chỗ nhìn thấy toàn bộ trường hợp vắng mà không phải mở từng chuyến.
     * Đây cũng là dữ liệu đầu tiên được lôi ra khi khách khiếu nại, nên phải kèm ai ghi và ghi
     * lúc nào, không chỉ tên người vắng.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $scheduleIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function absenceLogs($scheduleIds)
    {
        return PassengerCheckin::query()
            ->whereIn('tour_schedule_id', $scheduleIds)
            ->whereNot('status', PassengerCheckinStatus::Present->value)
            ->with([
                'bookingPassenger:id,booking_id,name',
                'bookingPassenger.booking:id,customer_name,customer_phone',
                'itineraryCheckpoint:id,name,tour_itinerary_id',
                'itineraryCheckpoint.tourItinerary:id,day_number,title',
                'checkedBy:id,name',
            ])
            ->latest('checked_at')
            ->limit(200)
            ->get()
            ->map(function (PassengerCheckin $checkin) {
                $booking = $checkin->bookingPassenger?->booking;
                $itinerary = $checkin->itineraryCheckpoint?->tourItinerary;

                return [
                    'id' => $checkin->id,
                    'booking_id' => (int) ($booking?->id ?? 0),
                    'passenger_name' => $checkin->bookingPassenger?->name ?? '',
                    'customer_name' => $booking?->customer_name ?? '',
                    'customer_phone' => $booking?->customer_phone ?? '',
                    'day_number' => (int) ($itinerary?->day_number ?? 0),
                    'itinerary_title' => $itinerary?->title ?? '',
                    'checkpoint_name' => $checkin->itineraryCheckpoint?->name ?? '',
                    'status' => $checkin->status->value,
                    'status_label' => $checkin->status->label(),
                    'note' => $checkin->note,
                    'checked_at' => $checkin->checked_at?->toDateTimeString(),
                    'guide_name' => $checkin->checkedBy?->name ?? '',
                ];
            });
    }

    /**
     * H13a — Báo cáo chi tiết điểm danh của một chuyến khởi hành sau khi kết thúc.
     *
     * Trả về 5 phần theo tài liệu 04 §5.5:
     *  1. Thông tin chuyến + HDV
     *  2. Tóm tắt: tỷ lệ, số vắng, số ghi bù muộn, số điểm thiếu ảnh
     *  3. by_checkpoint: tỷ lệ có mặt tại từng điểm dừng
     *  4. absent_passengers: danh sách hành khách vắng kèm lý do
     *  5. late_entries: các lần ghi bù muộn kèm delay_hours
     *
     * GET /api/admin/schedules/{id}/attendance-report
     */
    public function scheduleReport(int $id): JsonResponse
    {
        $schedule = TourSchedule::query()
            ->with(['tour:id,title,number_of_days', 'guides:id,name,phone'])
            ->find($id);

        if (!$schedule) {
            return $this->error('Không tìm thấy lịch khởi hành.', 404);
        }

        // ─── 1. Tất cả điểm dừng thuộc tour của chuyến ──────────────────────
        $checkpoints = ItineraryCheckpoint::query()
            ->whereHas('tourItinerary', fn ($q) => $q->where('tour_id', $schedule->tour_id))
            ->with('tourItinerary:id,day_number,tour_id')
            ->orderBy('tour_itinerary_id')
            ->orderBy('sequence')
            ->get();

        $checkpointIds = $checkpoints->pluck('id');

        // ─── 2. Toàn bộ bản ghi điểm danh của chuyến ───────────────────────
        $allCheckins = PassengerCheckin::query()
            ->where('tour_schedule_id', $schedule->id)
            ->whereIn('itinerary_checkpoint_id', $checkpointIds)
            ->with([
                'bookingPassenger:id,name,booking_id',
                'itineraryCheckpoint:id,name,tour_itinerary_id,expected_at',
                'itineraryCheckpoint.tourItinerary:id,day_number',
            ])
            ->get();

        // ─── 3. Tổng số hành khách trong chuyến (đơn còn hiệu lực) ─────────
        $totalPassengers = BookingPassenger::query()
            ->whereHas('booking', fn ($q) => $q
                ->where('tour_schedule_id', $schedule->id)
                ->whereNotIn('status', ['cancelled', 'transferred'])
            )
            ->count();

        // ─── 4. Summary ──────────────────────────────────────────────────────
        $presentCount    = $allCheckins->where('status', PassengerCheckinStatus::Present)->count();
        $nonPresentCount = $allCheckins->where('status', '!=', PassengerCheckinStatus::Present)->count();
        $lateEntryCount  = $allCheckins->where('is_late_entry', true)->count();

        // Số hành khách có mặt đầy đủ ở tất cả checkpoint (không tính present theo checkpoint, tính theo passenger)
        $passengerPresentCounts = $allCheckins
            ->where('status', PassengerCheckinStatus::Present)
            ->groupBy('booking_passenger_id');
        $fullyPresentPassengers = $passengerPresentCounts
            ->filter(fn ($group) => $group->count() === $checkpoints->count())
            ->count();

        // Điểm dừng yêu cầu ảnh nhưng chưa có ảnh
        $missingPhotoCheckpoints = $checkpoints
            ->where('is_required_photo', true)
            ->filter(fn ($cp) => !CheckpointPhoto::query()
                ->where('tour_schedule_id', $schedule->id)
                ->where('itinerary_checkpoint_id', $cp->id)
                ->exists()
            )
            ->count();

        $presenceRate = $totalPassengers > 0
            ? round(($presentCount / max(1, $allCheckins->count())) * 100, 1)
            : 100;

        $summary = [
            'total_passengers'        => $totalPassengers,
            'fully_present'           => $fullyPresentPassengers,
            'had_absent'              => $nonPresentCount > 0
                ? $allCheckins->where('status', '!=', PassengerCheckinStatus::Present)
                    ->pluck('booking_passenger_id')->unique()->count()
                : 0,
            'presence_rate'           => $presenceRate,
            'late_entry_count'        => $lateEntryCount,
            'missing_photo_checkpoints' => $missingPhotoCheckpoints,
        ];

        // ─── 5. by_checkpoint ────────────────────────────────────────────────
        //
        // Mỗi phần tử tương ứng với một điểm dừng trong lịch trình.
        // Các giá trị đếm (present, absent, ...) tính từ passenger_checkins của chuyến này.
        // has_photo kiểm tra checkpoint_photos có ít nhất một ảnh gắn với checkpoint đó không.
        // presence_rate = present / tổng bản ghi * 100 (không tính hành khách chưa được điểm danh).
        $byCheckpoint = $checkpoints->map(function ($cp) use ($allCheckins, $schedule) {
            $cpCheckins = $allCheckins->where('itinerary_checkpoint_id', $cp->id);

            $counts = [
                'present'    => $cpCheckins->where('status', PassengerCheckinStatus::Present)->count(),
                'absent'     => $cpCheckins->where('status', PassengerCheckinStatus::Absent)->count(),
                'late'       => $cpCheckins->where('status', PassengerCheckinStatus::Late)->count(),
                'left_early' => $cpCheckins->where('status', PassengerCheckinStatus::LeftEarly)->count(),
                'excused'    => $cpCheckins->where('status', PassengerCheckinStatus::Excused)->count(),
            ];
            $total = array_sum($counts);

            $hasPhoto = CheckpointPhoto::query()
                ->where('tour_schedule_id', $schedule->id)
                ->where('itinerary_checkpoint_id', $cp->id)
                ->exists();

            return [
                'checkpoint_id'  => $cp->id,
                'itinerary_day'  => $cp->tourItinerary?->day_number,
                'name'           => $cp->name,
                'type'           => $cp->type ?? null,
                'expected_at'    => $cp->expected_at,
                'requires_photo' => (bool) $cp->is_required_photo,
                'has_photo'      => $hasPhoto,
                ...$counts,
                'presence_rate'  => $total > 0 ? round(($counts['present'] / $total) * 100, 1) : 100,
            ];
        })->values();

        // ─── 6. absent_passengers ────────────────────────────────────────────
        //
        // Tất cả bản ghi có status khác present — bao gồm absent, late, left_early, excused.
        // FE có thể lọc thêm theo status nếu cần hiển thị riêng từng loại.
        $absentPassengers = $allCheckins
            ->where('status', '!=', PassengerCheckinStatus::Present)
            ->map(fn ($c) => [
                'passenger_id'    => $c->booking_passenger_id,
                'passenger_name'  => $c->bookingPassenger?->name ?? 'N/A',
                'booking_id'      => $c->bookingPassenger?->booking_id,
                'checkpoint_name' => $c->itineraryCheckpoint?->name,
                'itinerary_day'   => $c->itineraryCheckpoint?->tourItinerary?->day_number,
                'status'          => $c->status instanceof PassengerCheckinStatus
                    ? $c->status->value
                    : $c->status,
                'note'            => $c->note,
                'checked_at'      => $c->checked_at?->toIso8601String(),
                'is_late_entry'   => (bool) $c->is_late_entry,
            ])
            ->values();

        // ─── 7. late_entries ─────────────────────────────────────────────────
        $lateEntries = $allCheckins
            ->where('is_late_entry', true)
            ->map(function ($c) use ($schedule) {
                $cp          = $c->itineraryCheckpoint;
                $day         = $cp?->tourItinerary?->day_number ?? 1;
                $cpDate      = Carbon::parse($schedule->start_date)->addDays($day - 1);
                $expectedStr = $cp?->expected_at; // "HH:MM" string

                // Delay = checked_at - (ngày của checkpoint + giờ dự kiến)
                $expectedDt = null;
                if ($expectedStr && Carbon::hasFormat($expectedStr, 'H:i')) {
                    [$h, $m]    = explode(':', $expectedStr);
                    $expectedDt = $cpDate->copy()->setTime((int)$h, (int)$m);
                }

                $delayHours = $expectedDt && $c->checked_at
                    ? round($c->checked_at->diffInMinutes($expectedDt, false) / 60 * -1, 1)
                    : null;

                return [
                    'passenger_name'  => $c->bookingPassenger?->name ?? 'N/A',
                    'checkpoint_name' => $cp?->name,
                    'itinerary_day'   => $day,
                    'checked_at'      => $c->checked_at?->toIso8601String(),
                    'checkpoint_date' => $cpDate->toDateString(),
                    'expected_at'     => $expectedStr,
                    'delay_hours'     => $delayHours,
                ];
            })
            ->values();

        return $this->success([
            'schedule' => [
                'id'           => $schedule->id,
                'start_date'   => $schedule->start_date?->toIso8601String(),
                'end_date'     => $schedule->end_date?->toIso8601String(),
                'status'       => $schedule->status instanceof \App\Enums\ScheduleStatus
                    ? $schedule->status->value
                    : $schedule->status,
                'booked_people' => (int) $schedule->booked_people,
                'tour'         => [
                    'id'    => $schedule->tour->id,
                    'title' => $schedule->tour->title,
                ],
                'guides'       => $schedule->guides->map(fn ($guide) => [
                    'id'    => $guide->id,
                    'name'  => $guide->name,
                    'phone' => $guide->phone,
                ])->values(),
            ],
            'summary'           => $summary,
            'by_checkpoint'     => $byCheckpoint,
            'absent_passengers' => $absentPassengers,
            'late_entries'      => $lateEntries,
        ], 'Lấy báo cáo điểm danh chuyến thành công');
    }
}
