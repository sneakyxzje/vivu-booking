<?php

namespace Database\Seeders;

use App\Models\Category;
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
        // Loại hình chuyên tham chiếu tới danh mục thật. Gọi lại ở đây để chạy lẻ
        // `--class=GuideSeeder` vẫn gắn được chuyên môn; seeder đó idempotent.
        $this->call(CategorySeeder::class);

        /*
         * Hồ sơ năng lực đặt lệch nhau để màn xếp người **có gì đó để so**, chứ năm dòng giống hệt
         * nhau thì không minh họa được gì:
         *
         *   - Hoàng Long và Hoài Anh quen tuyến biển đảo phía Bắc và phía Nam, để thấy điểm cộng
         *     "quen tuyến" bật lên đúng tour và tắt ở tour khác.
         *   - Tuấn Kiệt chuyên khám phá, quen tuyến núi - người để so khi xếp tour Hạ Long: rảnh
         *     nhưng không có điểm nào khớp, nên tụt xuống dưới mà vẫn chọn được.
         *   - Phương Thảo sức dẫn 25, dùng để thấy cảnh báo bật lên khi đoàn đông hơn - và bấm
         *     vẫn được, vì đó là cảnh báo chứ không phải luật.
         *   - Gia Huy **cố ý để trống hồ sơ**, để thấy người chưa khai vẫn nằm trong danh sách,
         *     chỉ là không được cộng điểm nào.
         */
        $doiNgu = [
            // Người đã có sẵn, chỉ đặt lại tên và số điện thoại.
            [
                'email' => 'guide@gmail.com', 'name' => 'Phạm Hoàng Long', 'phone' => '0912004501',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Anh'],
                    'regions' => ['Hạ Long', 'Ninh Bình', 'Cát Bà'],
                    'max_group_size' => 35,
                    'note' => 'Dẫn tuyến vịnh Bắc Bộ từ 2019.',
                ],
                'loai_hinh' => ['bien-dao', 'nghi-duong'],
            ],
            [
                'email' => 'hoaianh.guide@gmail.com', 'name' => 'Đỗ Hoài Anh', 'phone' => '0912004502',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Anh', 'Tiếng Trung'],
                    'regions' => ['Phú Quốc', 'Nha Trang', 'Đà Nẵng'],
                    'max_group_size' => 40,
                ],
                'loai_hinh' => ['bien-dao'],
            ],
            [
                'email' => 'tuankiet.guide@gmail.com', 'name' => 'Vũ Tuấn Kiệt', 'phone' => '0912004503',
                'ho_so' => [
                    'languages' => ['Tiếng Việt'],
                    'regions' => ['Sapa', 'Hà Giang', 'Mộc Châu'],
                    'max_group_size' => 20,
                    'note' => 'Quen tuyến núi phía Bắc, đi được cung dài ngày.',
                ],
                'loai_hinh' => ['kham-pha'],
            ],
            [
                'email' => 'phuongthao.guide@gmail.com', 'name' => 'Bùi Phương Thảo', 'phone' => '0912004504',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Hàn'],
                    'regions' => ['Đà Nẵng', 'Hội An', 'Huế'],
                    'max_group_size' => 25,
                ],
                'loai_hinh' => ['nghi-duong', 'kham-pha'],
            ],
            // Cố ý không có hồ sơ.
            ['email' => 'giahuy.guide@gmail.com', 'name' => 'Đặng Gia Huy', 'phone' => '0912004505'],
        ];

        foreach ($doiNgu as $nguoi) {
            $daCo = User::query()->where('email', $nguoi['email'])->first();

            $guide = User::query()->updateOrCreate(
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

            if (!isset($nguoi['ho_so'])) {
                continue;
            }

            $guide->guideProfile()->updateOrCreate(
                ['user_id' => $guide->getKey()],
                $nguoi['ho_so'],
            );

            $loaiHinh = Category::query()
                ->whereIn('slug', $nguoi['loai_hinh'] ?? [])
                ->pluck('id')
                ->all();

            $guide->guideCategories()->sync($loaiHinh);
        }

        $this->command?->info(sprintf(
            'Đã có %d hướng dẫn viên. Đăng nhập: guide@gmail.com / %s',
            User::query()->where('role', 'guide')->count(),
            self::MAT_KHAU,
        ));
    }
}
