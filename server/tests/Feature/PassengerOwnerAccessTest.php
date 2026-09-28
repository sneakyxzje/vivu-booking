<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassengerOwnerAccessTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\VerifiesPassengerOtp;

    private User $owner;
    private Booking $booking;
    private TourSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $tour = Tour::factory()->create(['status' => 'active']);
        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id, 'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22), 'booking_deadline' => now()->addDays(17),
            'status' => 'open', 'max_people' => 20, 'min_people' => 1, 'booked_people' => 1,
        ]);
        $this->booking = Booking::create([
            'public_token' => (string) Str::uuid(), 'tour_id' => $tour->id,
            'tour_schedule_id' => $this->schedule->id, 'customer_id' => $this->owner->id,
            'customer_name' => 'Chủ đơn', 'customer_email' => 'booking-contact@example.com',
            'departure_date' => $this->schedule->start_date,
            'guests' => 1, 'adult_count' => 1, 'child_count' => 0, 'infant_count' => 0,
            'total_amount' => 1000000, 'status' => 'confirmed', 'paid_at' => now(),
        ]);
        $this->booking->passengers()->create([
            'name' => 'Hành khách ban đầu', 'type' => 'adult', 'identity_number' => '001234567890',
        ]);
    }

    private function url(): string
    {
        return "/api/bookings/{$this->booking->public_token}/passengers";
    }

    private function payload(): array
    {
        return ['passengers' => [['name' => 'Tên đã sửa', 'type' => 'adult', 'identity_number' => '001234567890']]];
    }

    private function loginOwner(): void
    {
        // Dùng Bearer token thật để kiểm tra optional auth trên tuyến công khai.
        $this->withToken($this->owner->createToken('test')->plainTextToken);
    }

    public function test_owner_can_read_and_update_without_booking_email(): void
    {
        $this->loginOwner();
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.requires_email', false)
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.passengers.0.identity_number', '001234567890');
        $this->putJson($this->url(), $this->payload())->assertOk()
            ->assertJsonPath('data.requires_email', false)
            ->assertJsonPath('data.passengers.0.name', 'Tên đã sửa');
    }

    public function test_owner_access_uses_customer_id_even_if_contact_email_differs(): void
    {
        $this->assertNotSame($this->owner->email, $this->booking->customer_email);
        $this->loginOwner();
        $this->putJson($this->url(), $this->payload() + ['customer_email' => 'wrong@example.com'])
            ->assertOk()->assertJsonPath('data.requires_email', false);
    }

    public function test_another_account_is_not_automatically_authorized_even_with_same_email(): void
    {
        $other = User::factory()->create([
            'email' => $this->booking->customer_email, 'role' => 'customer', 'status' => 'active',
        ]);
        $this->withToken($other->createToken('test')->plainTextToken);
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.requires_email', true)
            ->assertJsonPath('data.identity_masked', true);
        $this->putJson($this->url(), $this->payload())->assertForbidden();
        $this->assertSame('Hành khách ban đầu', $this->booking->passengers()->first()->name);
        // Tài khoản khác chỉ được sửa sau khi xác thực OTP của đơn.
        $this->putJson($this->url(), $this->payload() + ['customer_email' => $this->booking->customer_email])
            ->assertForbidden();
        $this->withHeaders($this->passengerAccessHeaders($this->booking));
        $this->putJson($this->url(), $this->payload())->assertOk()->assertJsonPath('data.requires_otp', false);
    }

    public function test_guest_still_needs_email_and_cannot_forge_owner_access(): void
    {
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.requires_email', true)->assertJsonPath('data.identity_masked', true);
        $this->putJson($this->url(), $this->payload() + ['requires_email' => false, 'customer_id' => $this->owner->id])
            ->assertForbidden();
        $this->putJson($this->url(), $this->payload() + ['customer_email' => 'wrong@example.com'])->assertForbidden();
        $this->putJson($this->url(), $this->payload() + ['customer_email' => $this->booking->customer_email])
            ->assertForbidden();
        $this->withHeaders($this->passengerAccessHeaders($this->booking));
        $this->putJson($this->url(), $this->payload())->assertOk()->assertJsonPath('data.requires_otp', false);
    }

    public function test_owner_still_cannot_edit_after_cutoff(): void
    {
        $this->schedule->update(['booking_deadline' => now()->subMinute()]);
        $this->loginOwner();
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.requires_email', false)->assertJsonPath('data.can_edit', false);
        $this->putJson($this->url(), $this->payload())->assertUnprocessable();
        $this->assertSame('Hành khách ban đầu', $this->booking->passengers()->first()->name);
    }

    public function test_owner_cannot_edit_a_departed_trip(): void
    {
        $this->schedule->update(['status' => 'in_progress', 'start_date' => now()->subHour()]);
        $this->loginOwner();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.can_edit', false);
        $this->putJson($this->url(), $this->payload())->assertUnprocessable();
    }

    public function test_blocked_owner_is_denied_even_with_correct_email(): void
    {
        $this->owner->update(['status' => 'blocked']);
        $this->loginOwner();
        $this->getJson($this->url() . '?email=' . $this->booking->customer_email)->assertForbidden();
        $this->putJson($this->url(), $this->payload() + ['customer_email' => $this->booking->customer_email])->assertForbidden();
    }

    public function test_revoked_login_does_not_grant_owner_access(): void
    {
        $token = $this->owner->createToken('test');
        $token->accessToken->delete();
        $this->withToken($token->plainTextToken);
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.requires_email', true)->assertJsonPath('data.identity_masked', true);
        $this->putJson($this->url(), $this->payload())->assertForbidden();
    }

    public function test_logged_in_account_does_not_own_a_guest_booking(): void
    {
        $this->booking->update(['customer_id' => null, 'customer_email' => $this->owner->email]);
        $this->loginOwner();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.requires_otp', true);
        $this->putJson($this->url(), $this->payload())->assertForbidden();
    }

    public function test_account_endpoint_keeps_owner_access_without_email(): void
    {
        $this->loginOwner();
        $this->getJson("/api/my-bookings/{$this->booking->id}/passengers")
            ->assertOk()->assertJsonPath('data.requires_email', false);
        $this->putJson("/api/my-bookings/{$this->booking->id}/passengers", $this->payload())
            ->assertOk()->assertJsonPath('data.requires_email', false);
    }
}
