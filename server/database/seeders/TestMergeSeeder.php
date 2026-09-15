<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestMergeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        $customer = User::query()->where('role', 'customer')->first() ?? User::factory()->create(['role' => 'customer']);

        // 1. Tạo 2 Tour riêng biệt
        $tourA = Tour::query()->firstOrCreate(
            ['slug' => 'test-tour-a'],
            [
                'title' => '[Test] Tour Hà Nội - Sapa 2N1Đ',
                'admin_id' => $admin->id,
                'adult_price' => 1500000,
                'child_price' => 1000000,
                'number_of_days' => 2,
                'number_of_nights' => 1,
                'start_location' => 'Hà Nội',
                'status' => 'active',
            ]
        );

        $tourB = Tour::query()->firstOrCreate(
            ['slug' => 'test-tour-b'],
            [
                'title' => '[Test] Tour Hà Nội - Sapa - Cát Cát 2N1Đ',
                'admin_id' => $admin->id,
                'adult_price' => 1800000,
                'child_price' => 1200000,
                'number_of_days' => 2,
                'number_of_nights' => 1,
                'start_location' => 'Hà Nội',
                'status' => 'active',
            ]
        );

        $ngayKhoiHanh = Carbon::now()->addDays(7)->startOfDay();

        // 2. Tạo 2 chuyến cùng ngày
        $chuyenA = TourSchedule::query()->updateOrCreate(
            ['tour_id' => $tourA->id, 'start_date' => $ngayKhoiHanh],
            [
                'end_date' => $ngayKhoiHanh->copy()->addDays(1),
                'max_people' => 20,
                'min_people' => 10,
                'booked_people' => 4, // Đã có 4 người
                'booking_deadline' => $ngayKhoiHanh->copy()->subDays(2),
                'status' => 'open',
            ]
        );

        $chuyenB = TourSchedule::query()->updateOrCreate(
            ['tour_id' => $tourB->id, 'start_date' => $ngayKhoiHanh],
            [
                'end_date' => $ngayKhoiHanh->copy()->addDays(1),
                'max_people' => 16,
                'min_people' => 10,
                'booked_people' => 0, // Chưa có khách nào
                'booking_deadline' => $ngayKhoiHanh->copy()->subDays(2),
                'status' => 'open',
            ]
        );

        // 3. Tạo một booking đã thanh toán cho chuyến A để có dữ liệu mà chuyển
        Booking::query()->firstOrCreate(
            ['tour_schedule_id' => $chuyenA->id, 'customer_name' => 'Khách ghép thử nghiệm'],
            [
                'tour_id' => $tourA->id,
                'customer_id' => $customer->id,
                'customer_email' => 'test-merge@example.com',
                'customer_phone' => '0987654321',
                'departure_date' => $ngayKhoiHanh,
                'guests' => 4,
                'adult_count' => 4,
                'child_count' => 0,
                'infant_count' => 0,
                'status' => 'confirmed',
                'total_amount' => 6000000,
                'paid_at' => now(),
                'expires_at' => null,
            ]
        );

        $this->command->info("Đã tạo dữ liệu Test thành công!");
        $this->command->info("Tour A (Nguồn): {$tourA->title} | Mã chuyến A: {$chuyenA->id} | Đã có 4 khách");
        $this->command->info("Tour B (Đích): {$tourB->title} | Mã chuyến B: {$chuyenB->id} | Trống 16 chỗ");
        $this->command->info("Ngày khởi hành chung: {$ngayKhoiHanh->format('d/m/Y')}");
        $this->command->info("=> Hướng dẫn test: Vào quản lý chuyến A (#{$chuyenA->id}), bấm ghép chuyến, sẽ thấy gợi ý chuyến B (#{$chuyenB->id}) hiện ra kèm chữ Khác Tour.");
    }
}
