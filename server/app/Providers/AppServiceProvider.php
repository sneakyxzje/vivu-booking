<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->dangKyHanMuc();
    }

    /**
     * Hạn mức gọi API, khai ở một chỗ duy nhất.
     *
     * Trước đây mỗi tuyến tự viết `throttle:5,1` ngay trong tệp route, nên muốn nới ra lúc ngồi thử
     * tay thì phải đi sửa sáu chỗ rồi nhớ trả lại — và quên trả lại một chỗ thì không ai biết. Nay
     * con số nằm trong `config/rate_limit.php`, đổi bằng .env, tắt hẳn bằng một dòng.
     *
     * Tên các nhóm dùng thẳng trong route: `throttle:login`, `throttle:email`...
     */
    private function dangKyHanMuc(): void
    {
        foreach (['api', 'login', 'register', 'email', 'reset'] as $nhom) {
            RateLimiter::for($nhom, fn (Request $request) => $this->hanMuc($nhom, $request));
        }
    }

    private function hanMuc(string $nhom, Request $request): Limit
    {
        if (! config('rate_limit.enabled', true)) {
            return Limit::none();
        }

        // Dạng "số lần,số phút". Thiếu vế sau thì hiểu là mỗi phút, giống cú pháp `throttle:` gốc.
        [$soLan, $soPhut] = array_pad(
            explode(',', (string) config("rate_limit.{$nhom}", '60,1')),
            2,
            '1',
        );

        return Limit::perMinutes(max(1, (int) $soPhut), max(1, (int) $soLan))
            // Đã đăng nhập thì đếm theo tài khoản: cả văn phòng ngồi chung một IP ra Internet, đếm
            // theo IP là người này bấm nhiều làm người kia bị chặn.
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip());
    }
}
