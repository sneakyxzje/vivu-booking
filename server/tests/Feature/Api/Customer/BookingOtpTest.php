<?php

namespace Tests\Feature\Api\Customer;

use App\Mail\BookingOtpMail;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function challenge(string $email = 'test@example.com'): array
    {
        $response = $this->postJson('/api/bookings/send-otp', ['email' => $email])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $mail = Mail::sent(BookingOtpMail::class)->last();
        return ['email' => $email, 'challenge' => $response->json('data.challenge'), 'otp' => $mail->otp];
    }

    private function proof(string $email = 'test@example.com'): array
    {
        $challenge = $this->challenge($email);
        $token = $this->postJson('/api/bookings/verify-otp', $challenge)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->json('data.verification_token');
        return ['X-Booking-Verification' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    }

    private function payload(): array
    {
        $tour = Tour::factory()->create(['status' => 'active', 'adult_price' => 1000000]);
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $tour->id, 'status' => 'open', 'start_date' => now()->addDays(10),
            'booking_deadline' => now()->addDays(7), 'max_people' => 1, 'booked_people' => 0,
        ]);
        return ['tour_id' => $tour->id, 'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Test Guest', 'customer_email' => 'test@example.com',
            'adult_count' => 1, 'accept_terms' => true];
    }

    public function test_sending_returns_a_private_challenge_and_stores_only_an_otp_hash(): void
    {
        $challenge = $this->challenge();
        $record = Cache::get('booking-challenge:'.hash('sha256', $challenge['challenge']));
        $this->assertSame(64, strlen($challenge['challenge']));
        $this->assertNotSame($challenge['otp'], $record['otp_hash']);
        $this->assertArrayNotHasKey('otp', $record);
        Mail::assertSent(BookingOtpMail::class, fn ($mail) => $mail->hasTo('test@example.com'));
    }

    public function test_sending_is_rate_limited_per_email_and_ip(): void
    {
        RateLimiter::increment('send-otp:test@example.com', 3600, 3);
        $this->postJson('/api/bookings/send-otp', ['email' => 'test@example.com'])->assertStatus(429);
        RateLimiter::increment('send-otp-ip:127.0.0.1', 3600, 10);
        $this->postJson('/api/bookings/send-otp', ['email' => 'other@example.com'])->assertStatus(429);
        Mail::assertNothingSent();
    }

    public function test_verification_issues_a_hashed_token_and_consumes_the_challenge(): void
    {
        $challenge = $this->challenge();
        $token = $this->postJson('/api/bookings/verify-otp', $challenge)->assertOk()->json('data.verification_token');
        $this->assertDatabaseHas('booking_checkout_verifications', ['token_hash' => hash('sha256', $token)]);
        $this->assertDatabaseMissing('booking_checkout_verifications', ['token_hash' => $token]);
        $this->assertNull(Cache::get('booking_verified_test@example.com'));
        $this->postJson('/api/bookings/verify-otp', $challenge)->assertStatus(400);
    }

    public function test_wrong_challenge_or_email_cannot_verify_someone_elses_otp(): void
    {
        $challenge = $this->challenge();
        $this->postJson('/api/bookings/verify-otp', [...$challenge, 'challenge' => Str::random(64)])->assertStatus(400);
        $this->postJson('/api/bookings/verify-otp', [...$challenge, 'email' => 'other@example.com'])->assertStatus(400);
        $this->postJson('/api/bookings/verify-otp', $challenge)->assertOk();
    }

    public function test_five_wrong_codes_invalidate_the_challenge(): void
    {
        $challenge = $this->challenge();
        $wrong = $challenge['otp'] === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/bookings/verify-otp', [...$challenge, 'otp' => $wrong])->assertStatus(400);
        }
        $this->postJson('/api/bookings/verify-otp', $challenge)->assertStatus(400);
        $this->assertDatabaseCount('booking_checkout_verifications', 0);
    }

    public function test_challenge_expires_without_wrong_attempts_extending_it(): void
    {
        $challenge = $this->challenge();
        $this->travel(299)->seconds();
        $wrong = $challenge['otp'] === '000000' ? '000001' : '000000';
        $this->postJson('/api/bookings/verify-otp', [...$challenge, 'otp' => $wrong])->assertStatus(400);
        $this->travel(2)->seconds();
        $this->postJson('/api/bookings/verify-otp', $challenge)->assertStatus(400);
    }

    public function test_missing_proof_and_old_email_flag_cannot_create_a_booking(): void
    {
        $payload = $this->payload();
        $this->proof(); // Another visitor knows only the email, not the returned token.
        Cache::put('booking_verified_test@example.com', true, 900);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->postJson('/api/bookings', $payload)->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_token_is_bound_to_email_and_authenticated_actor(): void
    {
        $payload = $this->payload();
        $user = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        Sanctum::actingAs($user);
        $proof = $this->proof();
        $this->postJson('/api/bookings', [...$payload, 'customer_email' => 'other@example.com'], $proof)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']));
        $this->postJson('/api/bookings', $payload, $proof)->assertForbidden();
        Sanctum::actingAs($user);
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
    }

    public function test_challenge_is_bound_to_the_authenticated_actor(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']));
        $challenge = $this->challenge();
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']));
        $this->postJson('/api/bookings/verify-otp', $challenge)->assertStatus(400);
    }

    public function test_expired_unused_token_is_rejected(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $this->travel(15)->minutes();
        $this->postJson('/api/bookings', $payload, $proof)->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_identical_retry_returns_the_booking_even_when_it_filled_the_last_seat(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $first = $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        $this->travel(16)->minutes(); // The verification and hold windows have passed.
        $second = $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        $this->assertSame($first->json('data.booking.id'), $second->json('data.booking.id'));
        $second->assertJsonPath('data.payment_url', null);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(1, TourSchedule::find($payload['tour_schedule_id'])->booked_people);
        $this->assertSame(1, DB::table('booking_checkout_verifications')->whereNotNull('consumed_at')->count());
    }

    public function test_consumed_token_rejects_another_request_or_changed_payload(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        $this->postJson('/api/bookings', $payload, [...$proof, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->postJson('/api/bookings', [...$payload, 'note' => 'Different request'], $proof)->assertStatus(409);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_failed_creation_does_not_consume_the_token(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        TourSchedule::find($payload['tour_schedule_id'])->update(['booked_people' => 1]);
        $this->postJson('/api/bookings', $payload, $proof)->assertUnprocessable();
        $this->assertSame(0, DB::table('booking_checkout_verifications')->whereNotNull('consumed_at')->count());
        TourSchedule::find($payload['tour_schedule_id'])->update(['booked_people' => 0]);
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
    }

    public function test_retry_after_response_generation_failure_does_not_duplicate_the_booking(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $this->mock(\App\Services\VNPayService::class, function ($mock) {
            $calls = 0;
            $mock->shouldReceive('createPayment')->twice()->andReturnUsing(function () use (&$calls) {
                if (++$calls === 1) {
                    throw new \RuntimeException('Simulated response failure');
                }
                return 'https://sandbox.vnpayment.vn/test-retry';
            });
        });
        $this->postJson('/api/bookings', $payload, $proof)->assertStatus(500);
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated()
            ->assertPlainCookie('guest_id', Booking::firstOrFail()->guest_id);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_retry_does_not_consume_discount_twice_after_its_last_use(): void
    {
        $payload = $this->payload();
        $code = DiscountCode::create(['code' => 'OTPTEST', 'name' => 'Test', 'type' => 'percent', 'value' => 10,
            'minimum_order_amount' => 0, 'usage_limit' => 1, 'used_count' => 0,
            'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(), 'is_active' => true]);
        $payload['discount_code'] = $code->code;
        $proof = $this->proof();
        $first = $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        $retry = $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        $this->assertSame($first->json('data.booking.id'), $retry->json('data.booking.id'));
        $this->assertSame(1, (int) $code->fresh()->used_count);
    }

    public function test_cancelled_booking_retry_returns_current_state_without_payment_link(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        Booking::firstOrFail()->update(['status' => 'cancelled']);
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated()
            ->assertJsonPath('data.booking.status', 'cancelled')->assertJsonPath('data.payment_url', null);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_another_guest_sending_a_code_cannot_replace_the_original_challenge(): void
    {
        $original = $this->challenge();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);
        $other = $this->challenge();
        $this->assertNotSame($original['challenge'], $other['challenge']);
        $this->postJson('/api/bookings/verify-otp', $original)->assertOk();
        $this->postJson('/api/bookings/verify-otp', $other)->assertOk();
    }

    public function test_invalid_proof_or_missing_request_identity_cannot_create_a_booking(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $this->postJson('/api/bookings', $payload, [...$proof, 'X-Booking-Verification' => Str::random(64)])->assertForbidden();
        $this->postJson('/api/bookings', $payload, [...$proof, 'Idempotency-Key' => ''])->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
    }

    public function test_replay_window_expires_without_creating_another_booking(): void
    {
        $payload = $this->payload();
        $proof = $this->proof();
        $this->postJson('/api/bookings', $payload, $proof)->assertCreated();
        $this->travel(24)->hours();
        $this->postJson('/api/bookings', $payload, $proof)->assertStatus(409);
        $this->assertDatabaseCount('bookings', 1);
    }
}
