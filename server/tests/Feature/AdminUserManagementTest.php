<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function taoNguoi(string $role, string $status = 'active'): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . Str::random(4),
            'email' => $role . '-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => $status,
        ]);
    }

    public function test_liet_ke_tai_khoan_va_loc_theo_vai(): void
    {
        $admin = $this->taoNguoi('admin');
        $this->taoNguoi('customer');
        $this->taoNguoi('customer');
        $this->taoNguoi('guide');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?role=customer')
            ->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(2, $response->json('data.counts.customer'));
    }

    public function test_tim_theo_email(): void
    {
        $admin = $this->taoNguoi('admin');
        $khach = $this->taoNguoi('customer');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?q=' . urlencode($khach->email))
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame($khach->id, $response->json('data.data.0.id'));
    }

    public function test_khoa_va_mo_lai_tai_khoan_khach(): void
    {
        $admin = $this->taoNguoi('admin');
        $khach = $this->taoNguoi('customer');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/users/' . $khach->id . '/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/users/' . $khach->id . '/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_khoa_tai_khoan_thi_thu_hoi_token_dang_co(): void
    {
        $admin = $this->taoNguoi('admin');
        $khach = $this->taoNguoi('customer');
        $tokenKhach = $khach->createToken('phien')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $tokenKhach)
            ->getJson('/api/me')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/users/' . $khach->id . '/status')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        // RoleMiddleware chặn tài khoản bị khóa ở tuyến có phân vai, nhưng /api/me thì không —
        // nên token phải bị xóa, không dựa vào việc mọi tuyến đều nhớ kiểm tra trạng thái.
        $this->withHeader('Authorization', 'Bearer ' . $tokenKhach)
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_khong_tu_khoa_chinh_minh(): void
    {
        $admin = $this->taoNguoi('admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/users/' . $admin->id . '/status')
            ->assertStatus(422);

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_khong_khoa_duoc_quan_tri_hoat_dong_cuoi_cung(): void
    {
        $admin = $this->taoNguoi('admin');
        $adminKhac = $this->taoNguoi('admin');

        // Còn hai người: khóa một người vẫn được.
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/users/' . $adminKhac->id . '/status')
            ->assertOk();

        // Giờ chỉ còn $admin đang hoạt động, và người đó không tự khóa mình được.
        // Dựng thêm một quản trị nữa để thử khóa $admin từ tài khoản khác.
        $adminThuBa = $this->taoNguoi('admin');
        $this->actingAs($adminThuBa, 'sanctum')
            ->putJson('/api/admin/users/' . $admin->id . '/status')
            ->assertOk();

        // Còn đúng $adminThuBa. Không ai khóa được người đó nữa.
        $this->actingAs($adminThuBa, 'sanctum')
            ->putJson('/api/admin/users/' . $adminThuBa->id . '/status')
            ->assertStatus(422);
    }

    public function test_khach_khong_vao_duoc_danh_sach_tai_khoan(): void
    {
        $khach = $this->taoNguoi('customer');

        $this->actingAs($khach, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }
}
