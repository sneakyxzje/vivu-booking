<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use App\Models\GuideAssignmentDecline;
use App\Models\TourSchedule;
use App\Services\ScheduleGuideService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chuyến được phân công: hướng dẫn viên xác nhận hoặc từ chối.
 *
 * Trước đây điều hành gán xong là coi như xong; hướng dẫn viên chỉ phát hiện khi tự mở danh sách
 * tour của mình, và muốn nói "hôm đó tôi bận" thì phải gọi điện.
 *
 * Chưa xác nhận **vẫn là đã được phân công** - điều hành đang trông vào họ. Xác nhận chỉ là bằng
 * chứng người ta đã biết; từ chối mới là thứ thay đổi danh sách.
 */
class AssignmentController extends Controller
{
    public function __construct(
        private ScheduleGuideService $guideService,
        private ScheduleLifecycleService $lifecycle,
    ) {
    }

    /** Các chuyến đang được phân công cho người này, chưa xác nhận xếp lên đầu. */
    public function index(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;

        $ds = TourSchedule::query()
            ->whereHas('guides', fn ($query) => $query->whereKey($guideId))
            ->with(['tour:id,title,number_of_days', 'guides:id,name'])
            ->orderBy('start_date')
            ->get()
            ->map(function (TourSchedule $sc) use ($guideId) {
                $toi = $sc->guides->firstWhere('id', $guideId);
                $trangThai = $this->lifecycle->effectiveStatus($sc);

                return [
                    'schedule_id' => $sc->id,
                    'tour_title' => $sc->tour?->title,
                    'start_date' => $sc->start_date,
                    'end_date' => $sc->end_date,
                    'number_of_days' => (int) ($sc->tour?->number_of_days ?? 1),
                    'status' => $trangThai->value,
                    'status_label' => $trangThai->label(),
                    'accepted_at' => $toi?->pivot?->accepted_at,
                    // Ai cùng dẫn chuyến này với mình.
                    'co_guides' => $sc->guides
                        ->where('id', '!=', $guideId)
                        ->pluck('name')
                        ->values(),
                    /*
                     * Đoàn đã lên đường thì không từ chối nữa: rút lui lúc đó là bàn giao, phải
                     * có người nhận trước khi mình rời, nếu không đoàn không còn ai.
                     */
                    'can_decline' => !$trangThai->isRunning() && !$trangThai->isFinal(),
                ];
            })
            // Chưa xác nhận lên đầu: đó là thứ cần làm, phần còn lại chỉ để xem.
            ->sortBy(fn (array $row) => $row['accepted_at'] ? 1 : 0)
            ->values();

        return $this->success($ds, 'Lấy danh sách chuyến được phân công thành công');
    }

    public function accept(Request $request, int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::query()->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $this->guideService->acceptAssignment($schedule, $request->user());

        return $this->success(['schedule_id' => $schedule->id], 'Đã xác nhận nhận chuyến.');
    }

    public function decline(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Phải ghi lý do từ chối, điều hành cần biết để xếp người khác.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự.',
        ]);

        $schedule = TourSchedule::query()->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $this->guideService->declineAssignment($schedule, $request->user(), $validated['reason']);

        return $this->success(
            ['schedule_id' => $schedule->id],
            'Đã từ chối chuyến này. Điều hành sẽ xếp người khác.',
        );
    }

    /** Lịch sử từ chối của chính mình, để đối chiếu khi cần. */
    public function myDeclines(Request $request): JsonResponse
    {
        $ds = GuideAssignmentDecline::query()
            ->where('guide_id', $request->user()->id)
            ->with(['schedule:id,start_date,tour_id', 'schedule.tour:id,title'])
            ->latest('declined_at')
            ->get()
            ->map(fn (GuideAssignmentDecline $tc) => [
                'id' => $tc->id,
                'schedule_id' => $tc->tour_schedule_id,
                'tour_title' => $tc->schedule?->tour?->title,
                'start_date' => $tc->schedule?->start_date,
                'reason' => $tc->reason,
                'declined_at' => $tc->declined_at?->toDateTimeString(),
            ]);

        return $this->success($ds, 'Lấy lịch sử từ chối thành công');
    }
}
