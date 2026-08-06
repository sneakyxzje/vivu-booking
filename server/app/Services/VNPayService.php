<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class VNPayService
{
    // VNPay yêu cầu vnp_CreateDate / vnp_ExpireDate theo giờ Việt Nam (GMT+7)
    private const VNPAY_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function createPayment($order)
    {
        $vnp_TmnCode = env('VNPAY_TMN_CODE');
        $vnp_HashSecret = env('VNPAY_HASH_SECRET');

        $vnp_Url = env('VNPAY_URL');
        $vnp_ReturnUrl = env('VNPAY_RETURN_URL');

        $vnp_TxnRef = $order->id;
        $vnp_OrderInfo = "Payment for order #" . $order->id;
        $vnp_Amount = ($order->total_price ?? $order->total_amount) * 100;
        $vnp_Locale = 'vn';
        $vnp_IpAddr = request()->ip();

        $expiresAt = $order->expires_at
            ? Carbon::parse($order->expires_at)
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
}
