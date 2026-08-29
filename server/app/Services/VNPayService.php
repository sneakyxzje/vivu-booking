<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;

class VNPayService
{
    // VNPay yêu cầu vnp_CreateDate / vnp_ExpireDate theo giờ Việt Nam (GMT+7)
    private const VNPAY_TIMEZONE = 'Asia/Ho_Chi_Minh';

    /**
     * Dựng liên kết thanh toán cho MỘT LẦN trả tiền của một đơn.
     *
     * `$amount` là số tiền của lần này, không phải giá trị đơn: một đơn có đặt cọc trả hai lần,
     * lần đầu là tiền cọc, lần sau là phần còn lại. Truyền null thì lấy toàn bộ giá trị đơn.
     */
    public function createPayment(Booking $booking, ?float $amount = null): string
    {
        $vnp_TmnCode = config('services.vnpay.tmn_code');
        $vnp_HashSecret = config('services.vnpay.hash_secret');

        $vnp_Url = config('services.vnpay.url');
        $vnp_ReturnUrl = config('services.vnpay.return_url');

        $vnp_TxnRef = $this->txnRef($booking);
        $vnp_OrderInfo = "Thanh toan don hang #" . $booking->id;
        $vnp_Amount = round($amount ?? (float) $booking->total_amount) * 100;
        $vnp_Locale = 'vn';
        $vnp_IpAddr = request()->ip();

        $expiresAt = $booking->expires_at
            ? Carbon::parse($booking->expires_at)
            : now()->addMinutes((int) config('booking.payment_ttl_minutes', 10));

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => now()->timezone(self::VNPAY_TIMEZONE)->format('YmdHis'),
            "vnp_ExpireDate" => $expiresAt->copy()->timezone(self::VNPAY_TIMEZONE)->format('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => 'billpayment',
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return $vnp_Url;
    }

    /**
     * Mã tham chiếu giao dịch: `{id đơn}-{dấu thời gian}`.
     *
     * VNPay coi `vnp_TxnRef` là mã DUY NHẤT của một giao dịch, không phải mã của đơn hàng. Trước
     * đây trường này là đúng id đơn, nên hai lần trả tiền cho cùng một đơn — trả cọc rồi trả nốt,
     * hoặc bấm lại sau một lần thất bại — gửi lên cùng một mã. Bản chạy thử bỏ qua, bản thật từ
     * chối với lỗi "giao dịch đã tồn tại", và triệu chứng là khách không sang được trang thanh
     * toán mà không hiểu vì sao.
     *
     * Phần trước dấu gạch vẫn là id đơn, để lượt quay về đọc ngược ra được — xem `bookingIdFrom`.
     */
    public function txnRef(Booking $booking): string
    {
        return $booking->id . '-' . now()->timestamp;
    }

    /**
     * Đọc id đơn từ `vnp_TxnRef` gửi về.
     *
     * Nhận cả dạng cũ (chỉ có id, không có dấu gạch) vì các giao dịch tạo trước thay đổi này vẫn
     * có thể đang trên đường quay về.
     */
    public function bookingIdFrom(?string $txnRef): ?int
    {
        if (!$txnRef) {
            return null;
        }

        $phanDau = explode('-', $txnRef)[0];

        return ctype_digit($phanDau) ? (int) $phanDau : null;
    }
}
