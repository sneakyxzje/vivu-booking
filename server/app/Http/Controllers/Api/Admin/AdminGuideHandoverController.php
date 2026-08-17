<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideHandover;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\GuideHandoverService;
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
    ) {
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

            // Người thay: đang hoạt động và chưa phụ trách chính chuyến này. Trùng lịch thì để
            // máy chủ từ chối lúc bấm, vì đó là phép so theo khoảng ngày chứ không lọc sẵn được.
            'available_guides' => User::query()
                ->where('role', 'guide')
                ->where('status', 'active')
                ->whereNotIn('id', $dangPhuTrach)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),

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
