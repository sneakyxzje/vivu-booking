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
 *
 * ## Vì sao đội ngũ phải đông
 *
 * Danh mục tour trải khắp ba miền và có những ngày ba bốn chuyến khởi hành cùng lúc. Đội năm
 * người thì màn xếp người luôn trả về "không còn ai rảnh" — người thử tay không phân biệt được
 * đó là luật chặn trùng lịch đang chạy đúng hay là dữ liệu quá mỏng.
 *
 * Hồ sơ cố ý lệch nhau ở cả bốn trục mà màn xếp người chấm điểm: vùng quen, loại hình, ngoại
 * ngữ, sức dẫn. Có vậy mới thấy thứ tự gợi ý đổi khi chuyển từ tour biển sang tour núi.
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

            /*
             * Bảy người tiếp theo phủ nốt những vùng và loại hình mà danh mục tour có mà đội cũ
             * không ai quen — miền Tây, Tây Nguyên, Côn Đảo, Quy Nhơn — để mọi tour đều có ít
             * nhất một người "quen tuyến", chứ không phải tour nào cũng xếp bừa.
             */
            [
                'email' => 'minhtam.guide@gmail.com', 'name' => 'Lê Minh Tâm', 'phone' => '0912004506',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Anh'],
                    'regions' => ['Cần Thơ', 'Châu Đốc', 'Cà Mau'],
                    'max_group_size' => 30,
                    'note' => 'Chuyên tuyến miền Tây sông nước, đi được cung ghép nhiều tỉnh.',
                ],
                'loai_hinh' => ['kham-pha', 'nghi-duong'],
            ],
            [
                'email' => 'quocbao.guide@gmail.com', 'name' => 'Trần Quốc Bảo', 'phone' => '0912004507',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Anh', 'Tiếng Nhật'],
                    'regions' => ['Buôn Ma Thuột', 'Pleiku', 'Kon Tum'],
                    'max_group_size' => 22,
                    'note' => 'Tuyến Tây Nguyên, quen cung dài ngày và đường đèo.',
                ],
                'loai_hinh' => ['kham-pha'],
            ],
            [
                'email' => 'thuyduong.guide@gmail.com', 'name' => 'Ngô Thùy Dương', 'phone' => '0912004508',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Hàn', 'Tiếng Anh'],
                    'regions' => ['Quy Nhơn', 'Phú Yên', 'Nha Trang'],
                    'max_group_size' => 28,
                ],
                'loai_hinh' => ['bien-dao', 'nghi-duong'],
            ],
            [
                'email' => 'huutin.guide@gmail.com', 'name' => 'Phan Hữu Tín', 'phone' => '0912004509',
                'ho_so' => [
                    'languages' => ['Tiếng Việt'],
                    'regions' => ['Côn Đảo', 'Vũng Tàu', 'Phú Quốc'],
                    'max_group_size' => 18,
                    'note' => 'Quen tuyến đảo phía Nam, có chứng chỉ cứu hộ đường thủy.',
                ],
                'loai_hinh' => ['bien-dao'],
            ],
            /*
             * Sức dẫn 50 — người duy nhất nhận nổi đoàn đông nhất trong danh mục. Có anh ta thì
             * cảnh báo "đoàn vượt sức dẫn" mới có đường thoát, thay vì bế tắc ở mọi lựa chọn.
             */
            [
                'email' => 'anhkhoa.guide@gmail.com', 'name' => 'Hồ Anh Khoa', 'phone' => '0912004510',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Anh', 'Tiếng Pháp'],
                    'regions' => ['Hà Nội', 'Ninh Bình', 'Hạ Long', 'Sapa'],
                    'max_group_size' => 50,
                    'note' => 'Dẫn đoàn lớn và đoàn khách công ty.',
                ],
                'loai_hinh' => ['bien-dao', 'nghi-duong', 'kham-pha'],
            ],
            /*
             * Sức dẫn 12 — đầu còn lại của thang đo, để thử cảnh báo bật lên ở đoàn cỡ trung chứ
             * không chỉ ở đoàn thật đông.
             */
            [
                'email' => 'khanhly.guide@gmail.com', 'name' => 'Đinh Khánh Ly', 'phone' => '0912004511',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Trung'],
                    'regions' => ['Huế', 'Quảng Bình', 'Hội An'],
                    'max_group_size' => 12,
                    'note' => 'Nhận đoàn nhỏ, tour riêng và khách gia đình.',
                ],
                'loai_hinh' => ['nghi-duong'],
            ],
            [
                'email' => 'vanphuc.guide@gmail.com', 'name' => 'Mai Văn Phúc', 'phone' => '0912004512',
                'ho_so' => [
                    'languages' => ['Tiếng Việt', 'Tiếng Nga'],
                    'regions' => ['Nha Trang', 'Đà Lạt', 'Mũi Né'],
                    'max_group_size' => 35,
                ],
                'loai_hinh' => ['bien-dao', 'nghi-duong'],
            ],

            /*
             * Hai người NGHỈ VIỆC.
             *
             * Mọi đường xếp người đều lọc `status = active`, nên không có ai ở trạng thái nghỉ thì
             * bộ lọc ấy chạy mà không ai biết nó có chạy hay không. Hai người này cũng để thử màn
             * quản lý tài khoản: khóa rồi thì biến khỏi danh sách phân công nhưng lịch sử chuyến
             * cũ vẫn còn tên.
             */
            [
                'email' => 'baochau.guide@gmail.com', 'name' => 'Lý Bảo Châu', 'phone' => '0912004513',
                'trang_thai' => 'inactive',
                'ho_so' => [
                    'languages' => ['Tiếng Việt'],
                    'regions' => ['Hạ Long'],
                    'max_group_size' => 20,
                    'note' => 'Đã nghỉ việc từ tháng trước.',
                ],
                'loai_hinh' => ['bien-dao'],
            ],
            [
                'email' => 'tiendat.guide@gmail.com', 'name' => 'Vương Tiến Đạt', 'phone' => '0912004514',
                'trang_thai' => 'inactive',
            ],
        ];

        foreach ($doiNgu as $nguoi) {
            $daCo = User::query()->where('email', $nguoi['email'])->first();

            $guide = User::query()->updateOrCreate(
                ['email' => $nguoi['email']],
                [
                    'name' => $nguoi['name'],
                    'phone' => $nguoi['phone'],
                    'role' => 'guide',
                    'status' => $nguoi['trang_thai'] ?? 'active',
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
            'Đã có %d hướng dẫn viên (%d đang làm, %d đã nghỉ). Đăng nhập: guide@gmail.com / %s',
            User::query()->where('role', 'guide')->count(),
            User::query()->where('role', 'guide')->where('status', 'active')->count(),
            User::query()->where('role', 'guide')->where('status', '!=', 'active')->count(),
            self::MAT_KHAU,
        ));
    }
}
