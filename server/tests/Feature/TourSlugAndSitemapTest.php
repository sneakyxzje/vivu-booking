<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TourSlugAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    private function taoTour(string $title, string $slug, string $status = 'active'): Tour
    {
        $admin = User::query()->where('role', 'admin')->first() ?? User::create([
            'name' => 'Admin Slug',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        return Tour::create([
            'admin_id' => $admin->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'x',
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Hà Nội',
            'end_location' => 'Hạ Long',
            'status' => $status,
        ]);
    }

    public function test_mo_chi_tiet_tour_bang_slug(): void
    {
        $tour = $this->taoTour('Tour Hạ Long 3N2Đ', 'tour-ha-long-3n2d');

        $this->getJson('/api/tours/tour-ha-long-3n2d')
            ->assertOk()
            ->assertJsonPath('data.id', $tour->id)
            ->assertJsonPath('data.slug', 'tour-ha-long-3n2d');
    }

    public function test_van_mo_duoc_bang_id_nhu_cu(): void
    {
        // Liên kết dạng số đã nằm trong thư gửi khách và trong các màn hình nội bộ.
        $tour = $this->taoTour('Tour Sapa', 'tour-sapa');

        $this->getJson('/api/tours/' . $tour->id)
            ->assertOk()
            ->assertJsonPath('data.slug', 'tour-sapa');
    }

    public function test_slug_khong_ton_tai_tra_ve_404(): void
    {
        $this->getJson('/api/tours/khong-co-tour-nao-ten-nay')->assertStatus(404);
    }

    public function test_tour_ngung_ban_khong_mo_duoc_bang_slug(): void
    {
        $this->taoTour('Tour đã ngừng', 'tour-da-ngung', 'inactive');

        $this->getJson('/api/tours/tour-da-ngung')->assertStatus(404);
    }

    public function test_sitemap_liet_ke_tour_dang_ban_va_bo_qua_tour_ngung_ban(): void
    {
        $this->taoTour('Tour đang bán', 'tour-dang-ban');
        $this->taoTour('Tour ngừng bán', 'tour-ngung-ban', 'inactive');
        Category::query()->create(['name' => 'Biển đảo', 'slug' => 'bien-dao', 'is_active' => true]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/tours/tour-dang-ban', false);
        $response->assertSee('categories=bien-dao', false);

        // Dẫn máy tìm kiếm tới một trang trả 404 là tự hạ điểm chính mình.
        $response->assertDontSee('/tours/tour-ngung-ban', false);
    }

    public function test_sitemap_dung_dia_chi_giao_dien_khong_phai_dia_chi_api(): void
    {
        config(['app.frontend_url' => 'https://vivubooking.test']);
        $this->taoTour('Tour kiểm tra', 'tour-kiem-tra');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('https://vivubooking.test/tours/tour-kiem-tra', false);
    }
}
