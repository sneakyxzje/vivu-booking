<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveClosedScheduleStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_availability_constraints_and_is_idempotent(): void
    {
        $expired = TourSchedule::factory()->create(['booking_deadline' => now()->subMinute(), 'booked_people' => 0]);
        $full = TourSchedule::factory()->create(['booking_deadline' => now()->addDay(), 'booked_people' => 10, 'max_people' => 10]);
        $available = TourSchedule::factory()->create(['booking_deadline' => now()->addDay(), 'booked_people' => 0]);
        DB::table('tour_schedules')->whereIn('id', [$expired->id, $full->id, $available->id])->update(['status' => 'closed']);
        $migration = require database_path('migrations/2026_09_21_000001_remove_closed_schedule_status.php');
        $migration->up();
        $migration->up();

        $this->assertSame(ScheduleStatus::Open, $expired->fresh()->status);
        $this->assertSame(ScheduleStatus::Open, $full->fresh()->status);
        $this->assertFalse($expired->fresh()->isBookable());
        $this->assertFalse($full->fresh()->isBookable());
        $this->assertTrue($available->fresh()->isBookable());
        $this->assertSame(10, $full->fresh()->booked_people);
        $this->assertTrue($expired->booking_deadline->equalTo($expired->fresh()->booking_deadline));
    }

    public function test_admin_cannot_restore_removed_status(): void
    {
        $schedule = TourSchedule::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']), 'sanctum')
            ->patchJson('/api/admin/schedules/' . $schedule->id . '/status', ['status' => 'closed'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame(ScheduleStatus::Open, $schedule->fresh()->status);
    }
}
