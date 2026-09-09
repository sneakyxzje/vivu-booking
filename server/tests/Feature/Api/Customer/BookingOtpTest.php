<?php

namespace Tests\Feature\Api\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BookingOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('send-otp:test@example.com');
        Cache::forget('booking_otp_test@example.com');
        Cache::forget('booking_verified_test@example.com');
    }

    public function test_it_rate_limits_otp_sending()
    {
        $email = 'test@example.com';
        $throttleKey = 'send-otp:' . $email;

        // Hit 3 times (the limit)
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($throttleKey, 3600);
        }

        $response = $this->postJson('/api/bookings/send-otp', [
            'email' => $email,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }
}
