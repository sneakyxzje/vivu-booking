<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleDisplayConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public static function clocks(): array
    {
        return ['real time' => [false], 'departure demo time' => [true]];
    }

    #[DataProvider('clocks')]
    public function test_admin_and_guide_agree_when_stored_status_has_not_caught_up(bool $demo): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        config(['demo.enabled' => $demo]);
        $businessNow = $demo ? now()->addDays(30) : now();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $guide = User::factory()->create(['role' => 'guide', 'status' => 'active']);
        $tour = Tour::factory()->create(['admin_id' => $admin->id, 'status' => 'active', 'number_of_days' => 2]);
        $expected = [];
        $stored = [];
        foreach ([
            ['open', 'in_progress', -1, 24],
            ['confirmed', 'completed', -48, -1],
            ['cancelled', 'cancelled', -1, 24],
            ['confirmed', 'confirmed', 24, 48],
        ] as [$status, $effective, $startHours, $endHours]) {
            $schedule = TourSchedule::factory()->create([
                'tour_id' => $tour->id, 'status' => $status,
                'start_date' => $businessNow->copy()->addHours($startHours),
                'end_date' => $businessNow->copy()->addHours($endHours),
            ]);
            if ($demo) {
                $schedule->forceFill(['demo_time' => $businessNow, 'demo_time_set_at' => now()])->save();
            }
            $schedule->guides()->sync([$guide->id]);
            $expected[$schedule->id] = $effective;
            $stored[$schedule->id] = $status;
        }

        Sanctum::actingAs($admin);
        $adminList = collect($this->getJson('/api/admin/tours')->assertOk()->json('data.0.schedules'))->keyBy('id');
        $adminDetail = collect($this->getJson("/api/admin/tours/{$tour->id}")->assertOk()->json('data.schedules'))->keyBy('id');
        Sanctum::actingAs($guide);
        $guideList = collect($this->getJson('/api/guide/my-tours')->assertOk()->json('data.0.schedules'))->keyBy('id');
        $assignments = collect($this->getJson('/api/guide/assignments')->assertOk()->json('data'))->keyBy('schedule_id');

        foreach ($expected as $id => $status) {
            $this->assertSame($status, $adminList[$id]['effective_status']);
            $this->assertSame($status, $adminDetail[$id]['effective_status']);
            $this->assertSame($status, $guideList[$id]['effective_status']);
            $this->assertSame($status, $assignments[$id]['status']);
            $this->assertSame($businessNow->toIso8601String(), $adminList[$id]['server_now']);
            $this->getJson("/api/guide/schedules/{$id}/attendance")->assertOk()
                ->assertJsonPath('data.schedule.status', $status)
                ->assertJsonPath('data.schedule.can_record', $status === 'in_progress');
            $this->assertSame($stored[$id], TourSchedule::findOrFail($id)->status->value);
        }
    }
}
