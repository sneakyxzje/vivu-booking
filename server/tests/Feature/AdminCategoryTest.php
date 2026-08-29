<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
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

    public function test_admin_tao_danh_muc_va_tu_sinh_slug(): void
    {
        Sanctum::actingAs($this->taoUser('admin'));

        $this->postJson('/api/admin/categories', [
            'name' => 'Du lịch tâm linh',
            'description' => 'Hành hương, chùa chiền',
        ])->assertCreated();

        $category = Category::query()->where('name', 'Du lịch tâm linh')->first();
        $this->assertNotNull($category);
        $this->assertSame('du-lich-tam-linh', $category->slug);
        $this->assertTrue((bool) $category->is_active);
    }

    public function test_khong_tao_duoc_danh_muc_trung_ten(): void
    {
        Category::create(['name' => 'Biển đảo', 'slug' => 'bien-dao', 'is_active' => true]);
        Sanctum::actingAs($this->taoUser('admin'));

        $this->postJson('/api/admin/categories', ['name' => 'Biển đảo'])
            ->assertStatus(422);
    }

    public function test_doi_ten_thi_slug_cap_nhat_theo(): void
    {
        $category = Category::create(['name' => 'Khám phá', 'slug' => 'kham-pha', 'is_active' => true]);
        Sanctum::actingAs($this->taoUser('admin'));

        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Trekking núi'])
            ->assertOk();

        $this->assertSame('trekking-nui', $category->fresh()->slug);
    }

    public function test_khong_xoa_duoc_danh_muc_dang_co_tour(): void
    {
        $admin = $this->taoUser('admin');
        $category = Category::create(['name' => 'Nghỉ dưỡng', 'slug' => 'nghi-duong', 'is_active' => true]);

        $tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Test Danh Muc',
            'slug' => 'tour-test-danh-muc-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 1,
            'number_of_nights' => 0,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);
        $tour->categories()->attach($category->id);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422);

        $this->assertNotNull(Category::find($category->id));
    }

    public function test_xoa_duoc_danh_muc_khong_con_tour(): void
    {
        $category = Category::create(['name' => 'Danh mục rỗng', 'slug' => 'danh-muc-rong', 'is_active' => true]);
        Sanctum::actingAs($this->taoUser('admin'));

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertOk();

        $this->assertNull(Category::find($category->id));
    }

    public function test_danh_muc_tat_khong_hien_cho_khach(): void
    {
        Category::create(['name' => 'Đang hiện', 'slug' => 'dang-hien', 'is_active' => true]);
        Category::create(['name' => 'Đã tắt', 'slug' => 'da-tat', 'is_active' => false]);

        $response = $this->getJson('/api/categories')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Đang hiện'));
        $this->assertFalse($names->contains('Đã tắt'));
    }

    public function test_customer_khong_duoc_quan_ly_danh_muc(): void
    {
        Sanctum::actingAs($this->taoUser('customer'));

        $this->postJson('/api/admin/categories', ['name' => 'Danh mục lậu'])
            ->assertStatus(403);
    }
}
