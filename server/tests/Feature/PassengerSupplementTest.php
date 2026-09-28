<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\BookingPassengerSupplement;
use App\Models\PassengerCheckin;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassengerSupplementTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private TourSchedule $schedule;
    private User $admin;
    private User $guide;
    private User $customer;
    private array $points;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->startOfDay()->addHours(12));
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->guide = User::factory()->create(['role' => 'guide', 'status' => 'active']);
        $this->customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 3]);
        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id, 'status' => 'in_progress', 'start_date' => now()->subDay()->startOfDay(),
            'end_date' => now()->addDay()->endOfDay(), 'booking_deadline' => now()->subDays(4),
            'max_people' => 20, 'min_people' => 1, 'booked_people' => 10,
        ]);
        $this->schedule->guides()->sync([$this->guide->id]);
        $this->booking = Booking::create([
            'public_token' => (string) Str::uuid(), 'customer_id' => $this->customer->id,
            'tour_id' => $tour->id, 'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Nguoi dat', 'customer_email' => 'contact@example.com',
            'departure_date' => $this->schedule->start_date, 'guests' => 10, 'seats' => 10,
            'adult_count' => 10, 'child_count' => 0, 'infant_count' => 0,
            'status' => 'confirmed', 'total_amount' => 10000000, 'paid_at' => now()->subDays(5),
        ]);
        $this->booking->payments()->create(['kind' => 'full', 'amount' => 10000000, 'method' => 'cash', 'paid_at' => now()->subDays(5)]);
        for ($i = 1; $i <= 9; $i++) {
            $this->booking->passengers()->create(['name' => "Existing {$i}", 'type' => 'adult',
                'identity_number' => sprintf('001234567%03d', $i), 'is_contact' => $i === 1]);
        }
        foreach ([1, 2, 3] as $day) {
            $itinerary = $tour->itineraries()->create(['day_number' => $day, 'title' => "Day {$day}", 'content' => 'Visit']);
            $this->points[$day] = $itinerary->checkpoints()->create(['name' => "Stop {$day}", 'sequence' => 1, 'is_required_photo' => false]);
        }
        $this->url = '/api/admin/bookings/'.$this->booking->id.'/passenger-supplements';
        Sanctum::actingAs($this->admin);
    }

    private function payload(array $passenger = []): array
    {
        return ['request_key' => (string) Str::uuid(), 'reported_by' => 'HDV Nguyen Van A',
            'reason' => 'Khach bo sung thong tin tai diem don',
            'passengers' => [array_merge(['name' => 'New Guest', 'type' => 'adult', 'identity_number' => '001234567010'], $passenger)]];
    }

    public function test_append_preserves_existing_passenger_ids_checkins_history_and_seats(): void
    {
        $oldIds = $this->booking->passengers()->pluck('id')->all();
        $checkin = PassengerCheckin::create(['booking_passenger_id' => $oldIds[0], 'tour_schedule_id' => $this->schedule->id,
            'itinerary_checkpoint_id' => $this->points[1]->id, 'status' => 'present', 'checked_by' => $this->guide->id, 'checked_at' => now()->subDay()]);
        $history = $checkin->histories()->create(['old_status' => 'late', 'new_status' => 'present', 'changed_by' => $this->guide->id, 'changed_at' => now()->subDay()]);
        $this->getJson($this->url)->assertOk()->assertJsonPath('data.can_supplement', true)->assertJsonPath('data.missing', 1);
        $result = $this->postJson($this->url, $this->payload())->assertCreated()
            ->assertJsonPath('data.created_by', $this->admin->id)->assertJsonPath('data.sent_at', null);
        $newId = $result->json('data.passengers.0.id');
        $this->assertSame($oldIds, $this->booking->passengers()->where('id', '!=', $newId)->pluck('id')->all());
        $this->assertDatabaseHas('passenger_checkins', ['id' => $checkin->id, 'status' => 'present']);
        $this->assertDatabaseHas('passenger_checkin_histories', ['id' => $history->id]);
        $this->assertDatabaseMissing('passenger_checkins', ['booking_passenger_id' => $newId]);
        $this->assertSame(10, $this->booking->passengers()->count());
        $this->assertSame(10, (int) $this->schedule->fresh()->booked_people);
        $this->assertSame(10000000, (int) $this->booking->fresh()->total_amount);
        $this->assertDatabaseHas('booking_audit_logs', ['booking_id' => $this->booking->id, 'action' => 'passengers_supplemented', 'actor_id' => $this->admin->id]);
        $this->getJson($this->url)->assertOk()->assertJsonPath('data.missing', 0)->assertJsonPath('data.can_supplement', false);
    }

    public function test_identical_retry_is_safe_but_changed_payload_is_rejected(): void
    {
        $payload = $this->payload();
        $first = $this->postJson($this->url, $payload)->assertCreated();
        $retry = $this->postJson($this->url, $payload)->assertCreated();
        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $payload['passengers'][0]['name'] = 'Different Guest';
        $this->postJson($this->url, $payload)->assertStatus(409);
        $this->assertDatabaseCount('booking_passenger_supplements', 1);
        $this->assertSame(10, $this->booking->passengers()->count());
    }

    public function test_excess_people_or_wrong_purchased_type_are_rejected_without_partial_writes(): void
    {
        $payload = $this->payload();
        $payload['passengers'][] = ['name' => 'Excess', 'type' => 'adult'];
        $this->postJson($this->url, $payload)->assertUnprocessable();
        $this->postJson($this->url, $this->payload(['type' => 'child']))->assertUnprocessable();
        $this->assertSame(9, $this->booking->passengers()->count());
        $this->assertDatabaseCount('booking_passenger_supplements', 0);
    }

    public function test_duplicate_identity_invalid_age_and_second_contact_are_rejected(): void
    {
        foreach ([['identity_number' => '001234567001'], ['date_of_birth' => now()->subYear()->toDateString()], ['is_contact' => true]] as $invalid) {
            $this->postJson($this->url, $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertSame(9, $this->booking->passengers()->count());
    }

    public function test_requires_reporter_reason_and_valid_fields_and_rejects_existing_ids(): void
    {
        foreach ([['reported_by' => ' '], ['reason' => ' '], ['request_key' => 'invalid']] as $invalid) {
            $this->postJson($this->url, array_merge($this->payload(), $invalid))->assertUnprocessable();
        }
        foreach ([['name' => ' '], ['id' => $this->booking->passengers()->first()->id], ['booking_id' => 999],
            ['date_of_birth' => now()->addDay()->toDateString()], ['phone' => 'abc']] as $invalid) {
            $this->postJson($this->url, $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('booking_passenger_supplements', 0);
    }

    public function test_customer_and_guide_cannot_use_admin_supplement_endpoints(): void
    {
        foreach ([$this->customer, $this->guide] as $actor) {
            Sanctum::actingAs($actor);
            $this->getJson($this->url)->assertForbidden();
            $this->postJson($this->url, $this->payload())->assertForbidden();
            $this->postJson($this->url.'/1/sent', ['sent_to' => 'Hotel'])->assertForbidden();
            $this->getJson($this->url.'/1/export')->assertForbidden();
        }
    }

    public function test_cancelled_completed_transferred_and_unpaid_bookings_cannot_add_people(): void
    {
        foreach (['cancelled', 'completed', 'transferred', 'pending', 'no_show'] as $status) {
            $this->booking->update(['status' => $status]);
            $this->postJson($this->url, $this->payload())->assertUnprocessable();
        }
        $this->booking->update(['status' => 'confirmed', 'paid_at' => null]);
        $this->booking->payments()->delete();
        $this->postJson($this->url, $this->payload())->assertUnprocessable();
        $this->assertSame(9, $this->booking->passengers()->count());
    }

    public function test_cancelled_and_effectively_completed_schedules_are_locked(): void
    {
        $this->schedule->update(['status' => 'cancelled']);
        $this->postJson($this->url, $this->payload())->assertUnprocessable();
        $this->schedule->update(['status' => 'in_progress', 'end_date' => now()->subSecond()]);
        $this->postJson($this->url, $this->payload())->assertUnprocessable();
        $this->assertSame(9, $this->booking->passengers()->count());
    }

    public function test_replacement_stays_locked_during_trip_for_admin_and_customer(): void
    {
        $this->putJson('/api/admin/bookings/'.$this->booking->id.'/passengers', ['passengers' => $this->payload()['passengers']])->assertUnprocessable();
        Sanctum::actingAs($this->customer);
        $this->putJson('/api/my-bookings/'.$this->booking->id.'/passengers', ['passengers' => $this->payload()['passengers']])->assertUnprocessable();
        $this->assertSame(9, $this->booking->passengers()->count());
    }

    public function test_guide_sees_new_guest_and_can_only_record_today_without_backfilling(): void
    {
        $id = $this->postJson($this->url, $this->payload())->assertCreated()->json('data.passengers.0.id');
        Sanctum::actingAs($this->guide);
        $this->getJson('/api/guide/schedules/'.$this->schedule->id.'/attendance')->assertOk()->assertJsonCount(10, 'data.bookings.0.passengers');
        foreach ([1, 3] as $day) {
            $this->putJson('/api/guide/schedules/'.$this->schedule->id.'/checkpoints/'.$this->points[$day]->id.'/attendance', [
                'checkins' => [['booking_passenger_id' => $id, 'status' => 'present']],
            ])->assertUnprocessable();
        }
        $this->putJson('/api/guide/schedules/'.$this->schedule->id.'/checkpoints/'.$this->points[2]->id.'/attendance', [
            'checkins' => [['booking_passenger_id' => $id, 'status' => 'present']],
        ])->assertOk();
        $this->assertSame(1, PassengerCheckin::where('booking_passenger_id', $id)->count());
    }

    public function test_export_is_a_snapshot_and_does_not_mark_it_as_sent(): void
    {
        $id = $this->postJson($this->url, $this->payload(['name' => '=DANGEROUS()']))->assertCreated()->json('data.id');
        $file = $this->get($this->url.'/'.$id.'/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=DANGEROUS()", $file);
        $this->assertStringNotContainsString('Existing 1', $file);
        $this->assertNull(BookingPassengerSupplement::findOrFail($id)->sent_at);
    }

    public function test_sent_confirmation_is_scoped_audited_and_cannot_be_overwritten_by_retry(): void
    {
        $id = $this->postJson($this->url, $this->payload())->assertCreated()->json('data.id');
        $this->postJson($this->url.'/'.$id.'/sent', ['sent_to' => ' '])->assertUnprocessable();
        $this->postJson('/api/admin/bookings/999999/passenger-supplements/'.$id.'/sent', ['sent_to' => 'Hotel'])->assertNotFound();
        $this->postJson($this->url.'/'.$id.'/sent', ['sent_to' => 'Hotel A; Bus B', 'sent_note' => 'Sent by email'])
            ->assertOk()->assertJsonPath('data.sent_by', $this->admin->id);
        $this->postJson($this->url.'/'.$id.'/sent', ['sent_to' => 'Different recipient'])->assertOk()->assertJsonPath('data.sent_to', 'Hotel A; Bus B');
        $this->assertSame(1, \App\Models\BookingAuditLog::where('booking_id', $this->booking->id)->where('action', 'passenger_supplement_sent')->count());
    }

    public function test_after_deadline_before_departure_can_also_supplement_without_replacing(): void
    {
        $this->schedule->update(['status' => 'confirmed', 'start_date' => now()->addDay(), 'end_date' => now()->addDays(3)]);
        $this->postJson($this->url, $this->payload())->assertCreated();
        $this->assertSame(10, $this->booking->passengers()->count());
    }
}
