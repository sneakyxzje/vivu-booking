<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourSchedule;
use App\Services\ScheduleDeadlineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Điều hành dời hạn chốt danh sách của một chuyến khởi hành.
 *
 * Form sửa tour cũng ghi được hạn chốt, nhưng ở đó nó lẫn giữa hai chục trường khác và không có
 * chỗ nói cho người bấm biết họ vừa đổi những gì. Màn hình quản lý chuyến cần một thao tác riêng
 * cho việc này, có xem trước tác động - giống xem trước hủy đơn và xem trước ghép chuyến.
 *
 * Cả hai đường đều gọi ScheduleDeadlineService, nên luật và nhật ký chỉ nằm ở một chỗ.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md.
 */
class AdminScheduleDeadlineController extends Controller
{
    public function __construct(
        private ScheduleDeadlineService $deadlineService,
    ) {
    }

    /** Tác động của hạn chốt mới, tính trước khi lưu. */
    public function preview(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'booking_deadline' => ['nullable', 'date'],
        ]);

        $schedule = TourSchedule::query()->with('tour:id,title')->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $moi = isset($validated['booking_deadline'])
            ? Carbon::parse($validated['booking_deadline'])
            : null;

        return $this->success([
            'schedule' => [
                'id' => $schedule->id,
                'tour_title' => $schedule->tour?->title,
                'start_date' => $schedule->start_date,
                'status' => $schedule->status,
                'booked_people' => (int) $schedule->booked_people,
                'max_people' => (int) $schedule->max_people,
            ],
            'impact' => $this->deadlineService->impact($schedule, $moi),
        ], 'Lấy tác động của hạn chốt mới thành công');
    }

    public function update(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'booking_deadline' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $schedule = TourSchedule::query()->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $moi = isset($validated['booking_deadline'])
            ? Carbon::parse($validated['booking_deadline'])
            : null;

        $daSua = $this->deadlineService->change(
            $schedule,
            $moi,
            isset($validated['reason']) ? trim($validated['reason']) : null,
            $request->user(),
        );

        return $this->success([
            'schedule_id' => $daSua->id,
            'booking_deadline' => $daSua->booking_deadline,
        ], $daSua->booking_deadline
            ? 'Đã đổi hạn chốt danh sách sang ' . $daSua->booking_deadline->format('d/m/Y H:i') . '.'
            : 'Đã xóa hạn chốt riêng, chuyến quay về dùng mốc mặc định của hệ thống.');
    }
}
