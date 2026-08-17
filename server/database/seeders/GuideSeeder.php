<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Đội hướng dẫn viên để thử tay.
 *
 * Trước đây hệ thống chỉ có đúng một hướng dẫn viên tên "Guide User", sinh ra từ migration
 * 2026_06_17_163237. Một người thì không thử được gì: không thấy được chuyến nhiều người dẫn,
 * cũng không thấy được luật chặn trùng lịch, vì muốn chặn thì phải có người thứ hai để so.
 *
 * Đổi tên người cũ nhưng **giữ nguyên email** guide@gmail.com, vì đó là tài khoản đăng nhập ghi
 * trong tài liệu và trong phần hướng dẫn thử tay. Đổi email là làm hỏng mọi chỗ đang trỏ tới.
 *
 * Mật khẩu đồng loạt guide123 cho dễ nhớ khi demo.
 */
class GuideSeeder extends Seeder
{
    private const MAT_KHAU = 'guide123';

    public function run(): void
    {
        $doiNgu = [
            // Người đã có sẵn, chỉ đặt lại tên và số điện thoại.
            ['email' => 'guide@gmail.com', 'name' => 'Phạm Hoàng Long', 'phone' => '0912004501'],

            ['email' => 'hoaianh.guide@gmail.com', 'name' => 'Đỗ Hoài Anh', 'phone' => '0912004502'],
            ['email' => 'tuankiet.guide@gmail.com', 'name' => 'Vũ Tuấn Kiệt', 'phone' => '0912004503'],
            ['email' => 'phuongthao.guide@gmail.com', 'name' => 'Bùi Phương Thảo', 'phone' => '0912004504'],
            ['email' => 'giahuy.guide@gmail.com', 'name' => 'Đặng Gia Huy', 'phone' => '0912004505'],
        ];

        foreach ($doiNgu as $nguoi) {
            $daCo = User::query()->where('email', $nguoi['email'])->first();

            User::query()->updateOrCreate(
                ['email' => $nguoi['email']],
                [
                    'name' => $nguoi['name'],
                    'phone' => $nguoi['phone'],
                    'role' => 'guide',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    // Không đặt lại mật khẩu của tài khoản đã có: máy nào đang dùng để đăng nhập
                    // thì vẫn đăng nhập được bằng mật khẩu cũ.
                    ...($daCo ? [] : ['password' => Hash::make(self::MAT_KHAU)]),
                ]
            );
        }

        $this->command?->info(sprintf(
            'Đã có %d hướng dẫn viên. Đăng nhập: guide@gmail.com / %s',
            User::query()->where('role', 'guide')->count(),
            self::MAT_KHAU,
        ));
    }
}
