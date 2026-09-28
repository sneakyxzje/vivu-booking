<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\DemoClock;
use App\Services\VNPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduleDemoClockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->travelTo(now()->startOfDay()->addHours(9));
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function schedule(): TourSchedule
    {
        return TourSchedule::factory()->create([
            'tour_id' => Tour::factory()->create(['status' => 'active', 'number_of_days' => 2, 'adult_price' => 1000000])->id,
            'start_date' => now()->addDays(30), 'end_date' => now()->addDays(31)->setTime(18, 0),
            'booking_deadline' => now()->addDays(27),
            'status' => 'open', 'max_people' => 10, 'min_people' => 1, 'booked_people' => 0,
        ]);
    }

    private function enable(TourSchedule $schedule): void
    {
        $this->actingAs($this->admin)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/enable")
            ->assertOk()->assertJsonPath('data.active', true);
    }

    private function advance(TourSchedule $schedule, string $milestone)
    {
        return $this->actingAs($this->admin)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/advance", compact('milestone'));
    }

    private function deposit(TourSchedule $schedule): Booking
    {
        $booking = Booking::factory()->choChuyen($schedule)->create(['total_amount' => 1000000, 'status' => 'confirmed', 'expires_at' => null]);
        $schedule->increment('booked_people');
        app(BookingPaymentService::class)->record($booking, 'deposit', 500000, 'bank_transfer');
        return $booking;
    }

    public function test_buttons_move_status_directly_without_separate_activation(): void
    {
        $schedule = $this->schedule();
        $other = $this->schedule();
        $booking = $this->deposit($schedule);
        app(BookingPaymentService::class)->record($booking, 'balance', 500000, 'bank_transfer');
        $realTime = now()->toDateTimeString();
        foreach (['confirmed', 'in_progress', 'completed'] as $status) {
            $this->actingAs($this->admin)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/status", compact('status'))
                ->assertOk()->assertJsonPath('data.status', $status);
        }
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame('open', $other->fresh()->status->value);
        $this->assertNull($other->fresh()->demo_time);
        $this->assertSame($realTime, now()->toDateTimeString());
    }

    public function test_status_buttons_keep_business_guards_and_report_insufficient_guests(): void
    {
        $schedule = $this->schedule();
        $url = "/api/admin/tour-schedules/{$schedule->id}/demo/status";
        $this->actingAs($this->admin)->postJson($url, ['status' => 'in_progress'])->assertStatus(422);
        $this->postJson($url, ['status' => 'completed'])->assertStatus(422);
        $this->assertNull($schedule->fresh()->demo_time);
        $this->postJson($url, ['status' => 'confirmed'])->assertOk()->assertJsonPath('data.status', 'open');
        $this->postJson($url, ['status' => 'in_progress'])->assertStatus(422);
        $this->postJson($url, ['status' => 'open'])->assertStatus(422);
        $this->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/milestone", ['milestone' => 'completion'])->assertStatus(422);
        config(['demo.enabled' => false]);
        $this->postJson($url, ['status' => 'confirmed'])->assertNotFound();
    }

    public function test_time_button_activates_clock_and_expires_hold_without_extra_setup(): void
    {
        $schedule = $this->schedule();
        $hold = Booking::factory()->choChuyen($schedule)->create();
        $schedule->increment('booked_people');
        $this->actingAs($this->admin)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/milestone", ['milestone' => 'hold_expired'])
            ->assertOk();
        $this->assertSame('cancelled', $hold->fresh()->status);
        $this->assertSame(0, $schedule->fresh()->booked_people);
    }

    public function test_real_booking_deposit_balance_attendance_and_completion_on_one_day(): void
    {
        $schedule = $this->schedule();
        $this->enable($schedule);
        $realTime = now()->toDateTimeString();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        Cache::put('booking_verified_' . $customer->email, true, now()->addMinutes(15));
        $this->actingAs($customer)->postJson('/api/bookings', [
            'tour_id' => $schedule->tour_id, 'tour_schedule_id' => $schedule->id,
            'customer_name' => $customer->name, 'customer_email' => $customer->email,
            'adult_count' => 1, 'accept_terms' => true,
            'passengers' => [['name' => 'Demo Guest', 'type' => 'adult']],
        ])->assertCreated();
        Cache::put('demo_otp_clock_probe', true, now()->addMinutes(15));
        $booking = Booking::where('tour_schedule_id', $schedule->id)->sole();
        $this->actingAs($this->admin)->putJson("/api/admin/bookings/{$booking->id}/confirm", [
            'amount' => 500000, 'method' => 'bank_transfer',
        ])->assertOk();
        $this->assertSame(500000.0, app(BookingPaymentService::class)->balanceDue($booking->fresh()));
        $this->advance($schedule, 'reminder')->assertOk();
        $this->assertNotNull($booking->fresh()->balance_reminder_sent_at);
        $this->postJson("/api/admin/bookings/{$booking->id}/payments", [
            'kind' => 'balance', 'amount' => 500000, 'method' => 'bank_transfer',
        ])->assertSuccessful();
        $this->advance($schedule, 'booking_deadline')->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertFalse($schedule->fresh()->isBookable());
        $this->assertFalse(TourSchedule::whereKey($schedule->id)->bookable()->exists());

        $guide = User::factory()->create(['role' => 'guide', 'status' => 'active']);
        $schedule->guides()->sync([$guide->id]);
        $itinerary = TourItinerary::create(['tour_id' => $schedule->tour_id, 'day_number' => 1, 'title' => 'Day one', 'content' => 'Trip']);
        $checkpoint = $itinerary->checkpoints()->create(['name' => 'Pickup', 'sequence' => 1]);
        $passenger = $booking->passengers()->sole();
        $url = "/api/guide/schedules/{$schedule->id}/checkpoints/{$checkpoint->id}/attendance";
        $payload = ['checkins' => [['booking_passenger_id' => $passenger->id, 'status' => 'present']]];
        $this->actingAs($guide)->putJson($url, $payload)->assertStatus(422);
        $this->advance($schedule, 'departure')->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->actingAs($guide)->putJson($url, $payload)->assertOk();
        $this->advance($schedule, 'day_2')->assertOk();
        $this->advance($schedule, 'completion')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame($realTime, now()->toDateTimeString());
        $this->assertTrue(Cache::get('demo_otp_clock_probe'));
        $this->advance($schedule, 'process')->assertOk();
        $this->assertSame(2, $booking->payments()->count());
    }

    public function test_overdue_deposit_cancellation_is_isolated_and_does_not_touch_paid_booking(): void
    {
        $schedule = $this->schedule();
        $other = $this->schedule();
        $this->enable($schedule);
        $unpaid = $this->deposit($schedule);
        $paid = $this->deposit($schedule);
        app(BookingPaymentService::class)->record($paid, 'balance', 500000, 'bank_transfer');
        $untouched = $this->deposit($other);
        $expiredElsewhere = Booking::factory()->choChuyen($other)->create(['expires_at' => now()->subMinute()]);
        $this->advance($schedule, 'final_notice')->assertOk();
        $this->assertNotNull($unpaid->fresh()->balance_final_notice_at);
        $this->advance($schedule, 'booking_deadline')->assertOk();
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertSame('confirmed', $paid->fresh()->status);
        $this->assertSame('confirmed', $untouched->fresh()->status);
        $this->assertSame('pending', $expiredElsewhere->fresh()->status);
        $this->assertNull($untouched->fresh()->balance_final_notice_at);
        $this->assertNull($other->fresh()->demo_time);
        $this->assertSame(1, $schedule->fresh()->booked_people);
        $this->assertNull(app(DemoClock::class)->commandSchedule);
    }

    public function test_hold_expiry_and_regular_scheduler_use_each_departures_clock(): void
    {
        $schedule = $this->schedule();
        $this->enable($schedule);
        $hold = Booking::factory()->choChuyen($schedule)->create();
        $schedule->increment('booked_people');
        $this->advance($schedule, 'hold_expired')->assertOk();
        $this->assertSame('cancelled', $hold->fresh()->status);
        $this->assertSame(0, $schedule->fresh()->booked_people);

        $this->advance($schedule, 'reminder')->assertOk();
        $expires = DemoClock::schedule($schedule->fresh())->addMinutes(10);
        $newHold = Booking::factory()->choChuyen($schedule)->create(['expires_at' => $expires]);
        $schedule->increment('booked_people');
        $this->travel(11)->minutes();
        $this->artisan('bookings:release-expired')->assertSuccessful();
        $this->assertSame('cancelled', $newHold->fresh()->status);
    }

    public function test_permissions_environment_opt_in_and_forward_only_guards(): void
    {
        $schedule = $this->schedule();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $this->actingAs($customer)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/enable")->assertForbidden();
        $this->advance($schedule, 'booking_deadline')->assertStatus(422);
        config(['demo.enabled' => false]);
        $this->actingAs($this->admin)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/enable")->assertNotFound();
        config(['demo.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->actingAs($this->admin)->postJson("/api/admin/tour-schedules/{$schedule->id}/demo/enable")->assertNotFound();
        $this->app->instance('env', 'testing');
        $this->enable($schedule);
        $this->advance($schedule, 'departure')->assertStatus(422);
        $this->advance($schedule, 'completion')->assertStatus(422);
        $this->advance($schedule, 'unknown')->assertStatus(422);
        $this->advance($schedule, 'booking_deadline')->assertOk()->assertJsonPath('data.status', 'open');
        $this->advance($schedule, 'reminder')->assertStatus(422);
        $this->advance($schedule, 'departure')->assertStatus(422);
    }

    public function test_gateway_dates_stay_real_and_sql_deadlines_match_model(): void
    {
        $schedule = $this->schedule();
        $this->enable($schedule);
        $this->advance($schedule, 'reminder')->assertOk();
        $booking = Booking::factory()->choChuyen($schedule)->create(['expires_at' => DemoClock::schedule($schedule->fresh())->addMinutes(10)]);
        parse_str(parse_url(app(VNPayService::class)->createPayment($booking), PHP_URL_QUERY), $query);
        $this->assertSame(now()->timezone('Asia/Ho_Chi_Minh')->format('YmdHis'), $query['vnp_CreateDate']);
        $this->assertSame(now()->addMinutes(10)->timezone('Asia/Ho_Chi_Minh')->format('YmdHis'), $query['vnp_ExpireDate']);
        config(['services.vnpay.hash_secret' => 'demo-clock-test-secret']);
        $callback = [
            'vnp_Amount' => 100000000, 'vnp_BankCode' => 'NCB', 'vnp_ResponseCode' => '00',
            'vnp_TransactionNo' => 'DEMO-CLOCK-ONE', 'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => app(VNPayService::class)->txnRef($booking),
        ];
        ksort($callback);
        $hashData = collect($callback)->map(fn ($value, $key) => urlencode($key) . '=' . urlencode($value))->implode('&');
        $callback['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'demo-clock-test-secret');
        $this->getJson('/api/vnpay/ipn?' . http_build_query($callback))->assertOk()->assertJsonPath('RspCode', '00');
        $this->getJson('/api/vnpay/ipn?' . http_build_query($callback))->assertOk()->assertJsonPath('RspCode', '02');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, $booking->payments()->count());
        $this->assertSame(1000000.0, app(BookingPaymentService::class)->netPaid($booking->fresh()));
        $schedule->forceFill(['demo_time' => $schedule->booking_deadline, 'demo_time_set_at' => now()])->save();
        $this->assertFalse($schedule->isBookable());
        $this->assertFalse(TourSchedule::whereKey($schedule->id)->bookable()->exists());
        $this->assertTrue(TourSchedule::whereKey($this->schedule()->id)->bookable()->exists());
    }

    public function test_absent_guest_finishes_as_no_show_and_future_day_attendance_is_rejected(): void
    {
        $schedule = $this->schedule();
        $this->enable($schedule);
        $booking = $this->deposit($schedule);
        app(BookingPaymentService::class)->record($booking, 'balance', 500000, 'bank_transfer');
        $passenger = $booking->passengers()->create(['name' => 'Absent guest', 'type' => 'adult']);
        $guide = User::factory()->create(['role' => 'guide', 'status' => 'active']);
        $schedule->guides()->sync([$guide->id]);
        $itinerary = TourItinerary::create(['tour_id' => $schedule->tour_id, 'day_number' => 1, 'title' => 'Day one', 'content' => 'Trip']);
        $checkpoint = $itinerary->checkpoints()->create(['name' => 'Pickup', 'sequence' => 1]);
        $next = TourItinerary::create(['tour_id' => $schedule->tour_id, 'day_number' => 2, 'title' => 'Day two', 'content' => 'Trip']);
        $later = $next->checkpoints()->create(['name' => 'Return', 'sequence' => 1]);
        $this->advance($schedule, 'booking_deadline')->assertOk();
        $this->advance($schedule, 'departure')->assertOk();
        $payload = ['checkins' => [['booking_passenger_id' => $passenger->id, 'status' => 'absent', 'note' => 'Guest did not arrive at pickup']]];
        $this->actingAs($guide)->putJson("/api/guide/schedules/{$schedule->id}/checkpoints/{$later->id}/attendance", $payload)->assertStatus(422);
        $this->putJson("/api/guide/schedules/{$schedule->id}/checkpoints/{$checkpoint->id}/attendance", $payload)->assertOk();
        $this->advance($schedule, 'completion')->assertOk();
        $this->assertSame('no_show', $booking->fresh()->status);
    }
}
