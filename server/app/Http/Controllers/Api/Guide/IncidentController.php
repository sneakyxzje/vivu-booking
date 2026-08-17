<?php

namespace App\Http\Controllers\Api\Guide;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Models\IncidentPhoto;
use App\Models\ScheduleIncident;
use App\Models\TourSchedule;
use App\Services\CloudinaryService;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O - Hướng dẫn viên báo cáo sự cố tại hiện trường.
 *
 * Controller này **cố ý không nhận trường tiền nào**. Không phải quên: người đang ở giữa đoàn
 * khách mệt và bực không nên là người quyết mức thu. Điều hành quyết, qua AdminIncidentController.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.2.
 */
class IncidentController extends Controller
{
    public function __construct(
        private IncidentService $incidentService,
        private CloudinaryService $cloudinaryService,
    ) {
    }

    /**
     * Biên bản bàn giao liên quan tới hướng dẫn viên này.
     *
     * Gồm cả hai chiều. Chiều nhận là thứ cần nhất: người mới bắt nhịp bằng đúng đoạn ghi chú
     * tình trạng đoàn. Chiều giao giữ lại để người cũ còn xem được mình đã bàn giao những gì -
     * họ mất quyền ghi khi rời danh sách phụ trách, nhưng vết bàn giao thì vẫn đọc được.
     */
    public function handovers(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;

        $ds = \App\Models\GuideHandover::query()
            ->where(fn ($q) => $q->where('to_guide_id', $guideId)->orWhere('from_guide_id', $guideId))
            ->with([
                'schedule:id,start_date,tour_id',
                'schedule.tour:id,title',
                'fromGuide:id,name,phone',
                'toGuide:id,name,phone',
            ])
            ->latest('handed_over_at')
            ->get()
            ->map(fn (\App\Models\GuideHandover $bg) => [
                'id' => $bg->id,
                'tour_schedule_id' => $bg->tour_schedule_id,
                'tour_title' => $bg->schedule?->tour?->title,
                'start_date' => $bg->schedule?->start_date,
                'direction' => (int) $bg->to_guide_id === (int) $guideId ? 'received' : 'given',
                'from_guide_name' => $bg->fromGuide?->name,
                'to_guide_name' => $bg->toGuide?->name,
                'to_guide_phone' => $bg->toGuide?->phone,
                'handed_over_at' => $bg->handed_over_at?->toDateTimeString(),
                'reason' => $bg->reason,
                'handover_note' => $bg->handover_note,
            ]);

        return $this->success($ds, 'Lấy biên bản bàn giao thành công');
    }

    /** Các sự cố của những chuyến hướng dẫn viên này phụ trách. */
    public function index(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;

        $incidents = ScheduleIncident::query()
            ->whereHas('schedule.guides', fn ($query) => $query->whereKey($guideId))
            ->with(['schedule:id,start_date,tour_id', 'schedule.tour:id,title', 'photos'])
            ->latest('occurred_at')
            ->get()
            ->map(fn (ScheduleIncident $sc) => $this->dong($sc));

        return $this->success($incidents, 'Lấy danh sách sự cố thành công');
    }

    public function store(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', IncidentType::values())],
            'severity' => ['required', 'string', 'in:' . implode(',', IncidentSeverity::values())],
            'occurred_at' => ['required', 'date'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'tour_itinerary_id' => ['nullable', 'integer', 'exists:tour_itineraries,id'],
        ], [
            'description.min' => 'Mô tả cần ít nhất 20 ký tự. Điều hành ở xa và chỉ có mô tả này '
                . 'để quyết phương án.',
        ]);

        $schedule = TourSchedule::query()->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $incident = $this->incidentService->report($schedule, $validated, $request->user());

        return $this->success(
            $this->dong($incident->fresh(['photos'])),
            $incident->reported_late
                ? 'Đã gửi báo cáo. Sự cố xảy ra khá lâu trước lúc báo nên được đánh dấu là ghi bù.'
                : 'Đã gửi báo cáo tới bộ phận điều hành.',
        );
    }

    /** Ảnh hiện trường, hoặc ảnh biên bản có xác nhận của khách. */
    public function uploadPhoto(Request $request, int $incidentId): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $guideId = $request->user()->id;

        $incident = ScheduleIncident::query()
            ->whereKey($incidentId)
            ->whereHas('schedule.guides', fn ($query) => $query->whereKey($guideId))
            ->first();

        if (!$incident) {
            return $this->error('Không tìm thấy sự cố của chuyến bạn phụ trách', 404);
        }

        $path = $this->cloudinaryService->uploadImage($validated['photo'], 'vivu-booking/incidents');

        $anh = IncidentPhoto::query()->create([
            'schedule_incident_id' => $incident->getKey(),
            'image_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'uploaded_by' => $guideId,
        ]);

        return $this->success($anh, 'Đã tải ảnh lên.');
    }

    /** @return array<string, mixed> */
    private function dong(ScheduleIncident $sc): array
    {
        return [
            'id' => $sc->id,
            'tour_schedule_id' => $sc->tour_schedule_id,
            'tour_title' => $sc->schedule?->tour?->title,
            'start_date' => $sc->schedule?->start_date,
            'type' => $sc->type->value,
            'type_label' => $sc->type->label(),
            'severity' => $sc->severity->value,
            'severity_label' => $sc->severity->label(),
            'status' => $sc->status->value,
            'status_label' => $sc->status->label(),
            'occurred_at' => $sc->occurred_at?->toDateTimeString(),
            'reported_late' => (bool) $sc->reported_late,
            'description' => $sc->description,
            // Phương án của điều hành, hướng dẫn viên chỉ đọc.
            'resolution' => $sc->resolution,
            'photos' => $sc->photos->map(fn (IncidentPhoto $anh) => [
                'id' => $anh->id,
                'image_path' => $anh->image_path,
                'caption' => $anh->caption,
            ])->values(),
        ];
    }
}
