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

        $email = strtolower(trim($validated['email']));
        $throttleKey = 'send-otp:' . $email;
        $ipKey = 'send-otp-ip:' . $request->ip();

        // 1. Chống spam IP: Tối đa 10 lần gửi OTP / giờ từ 1 IP
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $seconds = RateLimiter::availableIn($ipKey);
            $minutes = ceil($seconds / 60);
            return $this->error("IP của bạn đã yêu cầu quá nhiều mã OTP. Vui lòng thử lại sau {$minutes} phút.", 429);
        }

        // 2. Chống spam Email: Tối đa 3 lần gửi / giờ tới 1 Email
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);
            return $this->error("Bạn đã vượt quá giới hạn gửi mã tới email này. Vui lòng thử lại sau {$minutes} phút.", 429);
        }

        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($throttleKey, 3600);

        // Tạo OTP ngẫu nhiên 6 số
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Lưu vào cache 5 phút và reset đếm sai cũ nếu có
        Cache::put('booking_otp_' . $email, $otp, now()->addMinutes(5));
        Cache::forget('verify-otp-attempts:' . $email);

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

        $email = strtolower(trim($validated['email']));
        $otp = $validated['otp'];

        $attemptsKey = 'verify-otp-attempts:' . $email;
        $attempts = (int) Cache::get($attemptsKey, 0);

        // Chống Brute-Force: Quá 5 lần thử sai thì hủy luôn OTP
        if ($attempts >= 5) {
            Cache::forget('booking_otp_' . $email);
            Cache::forget($attemptsKey);
            return $this->error('Bạn đã nhập sai mã OTP quá 5 lần. Mã OTP đã bị hủy, vui lòng bấm gửi lại mã mới.', 400);
        }

        $cachedOtp = Cache::get('booking_otp_' . $email);

        if (!$cachedOtp || $cachedOtp !== $otp) {
            $attempts++;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(5));
            
            if ($attempts >= 5) {
                Cache::forget('booking_otp_' . $email);
                Cache::forget($attemptsKey);
                return $this->error('Bạn đã nhập sai mã OTP 5 lần. Mã đã bị hủy để đảm bảo an toàn, vui lòng yêu cầu mã mới.', 400);
            }

            $remaining = 5 - $attempts;
            return $this->error("Mã OTP không chính xác. Bạn còn {$remaining} lần thử.", 400);
        }

        // OTP đúng -> Xóa đếm thử sai & Sinh cờ xác thực lưu trong 15 phút
        Cache::forget($attemptsKey);
        Cache::put('booking_verified_' . $email, true, now()->addMinutes(15));
        
        // Xóa mã OTP cũ
        Cache::forget('booking_otp_' . $email);

        return $this->success(null, 'Xác thực email thành công. Bạn có thể tiến hành đặt tour.');
    }
}
