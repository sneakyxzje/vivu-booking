<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dang_ky_cong_khai_khong_the_tu_chon_role_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ke Tan Cong',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertOk();
        $this->assertSame('customer', User::where('email', 'attacker@example.com')->value('role'));
    }

    public function test_dang_ky_cong_khai_khong_the_tu_chon_role_guide(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Nguoi Dung',
            'email' => 'guide-wannabe@example.com',
            'password' => 'password123',
            'role' => 'guide',
        ])->assertOk();

        $this->assertSame('customer', User::where('email', 'guide-wannabe@example.com')->value('role'));
    }

    public function test_tai_khoan_bi_khoa_khong_dang_nhap_duoc(): void
    {
        User::create([
            'name' => 'Khach Bi Khoa',
            'email' => 'blocked@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'blocked',
        ]);

        $this->postJson('/api/login', [
            'email' => 'blocked@example.com',
            'password' => 'password123',
        ])->assertStatus(403);
    }

    public function test_tai_khoan_active_van_dang_nhap_binh_thuong(): void
    {
        User::create([
            'name' => 'Khach Binh Thuong',
            'email' => 'active@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_token_cu_het_hieu_luc_khi_tai_khoan_bi_khoa(): void
    {
        $admin = User::create([
            'name' => 'Quan Tri',
            'email' => 'admin-test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Token được phát hành khi tài khoản còn hoạt động...
        $token = $admin->createToken('test')->plainTextToken;

        // ...sau đó tài khoản bị khóa. Token cũ phải mất tác dụng ngay.
        $admin->update(['status' => 'blocked']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_admin_dang_hoat_dong_van_vao_duoc_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Quan Tri',
            'email' => 'admin-ok@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/dashboard')
            ->assertOk();
    }

    public function test_customer_khong_vao_duoc_khu_vuc_admin(): void
    {
        $customer = User::create([
            'name' => 'Khach Hang',
            'email' => 'customer-test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }
}
