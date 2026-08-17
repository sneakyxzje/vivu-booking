<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourSchedule;
use App\Services\ScheduleCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * K - Điều hành hủy cả chuyến.
 *
 * Tách khỏi endpoint đổi trạng thái chung. Hủy chuyến không phải một lần đổi trạng thái: nó chạm
 * tới tiền của từng khách, nên phải có bước gán phương án ở giữa. Gộp chung vào chỗ đổi trạng
 * thái thì sẽ có hai đường hủy, một đường xử lý đơn và một đường không - đúng khuôn của phần lớn
 * lỗi đã gặp ở dự án này.
 */
class AdminScheduleCancellationController extends Controller
{
    public function __construct(
        private ScheduleCancellationService $cancellationService,
    ) {
    }

    /** Xem tác động trước khi hủy: ai đã trả tiền, bao nhiêu, chuyển sang đâu được. */
    public function preview(int $scheduleId): JsonResponse
    {
        $schedule = TourSchedule::query()->with('tour:id,title')->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        return $this->success([
            'schedule' => [
                'id' => $schedule->id,
                'tour_title' => $schedule->tour?->title,
                'start_date' => $schedule->start_date,
                'booked_people' => (int) $schedule->booked_people,
                'max_people' => (int) $schedule->max_people,
            ],
            'impact' => $this->cancellationService->preview($schedule),
        ], 'Lấy tác động của việc hủy chuyến thành công');
    }

    public function store(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'plans' => ['present', 'array'],
            'plans.*.booking_id' => ['required', 'integer'],
            'plans.*.action' => ['required', 'string', 'in:refund,transfer'],
            'plans.*.to_schedule_id' => ['nullable', 'integer'],
        ], [
            'reason.required' => 'Lý do hủy chuyến là bắt buộc.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự, khách sẽ đọc được nội dung này.',
        ]);

        $schedule = TourSchedule::query()->with('tour')->find($scheduleId);

        if (!$schedule) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        // Khóa theo booking_id để lớp dịch vụ tra thẳng, khỏi duyệt lại danh sách cho từng đơn.
        $phuongAn = collect($validated['plans'])
            ->keyBy(fn (array $dong) => (int) $dong['booking_id'])
            ->map(fn (array $dong) => [
                'action' => $dong['action'],
                'to_schedule_id' => $dong['to_schedule_id'] ?? null,
            ])
            ->all();

        $ketQua = $this->cancellationService->cancel(
            $schedule,
            trim($validated['reason']),
            $phuongAn,
            $request->user(),
        );

        return $this->success($ketQua, sprintf(
            'Đã hủy chuyến. Hoàn đủ %d đơn, chuyển %d đơn sang chuyến khác, hủy %d đơn chưa thanh toán.',
            $ketQua['refunded'],
            $ketQua['transferred'],
            $ketQua['cancelled_unpaid'],
        ));
    }
}
