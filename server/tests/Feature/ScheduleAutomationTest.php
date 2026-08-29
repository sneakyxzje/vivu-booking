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
            // Chỉ chuyến đã tới hoặc sắp tới hạn chốt danh sách mới được xét.
            'booking_deadline' => now()->addHours(2),
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

        Mail::assertQueued(
            BookingConfirmedMail::class,
            fn (BookingConfirmedMail $mail) => $mail->hasTo($booking->customer_email),
        );
    }

    /**
     * Chặn tái phát lỗi làm gãy luồng đặt tour.
     *
     * Trước đây lệnh chốt mọi chuyến có booked_people >= min_people, bất kể còn cách hạn chốt
     * bao lâu. Vì min_people mặc định là 1 nên gần như mọi chuyến vừa có khách là bị chốt ngay,
     * mà chuyến đã chốt thì không nhận đặt mới. Kết quả là chuyến còn 19 trên 22 chỗ trống vẫn
     * ngừng bán chỉ sau một lần chạy lệnh.
     */
    public function test_khong_chot_chuyen_con_xa_han_chot_danh_sach(): void
    {
        Mail::fake();

        $tour = Tour::factory()->create();
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(30),
            'booking_deadline' => now()->addDays(27),
            'min_people' => 1,
            'booked_people' => 3,
            'max_people' => 22,
        ]);

        $this->taoDonDaThanhToan($tour, $schedule, guests: 3);

        $this->artisan('schedules:confirm-ready')->assertSuccessful();

        $this->assertSame(ScheduleStatus::Open, $schedule->fresh()->status);
        $this->assertTrue($schedule->fresh()->isBookable(), 'Chuyến còn chỗ phải tiếp tục bán được.');
    }

    /**
     * Chặn tái phát: chốt chuyến phải đếm khách đã trả tiền, không đếm booked_people.
     * booked_people gồm cả đơn pending đang giữ chỗ tạm và sẽ tự hủy sau mười phút.
     */
    public function test_khong_chot_chuyen_khi_khach_moi_giu_cho_chua_thanh_toan(): void
    {
        Mail::fake();

        $tour = Tour::factory()->create();
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Closed->value,
            'start_date' => now()->addDays(4),
            'booking_deadline' => now()->addHours(2),
            'min_people' => 4,
            'booked_people' => 4,
            'max_people' => 10,
        ]);

        // Bốn chỗ đang bị chiếm nhưng là đơn chưa thanh toán.
        $this->taoDonDaThanhToan($tour, $schedule, guests: 4, status: 'pending');

        $this->artisan('schedules:confirm-ready')->assertSuccessful();

        $this->assertSame(ScheduleStatus::Closed, $schedule->fresh()->status);
        Mail::assertNothingQueued();
    }

    private function taoDonDaThanhToan(
        Tour $tour,
        TourSchedule $schedule,
        int $guests,
        string $status = 'confirmed',
    ): Booking {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $guests,
            'adult_count' => $guests,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $guests * 1_000_000,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'expires_at' => $status === 'pending' ? now()->addMinutes(10) : null,
        ]);
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

        $this->assertSame(ScheduleStatus::InProgress, $confirmed->fresh()->status);
        $this->assertSame(ScheduleStatus::Completed, $running->fresh()->status);
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