<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingOtpMail;

class OtpController extends Controller
{
    /**
     * Khởi tạo yêu cầu gửi OTP.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', new \App\Rules\ValidEmail(), 'max:255'],
        ], [
            'email.required' => 'Vui lòng nhập email để nhận mã OTP.',
        ]);

        $email = $validated['email'];
        $throttleKey = 'send-otp:' . $email;

        // Chống spam: Mỗi email chỉ được gửi tối đa 3 lần mỗi giờ
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);
            return response()->json([
                'success' => false,
                'message' => "Bạn đã vượt quá giới hạn gửi mã. Vui lòng thử lại sau {$minutes} phút.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 3600); // Lưu key trong 1 giờ

        // Tạo OTP ngẫu nhiên 6 số
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Lưu vào cache 5 phút
        Cache::put('booking_otp_' . $email, $otp, now()->addMinutes(5));

        // Gửi email
        Mail::to($email)->send(new BookingOtpMail($otp));

        return $this->success(null, 'Mã OTP đã được gửi đến email của bạn.');
    }

    /**
     * Xác thực OTP.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'otp.required' => 'Vui lòng nhập mã OTP.',
            'otp.size' => 'Mã OTP phải gồm 6 chữ số.',
        ]);

        $email = $validated['email'];
        $otp = $validated['otp'];

        $cachedOtp = Cache::get('booking_otp_' . $email);

        if (!$cachedOtp || $cachedOtp !== $otp) {
            return response()->json([
                'success' => false,
                'message' => 'Mã OTP không chính xác hoặc đã hết hạn.',
            ], 400);
        }

        // OTP đúng -> Sinh cờ xác thực lưu trong 15 phút
        Cache::put('booking_verified_' . $email, true, now()->addMinutes(15));
        
        // Xóa mã OTP cũ
        Cache::forget('booking_otp_' . $email);

        return $this->success(null, 'Xác thực email thành công. Bạn có thể tiến hành đặt tour.');
    }
}
