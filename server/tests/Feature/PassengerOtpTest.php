<?php

namespace Tests\Feature;

use App\Mail\PassengerOtpMail;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassengerOtpTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\VerifiesPassengerOtp;

    private Booking $booking;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $tour = Tour::factory()->create(['status' => 'active']);
        $schedule = TourSchedule::create([
            'tour_id' => $tour->id, 'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22), 'booking_deadline' => now()->addDays(17),
            'status' => 'open', 'max_people' => 20, 'min_people' => 1, 'booked_people' => 1,
        ]);
        $this->booking = Booking::create([
            'public_token' => (string) Str::uuid(), 'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id, 'customer_id' => null,
            'customer_name' => 'Người đặt', 'customer_email' => 'contact@example.com',
            'departure_date' => $schedule->start_date, 'guests' => 1, 'adult_count' => 1,
            'child_count' => 0, 'infant_count' => 0, 'total_amount' => 1000000,
            'status' => 'confirmed', 'paid_at' => now(),
        ]);
        $this->booking->passengers()->create([
            'name' => 'Khách', 'type' => 'adult', 'identity_number' => '001234567890',
        ]);
        $this->url = '/api/bookings/' . $this->booking->public_token . '/passengers';
    }

    private function challenge(): array
    {
        $id = $this->postJson($this->url . '/send-otp', ['email' => 'CONTACT@example.com'])
            ->assertOk()->assertJsonPath('data.expires_in', 300)->json('data.challenge_id');

        return ['challenge_id' => $id, 'otp' => Mail::sent(PassengerOtpMail::class)->last()->otp];
    }

    private function payload(): array
    {
        return ['passengers' => [['name' => 'Tên mới', 'type' => 'adult', 'identity_number' => '001234567890']]];
    }

    public function test_matching_email_and_checkout_verification_cannot_bypass_passenger_otp(): void
    {
        Cache::put('booking_verified_contact@example.com', true, 900);
        $this->getJson($this->url . '?email=contact@example.com')->assertOk()
            ->assertJsonPath('data.identity_masked', true)->assertJsonPath('data.requires_otp', true);
        $this->getJson('/api/bookings/' . $this->booking->public_token . '?email=contact@example.com')
            ->assertOk()->assertJsonPath('data.identity_masked', true)
            ->assertJsonPath('data.passengers.0.identity_number', '••••••••7890');
        $this->putJson($this->url, $this->payload() + ['customer_email' => 'contact@example.com', 'requires_otp' => false])
            ->assertForbidden()->assertJsonPath('code', 'passenger_verification_required');
    }

    public function test_code_goes_only_to_booking_email_and_is_not_returned_or_stored_plaintext(): void
    {
        $this->postJson($this->url . '/send-otp', ['email' => 'attacker@example.com'])->assertForbidden();
        Mail::assertNothingSent();
        $challenge = $this->challenge();
        Mail::assertSent(PassengerOtpMail::class, fn ($mail) => $mail->hasTo('contact@example.com'));
        $this->assertNotSame($challenge['otp'], Cache::get('passenger-otp:' . $this->booking->id)['otp']);
        $this->assertStringContainsString('5 phút', Mail::sent(PassengerOtpMail::class)->last()->render());
    }

    public function test_verified_session_can_read_and_save_repeatedly_without_more_codes(): void
    {
        $headers = $this->passengerAccessHeaders($this->booking);
        $this->getJson($this->url, $headers)->assertOk()->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.passengers.0.identity_number', '001234567890');
        $this->putJson($this->url, $this->payload(), $headers)->assertOk();
        $this->putJson($this->url, $this->payload(), $headers)->assertOk();
        Mail::assertSentCount(1);
        $this->getJson($this->url)->assertOk()->assertJsonPath('data.identity_masked', true);
    }

    public function test_code_is_single_use(): void
    {
        $challenge = $this->challenge();
        $this->postJson($this->url . '/verify-otp', $challenge)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.expires_in', 1800);
        $this->postJson($this->url . '/verify-otp', $challenge)->assertUnprocessable();
    }

    public function test_code_expires_after_five_minutes(): void
    {
        $challenge = $this->challenge();
        $this->travel(301)->seconds();
        $this->postJson($this->url . '/verify-otp', $challenge)->assertUnprocessable();
    }

    public function test_five_wrong_attempts_invalidate_even_the_correct_code(): void
    {
        $challenge = $this->challenge();
        $wrong = $challenge['otp'] === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->url . '/verify-otp', array_replace($challenge, ['otp' => $wrong]))->assertUnprocessable();
        }
        $this->postJson($this->url . '/verify-otp', $challenge)->assertUnprocessable();
    }

    public function test_resend_is_throttled_and_invalidates_previous_challenge(): void
    {
        $old = $this->challenge();
        $this->postJson($this->url . '/send-otp', ['email' => 'contact@example.com'])->assertStatus(429);
        $this->travel(61)->seconds();
        $new = $this->challenge();
        $this->postJson($this->url . '/verify-otp', $old)->assertUnprocessable();
        $this->postJson($this->url . '/verify-otp', $new)->assertOk();
    }

    public function test_access_and_code_are_scoped_to_one_booking_even_with_same_email(): void
    {
        $challenge = $this->challenge();
        $other = $this->booking->replicate();
        $other->public_token = (string) Str::uuid();
        $other->save();
        $url = '/api/bookings/' . $other->public_token . '/passengers';
        $this->postJson($url . '/verify-otp', $challenge)->assertUnprocessable();
        $token = $this->postJson($this->url . '/verify-otp', $challenge)->assertOk()->json('data.access_token');
        $this->putJson($url, $this->payload(), ['X-Passenger-Access' => $token])->assertForbidden();
    }

    public function test_access_expires_and_cannot_override_cutoff_or_departure(): void
    {
        $headers = $this->passengerAccessHeaders($this->booking);
        $this->booking->schedule->update(['booking_deadline' => now()->subSecond()]);
        $this->putJson($this->url, $this->payload(), $headers)->assertUnprocessable();
        $this->booking->schedule->update(['status' => 'in_progress', 'start_date' => now()->subHour()]);
        $this->putJson($this->url, $this->payload(), $headers)->assertUnprocessable();
        $this->travel(1801)->seconds();
        $this->getJson($this->url, $headers)->assertOk()->assertJsonPath('data.identity_masked', true);
        $this->putJson($this->url, $this->payload(), $headers)->assertForbidden();
    }

    public function test_contact_email_change_revokes_access(): void
    {
        $headers = $this->passengerAccessHeaders($this->booking);
        $this->booking->update(['customer_email' => 'changed@example.com']);
        $this->putJson($this->url, $this->payload(), $headers)->assertForbidden();
    }

    public function test_guest_access_is_not_reused_after_signing_in_as_another_account(): void
    {
        $headers = $this->passengerAccessHeaders($this->booking);
        $other = \App\Models\User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $this->withToken($other->createToken('test')->plainTextToken);
        $this->putJson($this->url, $this->payload(), $headers)->assertForbidden();
    }

    public function test_blocked_account_cannot_request_or_exchange_a_code(): void
    {
        $challenge = $this->challenge();
        $other = \App\Models\User::factory()->create(['role' => 'customer', 'status' => 'blocked']);
        $this->withToken($other->createToken('test')->plainTextToken);
        $this->postJson($this->url . '/send-otp', ['email' => 'contact@example.com'])->assertForbidden();
        $this->postJson($this->url . '/verify-otp', $challenge)->assertForbidden();
    }

    public function test_send_limit_applies_across_bookings(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($this->url . '/send-otp', ['email' => 'wrong@example.com'])->assertForbidden();
        }
        $this->postJson($this->url . '/send-otp', ['email' => 'contact@example.com'])->assertStatus(429);
        Mail::assertNothingSent();
    }

    public function test_mail_failure_does_not_leave_a_usable_code(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('mail unavailable'));
        $this->postJson($this->url . '/send-otp', ['email' => 'contact@example.com'])->assertStatus(503);
        $this->assertNull(Cache::get('passenger-otp:' . $this->booking->id));
    }
}
