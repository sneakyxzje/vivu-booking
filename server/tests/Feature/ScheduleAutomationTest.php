<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A13 - Acceptance tests cho block A schedule lifecycle.
 */
class ScheduleAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_khong_dat_duoc_chuyen_da_dong_ban(): void
    {
        $tour = Tour::factory()->create(['status' => 'active']);
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Closed->value,
            'start_date' => now()->addDays(10),
            'booking_deadline' => now()->addDays(7),
            'max_people' => 10,
            'booked_people' => 0,
        ]);

        $response = $this->postJson('/api/bookings', [
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach-test@example.com',
            'adult_count' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tour_schedule_id']);
    }

    public function test_lenh_dong_ban_chuyen_qua_han(): void
    {
        $schedule = TourSchedule::factory()->create([
            'status' => ScheduleStatus::Open->value,
            'booking_deadline' => now()->subMinute(),
            'booked_people' => 0,
            'max_people' => 10,
        ]);

        $this->artisan('schedules:close-expired')->assertSuccessful();

        $this->assertDatabaseHas('tour_schedules', [
            'id' => $schedule->id,
            'status' => ScheduleStatus::Closed->value,
        ]);
    }

    public function test_lenh_chot_chuyen_du_khach_va_gui_mail(): void
    {
        Mail::fake();

        $tour = Tour::factory()->create();
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Closed->value,
            'start_date' => now()->addDays(10),
            'min_people' => 2,
            'booked_people' => 2,
            'max_people' => 10,
        ]);

        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Confirmed',
            'customer_email' => 'confirmed@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 2_000_000,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->artisan('schedules:confirm-ready')->assertSuccessful();

        $this->assertDatabaseHas('tour_schedules', [
            'id' => $schedule->id,
            'status' => ScheduleStatus::Confirmed->value,
        ]);
        $this->assertNotNull($schedule->fresh()->confirmed_at);

        Mail::assertSent(
            BookingConfirmedMail::class,
            fn (BookingConfirmedMail $mail) => $mail->hasTo($booking->customer_email),
        );
    }

    public function test_lenh_chuyen_trang_thai_theo_thoi_gian(): void
    {
        $confirmed = TourSchedule::factory()->create([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
        ]);

        $running = TourSchedule::factory()->create([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDay(),
        ]);

        $this->artisan('schedules:advance-status')->assertSuccessful();

        $this->assertSame(ScheduleStatus::InProgress->value, $confirmed->fresh()->status);
        $this->assertSame(ScheduleStatus::Completed->value, $running->fresh()->status);
    }

    public function test_chuyen_trang_thai_sai_bi_tu_choi(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $token = $admin->createToken('test')->plainTextToken;
        $schedule = TourSchedule::factory()->create([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(10),
        ]);

        $this->patchJson("/api/admin/schedules/{$schedule->id}/status", [
            'status' => ScheduleStatus::Open->value,
        ], ['Authorization' => 'Bearer ' . $token])->assertStatus(422);
    }
}