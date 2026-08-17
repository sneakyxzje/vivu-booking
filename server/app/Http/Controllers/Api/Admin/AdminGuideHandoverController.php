<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideHandover;
use App\Models\GuideHandoverRequest;
use App\Models\TourSchedule;
use App\Models\User;
use App\Enums\ScheduleStatus;
use App\Services\GuideHandoverService;
use App\Services\ScheduleLifecycleService;
use App\Support\GioVietNam;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Đổi hướng dẫn viên giữa chừng chuyến.
 *
 * Tách khỏi endpoint phân công thường vì đây không phải sửa danh sách: nó bắt buộc kèm lý do và
 * tình trạng đoàn, và để lại biên bản. Gộp chung thì sớm muộn sẽ có người đổi người dẫn bằng màn
 * phân công và bỏ qua biên bản.
 */
class AdminGuideHandoverController extends Controller
{
    public function __construct(
        private GuideHandoverService $handoverService,
        private ScheduleLifecycleService $lifecycle,
    ) {
    }

    /** Người này có đang dẫn một đoàn khác cũng đang trên đường không. */
    private function dangDanDoanKhac(int $guideId, int $boQuaChuyen): bool
    {
        return TourSchedule::query()
            ->whereHas('guides', fn ($query) => $query->whereKey($guideId))
            ->whereKeyNot($boQuaChuyen)
            ->get()
            ->contains(fn (TourSchedule $khac) => $this->lifecycle->effectiveStatus($khac) === ScheduleStatus::InProgress);
    }

    /** Ai đang phụ trách, ai thay được, và lịch sử đã bàn giao. */
    public function index(int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::query()
            ->with(['tour:id,title,number_of_days', 'guides:id,name,phone'])
            ->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $dangPhuTrach = $schedule->guides->pluck('id')->all();

        return $this->success([
            'schedule' => [
                'id' => $schedule->id,
                'tour_title' => $schedule->tour?->title,
                'start_date' => $schedule->start_date,
                'status' => $schedule->status,
            ],
            'current_guides' => $schedule->guides->map(fn (User $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'phone' => $g->phone,
            ])->values(),

            // Đoàn đang trên đường mà chỉ còn một người thì chỉ nhờ được người đang dẫn đoàn khác.
            'needs_emergency_cover' => $this->lifecycle->effectiveStatus($schedule) === ScheduleStatus::InProgress
                && count($dangPhuTrach) < 2,

            /*
             * Người thay: đang hoạt động và chưa phụ trách chính chuyến này.
             *
             * Kèm cờ leading_other_group để điều hành biết ai đang ở ngoài đường mà nhờ. Trùng
             * lịch thì vẫn để máy chủ từ chối lúc bấm, vì đó là phép so theo khoảng ngày.
             */
            'available_guides' => User::query()
                ->where('role', 'guide')
                ->where('status', 'active')
                ->whereNotIn('id', $dangPhuTrach)
                ->orderBy('name')
                ->get(['id', 'name', 'phone'])
                ->map(fn (User $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'phone' => $g->phone,
                    'leading_other_group' => $this->dangDanDoanKhac($g->id, $schedule->id),
                ])
                ->values(),

            'handovers' => $this->handoverService->lichSu($schedule)->map(
                fn (GuideHandover $bg) => $this->dong($bg),
            ),
        ], 'Lấy thông tin bàn giao thành công');
    }

    public function store(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'from_guide_id' => ['required', 'integer'],
            'to_guide_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'handover_note' => ['required', 'string', 'min:20', 'max:2000'],
            'handed_over_at' => ['nullable', 'date'],
        ], [
            'reason.required' => 'Phải ghi lý do thay người.',
            'handover_note.required' => 'Phải ghi tình trạng đoàn tại thời điểm bàn giao.',
            'handover_note.min' => 'Tình trạng đoàn cần ít nhất 20 ký tự: đoàn đang ở đâu, ai chưa '
                . 'điểm danh, việc gì đang dở. Người nhận chỉ có đoạn này để bắt nhịp.',
        ]);

        $schedule = TourSchedule::query()->with('tour')->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $bienBan = $this->handoverService->handover(
            $schedule,
            (int) $validated['from_guide_id'],
            (int) $validated['to_guide_id'],
            $validated['reason'],
            $validated['handover_note'],
            isset($validated['handed_over_at']) ? Carbon::parse($validated['handed_over_at']) : null,
            $request->user(),
        );

        return $this->success($this->dong($bienBan), sprintf(
            'Đã bàn giao đoàn cho %s. Người cũ không ghi tiếp được từ lúc này.',
            $bienBan->toGuide?->name ?? 'hướng dẫn viên mới',
        ));
    }

    /** Các yêu cầu bàn giao hướng dẫn viên gửi lên, chờ điều hành chọn người thay. */
    public function pendingRequests(): JsonResponse
    {
        $ds = GuideHandoverRequest::query()
            ->dangCho()
            ->with([
                'schedule:id,start_date,tour_id',
                'schedule.tour:id,title',
                'requester:id,name,phone',
            ])
            ->oldest('created_at')
            ->get()
            ->map(fn (GuideHandoverRequest $yc) => [
                'id' => $yc->id,
                'tour_schedule_id' => $yc->tour_schedule_id,
                'tour_title' => $yc->schedule?->tour?->title,
                'start_date' => $yc->schedule?->start_date,
                'requester_name' => $yc->requester?->name,
                'requester_phone' => $yc->requester?->phone,
                'reason' => $yc->reason,
                // Chữ của người đang đứng cùng đoàn, không phải của người ngồi văn phòng.
                'group_state' => $yc->group_state,
                'created_at' => $yc->created_at?->toDateTimeString(),
            ]);

        return $this->success($ds, 'Lấy yêu cầu bàn giao đang chờ thành công');
    }

    public function approveRequest(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'to_guide_id' => ['required', 'integer'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ], [
            'to_guide_id.required' => 'Phải chọn người thay trước khi duyệt.',
        ]);

        $yeuCau = GuideHandoverRequest::query()->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu bàn giao', 404);
        }

        $bienBan = $this->handoverService->approveRequest(
            $yeuCau,
            (int) $validated['to_guide_id'],
            $validated['review_note'] ?? null,
            $request->user(),
        );

        return $this->success($this->dong($bienBan), sprintf(
            'Đã duyệt và bàn giao đoàn cho %s.',
            $bienBan->toGuide?->name ?? 'hướng dẫn viên mới',
        ));
    }

    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'review_note.required' => 'Từ chối thì phải ghi lý do, hướng dẫn viên sẽ đọc được.',
        ]);

        $yeuCau = GuideHandoverRequest::query()->find($id);

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu bàn giao', 404);
        }

        $daTuChoi = $this->handoverService->rejectRequest(
            $yeuCau,
            $validated['review_note'],
            $request->user(),
        );

        return $this->success(
            ['id' => $daTuChoi->id, 'status' => $daTuChoi->status->value],
            'Đã từ chối. Hướng dẫn viên cũ vẫn giữ nguyên quyền phụ trách.',
        );
    }

    /** @return array<string, mixed> */
    private function dong(GuideHandover $bg): array
    {
        return [
            'id' => $bg->id,
            'tour_schedule_id' => $bg->tour_schedule_id,
            'from_guide' => $bg->fromGuide?->only(['id', 'name', 'phone']),
            'to_guide' => $bg->toGuide?->only(['id', 'name', 'phone']),
            'handed_over_at' => $bg->handed_over_at?->toDateTimeString(),
            'reason' => $bg->reason,
            'handover_note' => $bg->handover_note,
            // Nhờ người của đoàn khác trông hộ: người nhận đang giữ hai đoàn, còn việc dở.
            'is_emergency_cover' => (bool) $bg->is_emergency_cover,
            'created_by_name' => $bg->creator?->name,
            'created_at' => $bg->created_at?->toDateTimeString(),
            // Ghi vào máy muộn hơn lúc bàn giao thật: bàn giao xảy ra trên đường.
            'recorded_late' => $bg->handed_over_at && $bg->created_at
                ? $bg->created_at->diffInMinutes($bg->handed_over_at, true) > 60
                : false,
            'now' => GioVietNam::bayGio()->toDateTimeString(),
        ];
    }
}
