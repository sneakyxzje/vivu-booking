<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Tour $tour;

    private function dungDuLieu(bool $daXacNhanBooking): void
    {
        $admin = User::create([
            'name' => 'Admin Review',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Khach Review',
            'email' => 'khach-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Review Test',
            'slug' => 'tour-review-test-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 1,
            'number_of_nights' => 0,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        if ($daXacNhanBooking) {
            $schedule = TourSchedule::create([
                'tour_id' => $this->tour->id,
                'start_date' => now()->addDays(3),
                'max_people' => 10,
                'booked_people' => 1,
                'status' => 'open',
            ]);

            Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $this->tour->id,
                'customer_id' => $this->customer->id,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $this->customer->name,
                'customer_email' => $this->customer->email,
                'departure_date' => $schedule->start_date,
                'guests' => 1,
                'adult_count' => 1,
                'child_count' => 0,
                'infant_count' => 0,
                'total_amount' => 1000000,
                'status' => 'confirmed',
            ]);
        }
    }

    public function test_khach_chua_dat_tour_khong_duoc_danh_gia(): void
    {
        $this->dungDuLieu(daXacNhanBooking: false);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Tour tuyet voi!',
        ])->assertStatus(403);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_khach_da_xac_nhan_booking_duoc_danh_gia_va_gui_lai_thi_cap_nhat(): void
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 4,
            'comment' => 'Chuyen di on.',
        ])->assertStatus(201);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Nghi lai thay rat tuyet!',
        ])->assertStatus(201);

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(5, (int) Review::query()->first()->rating);
    }

    /**
     * Từ D03, đơn của chuyến đã đi xong tự chuyển sang 'completed'. Khách vừa đi về chính là
     * người có nhiều thứ để nói nhất, không được mất quyền đánh giá chỉ vì trạng thái đơn đổi.
     */
    public function test_khach_da_di_xong_chuyen_van_danh_gia_duoc(): void
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Booking::query()->update(['status' => BookingStatus::Completed->value]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Di ve roi moi thay dang tien.',
        ])->assertStatus(201);

        $this->assertSame(1, Review::query()->count());
    }

    /** Khách không có mặt thì không đi chuyến này, không có căn cứ để đánh giá. */
    public function test_khach_khong_co_mat_thi_khong_danh_gia_duoc(): void
    {
        $this->dungDuLieu(daXacNhanBooking: true);
        Booking::query()->update(['status' => BookingStatus::NoShow->value]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 1,
            'comment' => 'Khong di duoc nhung van cham diem.',
        ])->assertStatus(403);

        $this->assertSame(0, Review::query()->count());
    }
}

