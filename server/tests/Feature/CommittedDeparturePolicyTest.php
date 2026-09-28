<?php

namespace Tests\Feature;

use App\Enums\ProposalStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingProposalMail;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\ScheduleMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommittedDeparturePolicyTest extends TestCase
{
    use \Tests\Concerns\VerifiesBookingOtp;

    use RefreshDatabase;

    private Tour $tour;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfMinute());
        Mail::fake();
        $this->tour = Tour::factory()->create(['type' => 'shared', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function schedule(int $days = 10, array $extra = []): TourSchedule
    {
        return TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id, 'status' => 'open',
            'start_date' => now()->addDays($days), 'end_date' => now()->addDays($days + 1),
            'booking_deadline' => now()->addDays($days - 3),
            'min_people' => 10, 'max_people' => 20, 'booked_people' => 0,
        ], $extra));
    }

    private function booking(TourSchedule $schedule, int $seats = 1, float $fraction = .5): Booking
    {
        $schedule->increment('booked_people', $seats);
        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id, 'tour_schedule_id' => $schedule->id,
            'departure_date' => $schedule->start_date, 'customer_name' => 'Khách thử',
            'customer_email' => 'guest@example.com', 'guests' => $seats, 'seats' => $seats,
            'adult_count' => $seats, 'child_count' => 0, 'infant_count' => 0,
            'total_amount' => $seats * 1000000, 'status' => 'confirmed', 'confirmed_at' => now(),
        ]);
        if ($fraction > 0) {
            // Fixture ledger; production payments are subject to the deadline guard.
            BookingPayment::create(['booking_id' => $booking->id, 'kind' => 'deposit',
                'amount' => $booking->total_amount * $fraction, 'paid_at' => now()]);
        }
        return $booking;
    }

    private function propose(Booking $booking, TourSchedule $target): BookingChangeProposal
    {
        app(ScheduleMergeService::class)->merge($booking->schedule, $target,
            'Đề xuất ghép để tổ chức chuyến phù hợp.', $this->admin);
        return $booking->proposals()->latest('id')->firstOrFail();
    }

    public function test_late_booking_still_deposits_half_and_uses_custom_cutoff(): void
    {
        $schedule = $this->schedule(2, ['booking_deadline' => now()->addHours(4)]);
        $booking = $this->booking($schedule, 1, 0);
        $this->assertSame(500000.0, app(BookingPaymentService::class)->nextPaymentAmount($booking));
        $this->assertTrue($booking->balanceDueAt()->equalTo($schedule->booking_deadline));
        $schedule->update(['booking_deadline' => null]);
        $this->assertTrue($booking->fresh()->balanceDueAt()->equalTo($schedule->start_date->copy()->subDays(3)));
    }

    public function test_cutoff_cancels_deposits_without_grace_and_runs_with_eight_of_twelve(): void
    {
        $schedule = $this->schedule();
        $paid = $this->booking($schedule, 8, 1);
        $unpaid = $this->booking($schedule, 4);
        $this->travelTo($schedule->booking_deadline->copy()->subSecond());
        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();
        $this->assertSame('confirmed', $unpaid->fresh()->status);
        $this->travelTo($schedule->booking_deadline);
        // Running confirm by itself must settle unpaid bookings first.
        $this->artisan('schedules:confirm-ready')->assertSuccessful();
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertEquals(0, $unpaid->fresh()->refund_amount);
        $this->assertTrue($unpaid->fresh()->seats_released);
        $this->assertSame('confirmed', $paid->fresh()->status);
        $this->assertEquals(8, $schedule->fresh()->booked_people);
        $this->assertSame(ScheduleStatus::Confirmed, $schedule->fresh()->status);
        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();
        $this->assertEquals(8, $schedule->fresh()->booked_people);
    }

    public function test_partial_balance_only_forfeits_deposit_and_refunds_excess(): void
    {
        $schedule = $this->schedule();
        $booking = $this->booking($schedule, 1, .75);
        $this->travelTo($schedule->booking_deadline);
        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();
        $this->assertEquals(250000, $booking->fresh()->refund_amount);
    }

    public function test_payment_cannot_bypass_cutoff_before_worker_runs(): void
    {
        $schedule = $this->schedule();
        $booking = $this->booking($schedule);
        $this->travelTo($schedule->booking_deadline);
        $this->expectException(BusinessRuleException::class);
        app(BookingPaymentService::class)->record($booking, 'balance', 500000);
    }

    public function test_merge_cancels_source_and_waits_for_consent_before_moving_booking(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source, 8, 1);
        $proposal = $this->propose($booking, $target);
        $this->assertSame($source->id, $booking->fresh()->tour_schedule_id);
        $this->assertSame(ScheduleStatus::Cancelled, $source->fresh()->status);
        $this->assertSame('awaiting_transfer', $booking->fresh()->status);
        $this->assertEquals(0, $source->fresh()->booked_people);
        Mail::assertQueued(BookingProposalMail::class);
        $this->assertStringContainsString('Chuyến ban đầu đã hủy.', (new BookingProposalMail($booking, $proposal))->render());
        $this->assertSame(1, $booking->proposals()->count());
    }

    public function test_acceptance_merges_eight_and_one_and_runs_below_target(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source, 8, 1);
        $this->booking($target, 1, 1);
        $proposal = $this->propose($booking, $target);
        $this->postJson('/api/bookings/'.$booking->public_token.'/proposals/'.$proposal->id.'/respond?email=guest@example.com',
            ['action' => 'accept'])->assertOk();
        $this->assertSame($target->id, $booking->fresh()->tour_schedule_id);
        $this->assertEquals(9, $target->fresh()->booked_people);
        $this->assertSame(ScheduleStatus::Cancelled, $source->fresh()->status);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
        $this->travelTo($target->booking_deadline);
        $this->artisan('schedules:confirm-ready')->assertSuccessful();
        $this->assertSame(ScheduleStatus::Confirmed, $target->fresh()->status);
    }

    public function test_rejection_or_silence_cancels_and_records_full_refund(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $reject = $this->booking($source, 1, 1);
        $silent = $this->booking($source, 1, 1);
        $this->propose($reject, $target);
        app(ScheduleMergeService::class)->respond($reject->proposals()->first(), 'reject', null);
        $this->travelTo($silent->proposals()->first()->response_deadline);
        $this->getJson('/api/bookings/'.$silent->public_token.'/proposals?email=guest@example.com')
            ->assertOk()->assertJsonPath('data.0.status', 'expired');
        $this->assertSame($source->id, $reject->fresh()->tour_schedule_id);
        $this->assertSame($source->id, $silent->fresh()->tour_schedule_id);
        $this->travelTo($source->booking_deadline);
        $this->artisan('schedules:confirm-ready')->assertSuccessful();
        $this->assertSame(ScheduleStatus::Cancelled, $source->fresh()->status);
        foreach ([$reject, $silent] as $booking) {
            $this->assertSame('cancelled', $booking->fresh()->status);
            $this->assertSame('by_company', $booking->fresh()->cancel_type);
            $this->assertEquals(1000000, app(BookingPaymentService::class)->refundOutstanding($booking->fresh()));
            $this->assertSame(0, $booking->payments()->where('kind', 'refund')->count());
        }
    }

    public function test_acceptance_rechecks_capacity_and_rolls_back_without_losing_source(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source, 8, 1);
        $proposal = $this->propose($booking, $target);
        $this->booking($target, 15, 1);
        $this->postJson('/api/bookings/'.$booking->public_token.'/proposals/'.$proposal->id.'/respond?email=guest@example.com',
            ['action' => 'accept'])->assertStatus(422);
        $this->assertSame($source->id, $booking->fresh()->tour_schedule_id);
        $this->assertEquals(15, $target->fresh()->booked_people);
        $this->assertSame(ProposalStatus::Pending, $proposal->fresh()->status);
    }

    public function test_wrong_email_and_changed_itinerary_cannot_authorize_transfer(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source);
        $proposal = $this->propose($booking, $target);
        $url = '/api/bookings/'.$booking->public_token.'/proposals/'.$proposal->id.'/respond';
        $this->postJson($url.'?email=wrong@example.com', ['action' => 'accept'])->assertNotFound();
        $target->update(['start_date' => $target->start_date->copy()->addHour()]);
        $this->postJson($url.'?email=guest@example.com', ['action' => 'accept'])->assertStatus(422);
        $this->assertSame($source->id, $booking->fresh()->tour_schedule_id);
    }

    public function test_checkout_near_cutoff_charges_half_and_caps_hold_expiry(): void
    {
        $schedule = $this->schedule(1, ['booking_deadline' => now()->addMinutes(2)]);
        $response = $this->postVerifiedBooking([
            'tour_id' => $this->tour->id, 'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khách sát hạn', 'customer_email' => 'guest@example.com',
            'customer_phone' => '0901234567', 'adult_count' => 1, 'accept_terms' => true,
        ])->assertCreated();
        $booking = Booking::latest('id')->firstOrFail();
        $this->assertEquals($booking->total_amount / 2, $response->json('data.deposit_amount'));
        $this->assertTrue($booking->expires_at->equalTo($schedule->booking_deadline));
        $this->travelTo($schedule->booking_deadline);
        $this->getJson('/api/bookings/'.$booking->public_token)->assertOk()->assertJsonMissingPath('data.payment_url');
    }

    public function test_expiry_worker_records_refund_and_cannot_accept_twice(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source, 1, 1);
        $silent = $this->booking($source, 1, 1);
        $proposal = $this->propose($booking, $target);
        app(ScheduleMergeService::class)->respond($proposal, 'accept', null);
        $url = '/api/bookings/'.$booking->public_token.'/proposals/'.$proposal->id.'/respond?email=guest@example.com';
        $this->postJson($url, ['action' => 'accept'])->assertStatus(422);
        $this->assertEquals(1, $target->fresh()->booked_people);
        $this->travelTo($silent->proposals()->first()->response_deadline);
        $this->artisan('bookings:expire-proposals')->assertSuccessful();
        $this->assertSame(ProposalStatus::Expired, $silent->proposals()->first()->status);
        $this->assertSame($source->id, $silent->fresh()->tour_schedule_id);
        $this->assertSame('cancelled', $silent->fresh()->status);
        $this->assertEquals(1000000, $silent->fresh()->refund_amount);
    }

    public function test_late_gateway_callback_does_not_confirm_an_unrecorded_deposit(): void
    {
        config(['services.vnpay.hash_secret' => 'cutoff-test-secret']);
        $schedule = $this->schedule();
        $booking = $this->booking($schedule, 1, 0);
        $booking->update(['status' => 'pending', 'confirmed_at' => null, 'expires_at' => $schedule->booking_deadline]);
        $this->travelTo($schedule->booking_deadline);
        $query = [
            'vnp_TxnRef' => $booking->id.'-late', 'vnp_Amount' => 50000000,
            'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => 'LATE-CUTOFF',
        ];
        ksort($query);
        $data = [];
        foreach ($query as $key => $value) {
            $data[] = urlencode($key).'='.urlencode($value);
        }
        $query['vnp_SecureHash'] = hash_hmac('sha512', implode('&', $data), 'cutoff-test-secret');
        $result = app(\App\Services\VNPayCallbackService::class)->handle($query);
        $this->assertFalse($result['successful']);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, $booking->payments()->count());
        $this->assertDatabaseHas('payment_logs', ['booking_id' => $booking->id, 'transaction_no' => 'LATE-CUTOFF']);
    }

    public function test_rejecting_a_deposit_refunds_only_money_collected_and_expiry_is_idempotent(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source);
        // A previous partial refund must not be subtracted twice.
        BookingPayment::create(['booking_id' => $booking->id, 'kind' => 'refund',
            'amount' => 100000, 'paid_at' => now()]);
        $proposal = $this->propose($booking, $target);
        $this->assertSame('awaiting_transfer', $booking->fresh()->status);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
        app(ScheduleMergeService::class)->respond($proposal, 'reject');
        $this->assertEquals(400000, app(BookingPaymentService::class)->refundOutstanding($booking->fresh()));
        $this->assertEquals(500000, $booking->fresh()->refund_amount);
        $this->assertSame(2, $booking->payments()->count());
        $this->travelTo($proposal->response_deadline);
        $this->artisan('bookings:expire-proposals')->assertSuccessful();
        $this->artisan('bookings:expire-proposals')->assertSuccessful();
        $this->assertSame(2, $booking->payments()->count());
        Mail::assertQueued(\App\Mail\BookingCancelledMail::class, 1);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_waiting_booking_cannot_pay_balance_or_overwrite_merge_proposal(): void
    {
        $booking = $this->booking($this->schedule());
        $proposal = $this->propose($booking, $this->schedule(11));
        \Laravel\Sanctum\Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/bookings/'.$booking->id.'/proposals/'.$proposal->id)->assertStatus(422);
        $this->postJson('/api/admin/bookings/'.$booking->id.'/proposals', [
            'reason' => 'Không được thay thế phương án ghép đang chờ',
            'response_deadline' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);
        $this->assertSame(ProposalStatus::Pending, $proposal->fresh()->status);
        $this->expectException(BusinessRuleException::class);
        app(BookingPaymentService::class)->record($booking->fresh(), 'balance', 500000);
    }

    public function test_acceptance_preserves_deposit_and_moves_only_accepting_group(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $accept = $this->booking($source, 2);
        $reject = $this->booking($source, 3);
        $this->propose($accept, $target);
        app(ScheduleMergeService::class)->respond($accept->proposals()->first(), 'accept');
        app(ScheduleMergeService::class)->respond($reject->proposals()->first(), 'reject');
        $this->assertSame('confirmed', $accept->fresh()->status);
        $this->assertEquals(1000000, app(BookingPaymentService::class)->paidForTour($accept->fresh()));
        $this->assertSame($target->id, $accept->fresh()->tour_schedule_id);
        $this->assertSame($source->id, $reject->fresh()->tour_schedule_id);
        $this->assertEquals(1500000, $reject->fresh()->refund_amount);
        $this->assertEquals(2, $target->fresh()->booked_people);
        $this->assertEquals(0, $source->fresh()->booked_people);
        $this->assertFalse($accept->fresh()->seats_released);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_demo_can_expire_merge_on_cancelled_source_without_touching_other_departures(): void
    {
        config(['demo.enabled' => true]);
        $source = $this->schedule();
        $booking = $this->booking($source);
        $proposal = $this->propose($booking, $this->schedule(11));
        $otherSource = $this->schedule(20);
        $other = $this->booking($otherSource);
        $otherProposal = $this->propose($other, $this->schedule(21));
        $result = app(\App\Services\ScheduleDemoService::class)
            ->moveToMilestone($source->id, 'proposal_expired', $this->admin);
        $this->assertSame('cancelled', $result['status']);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertEquals(500000, $booking->fresh()->refund_amount);
        $this->assertSame(ProposalStatus::Expired, $proposal->fresh()->status);
        $this->assertSame(ProposalStatus::Pending, $otherProposal->fresh()->status);
        $this->assertSame('awaiting_transfer', $other->fresh()->status);
    }

    public function test_accept_at_deadline_becomes_full_refund_without_moving(): void
    {
        $source = $this->schedule();
        $target = $this->schedule(11);
        $booking = $this->booking($source);
        $proposal = $this->propose($booking, $target);
        $this->travelTo($proposal->response_deadline);
        $this->postJson('/api/bookings/'.$booking->public_token.'/proposals/'.$proposal->id.'/respond?email=guest@example.com',
            ['action' => 'accept'])->assertStatus(422);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertEquals(500000, $booking->fresh()->refund_amount);
        $this->assertEquals(0, $target->fresh()->booked_people);
        $this->assertSame(0, \App\Models\BookingTransfer::where('booking_id', $booking->id)->count());
    }

    public function test_waiting_booking_cannot_be_cancelled_with_customer_penalty(): void
    {
        $booking = $this->booking($this->schedule());
        $this->propose($booking, $this->schedule(11));
        $this->expectException(BusinessRuleException::class);
        app(\App\Services\BookingPolicyService::class)->assertCancellable($booking->fresh());
    }

    public function test_invalid_contact_rolls_back_source_cancellation_and_all_proposals(): void
    {
        $source = $this->schedule();
        $first = $this->booking($source);
        $invalid = $this->booking($source);
        $invalid->update(['customer_email' => '']);
        try {
            $this->propose($first, $this->schedule(11));
            $this->fail('Expected the missing contact to block the merge.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('email', $exception->getMessage());
        }
        $this->assertSame(ScheduleStatus::Open, $source->fresh()->status);
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame(0, $first->proposals()->count());
        $this->assertEquals(2, $source->fresh()->booked_people);
    }

    public function test_source_cannot_merge_again_while_waiting_for_responses(): void
    {
        $source = $this->schedule();
        $booking = $this->booking($source);
        $target = $this->schedule(11);
        $this->propose($booking, $target);
        try {
            $this->propose($booking->fresh(), $target);
            $this->fail('A cancelled source must not merge twice.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, $booking->proposals()->count());
            $this->assertSame('awaiting_transfer', $booking->fresh()->status);
        }
    }
}
