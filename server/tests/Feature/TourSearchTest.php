<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TourSearchTest extends TestCase
{
    use RefreshDatabase;

    private function taoTour(string $title, string $start, ?string $end, string $description): Tour
    {
        $admin = User::query()->where('role', 'admin')->first() ?? User::create([
            'name' => 'Admin Search',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        return Tour::create([
            'admin_id' => $admin->id,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'description' => $description,
            'price' => 1000000,
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => $start,
            'end_location' => $end,
            'status' => 'active',
        ]);
    }

    public function test_tim_theo_ten_tour(): void
    {
        $this->taoTour('Tour Hạ Long 3N2Đ', 'Hà Nội', 'Hạ Long', 'Du thuyền vịnh');
        $this->taoTour('Tour Sapa 2N1Đ', 'Hà Nội', 'Sapa', 'Săn mây Fansipan');

        $titles = collect($this->getJson('/api/tours?q=Sapa')->assertOk()->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Tour Sapa 2N1Đ'));
        $this->assertFalse($titles->contains('Tour Hạ Long 3N2Đ'));
    }

    public function test_tim_theo_diem_den(): void
    {
        $this->taoTour('Combo nghỉ dưỡng cao cấp', 'TP. Hồ Chí Minh', 'Phú Quốc', 'Biển đảo');

        $titles = collect($this->getJson('/api/tours?q=Phú Quốc')->assertOk()->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Combo nghỉ dưỡng cao cấp'));
    }

    public function test_khong_tim_theo_mo_ta(): void
    {
        $this->taoTour('Tour Hạ Long 3N2Đ', 'Hà Nội', 'Hạ Long', 'Chèo kayak và tắm biển tuyệt vời');

        $data = $this->getJson('/api/tours?q=kayak')->assertOk()->json('data');

        $this->assertCount(0, $data);
    }

    public function test_khong_tim_theo_ten_dich_vu_hoac_danh_muc(): void
    {
        $tour = $this->taoTour('Tour Hạ Long 3N2Đ', 'Hà Nội', 'Hạ Long', 'Du thuyền vịnh');

        $service = Service::query()->firstOrCreate(['name' => 'Ăn sáng']);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'bien-dao'],
            ['name' => 'Biển đảo', 'is_active' => true]
        );
        $tour->services()->attach($service->id);
        $tour->categories()->attach($category->id);

        $this->assertCount(0, $this->getJson('/api/tours?q=Ăn sáng')->assertOk()->json('data'));
        $this->assertCount(0, $this->getJson('/api/tours?q=Biển đảo')->assertOk()->json('data'));
    }
}
