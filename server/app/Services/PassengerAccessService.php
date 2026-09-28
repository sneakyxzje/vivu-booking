<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Mail\PassengerOtpMail;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PassengerAccessService
{
    public function isOwner(Booking $booking): bool
    {
        $user = auth('sanctum')->user();
        if ($user && $user->status !== 'active') {
            throw new BusinessRuleException('Tài khoản của bạn đã bị khóa.', 403);
        }

        return $user && $user->role === 'customer' && $booking->customer_id !== null
            && (int) $booking->customer_id === (int) $user->getKey();
    }

    private function binding(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'email' => hash('sha256', strtolower(trim($booking->customer_email))),
            'actor_id' => auth('sanctum')->id(),
        ];
    }

    public function canAccess(Booking $booking, Request $request): bool
    {
        if ($this->isOwner($booking)) {
            return true;
        }
        $token = $request->header('X-Passenger-Access', '');
        if (!preg_match('/^[a-zA-Z0-9]{64}$/', $token)) {
            return false;
        }

        return Cache::get('passenger-access:' . hash('sha256', $token)) === $this->binding($booking);
    }

    public function send(Booking $booking, string $email, Request $request): array
    {
        $this->isOwner($booking); // Also rejects blocked accounts on these public routes.
        $this->limit('passenger-send-ip:' . $request->ip(), 10, 3600);
        if (!$booking->khopEmail($email)) {
            throw new BusinessRuleException('Email không khớp với đơn đặt tour.', 403);
        }

        return Cache::lock('passenger-otp-lock:' . $booking->id, 30)->block(5, function () use ($booking) {
            $this->limit('passenger-send-cooldown:' . $booking->id, 1, 60);
            $this->limit('passenger-send-email:' . $this->binding($booking)['email'], 10, 3600);
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $challenge = Str::random(64);
            $key = 'passenger-otp:' . $booking->id;
            // Reissuing invalidates the previous code. Never store the plaintext OTP.
            Cache::put($key, [
                'binding' => $this->binding($booking),
                'challenge' => hash('sha256', $challenge),
                'otp' => hash_hmac('sha256', $otp, config('app.key')),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ], 300);

            try {
                Mail::to($booking->customer_email)->send(new PassengerOtpMail($otp));
            } catch (\Throwable $e) {
                Cache::forget($key);
                report($e);
                throw new BusinessRuleException('Chưa gửi được mã OTP. Vui lòng thử lại sau ít phút.', 503);
            }

            return ['challenge_id' => $challenge, 'expires_in' => 300, 'retry_after' => 60];
        });
    }

    public function verify(Booking $booking, string $challenge, string $otp, Request $request): array
    {
        $this->isOwner($booking);
        $this->limit('passenger-verify-ip:' . $request->ip(), 30, 600);

        return Cache::lock('passenger-otp-lock:' . $booking->id, 30)->block(5, function () use ($booking, $challenge, $otp) {
            $key = 'passenger-otp:' . $booking->id;
            $record = Cache::get($key);
            if (!$record || $record['expires_at'] <= now()->timestamp
                || $record['binding'] !== $this->binding($booking)
                || !hash_equals($record['challenge'], hash('sha256', $challenge))) {
                throw new BusinessRuleException('Yêu cầu OTP đã hết hạn hoặc không hợp lệ. Vui lòng gửi lại mã.', 422);
            }
            if (!hash_equals($record['otp'], hash_hmac('sha256', $otp, config('app.key')))) {
                $record['attempts']++;
                if ($record['attempts'] >= 5) {
                    Cache::forget($key);
                    throw new BusinessRuleException('Bạn đã nhập sai 5 lần. Vui lòng gửi lại mã OTP.', 422);
                }
                Cache::put($key, $record, max(1, $record['expires_at'] - now()->timestamp));
                throw new BusinessRuleException('Mã OTP không đúng. Còn ' . (5 - $record['attempts']) . ' lần thử.', 422);
            }

            Cache::forget($key); // A code can only be exchanged once.
            $token = Str::random(64);
            Cache::put('passenger-access:' . hash('sha256', $token), $this->binding($booking), 1800);

            return ['access_token' => $token, 'expires_in' => 1800];
        });
    }

    private function limit(string $key, int $max, int $seconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new BusinessRuleException('Bạn thao tác quá nhanh. Vui lòng thử lại sau ' . RateLimiter::availableIn($key) . ' giây.', 429);
        }
        RateLimiter::hit($key, $seconds);
    }
}
