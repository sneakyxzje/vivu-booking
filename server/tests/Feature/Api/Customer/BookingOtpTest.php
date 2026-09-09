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

    public function test_it_can_send_otp_email()
    {
        $email = 'test@example.com';
        
        $response = $this->postJson('/api/bookings/send-otp', [
            'email' => $email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNotNull(Cache::get('booking_otp_' . $email));
        Mail::assertSent(\App\Mail\BookingOtpMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    public function test_it_can_verify_otp()
    {
        $email = 'test@example.com';
        $otp = '123456';
        Cache::put('booking_otp_' . $email, $otp, now()->addMinutes(5));

        $response = $this->postJson('/api/bookings/verify-otp', [
            'email' => $email,
            'otp' => $otp,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue(Cache::get('booking_verified_' . $email));
        $this->assertNull(Cache::get('booking_otp_' . $email));
    }

    public function test_it_blocks_verification_with_wrong_otp()
    {
        $email = 'test@example.com';
        Cache::put('booking_otp_' . $email, '123456', now()->addMinutes(5));

        $response = $this->postJson('/api/bookings/verify-otp', [
            'email' => $email,
            'otp' => '654321', // Wrong OTP
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertNull(Cache::get('booking_verified_' . $email));
    }

    public function test_it_invalidates_otp_after_5_failed_attempts()
    {
        $email = 'test@example.com';
        Cache::put('booking_otp_' . $email, '123456', now()->addMinutes(5));

        // Nhập sai 5 lần
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/bookings/verify-otp', [
                'email' => $email,
                'otp' => '000000',
            ]);
            $response->assertStatus(400);
        }

        // Sau 5 lần nhập sai, OTP ban đầu trong Cache phải bị hủy hoàn toàn
        $this->assertNull(Cache::get('booking_otp_' . $email));
    }

    public function test_it_blocks_booking_without_otp()
    {
        $email = 'test@example.com';
        
        $tour = \App\Models\Tour::factory()->create(['status' => 'published']);
        $schedule = \App\Models\TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(10),
            'max_people' => 20,
            'booked_people' => 0,
        ]);

        // Cố tình không tạo cache booking_verified_$email
        
        $response = $this->postJson('/api/bookings', [
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'John Doe',
            'customer_email' => $email,
            'customer_phone' => '0912345678',
            'adult_count' => 1,
            'accept_terms' => true,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn chưa xác thực email. Vui lòng nhận và nhập mã OTP trước khi đặt tour.',
            ]);
    }
}
