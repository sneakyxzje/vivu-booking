<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\BookingChangeRequestService;
use App\Services\BookingHoldService;
use App\Services\CancellationPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F02 - Khách xem mức hoàn dự kiến rồi gửi yêu cầu hủy.
 *
 * Tách khỏi BookingController vì đây là luồng khác hẳn: đơn chưa thanh toán thì khách tự hủy
 * qua BookingController::cancelBooking, đơn đã thanh toán thì đi qua đây và phải chờ duyệt.
 */
class ChangeRequestController extends Controller
{
    public function __construct(
        private BookingChangeRequestService $changeRequests,
        private CancellationPolicyService $cancellationPolicy,
        private BookingHoldService $holdService,
    ) {
    }

    /**
     * Mức hoàn nếu hủy ngay bây giờ, để khách xem trước khi quyết định gửi.
     *
     * Bước bắt buộc theo tài liệu 03 mục 5.2: cho khách xác nhận con số trước khi gửi là cách
     * duy nhất tránh khiếu nại "tôi tưởng được hoàn nhiều hơn" về sau.
     */
    public function preview(Request $request, int $bookingId): JsonResponse
    {
        $booking = $this->timDonCuaKhach($request, $bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        $duBao = $this->cancellationPolicy->quote($booking, $booking->schedule);

        return $this->success($duBao + [
            'seats_will_be_released' => $this->holdService->shouldReleaseSeats($booking, $booking->schedule),
            'policy_name' => $booking->cancellationPolicy?->name,
            'pending_request' => $this->yeuCauDangCho($booking),
        ], 'Lấy mức hoàn dự kiến thành công');
    }

    /** Gửi yêu cầu hủy. Đơn không đổi trạng thái cho tới khi điều hành duyệt. */
    public function store(Request $request, int $bookingId): JsonResponse
    {
        /*
         * Tài khoản nhận tiền hoàn, hỏi ngay tại đây.
         *
         * Đây là lúc duy nhất khách đang ngồi trước màn hình và có động lực khai đúng. Hỏi sau —
         * lúc kế toán chuẩn bị chuyển tiền — nghĩa là gọi điện cho từng người, và mỗi cuộc gọi
         * không nghe máy là một khoản treo thêm vài ngày.
         *
         * Bắt buộc khi đơn đã trả tiền, để trống khi chưa: không có gì để hoàn thì hỏi số tài
         * khoản là bắt người ta khai một thứ vô ích.
         */
        $booking = $this->timDonCuaKhach($request, $bookingId);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt tour của bạn.', 404);
        }

        $daTraTien = $this->cancellationPolicy->quote($booking, $booking->schedule)['paid_amount'] > 0;
        $batBuoc = $daTraTien ? 'required' : 'nullable';

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'refund_bank_account' => [$batBuoc, 'string', 'max:50'],
            'refund_bank_name' => [$batBuoc, 'string', 'max:120'],
            'refund_account_holder' => [$batBuoc, 'string', 'max:120'],
        ], [
            'reason.required' => 'Vui lòng cho biết lý do bạn muốn hủy.',
            'reason.min' => 'Lý do cần ít nhất 10 ký tự để chúng tôi hiểu và xử lý nhanh hơn.',
            'refund_bank_account.required' => 'Vui lòng nhập số tài khoản để chúng tôi chuyển tiền hoàn.',
            'refund_bank_name.required' => 'Vui lòng nhập tên ngân hàng.',
            'refund_account_holder.required' => 'Vui lòng nhập tên chủ tài khoản, đúng như trên thẻ.',
        ]);

        if ($daTraTien) {
            $booking->forceFill([
                'refund_bank_account' => trim($validated['refund_bank_account']),
                'refund_bank_name' => trim($validated['refund_bank_name']),
                'refund_account_holder' => trim($validated['refund_account_holder']),
            ])->save();
        }

        $yeuCau = $this->changeRequests->requestCancellation(
            $booking,
            trim($validated['reason']),
            $request->user(),
        );

        return $this->success(
            $yeuCau,
            'Đã gửi yêu cầu hủy. Bộ phận điều hành sẽ xem xét và phản hồi bạn.',
            201,
        );
    }

    /** Danh sách yêu cầu của chính khách, để theo dõi trạng thái. */
    public function index(Request $request): JsonResponse
    {
        $yeuCau = BookingChangeRequest::query()
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $request->user()->id))
            ->with('booking:id,tour_id,departure_date,total_amount,status')
            ->latest()
            ->get();

        return $this->success($yeuCau, 'Lấy danh sách yêu cầu thành công');
    }

    /** Khách rút lại yêu cầu khi chưa ai duyệt. */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $yeuCau = BookingChangeRequest::query()
            ->whereKey($id)
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $request->user()->id))
            ->first();

        if (!$yeuCau) {
            return $this->error('Không tìm thấy yêu cầu của bạn.', 404);
        }

        return $this->success(
            $this->changeRequests->withdraw($yeuCau),
            'Đã rút lại yêu cầu hủy.',
        );
    }

    private function timDonCuaKhach(Request $request, int $bookingId): ?Booking
    {
        return Booking::query()
            ->with(['schedule', 'cancellationPolicy.rules'])
            ->whereKey($bookingId)
            ->where('customer_id', $request->user()->id)
            ->first();
    }

    private function yeuCauDangCho(Booking $booking): ?BookingChangeRequest
    {
        return BookingChangeRequest::query()
            ->where('booking_id', $booking->getKey())
            ->pending()
            ->first();
    }
}
