<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function taoCustomer(): User
    {
        return User::create([
            'name' => 'Khach Cu',
            'email' => 'customer-' . Str::random(6) . '@example.com',
            'password' => Hash::make('matkhaucu123'),
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    public function test_cap_nhat_ho_so_thanh_cong(): void
    {
        $user = $this->taoCustomer();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'name' => 'Khach Moi',
            'phone' => '0909123456',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Khach Moi', $user->name);
        $this->assertSame('0909123456', $user->phone);
    }

    public function test_doi_mat_khau_sai_mat_khau_hien_tai_bi_tu_choi(): void
    {
        Sanctum::actingAs($this->taoCustomer());

        $this->putJson('/api/profile/password', [
            'current_password' => 'sai-mat-khau',
            'password' => 'matkhaumoi123',
            'password_confirmation' => 'matkhaumoi123',
        ])->assertStatus(422);
    }

    public function test_doi_mat_khau_thanh_cong_va_dang_nhap_duoc_bang_mat_khau_moi(): void
    {
        $user = $this->taoCustomer();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/password', [
            'current_password' => 'matkhaucu123',
            'password' => 'matkhaumoi123',
            'password_confirmation' => 'matkhaumoi123',
        ])->assertOk();

        $this->assertTrue(Hash::check('matkhaumoi123', $user->fresh()->password));
    }
}
