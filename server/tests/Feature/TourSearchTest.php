<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    // --- Lọc theo ngày khởi hành --------------------------------------------------------------

    /** Gắn một chuyến còn bán được vào tour, khởi hành đúng ngày yêu cầu. */
    private function themChuyen(Tour $tour, string $ngayKhoiHanh, array $ghiDe = []): TourSchedule
    {
        $start = Carbon::parse($ngayKhoiHanh);

        return $tour->schedules()->create(array_merge([
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'max_people' => 20,
            'min_people' => 1,
            'booked_people' => 0,
            'booking_deadline' => $start->copy()->subDays(3),
            'status' => ScheduleStatus::Open->value,
        ], $ghiDe));
    }

    public function test_loc_theo_khoang_ngay_khoi_hanh(): void
    {
        $trongKhoang = $this->taoTour('Tour đi trong khoảng', 'Hà Nội', 'Sapa', 'x');
        $ngoaiKhoang = $this->taoTour('Tour đi ngoài khoảng', 'Hà Nội', 'Huế', 'x');

        $this->themChuyen($trongKhoang, now()->addDays(30)->toDateString());
        $this->themChuyen($ngoaiKhoang, now()->addDays(90)->toDateString());

        $titles = collect($this->getJson(sprintf(
            '/api/tours?departure_from=%s&departure_to=%s',
            now()->addDays(20)->toDateString(),
            now()->addDays(40)->toDateString(),
        ))->assertOk()->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Tour đi trong khoảng'));
        $this->assertFalse($titles->contains('Tour đi ngoài khoảng'));
    }

    public function test_chuyen_da_day_cho_khong_lot_vao_ket_qua_loc_ngay(): void
    {
        $tour = $this->taoTour('Tour đã kín chỗ', 'Hà Nội', 'Đà Nẵng', 'x');
        $this->themChuyen($tour, now()->addDays(30)->toDateString(), [
            'max_people' => 10,
            'booked_people' => 10,
        ]);

        // Bộ lọc ngày phải trả lời "còn đặt được ngày đó", không phải "có chuyến ngày đó".
        // Trả về tour này là mời khách bấm vào một chuyến mà trang đặt sẽ từ chối.
        $titles = collect($this->getJson(sprintf(
            '/api/tours?departure_from=%s&departure_to=%s',
            now()->addDays(20)->toDateString(),
            now()->addDays(40)->toDateString(),
        ))->assertOk()->json('data'))->pluck('title');

        $this->assertFalse($titles->contains('Tour đã kín chỗ'));
    }

    public function test_chuyen_qua_han_chot_khong_lot_vao_ket_qua_loc_ngay(): void
    {
        $tour = $this->taoTour('Tour quá hạn chốt', 'Hà Nội', 'Hội An', 'x');
        $this->themChuyen($tour, now()->addDay()->toDateString(), [
            'booking_deadline' => now()->subDay(),
        ]);

        $titles = collect($this->getJson(sprintf(
            '/api/tours?departure_from=%s&departure_to=%s',
            now()->toDateString(),
            now()->addDays(5)->toDateString(),
        ))->assertOk()->json('data'))->pluck('title');

        $this->assertFalse($titles->contains('Tour quá hạn chốt'));
    }

    public function test_ngay_ve_truoc_ngay_di_thi_bi_tu_choi(): void
    {
        $this->getJson(sprintf(
            '/api/tours?departure_from=%s&departure_to=%s',
            now()->addDays(30)->toDateString(),
            now()->addDays(10)->toDateString(),
        ))->assertStatus(422);
    }

    // --- Phân trang ----------------------------------------------------------------------------

    public function test_danh_sach_tour_duoc_phan_trang(): void
    {
        foreach (range(1, 15) as $i) {
            $this->taoTour('Tour số ' . $i, 'Hà Nội', 'Sapa', 'x');
        }

        $trang1 = $this->getJson('/api/tours?per_page=10')->assertOk();

        $this->assertCount(10, $trang1->json('data'));
        $this->assertSame(15, $trang1->json('meta.total'));
        $this->assertSame(2, $trang1->json('meta.last_page'));

        // Tổng số phải là tổng thật của bộ lọc, không phải số phần tử trên trang đang xem -
        // nếu không thì dòng "Tìm thấy N kết quả" nói sai ngay từ trang đầu.
        $trang2 = $this->getJson('/api/tours?per_page=10&page=2')->assertOk();
        $this->assertCount(5, $trang2->json('data'));
        $this->assertSame(15, $trang2->json('meta.total'));
    }

    public function test_per_page_khong_vuot_qua_muc_toi_da(): void
    {
        $this->getJson('/api/tours?per_page=500')->assertStatus(422);
    }
}
