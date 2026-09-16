<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingTransfer;
use App\Notifications\Alert;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingTransferResponseController extends Controller
{
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    public function handleResponse(Request $request, string $token, string $action)
    {
        if (!in_array($action, ['accept', 'reject'])) {
            return response("Hành động không hợp lệ.", 400);
        }

        $transfer = BookingTransfer::with(['booking', 'toSchedule.tour'])->where('response_token', $token)->first();

        if (!$transfer) {
            return response("Liên kết không tồn tại hoặc không hợp lệ.", 404);
        }

        if ($transfer->response !== 'pending') {
            return response("Cảm ơn! Bạn đã phản hồi yêu cầu này trước đó rồi.", 200);
        }

        $startDate = $transfer->toSchedule?->start_date;
        if ($startDate) {
            $deadline = Carbon::parse($startDate)->subDays(2)->endOfDay();
            if (now()->greaterThan($deadline)) {
                return response("Rất tiếc, thời hạn phản hồi đã kết thúc (trước ngày khởi hành 2 ngày). Vui lòng liên hệ hotline để được hỗ trợ.", 403);
            }
        }

        // Cập nhật phản hồi
        $transfer->response = $action === 'accept' ? 'accepted' : 'rejected';
        $transfer->responded_at = now();
        $transfer->save();

        $booking = $transfer->booking;
        $tourName = $transfer->toSchedule?->tour?->title ?? 'Tour';

        // Thông báo cho admin
        if ($action === 'accept') {
            $this->notifier->toiDieuHanh(
                Alert::KHACH_PHAN_HOI_GHEP_CHUYEN,
                sprintf('Khách %s (Đơn #%d) đã ĐỒNG Ý ghép chuyến sang ngày %s', $booking->customer_name, $booking->id, Carbon::parse($startDate)->format('d/m/Y')),
                sprintf('Đơn hàng: %s', $tourName),
                '/admin/bookings'
            );
            return response("Cảm ơn! Bạn đã đồng ý tham gia chuyến đi vào ngày mới. Chúc bạn một chuyến đi vui vẻ!", 200);
        } else {
            // Từ chối
            $this->notifier->toiDieuHanh(
                Alert::KHACH_PHAN_HOI_GHEP_CHUYEN,
                sprintf('Khách %s (Đơn #%d) đã TỪ CHỐI đổi ngày chuyến đi', $booking->customer_name, $booking->id),
                sprintf('Khách từ chối phương án ghép chuyến %s. Vui lòng liên hệ khách và tiến hành HỦY ĐƠN & HOÀN TIỀN thủ công.', $tourName),
                '/admin/bookings'
            );
            return response("Bạn đã từ chối đổi ngày. Công ty đã ghi nhận yêu cầu và sẽ sớm liên hệ hoàn tiền 100% cho bạn.", 200);
        }
    }
}
