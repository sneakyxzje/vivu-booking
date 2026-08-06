<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingHoldExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function taoTourVaLich(int $maxPeople, int $bookedPeople): TourSchedule
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Test Giu Cho',
            'slug' => 'tour-test-giu-cho-' . Str::random(6),
            'price' => 1000000,
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 1,
            'number_of_nights' => 0,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        return TourSchedule::create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(7),
            'max_people' => $maxPeople,
            'booked_people' => $bookedPeople,
            'status' => $bookedPeople >= $maxPeople ? 'full' : 'active',
        ]);
    }

    private function taoDonGiuCho(TourSchedule $schedule, int $guests, \DateTimeInterface $expiresAt): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Giu Cho',
            'customer_email' => 'giu-cho@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $guests,
            'adult_count' => $guests,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $guests * 1000000,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_don_qua_han_duoc_nha_cho_khi_co_khach_moi_dat(): void
    {
        $schedule = $this->taoTourVaLich(maxPeople: 5, bookedPeople: 3);
        $donQuaHan = $this->taoDonGiuCho($schedule, guests: 3, expiresAt: now()->subMinute());

        $response = $this->postJson('/api/bookings', [
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Moi',
            'customer_email' => 'khach-moi@example.com',
            'adult_count' => 4,
        ]);

        $response->assertCreated();

        $donQuaHan->refresh();
        $this->assertSame('cancelled', $donQuaHan->status);
        $this->assertSame(BookingHoldService::EXPIRED_REASON, $donQuaHan->cancel_reason);

        $schedule->refresh();
        $this->assertSame(4, (int) $schedule->booked_people);
    }

    public function test_don_chua_qua_han_van_giu_cho_cua_khach(): void
    {
        $schedule = $this->taoTourVaLich(maxPeople: 5, bookedPeople: 3);
        $donDangGiu = $this->taoDonGiuCho($schedule, guests: 3, expiresAt: now()->addMinutes(9));

        $response = $this->postJson('/api/bookings', [
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Moi',
            'customer_email' => 'khach-moi@example.com',
            'adult_count' => 4,
        ]);

        $response->assertUnprocessable();

        $donDangGiu->refresh();
        $this->assertSame('pending', $donDangGiu->status);

        $schedule->refresh();
        $this->assertSame(3, (int) $schedule->booked_people);
    }

    public function test_xem_don_qua_han_thi_don_tu_huy_va_khong_con_link_thanh_toan(): void
    {
        $schedule = $this->taoTourVaLich(maxPeople: 5, bookedPeople: 2);
        $don = $this->taoDonGiuCho($schedule, guests: 2, expiresAt: now()->subMinute());

        $response = $this->getJson('/api/bookings/' . $don->public_token);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonMissingPath('data.payment_url');

        $schedule->refresh();
        $this->assertSame(0, (int) $schedule->booked_people);
    }

    public function test_lenh_don_dep_mo_lai_lich_va_tour_da_full(): void
    {
        $schedule = $this->taoTourVaLich(maxPeople: 5, bookedPeople: 5);
        $schedule->tour->update(['status' => 'full']);
        $this->taoDonGiuCho($schedule, guests: 5, expiresAt: now()->subMinute());

        $this->artisan('bookings:release-expired')->assertSuccessful();

        $schedule->refresh();
        $this->assertSame(0, (int) $schedule->booked_people);
        $this->assertSame('active', $schedule->status);
        $this->assertSame('active', $schedule->tour->fresh()->status);
    }
}
