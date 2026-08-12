<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B05 - Quản lý chính sách hủy.
 */
class AdminCancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function dangNhapAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bacPhiHopLe(): array
    {
        return [
            ['min_hours_before' => 360, 'max_hours_before' => null, 'refund_percent' => 90],
            ['min_hours_before' => 48, 'max_hours_before' => 360, 'refund_percent' => 50],
            ['min_hours_before' => 0, 'max_hours_before' => 48, 'refund_percent' => 0],
        ];
    }

    public function test_tao_chinh_sach_kem_cac_bac_phi(): void
    {
        $this->dangNhapAdmin();

        $this->postJson('/api/admin/cancellation-policies', [
            'name' => 'Chính sách tour lễ tết',
            'description' => 'Phí cao hơn vì vé và phòng đã xuất trước.',
            'rules' => $this->bacPhiHopLe(),
        ])->assertOk();

        $policy = CancellationPolicy::query()->with('rules')->first();

        $this->assertSame('Chính sách tour lễ tết', $policy->name);
        $this->assertCount(3, $policy->rules);
        // rules() sắp giảm dần theo mốc dưới, bậc xa nhất đứng đầu.
        $this->assertSame(360, $policy->rules->first()->min_hours_before);
    }

    public function test_phai_co_bac_bat_dau_tu_khong_gio(): void
    {
        $this->dangNhapAdmin();

        $this->postJson('/api/admin/cancellation-policies', [
            'name' => 'Thiếu bậc sát ngày',
            'rules' => [
                ['min_hours_before' => 48, 'max_hours_before' => null, 'refund_percent' => 50],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, CancellationPolicy::query()->count());
    }

    public function test_moc_tren_phai_lon_hon_moc_duoi(): void
    {
        $this->dangNhapAdmin();

        $this->postJson('/api/admin/cancellation-policies', [
            'name' => 'Bậc ngược',
            'rules' => [
                ['min_hours_before' => 100, 'max_hours_before' => 50, 'refund_percent' => 30],
                ['min_hours_before' => 0, 'max_hours_before' => 50, 'refund_percent' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_muc_hoan_khong_vuot_qua_mot_tram(): void
    {
        $this->dangNhapAdmin();

        $this->postJson('/api/admin/cancellation-policies', [
            'name' => 'Hoàn quá tay',
            'rules' => [
                ['min_hours_before' => 0, 'max_hours_before' => null, 'refund_percent' => 150],
            ],
        ])->assertStatus(422);
    }

    public function test_chi_co_mot_chinh_sach_mac_dinh(): void
    {
        $this->dangNhapAdmin();

        $cu = CancellationPolicy::create(['name' => 'Cũ', 'is_default' => true]);
        $cu->rules()->create(['min_hours_before' => 0, 'max_hours_before' => null, 'refund_percent' => 10]);

        $this->postJson('/api/admin/cancellation-policies', [
            'name' => 'Mới',
            'is_default' => true,
            'rules' => $this->bacPhiHopLe(),
        ])->assertOk();

        $this->assertFalse((bool) $cu->fresh()->is_default);
        $this->assertSame(1, CancellationPolicy::query()->where('is_default', true)->count());
    }

    public function test_sua_chinh_sach_thay_toan_bo_cac_bac(): void
    {
        $this->dangNhapAdmin();

        $policy = CancellationPolicy::create(['name' => 'Ban đầu']);
        $policy->rules()->create(['min_hours_before' => 0, 'max_hours_before' => null, 'refund_percent' => 10]);

        $this->putJson("/api/admin/cancellation-policies/{$policy->id}", [
            'name' => 'Đã sửa',
            'rules' => $this->bacPhiHopLe(),
        ])->assertOk();

        $policy->refresh()->load('rules');

        $this->assertSame('Đã sửa', $policy->name);
        $this->assertCount(3, $policy->rules);
    }

    public function test_khong_xoa_duoc_chinh_sach_mac_dinh(): void
    {
        $this->dangNhapAdmin();

        $policy = CancellationPolicy::create(['name' => 'Mặc định', 'is_default' => true]);

        $this->deleteJson("/api/admin/cancellation-policies/{$policy->id}")->assertStatus(422);
        $this->assertNotNull(CancellationPolicy::query()->find($policy->id));
    }

    public function test_khong_xoa_duoc_chinh_sach_dang_co_tour_dung(): void
    {
        $this->dangNhapAdmin();

        $policy = CancellationPolicy::create(['name' => 'Đang dùng']);
        Tour::factory()->create(['cancellation_policy_id' => $policy->id]);

        $this->deleteJson("/api/admin/cancellation-policies/{$policy->id}")->assertStatus(422);
    }

    /**
     * Đơn đã sao chép chính sách thì phải giữ lại, nếu không sẽ mất căn cứ giải thích điều
     * khoản mà khách đã đồng ý lúc đặt.
     */
    public function test_khong_xoa_duoc_chinh_sach_da_gan_vao_don(): void
    {
        $this->dangNhapAdmin();

        $policy = CancellationPolicy::create(['name' => 'Đã gán vào đơn']);
        $tour = Tour::factory()->create();

        Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'cancellation_policy_id' => $policy->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => now()->addDays(10),
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 2_000_000,
            'status' => 'confirmed',
        ]);

        $this->deleteJson("/api/admin/cancellation-policies/{$policy->id}")->assertStatus(422);
    }

    public function test_xoa_duoc_chinh_sach_khong_ai_dung(): void
    {
        $this->dangNhapAdmin();

        $policy = CancellationPolicy::create(['name' => 'Không ai dùng']);

        $this->deleteJson("/api/admin/cancellation-policies/{$policy->id}")->assertOk();
        $this->assertNull(CancellationPolicy::query()->find($policy->id));
    }

    public function test_khach_khong_goi_duoc_api_quan_tri(): void
    {
        $customer = User::create([
            'name' => 'Khach',
            'email' => 'khach-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/cancellation-policies')->assertStatus(403);
    }
}
