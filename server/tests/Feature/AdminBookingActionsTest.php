<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingActionsTest extends TestCase
{
    use RefreshDatabase;

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

    private function taoLichVaDon(int $maxPeople, int $guests, string $status): Booking
    {
        $admin = $this->taoUser('admin');

        $tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Test Admin',
            'slug' => 'tour-test-admin-' . Str::random(6),
            'price' => 1000000,
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 1,
            'number_of_nights' => 0,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(7),
            'max_people' => $maxPeople,
            'booked_people' => $guests,
            'status' => $guests >= $maxPeople ? 'full' : 'active',
        ]);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $guests,
            'adult_count' => $guests,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $guests * 1000000,
            'status' => $status,
            'expires_at' => $status === 'pending' ? now()->addMinutes(10) : null,
        ]);
    }

    public function test_admin_xac_nhan_don_pending(): void
    {
        $don = $this->taoLichVaDon(maxPeople: 5, guests: 2, status: 'pending');
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/bookings/{$don->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $don->refresh();
        $this->assertNull($don->expires_at);
        $this->assertNotNull($don->confirmed_at);
    }

    public function test_admin_huy_don_confirmed_va_tra_lai_cho(): void
    {
        $don = $this->taoLichVaDon(maxPeople: 5, guests: 2, status: 'confirmed');
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach yeu cau hoan do thay doi lich trinh',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, (int) $don->schedule->fresh()->booked_people);
    }

    public function test_khong_the_huy_don_da_huy(): void
    {
        $don = $this->taoLichVaDon(maxPeople: 5, guests: 2, status: 'cancelled');
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Huy lai lan nua',
        ])->assertStatus(400);
    }

    public function test_customer_khong_duoc_goi_api_admin(): void
    {
        $don = $this->taoLichVaDon(maxPeople: 5, guests: 2, status: 'pending');
        Sanctum::actingAs($this->taoUser('customer'));

        $this->putJson("/api/admin/bookings/{$don->id}/confirm")->assertStatus(403);
    }
}
