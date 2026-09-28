<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingBalanceDeadlineService;
use App\Services\BookingChangeRequestService;
use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TourSchedule $schedule;
    private CancellationPolicyService $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->startOfSecond());
        $this->policy = app(CancellationPolicyService::class);
        $tour = Tour::factory()->create();
        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id, 'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12), 'booking_deadline' => now()->addHours(4),
            'status' => 'open', 'max_people' => 20, 'min_people' => 1, 'booked_people' => 1,
        ]);
    }

    private function booking(float $paid = 5_000_000, float $total = 10_000_000): Booking
    {
        $booking = Booking::create([
            'public_token' => (string) Str::uuid(), 'tour_id' => $this->schedule->tour_id,
            'tour_schedule_id' => $this->schedule->id, 'departure_date' => $this->schedule->start_date,
            'customer_name' => 'Test', 'customer_email' => 'test@example.com',
            'guests' => 1, 'seats' => 1, 'adult_count' => 1, 'child_count' => 0, 'infant_count' => 0,
            'total_amount' => $total, 'status' => 'confirmed', 'confirmed_at' => now(),
            'seats_released' => false,
        ]);
        if ($paid > 0) {
            BookingPayment::create(['booking_id' => $booking->id, 'kind' => 'deposit', 'amount' => $paid, 'paid_at' => now()]);
        }
        return $booking;
    }

    public static function amounts(): array
    {
        return [
            'before-unpaid' => [-1, 0, 0, 0],
            'before-deposit' => [-1, 5_000_000, 0, 5_000_000],
            'before-full' => [-1, 10_000_000, 0, 10_000_000],
            'at-cutoff-deposit' => [0, 5_000_000, 5_000_000, 0],
            'at-cutoff-partial-balance' => [0, 7_000_000, 5_000_000, 2_000_000],
            'at-cutoff-full' => [0, 10_000_000, 5_000_000, 5_000_000],
            'after-cutoff-full' => [1, 10_000_000, 5_000_000, 5_000_000],
            'under-deposit-never-negative' => [1, 2_000_000, 5_000_000, 0],
        ];
    }

    #[DataProvider('amounts')]
    public function test_refund_uses_exact_custom_cutoff(int $seconds, float $paid, float $fee, float $refund): void
    {
        $booking = $this->booking($paid);
        $this->travelTo($this->schedule->booking_deadline->copy()->addSeconds($seconds));
        $quote = $this->policy->quote($booking);
        $this->assertSame($fee, $quote['cancellation_fee']);
        $this->assertSame($refund, $quote['refund_amount']);
        $this->assertSame($seconds < 0, $quote['before_deadline']);
    }

    public function test_default_cutoff_is_used_when_schedule_has_no_override(): void
    {
        config(['booking.booking_deadline_days' => 3]);
        $this->schedule->update(['booking_deadline' => null]);
        $booking = $this->booking(10_000_000);
        $cutoff = $this->schedule->start_date->copy()->subDays(3);
        $this->assertSame(10_000_000.0, $this->policy->quote($booking, now: $cutoff->copy()->subSecond())['refund_amount']);
        $this->assertSame(5_000_000.0, $this->policy->quote($booking, now: $cutoff)['refund_amount']);
    }

    public function test_old_database_tiers_cannot_override_cutoff(): void
    {
        $legacy = CancellationPolicy::create(['name' => 'Old tier policy', 'effective_from' => now()->subDay()]);
        $legacy->rules()->create(['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 10]);
        $booking = $this->booking();
        $booking->update(['cancellation_policy_id' => $legacy->id]);
        $this->assertSame(5_000_000.0, $this->policy->quote($booking)['refund_amount']);
        $this->travelTo($this->schedule->booking_deadline);
        $this->assertSame(0.0, $this->policy->quote($booking)['refund_amount']);
    }

    public function test_company_cancellation_refunds_full_payment_after_cutoff(): void
    {
        $booking = $this->booking(7_000_000);
        $this->travelTo($this->schedule->booking_deadline);
        $quote = $this->policy->quote($booking, congTyHuy: true);
        $this->assertSame(7_000_000.0, $quote['refund_amount']);
        $this->assertSame(0.0, $quote['cancellation_fee']);
    }

    public function test_departure_instant_no_longer_quotes_a_voluntary_refund(): void
    {
        $booking = $this->booking(10_000_000);
        $quote = $this->policy->quote($booking, now: $this->schedule->start_date);
        $this->assertSame(0.0, $quote['refund_amount']);
    }

    public function test_rounds_deposit_to_whole_currency_units(): void
    {
        $booking = $this->booking(3_333_333, 3_333_333);
        $quote = $this->policy->quote($booking, now: $this->schedule->booking_deadline);
        $this->assertSame(1_666_667.0, $quote['cancellation_fee']);
        $this->assertSame(1_666_666.0, $quote['refund_amount']);
    }

    public function test_approval_after_cutoff_preserves_early_request_and_counts_later_payment(): void
    {
        $booking = $this->booking();
        $requests = app(BookingChangeRequestService::class);
        $request = $requests->requestCancellation($booking, 'Customer cancels before deadline.');
        BookingPayment::create(['booking_id' => $booking->id, 'kind' => 'balance', 'amount' => 5_000_000, 'paid_at' => now()]);
        $this->travelTo($this->schedule->booking_deadline->copy()->addSecond());
        $approved = $requests->approve($request, User::factory()->create(['role' => 'admin']));
        $this->assertSame(ChangeRequestStatus::Approved, $approved->status);
        $this->assertEquals(10_000_000, $booking->fresh()->refund_amount);
        $this->assertEquals(10_000_000, $approved->estimated_refund);
    }

    public function test_automatic_cutoff_honors_pending_early_cancellation_once(): void
    {
        $booking = $this->booking();
        $request = app(BookingChangeRequestService::class)->requestCancellation($booking, 'Customer requests refund before cutoff.');
        $this->travelTo($this->schedule->booking_deadline);
        $deadline = app(BookingBalanceDeadlineService::class);
        $this->assertTrue($deadline->cancel($booking));
        $this->assertEquals(5_000_000, $booking->fresh()->refund_amount);
        $this->assertSame('by_customer', $booking->fresh()->cancel_type);
        $this->assertSame(ChangeRequestStatus::Approved, $request->fresh()->status);
        $this->assertFalse($deadline->cancel($booking->fresh()));
        // Xử lý sau hạn chốt giữ chỗ đã cam kết với nhà cung cấp như luồng duyệt hủy.
        $this->assertEquals(1, $this->schedule->fresh()->booked_people);
        $this->assertFalse($booking->fresh()->seats_released);
        $this->assertSame(0, BookingPayment::where('kind', 'refund')->count());
    }

    public function test_withdrawn_request_does_not_prevent_forfeiture(): void
    {
        $booking = $this->booking(7_000_000);
        $request = app(BookingChangeRequestService::class)->requestCancellation($booking, 'Customer changes plans.');
        $request->update(['status' => ChangeRequestStatus::CancelledByCustomer]);
        $this->travelTo($this->schedule->booking_deadline);
        app(BookingBalanceDeadlineService::class)->cancel($booking);
        $this->assertEquals(2_000_000, $booking->fresh()->refund_amount);
        $this->assertSame('unpaid_balance', $booking->fresh()->cancel_type);
    }

    public function test_pending_legacy_request_uses_cutoff_when_previewed_and_approved(): void
    {
        $booking = $this->booking(10_000_000);
        $requests = app(BookingChangeRequestService::class);
        $request = $requests->requestCancellation($booking, 'Early cancellation request.');
        $request->update(['payload' => [], 'estimated_refund_percent' => 10, 'estimated_refund' => 1_000_000]);
        $this->travelTo($this->schedule->booking_deadline->copy()->addSecond());
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/change-requests/'.$request->id)->assertOk()
            ->assertJsonPath('data.request.estimated_refund', '10000000.00')
            ->assertJsonPath('data.request.estimated_refund_percent', 100);
        // Đọc preview không ghi lại DB.
        $this->assertEquals(1_000_000, $request->fresh()->estimated_refund);
        $requests->approve($request, $admin);
        $this->assertEquals(10_000_000, $booking->fresh()->refund_amount);
    }

    public function test_admin_direct_cancellation_honors_early_request_and_closes_it(): void
    {
        $booking = $this->booking(10_000_000);
        $request = app(BookingChangeRequestService::class)->requestCancellation($booking, 'Cancel before deadline.');
        $this->travelTo($this->schedule->booking_deadline->copy()->addSecond());
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->getJson('/api/admin/bookings/'.$booking->id.'/cancel-preview')->assertOk()
            ->assertJsonPath('data.refund_amount', 10_000_000);
        $this->putJson('/api/admin/bookings/'.$booking->id.'/cancel', ['cancel_reason' => 'Customer requested cancellation.'])->assertOk();
        $this->assertEquals(10_000_000, $booking->fresh()->refund_amount);
        $this->assertSame(ChangeRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_pending_late_request_keeps_deposit_fee_even_if_cutoff_is_moved(): void
    {
        $booking = $this->booking(10_000_000);
        $this->travelTo($this->schedule->booking_deadline);
        $requests = app(BookingChangeRequestService::class);
        $request = $requests->requestCancellation($booking, 'Late cancellation request.');
        $this->schedule->update(['booking_deadline' => now()->addDay()]);
        $requests->approve($request, User::factory()->create(['role' => 'admin']));
        $this->assertEquals(5_000_000, $booking->fresh()->refund_amount);
    }

    public function test_demo_clock_is_used_when_recording_request_fee(): void
    {
        config(['demo.enabled' => true]);
        $booking = $this->booking(10_000_000);
        $this->schedule->forceFill([
            'demo_time' => $this->schedule->booking_deadline->copy()->subSecond(),
            'demo_time_set_at' => now(),
        ])->save();
        $request = app(BookingChangeRequestService::class)->requestCancellation($booking->fresh(), 'Cancel before demo cutoff.');
        $this->assertEquals(0, $request->payload['cancellation_fee']);
        $this->assertSame($this->schedule->booking_deadline->copy()->subSecond()->format('Y-m-d H:i:s'), $request->payload['quoted_at']);
        $this->schedule->forceFill(['demo_time' => $this->schedule->booking_deadline])->save();
        $this->assertSame(5_000_000.0, $this->policy->quote($booking->fresh())['refund_amount']);
        app(BookingChangeRequestService::class)->approve($request, User::factory()->create(['role' => 'admin']));
        $this->assertEquals(10_000_000, $booking->fresh()->refund_amount);
    }
}
