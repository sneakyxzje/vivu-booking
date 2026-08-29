<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactAndNewsletterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function loiNhan(): array
    {
        return [
            'name' => 'Nguyen Van A',
            'email' => 'khach@example.com',
            'phone' => '0901234567',
            'subject' => 'Hoi ve tour Ha Long',
            'message' => 'Toi muon hoi tour Ha Long thang sau con cho khong.',
        ];
    }

    public function test_khach_vang_lai_gui_duoc_loi_nhan(): void
    {
        // Bắt tạo tài khoản để hỏi một câu là cách chắc chắn nhất để không nhận được câu hỏi nào.
        $this->postJson('/api/contact', $this->loiNhan())->assertStatus(201);

        $this->assertSame(1, ContactMessage::query()->count());
        $this->assertSame('new', ContactMessage::query()->first()->status);
    }

    public function test_noi_dung_qua_ngan_bi_tu_choi(): void
    {
        $this->postJson('/api/contact', ['message' => 'hi'] + $this->loiNhan())->assertStatus(422);
    }

    public function test_dieu_hanh_nhan_duoc_thong_bao_khi_co_loi_nhan_moi(): void
    {
        $admin = $this->admin();

        $this->postJson('/api/contact', $this->loiNhan())->assertStatus(201);

        // Không có thông báo thì hộp thư chỉ được đọc khi ai đó nhớ ra mà mở nó.
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_hop_thu_liet_ke_loi_nhan_chua_xu_ly_len_dau(): void
    {
        $admin = $this->admin();

        $daXuLy = ContactMessage::query()->create($this->loiNhan() + [
            'status' => ContactMessage::DA_XU_LY,
            'handled_at' => now(),
        ]);
        $moi = ContactMessage::query()->create($this->loiNhan() + ['status' => ContactMessage::CHUA_XU_LY]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/contact-messages')
            ->assertOk();

        $this->assertSame($moi->id, $response->json('data.data.0.id'));
        $this->assertSame($daXuLy->id, $response->json('data.data.1.id'));
        $this->assertSame(1, $response->json('data.new_count'));
    }

    public function test_danh_dau_da_xu_ly_va_mo_lai(): void
    {
        $admin = $this->admin();
        $tinNhan = ContactMessage::query()->create($this->loiNhan() + ['status' => ContactMessage::CHUA_XU_LY]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/contact-messages/' . $tinNhan->id . '/handled', [
                'note' => 'Đã gọi lại, khách sẽ đặt tuần sau.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'handled');

        $this->assertSame($admin->id, $tinNhan->fresh()->handled_by);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/contact-messages/' . $tinNhan->id . '/handled')
            ->assertOk()
            ->assertJsonPath('data.status', 'new');
    }

    public function test_khach_khong_vao_duoc_hop_thu(): void
    {
        $khach = User::create([
            'name' => 'Khach',
            'email' => 'khach-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->actingAs($khach, 'sanctum')->getJson('/api/admin/contact-messages')->assertStatus(403);
    }

    // --- Bản tin -------------------------------------------------------------------------------

    public function test_danh_sach_dang_ky_nhan_tin_doc_duoc(): void
    {
        $admin = $this->admin();
        NewsletterSubscriber::query()->create(['email' => 'a@example.com']);
        NewsletterSubscriber::query()->create(['email' => 'b@example.com']);

        // Trước đây bảng này nhận email từ trang chủ nhưng không màn hình nào đọc nó.
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/newsletter-subscribers')
            ->assertOk();

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_xuat_csv_danh_sach_dang_ky(): void
    {
        $admin = $this->admin();
        NewsletterSubscriber::query()->create(['email' => 'a@example.com']);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/newsletter-subscribers/export')
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('a@example.com', $response->streamedContent());
    }
}
