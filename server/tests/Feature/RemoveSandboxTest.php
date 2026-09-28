<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RemoveSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_removes_only_flagged_tours_and_their_related_data(): void
    {
        $migration = require database_path('migrations/2026_09_20_000001_remove_sandbox_tours.php');
        $migration->down();

        $regular = Booking::factory()->create();
        $trial = Tour::factory()->create();
        $deletedTrial = Tour::factory()->create();
        $deletedTrial->delete();
        DB::table('tours')->whereIn('id', [$trial->id, $deletedTrial->id])->update(['is_sandbox' => true]);
        $schedule = TourSchedule::factory()->create(['tour_id' => $trial->id]);
        $booking = Booking::factory()->choChuyen($schedule)->create();
        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'kind' => 'deposit',
            'amount' => 100000,
            'method' => 'gateway',
            'paid_at' => now(),
        ]);
        $auditId = DB::table('schedule_audit_logs')->insertGetId([
            'tour_schedule_id' => $schedule->id,
            'action' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $chunkId = DB::table('ai_knowledge_chunks')->insertGetId([
            'chunk_key' => "tour:{$trial->id}:tong-quan",
            'source_type' => 'tour',
            'source_id' => $trial->id,
            'title' => $trial->title,
            'content' => 'Trial data',
            'content_hash' => hash('sha256', 'Trial data'),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('tours', 'is_sandbox'));
        $this->assertDatabaseMissing('tours', ['id' => $trial->id]);
        $this->assertDatabaseMissing('tours', ['id' => $deletedTrial->id]);
        $this->assertDatabaseMissing('tour_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('booking_payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('schedule_audit_logs', ['id' => $auditId]);
        $this->assertDatabaseMissing('ai_knowledge_chunks', ['id' => $chunkId]);
        $this->assertDatabaseHas('bookings', ['id' => $regular->id]);
        $this->assertDatabaseHas('tours', ['id' => $regular->tour_id]);
        $this->assertDatabaseHas('tour_schedules', ['id' => $regular->tour_schedule_id]);
    }

    public function test_no_sandbox_api_routes_are_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('/sandbox', $route->uri());
        }
    }
}
