<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCheckin;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuideAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $guide;
    private TourSchedule $schedule;
    private TourItinerary $itinerary;
    private Booking $booking;

    private function taoUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'email' => $role . '-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function dungChuyenDi(): void
    {
        $admin = $this->taoUser('admin');
        $this->guide = $this->taoUser('guide');

        $tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Diem Danh',
            'slug' => 'tour-diem-danh-' . Str::random(6),
            'price' => 1000000,
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $this->itinerary = TourItinerary::create([
            'tour_id' => $tour->id,
            'day_number' => 1,
            'title' => 'Ha Noi - Ha Long',
            'start_point' => 'Ha Noi',
            'end_point' => 'Ha Long',
            'content' => 'Khoi hanh va tham quan vinh',
        ]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'guide_id' => $this->guide->id,
            'start_date' => now()->addDays(3),
            'max_people' => 10,
            'booked_people' => 2,
            'status' => 'active',
        ]);

        $this->booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Khach Diem Danh',
            'customer_email' => 'diemdanh@example.com',
            'departure_date' => $this->schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 2000000,
            'status' => 'confirmed',
        ]);
    }

    public function test_guide_xem_duoc_du_lieu_diem_danh_cua_lich_duoc_phan_cong(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.tour.title', 'Tour Diem Danh')
            ->assertJsonPath('data.guests.0.customer_name', 'Khach Diem Danh')
            ->assertJsonPath('data.itineraries.0.title', 'Ha Noi - Ha Long');
    }

    public function test_guide_luu_diem_danh_cho_mot_chang(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->putJson(
            "/api/guide/schedules/{$this->schedule->id}/itineraries/{$this->itinerary->id}/attendance",
            ['checkins' => [['booking_id' => $this->booking->id, 'present' => true]]]
        )
            ->assertOk()
            ->assertJsonPath('data.saved', 1);

        $checkin = BookingCheckin::query()
            ->where('booking_id', $this->booking->id)
            ->where('tour_itinerary_id', $this->itinerary->id)
            ->first();

        $this->assertNotNull($checkin);
        $this->assertTrue($checkin->present);
        $this->assertSame($this->guide->id, (int) $checkin->guide_id);
    }

    public function test_guide_khac_khong_xem_duoc_lich_khong_duoc_phan_cong(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->taoUser('guide'));

        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")
            ->assertStatus(404);
    }

    public function test_booking_chua_xac_nhan_khong_duoc_diem_danh(): void
    {
        $this->dungChuyenDi();
        $this->booking->update(['status' => 'pending']);
        Sanctum::actingAs($this->guide);

        $this->putJson(
            "/api/guide/schedules/{$this->schedule->id}/itineraries/{$this->itinerary->id}/attendance",
            ['checkins' => [['booking_id' => $this->booking->id, 'present' => true]]]
        )
            ->assertOk()
            ->assertJsonPath('data.saved', 0);

        $this->assertSame(0, BookingCheckin::query()->count());
    }
}
