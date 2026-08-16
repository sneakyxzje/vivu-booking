<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\TourSchedule;
use App\Services\ScheduleMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L03 - Điều hành ghép hai chuyến của cùng một tour.
 *
 * Câu số 16 của hội đồng. Tình huống: hai chuyến gần ngày nhau, mỗi chuyến ít khách, không
 * chuyến nào đủ mức tối thiểu. Ghép thì cả hai đoàn đều được đi.
 */
class AdminScheduleMergeController extends Controller
{
    public function __construct(
        private ScheduleMergeService $mergeService,
    ) {
    }

    /**
     * Các chuyến có thể ghép chuyến này vào, kèm tác động của từng lựa chọn.
     *
     * Tính sẵn số đơn sẽ chuyển và số đơn sẽ bị hủy cho từng chuyến đích, để điều hành thấy
     * hậu quả trước khi chọn chứ không phải chọn rồi mới biết.
     */
    public function candidates(int $scheduleId): JsonResponse
    {
        $nguon = TourSchedule::query()->with('tour:id,title,type,status')->find($scheduleId);

        if (!$nguon) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $ungVien = TourSchedule::query()
            ->with('tour:id,title,type,status')
            ->where('tour_id', $nguon->tour_id)
            ->whereKeyNot($nguon->getKey())
            ->whereIn('status', [
                ScheduleStatus::Open->value,
                ScheduleStatus::Closed->value,
                ScheduleStatus::Confirmed->value,
            ])
            ->where('start_date', '>', now())
            ->orderBy('start_date')
            ->get()
            ->map(function (TourSchedule $dich) use ($nguon) {
                return [
                    'schedule_id' => $dich->id,
                    'start_date' => $dich->start_date,
                    'booked_people' => (int) $dich->booked_people,
                    'max_people' => (int) $dich->max_people,
                ] + $this->mergeService->preview($nguon, $dich);
            })
            ->filter(fn (array $row) => $row['can_merge'])
            ->values();

        return $this->success([
            'schedule' => [
                'id' => $nguon->id,
                'start_date' => $nguon->start_date,
                'booked_people' => (int) $nguon->booked_people,
                'tour_title' => $nguon->tour?->title,
                'tour_type' => $nguon->tour?->type,
            ],
            'candidates' => $ungVien,
        ], 'Lấy danh sách chuyến có thể ghép thành công');
    }

    public function store(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'to_schedule_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do ghép chuyến.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự, khách sẽ đọc được nội dung này.',
        ]);

        $nguon = TourSchedule::query()->with('tour')->find($scheduleId);
        $dich = TourSchedule::query()->with('tour')->find($validated['to_schedule_id']);

        if (!$nguon || !$dich) {
            return $this->error('Không tìm thấy chuyến khởi hành', 404);
        }

        $ketQua = $this->mergeService->merge(
            $nguon,
            $dich,
            trim($validated['reason']),
            $request->user(),
        );

        return $this->success(
            $ketQua + ['to_schedule_id' => $dich->id],
            sprintf(
                'Đã ghép chuyến. Chuyển %d đơn sang chuyến mới, hủy %d đơn chưa thanh toán.',
                $ketQua['transferred'],
                $ketQua['cancelled'],
            ),
        );
    }
}
