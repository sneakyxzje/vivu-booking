<?php

namespace Tests\Concerns;

use App\Services\BookingCheckoutVerification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait VerifiesBookingOtp
{
    /** Business-rule tests start with a verified email; OTP endpoints have their own tests. */
    protected function postVerifiedBooking(array $payload, array $headers = []): TestResponse
    {
        $token = app(BookingCheckoutVerification::class)->issue($payload['customer_email'], auth('sanctum')->id());
        return $this->postJson('/api/bookings', $payload, [
            'X-Booking-Verification' => $token,
            'Idempotency-Key' => (string) Str::uuid(),
            ...$headers,
        ]);
    }
}
