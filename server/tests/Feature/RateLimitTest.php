<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hạn mức gọi API.
 *
 * Con số nằm ở `config/rate_limit.php` và tắt được bằng một dòng .env, nên bộ test này giữ đúng hai
 * điều: công tắc thật sự tắt được, và khi chưa tắt thì hạn mức thật sự chặn. Một công tắc lỡ tay để
 * ở vị trí tắt vĩnh viễn là thứ không ai nhận ra cho tới lúc có người dò mật khẩu.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function dangNhapSai(): int
    {
        return $this->postJson('/api/login', [
            'email' => 'khong-co-that@example.com',
            'password' => 'mat-khau-sai',
        ])->getStatusCode();
    }

    /** Trần chung của /api đọc đúng cấu hình. */
    public function test_tran_chung_cua_api_doc_theo_cau_hinh(): void
    {
        config(['rate_limit.enabled' => true, 'rate_limit.api' => '2,1']);

        $this->getJson('/api/categories')->assertOk();
        $this->getJson('/api/categories')->assertOk();
        $this->getJson('/api/categories')->assertStatus(429);
    }

    /**
     * Đăng nhập có hạn mức riêng, chặt hơn trần chung.
     *
     * Đây là chỗ hạn mức có ý nghĩa thật: 60 lần thử mật khẩu mỗi phút là hơn tám vạn lần một ngày
     * từ một địa chỉ.
     */
    public function test_do_mat_khau_bi_chan(): void
    {
        config([
            'rate_limit.enabled' => true,
            'rate_limit.api' => '600,1',
            'rate_limit.login' => '2,1',
        ]);

        $this->assertNotSame(429, $this->dangNhapSai());
        $this->assertNotSame(429, $this->dangNhapSai());
        $this->assertSame(429, $this->dangNhapSai(), 'Lần thử thứ ba trong một phút phải bị chặn.');
    }

    /** Tắt công tắc thì gõ bao nhiêu lần cũng không bị chặn — dùng lúc ngồi thử tay. */
    public function test_tat_cong_tac_thi_khong_con_han_muc(): void
    {
        config([
            'rate_limit.enabled' => false,
            'rate_limit.api' => '2,1',
            'rate_limit.login' => '2,1',
        ]);

        foreach (range(1, 6) as $lan) {
            $this->assertNotSame(429, $this->dangNhapSai(), "Lần thử thứ {$lan} không được bị chặn.");
            $this->getJson('/api/categories')->assertOk();
        }
    }
}
