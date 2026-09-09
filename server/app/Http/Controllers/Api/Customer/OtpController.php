<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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

        return $this->success(null, 'Mã OTP đã được gửi đến email của bạn.');
    }
}
