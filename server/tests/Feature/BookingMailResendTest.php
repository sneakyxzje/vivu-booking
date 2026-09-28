<?php

namespace Tests\Feature;

use App\Mail\BalanceReminderMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingMailResendTest extends TestCase
{
    use RefreshDatabase;

    private function booking(float $paid = 500000): Booking
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'total_amount' => 1000000,
            'paid_at' => $paid >= 1000000 ? now() : null,
        ]);
        BookingPayment::create([
            'booking_id' => $booking->id,
            'kind' => 'deposit',
            'amount' => $paid,
            'method' => 'gateway',
            'paid_at' => now(),
        ]);

        return $booking;
    }

    public function test_admin_can_resend_balance_reminder_for_an_unpaid_balance(): void
    {
        Mail::fake();
        $booking = $this->booking();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->postJson("/api/admin/bookings/{$booking->id}/send-mail", [
            'type' => 'balance_reminder',
        ])->assertOk()->assertJsonPath('data.gui_toi', $booking->customer_email);

        Mail::assertQueued(BalanceReminderMail::class, fn ($mail) => $mail->hasTo($booking->customer_email));
    }

    public function test_reminder_for_fully_paid_booking_is_rejected(): void
    {
        Mail::fake();
        $booking = $this->booking(1000000);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->postJson("/api/admin/bookings/{$booking->id}/send-mail", [
            'type' => 'balance_reminder',
        ])->assertStatus(422);

        Mail::assertNothingOutgoing();
    }

    public function test_cancellation_mail_for_active_booking_is_rejected(): void
    {
        Mail::fake();
        $booking = $this->booking();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->postJson("/api/admin/bookings/{$booking->id}/send-mail", [
            'type' => 'cancelled',
        ])->assertStatus(422);

        Mail::assertNothingOutgoing();
    }

    public function test_customer_cannot_resend_booking_mail(): void
    {
        Mail::fake();
        $booking = $this->booking();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $this->actingAs($customer)->postJson("/api/admin/bookings/{$booking->id}/send-mail", [
            'type' => 'balance_reminder',
        ])->assertForbidden();

        Mail::assertNothingOutgoing();
    }
}
