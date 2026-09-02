<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ScheduleStatus;
use App\Enums\TransferReasonCategory;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTransfer;
use App\Models\CustomerContactLog;
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
 *
 * Từ nay `store()` đòi thêm hai thứ, và cả hai đều là điều kiện chứ không phải trường mô tả:
 *
 *   - `contact_log_id` - cuộc trao đổi với khách làm căn cứ. Điều hành không tự ý dời ngày đi của
 *     người khác; phải hỏi trước, và câu hỏi ấy phải để lại dấu vết.
 *   - `reason_category` - nhóm căn cứ. Ô ghi chú tự do đặt "khách bận công tác" ngang hàng với
 *     "bão số 9 cấm biển", trong khi hai thứ ấy dẫn tới hai cách xử lý tiền khác nhau.
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

        // Nhóm lý do đổi mức phí đổi lịch, nên bản xem trước phải biết để con số hiện ra đúng thứ
        // sẽ thu thật. Chưa chọn nhóm thì tính theo quy tắc cũ.
        $nhomLyDo = TransferReasonCategory::tryFrom((string) $request->query('reason_category'));

        $ketQua = $chuyen
            ->map(function (TourSchedule $schedule) use ($booking, $khoiXuong, $nhomLyDo) {
                $duBao = $this->transferService->preview($booking, $schedule, $khoiXuong, nhomLyDo: $nhomLyDo);

                return [
                    'schedule_id' => $schedule->id,
                    'tour_id' => $schedule->tour_id,
                    'tour_title' => $schedule->tour?->title,
                    'start_date' => $schedule->start_date,
                    'remaining_seats' => (int) $schedule->max_people - (int) $schedule->booked_people,
                ] + $duBao;
            })
            /*
             * Trả về cả chuyến KHÔNG chuyển được, kèm lý do.
             *
             * Trước đây danh sách lọc sạch chuyến bị chặn. Nghe thì gọn, nhưng phần lớn lý do chặn
             * là chuyện của ĐƠN chứ không của chuyến đích: quá hạn chốt ở chuyến gốc, hoặc khách
             * xin đổi khi còn dưới bảy ngày. Những lúc ấy mọi lựa chọn cùng biến mất, và màn hình
             * kết luận sai rằng không chuyến nào còn đủ chỗ - trong khi chuyến đích đang trống
             * trơn. Người dùng đi tìm chỗ trống, còn nguyên nhân thật thì không ai nói ra.
             *
             * Chuyển được xếp lên trước; phần còn lại hiện mờ kèm đúng câu máy chủ sẽ trả lời nếu
             * bấm.
             */
            ->sortBy(fn (array $row) => $row['can_transfer'] ? 0 : 1)
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
            'contact_log_id' => ['required', 'integer'],
            'reason_category' => ['required', 'string', 'in:' . implode(',', TransferReasonCategory::values())],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'initiated_by' => ['nullable', 'in:customer,company'],
        ], [
            'contact_log_id.required' => 'Phải chọn cuộc trao đổi với khách làm căn cứ. Chưa có thì '
                . 'ghi nhận một cuộc liên hệ trước.',
            'reason_category.required' => 'Phải chọn nhóm lý do chuyển chuyến.',
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

        $canCu = CustomerContactLog::query()->find($validated['contact_log_id']);

        if (!$canCu) {
            return $this->error('Không tìm thấy bản ghi liên hệ', 404);
        }

        $banGhi = $this->transferService->transfer(
            $booking,
            $chuyenDich,
            trim($validated['reason']),
            $request->user(),
            $validated['initiated_by'] ?? 'company',
            canCu: $canCu,
            nhomLyDo: TransferReasonCategory::from($validated['reason_category']),
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
