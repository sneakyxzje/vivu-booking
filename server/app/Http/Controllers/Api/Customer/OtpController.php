<?php

namespace App\Http\Controllers\Api\Customer;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Mail\BookingOtpMail;
use App\Services\BookingCheckoutVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class OtpController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', new \App\Rules\ValidEmail(), 'max:255'],
        ]);
        $email = strtolower(trim($validated['email']));
        foreach (['send-otp:'.$email => 3, 'send-otp-ip:'.$request->ip() => 10] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return $this->error('Bạn đã yêu cầu quá nhiều mã OTP. Vui lòng thử lại sau '.ceil(RateLimiter::availableIn($key) / 60).' phút.', 429);
            }
        }
        RateLimiter::hit('send-otp:'.$email, 3600);
        RateLimiter::hit('send-otp-ip:'.$request->ip(), 3600);
        $challenge = Str::random(64);
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = 'booking-challenge:'.hash('sha256', $challenge);
        Cache::put($key, [
            'email' => $email, 'actor_id' => auth('sanctum')->id(),
            'otp_hash' => hash_hmac('sha256', $otp, config('app.key')),
            'attempts' => 0, 'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);
        try {
            Mail::to($email)->send(new BookingOtpMail($otp));
        } catch (Throwable $e) {
            Cache::forget($key);
            report($e);
            return $this->error('Chưa gửi được mã OTP. Vui lòng thử lại.', 503);
        }
        return $this->success(['challenge' => $challenge, 'expires_in' => 300], 'Mã OTP đã được gửi đến email của bạn.')
            ->header('Cache-Control', 'no-store, private');
    }

    public function verifyOtp(Request $request, BookingCheckoutVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'regex:/^[0-9]{6}$/D'],
            'challenge' => ['required', 'string', 'size:64', 'alpha_num:ascii'],
        ]);
        $email = strtolower(trim($data['email']));
        $key = 'booking-challenge:'.hash('sha256', $data['challenge']);
        return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $email, $data, $verification) {
            $record = Cache::get($key);
            if (!$record || $record['email'] !== $email || $record['actor_id'] !== auth('sanctum')->id()
                || $record['expires_at'] <= now()->timestamp) {
                throw new BusinessRuleException('Yêu cầu xác thực không hợp lệ hoặc đã hết hạn. Vui lòng gửi lại mã.', 400);
            }
            if (!hash_equals($record['otp_hash'], hash_hmac('sha256', $data['otp'], config('app.key')))) {
                $record['attempts']++;
                if ($record['attempts'] >= 5) {
                    Cache::forget($key);
                    return $this->error('Bạn đã nhập sai mã OTP 5 lần. Vui lòng yêu cầu mã mới.', 400);
                }
                Cache::put($key, $record, max(1, $record['expires_at'] - now()->timestamp));
                return $this->error('Mã OTP không chính xác. Bạn còn '.(5 - $record['attempts']).' lần thử.', 400);
            }
            $token = $verification->issue($email, auth('sanctum')->id());
            Cache::forget($key);
            return $this->success(['verification_token' => $token, 'expires_in' => 900], 'Xác thực email thành công.')
                ->header('Cache-Control', 'no-store, private');
        });
    }
}
