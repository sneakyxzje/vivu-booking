<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CostBearer;
use App\Enums\IncidentStatus;
use App\Enums\SurchargeKind;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSurcharge;
use App\Models\IncidentPhoto;
use App\Models\ScheduleIncident;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O - Điều hành xử lý sự cố và phân bổ chi phí.
 *
 * Đây là nơi duy nhất quyết được tiền của một sự cố. Hướng dẫn viên báo cáo qua controller riêng
 * và không có đường nào chạm tới các trường ở đây.
 */
class AdminIncidentController extends Controller
{
    public function __construct(
        private IncidentService $incidentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:' . implode(',', IncidentStatus::values())],
            'schedule_id' => ['nullable', 'integer'],
        ]);

        $incidents = ScheduleIncident::query()
            ->with(['schedule:id,start_date,tour_id', 'schedule.tour:id,title', 'reporter:id,name', 'photos', 'surcharges'])
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->when(isset($validated['schedule_id']), fn ($q) => $q->where('tour_schedule_id', $validated['schedule_id']))
            // Việc gấp lên đầu: chờ xử lý trước, trong đó mức nghiêm trọng trước.
            ->orderByRaw("CASE status WHEN 'reported' THEN 0 WHEN 'reviewed' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->latest('occurred_at')
            ->get()
            ->map(fn (ScheduleIncident $sc) => $this->dong($sc));

        return $this->success([
            'incidents' => $incidents,
            'options' => [
                'bearers' => array_map(fn (CostBearer $c) => [
                    'value' => $c->value,
                    'label' => $c->label(),
                    'customer_pays' => $c->khachPhaiTra(),
                ], CostBearer::cases()),
                'kinds' => array_map(fn (SurchargeKind $k) => [
                    'value' => $k->value,
                    'label' => $k->label(),
                ], SurchargeKind::cases()),
            ],
        ], 'Lấy danh sách sự cố thành công');
    }

    /** Chi tiết một sự cố, kèm các đơn của chuyến để phân bổ chi phí. */
    public function show(int $id): JsonResponse
    {
        $incident = ScheduleIncident::query()
            ->with(['schedule:id,start_date,tour_id', 'schedule.tour:id,title', 'reporter:id,name', 'photos', 'surcharges.booking:id,customer_name,guests'])
            ->find($id);

        if (!$incident) {
            return $this->error('Không tìm thấy báo cáo sự cố', 404);
        }

        $donCuaChuyen = Booking::query()
            ->where('tour_schedule_id', $incident->tour_schedule_id)
            ->whereNotIn('status', ['cancelled', 'transferred'])
            ->orderBy('id')
            ->get(['id', 'customer_name', 'customer_phone', 'guests', 'status'])
            ->map(fn (Booking $don) => [
                'booking_id' => $don->id,
                'customer_name' => $don->customer_name,
                'customer_phone' => $don->customer_phone,
                'guests' => (int) $don->guests,
            ]);

        return $this->success([
            'incident' => $this->dong($incident),
            'bookings' => $donCuaChuyen,
        ], 'Lấy chi tiết sự cố thành công');
    }

    /** Ghi phương án và phân bổ chi phí cho từng đơn. */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'min:20', 'max:2000'],
            'cost_delta' => ['nullable', 'numeric'],
            'who_bears' => ['nullable', 'string', 'in:' . implode(',', CostBearer::values())],
            'charges' => ['present', 'array'],
            'charges.*.booking_id' => ['required', 'integer'],
            'charges.*.kind' => ['required', 'string', 'in:' . implode(',', SurchargeKind::values())],
            // Người chịu của TỪNG khoản. Bỏ trống thì lùi về `who_bears` của phương án.
            'charges.*.who_bears' => ['nullable', 'string', 'in:' . implode(',', CostBearer::values())],
            'charges.*.amount' => ['required', 'numeric', 'min:1'],
            'charges.*.reason' => ['required', 'string', 'max:500'],
        ], [
            'resolution.min' => 'Phương án cần ít nhất 20 ký tự: đây là thứ hướng dẫn viên đọc cho '
                . 'khách và là căn cứ khi có khiếu nại.',
        ]);

        $incident = ScheduleIncident::query()->find($id);

        if (!$incident) {
            return $this->error('Không tìm thấy báo cáo sự cố', 404);
        }

        $daXuLy = $this->incidentService->resolve(
            $incident,
            [
                'resolution' => $validated['resolution'],
                'cost_delta' => $validated['cost_delta'] ?? null,
                'who_bears' => $validated['who_bears'] ?? null,
            ],
            $validated['charges'],
            $request->user(),
        );

        return $this->success(
            $this->dong($daXuLy->fresh(['photos', 'surcharges', 'reporter:id,name', 'schedule.tour:id,title'])),
            sprintf('Đã ghi phương án và lập %d khoản chờ duyệt.', count($validated['charges'])),
        );
    }

    public function approveSurcharge(Request $request, int $surchargeId): JsonResponse
    {
        $surcharge = BookingSurcharge::query()->find($surchargeId);

        if (!$surcharge) {
            return $this->error('Không tìm thấy khoản này', 404);
        }

        $daDuyet = $this->incidentService->approveSurcharge($surcharge, $request->user());

        return $this->success($daDuyet, 'Đã duyệt. Khoản này bắt đầu có hiệu lực với khách.');
    }

    public function waiveSurcharge(Request $request, int $surchargeId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Miễn một khoản đã lập thì phải ghi lý do.',
        ]);

        $surcharge = BookingSurcharge::query()->find($surchargeId);

        if (!$surcharge) {
            return $this->error('Không tìm thấy khoản này', 404);
        }

        $daMien = $this->incidentService->waiveSurcharge($surcharge, $validated['reason'], $request->user());

        return $this->success($daMien, 'Đã miễn khoản này.');
    }

    /**
     * Ghi nhận khách đã đồng ý với khoản phải trả.
     *
     * Bước riêng, đứng trước bước thu. Khách nghe giải thích và đồng ý là một sự kiện có thật ở
     * hiện trường; gộp nó vào lúc bấm "đã thu" thì mất luôn dấu vết ai nói với khách và lúc nào.
     */
    public function recordConsent(Request $request, int $surchargeId): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $surcharge = BookingSurcharge::query()->find($surchargeId);

        if (!$surcharge) {
            return $this->error('Không tìm thấy khoản này', 404);
        }

        $daGhi = $this->incidentService->recordConsent($surcharge, $validated['note'] ?? null);

        return $this->success($daGhi, 'Đã ghi nhận khách đồng ý. Giờ có thể thu khoản này.');
    }

    /** Ghi nhận đã thu hoặc đã hoàn: đẩy một dòng vào sổ giao dịch và đóng khoản lại. */
    public function settleSurcharge(Request $request, int $surchargeId): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $surcharge = BookingSurcharge::query()->find($surchargeId);

        if (!$surcharge) {
            return $this->error('Không tìm thấy khoản này', 404);
        }

        $daTatToan = $this->incidentService->settleSurcharge(
            $surcharge,
            $request->user(),
            $validated['method'] ?? null,
            $validated['reference'] ?? null,
            $validated['note'] ?? null,
        );

        return $this->success(
            $daTatToan,
            $daTatToan->kind === SurchargeKind::Surcharge
                ? 'Đã ghi nhận thu tiền. Khoản này đã vào sổ giao dịch của đơn.'
                : 'Đã ghi nhận hoàn tiền cho khách. Khoản này đã vào sổ giao dịch của đơn.',
        );
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
            'needs_attention' => $sc->severity->canXuLyNgay() && $sc->status === IncidentStatus::Reported,
            'status' => $sc->status->value,
            'status_label' => $sc->status->label(),
            'occurred_at' => $sc->occurred_at?->toDateTimeString(),
            'reported_late' => (bool) $sc->reported_late,
            'description' => $sc->description,
            'reporter_name' => $sc->reporter?->name,
            'resolution' => $sc->resolution,
            'cost_delta' => $sc->cost_delta !== null ? (float) $sc->cost_delta : null,
            'who_bears' => $sc->who_bears?->value,
            'who_bears_label' => $sc->who_bears?->label(),
            'reviewed_at' => $sc->reviewed_at?->toDateTimeString(),
            'photos' => $sc->photos->map(fn (IncidentPhoto $anh) => [
                'id' => $anh->id,
                'image_path' => $anh->image_path,
                'caption' => $anh->caption,
            ])->values(),
            'surcharges' => $sc->surcharges->map(fn (BookingSurcharge $kh) => [
                'id' => $kh->id,
                'booking_id' => $kh->booking_id,
                'customer_name' => $kh->booking?->customer_name,
                'kind' => $kh->kind->value,
                'kind_label' => $kh->kind->label(),
                'who_bears' => $kh->who_bears?->value,
                'who_bears_label' => $kh->who_bears?->label(),
                'amount' => (float) $kh->amount,
                'reason' => $kh->reason,
                'status' => $kh->status->value,
                'status_label' => $kh->status->label(),
                'in_effect' => $kh->status->coHieuLuc(),
                'customer_consent_at' => $kh->customer_consent_at?->toDateTimeString(),
                'consent_note' => $kh->consent_note,
                /*
                 * Ba câu hỏi giao diện hay phải trả lời, tính ở đây một lần thay vì để mỗi màn
                 * tự suy từ trạng thái - suy sai một chỗ là nút hiện sai ở chỗ đó.
                 */
                'needs_consent' => $kh->kind === SurchargeKind::Surcharge
                    && $kh->customer_consent_at === null
                    && $kh->status !== \App\Enums\SurchargeStatus::Waived,
                'can_settle' => $kh->status === \App\Enums\SurchargeStatus::Approved
                    && ($kh->kind !== SurchargeKind::Surcharge || $kh->customer_consent_at !== null),
                'settled' => $kh->status === \App\Enums\SurchargeStatus::Paid,
            ])->values(),
        ];
    }
}
