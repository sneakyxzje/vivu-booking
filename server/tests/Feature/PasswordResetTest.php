<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function taoKhach(string $email, string $status = 'active'): User
    {
        return User::create([
            'name' => 'Khach Hang',
            'email' => $email,
            'password' => Hash::make('matkhaucu'),
            'role' => 'customer',
            'status' => $status,
        ]);
    }

    public function test_gui_lien_ket_dat_lai_cho_tai_khoan_dang_hoat_dong(): void
    {
        Mail::fake();
        $khach = $this->taoKhach('quen@example.com');

        $this->postJson('/api/forgot-password', ['email' => $khach->email])->assertOk();

        Mail::assertSent(
            PasswordResetMail::class,
            fn (PasswordResetMail $thu) => $thu->hasTo($khach->email),
        );
    }

    public function test_email_khong_ton_tai_van_tra_ve_cung_mot_cau(): void
    {
        Mail::fake();
        $khach = $this->taoKhach('co-that@example.com');

        $coThat = $this->postJson('/api/forgot-password', ['email' => $khach->email]);
        $khongCo = $this->postJson('/api/forgot-password', ['email' => 'khong-ai@example.com']);

        // Hai câu trả lời phải giống hệt nhau, nếu không trang quên mật khẩu thành công cụ
        // dò xem địa chỉ nào có tài khoản ở đây.
        $coThat->assertOk();
        $khongCo->assertOk();
        $this->assertSame($coThat->json('message'), $khongCo->json('message'));

        Mail::assertSentCount(1);
    }

    public function test_tai_khoan_bi_khoa_khong_nhan_duoc_lien_ket(): void
    {
        Mail::fake();
        $this->taoKhach('bi-khoa@example.com', 'blocked');

        $this->postJson('/api/forgot-password', ['email' => 'bi-khoa@example.com'])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_dat_lai_mat_khau_thanh_cong_va_dang_nhap_duoc_bang_mat_khau_moi(): void
    {
        $khach = $this->taoKhach('doi@example.com');
        $token = Password::createToken($khach);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $khach->email,
            'password' => 'matkhaumoi',
            'password_confirmation' => 'matkhaumoi',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $khach->email,
            'password' => 'matkhaumoi',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $khach->email,
            'password' => 'matkhaucu',
        ])->assertStatus(401);
    }

    public function test_dat_lai_mat_khau_thu_hoi_moi_token_dang_nhap_cu(): void
    {
        $khach = $this->taoKhach('thu-hoi@example.com');
        $tokenCu = $khach->createToken('phien-cu')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $tokenCu)
            ->getJson('/api/me')
            ->assertOk();

        $this->postJson('/api/reset-password', [
            'token' => Password::createToken($khach),
            'email' => $khach->email,
            'password' => 'matkhaumoi',
            'password_confirmation' => 'matkhaumoi',
        ])->assertOk();

        $this->assertSame(0, $khach->tokens()->count());

        /*
         * Xóa bộ nhớ đệm của guard trước khi gọi lại.
         *
         * `RequestGuard` của Sanctum giữ lại người dùng đã giải mã ở lần gọi trước, và trong một
         * test thì cả hai lượt gọi dùng chung một thực thể ứng dụng. Không quên guard thì lượt thứ
         * hai trả về người dùng cũ từ bộ nhớ chứ không đọc lại bảng token - test xanh hay đỏ lúc
         * đó không nói gì về hành vi thật.
         */
        $this->app['auth']->forgetGuards();

        // Người đổi mật khẩu thường đang nghĩ có kẻ khác vào được tài khoản. Phiên cũ phải chết.
        $this->withHeader('Authorization', 'Bearer ' . $tokenCu)
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_token_dung_lai_lan_thu_hai_bi_tu_choi(): void
    {
        $khach = $this->taoKhach('mot-lan@example.com');
        $token = Password::createToken($khach);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $khach->email,
            'password' => 'matkhaumoi',
            'password_confirmation' => 'matkhaumoi',
        ])->assertOk();

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $khach->email,
            'password' => 'matkhaukhac',
            'password_confirmation' => 'matkhaukhac',
        ])->assertStatus(422);
    }

    public function test_token_sai_bi_tu_choi(): void
    {
        $khach = $this->taoKhach('token-sai@example.com');

        $this->postJson('/api/reset-password', [
            'token' => 'khong-phai-token-that',
            'email' => $khach->email,
            'password' => 'matkhaumoi',
            'password_confirmation' => 'matkhaumoi',
        ])->assertStatus(422);
    }

    public function test_hai_lan_nhap_mat_khau_khong_khop_thi_bi_tu_choi(): void
    {
        $khach = $this->taoKhach('lech@example.com');

        $this->postJson('/api/reset-password', [
            'token' => Password::createToken($khach),
            'email' => $khach->email,
            'password' => 'matkhaumoi',
            'password_confirmation' => 'go-nham',
        ])->assertStatus(422);
    }
}
