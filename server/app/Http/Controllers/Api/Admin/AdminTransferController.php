<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTransfer;
use App\Models\TourSchedule;
use App\Services\BookingTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * I05 - Điều hành chuyển đơn sang chuyến khác.
 *
 * Chỉ mở đường quản trị. Khách muốn đổi chuyến thì gọi điện, vì đổi chuyến chạm tới số chỗ của
 * hai chuyến và có thể sinh khoản thu thêm - cùng loại rủi ro với hoàn tiền ở nhóm F, nên cũng
 * cần người của công ty chịu trách nhiệm.
 */
class AdminTransferController extends Controller
{
    public function __construct(
        private BookingTransferService $transferService,
    ) {
    }

    /**
     * Các chuyến có thể chuyển tới, kèm chênh lệch giá của từng chuyến.
     *
     * Tính sẵn chênh lệch cho từng lựa chọn thay vì bắt điều hành chọn rồi mới biết: thấy trước
     * con số mới chọn được chuyến hợp lý cho khách.
     */
    public function options(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::query()->with('schedule')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $chiTour = $request->boolean('same_tour', true);

        $chuyen = TourSchedule::query()
            ->with('tour:id,title,status,adult_price,child_price,infant_price')
            ->where('status', ScheduleStatus::Open->value)
            ->where('start_date', '>', now())
            ->whereKeyNot($booking->tour_schedule_id)
            ->when($chiTour, fn ($query) => $query->where('tour_id', $booking->tour_id))
            ->orderBy('start_date')
            ->limit(50)
            ->get();

        $khoiXuong = $request->query('initiated_by', 'company');

        $ketQua = $chuyen
            ->map(function (TourSchedule $schedule) use ($booking, $khoiXuong) {
                $duBao = $this->transferService->preview($booking, $schedule, $khoiXuong);

                return [
                    'schedule_id' => $schedule->id,
                    'tour_id' => $schedule->tour_id,
                    'tour_title' => $schedule->tour?->title,
                    'start_date' => $schedule->start_date,
                    'remaining_seats' => (int) $schedule->max_people - (int) $schedule->booked_people,
                ] + $duBao;
            })
            // Chỉ hiện chuyến thật sự chuyển được. Bày ra lựa chọn rồi báo lỗi khi bấm là bắt
            // điều hành dò từng cái.
            ->filter(fn (array $row) => $row['can_transfer'])
            ->values();

        return $this->success([
            'booking' => [
                'id' => $booking->id,
                'guests' => (int) $booking->guests,
                'total_amount' => (float) $booking->total_amount,
                'transfer_count' => (int) $booking->transfer_count,
            ],
            'options' => $ketQua,
        ], 'Lấy danh sách chuyến có thể chuyển thành công');
    }

    public function store(Request $request, int $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'to_schedule_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'initiated_by' => ['nullable', 'in:customer,company'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do chuyển chuyến.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự để đọc lại còn hiểu.',
        ]);

        $booking = Booking::query()->with('schedule')->find($bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $chuyenDich = TourSchedule::query()->with('tour')->find($validated['to_schedule_id']);

        if (!$chuyenDich) {
            return $this->error('Không tìm thấy chuyến đích', 404);
        }

        $banGhi = $this->transferService->transfer(
            $booking,
            $chuyenDich,
            trim($validated['reason']),
            $request->user(),
            $validated['initiated_by'] ?? 'company',
        );

        $chenh = (float) $banGhi->price_difference + (float) $banGhi->fee;

        return $this->success(
            $banGhi->load(['booking:id,tour_id,tour_schedule_id,total_amount,transfer_count', 'toSchedule:id,start_date']),
            $chenh > 0
                ? sprintf('Đã chuyển chuyến. Cần thu thêm %s đồng từ khách.', number_format($chenh, 0, ',', '.'))
                : ($chenh < 0
                    ? sprintf('Đã chuyển chuyến. Chuyến mới rẻ hơn %s đồng, xử lý theo chính sách công nợ.', number_format(abs($chenh), 0, ',', '.'))
                    : 'Đã chuyển chuyến, không phát sinh chênh lệch.'),
        );
    }

    /** Lịch sử chuyển của một đơn, để biết đã đổi mấy lần và còn được miễn phí không. */
    public function history(int $bookingId): JsonResponse
    {
        $transfers = BookingTransfer::query()
            ->where('booking_id', $bookingId)
            ->with([
                'fromSchedule:id,start_date',
                'toSchedule:id,start_date',
                'approver:id,name',
            ])
            ->orderBy('created_at')
            ->get();

        return $this->success($transfers, 'Lấy lịch sử chuyển chuyến thành công');
    }
}
