<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Mọi đường dẫn máy chủ gắn vào thông báo phải là một màn hình có thật.
 *
 * ## Vì sao cần một bài kiểm riêng
 *
 * Thông báo mang theo một `url` để người đọc bấm thẳng vào chỗ xử lý việc đó. Địa chỉ ấy là chuỗi
 * viết tay trong PHP, còn bộ định tuyến lại nằm ở `client/src/App.tsx` — hai tệp không có gì ràng
 * buộc nhau. Gõ sai một đoạn thì không trình biên dịch nào kêu, không bài kiểm nào đỏ, và lỗi chỉ
 * lộ ra khi có người thật bấm vào thông báo rồi rơi vào trang 404.
 *
 * Đó chính là chuyện đã xảy ra: hai chỗ sinh `/admin/schedules/{id}` trong khi giao diện chỉ có
 * `/admin/schedules` dạng danh sách. Cùng khuôn lỗi với hai câu truy vấn hỏi cột không tồn tại —
 * một bên khai, một bên dùng, và không ai bắt tay hai bên lại.
 *
 * ## Cách kiểm
 *
 * Quét mọi chuỗi bắt đầu bằng `/admin/` hoặc `/guide/` trong `app/`, rồi so với danh sách tuyến
 * dưới đây. Chuỗi kết thúc bằng dấu gạch chéo là phần đầu của một địa chỉ được ghép thêm id, nên
 * ghép thử một id vào rồi mới so.
 *
 * Thêm màn hình mới thì thêm một dòng vào `TUYEN_GIAO_DIEN`. Việc phải sửa danh sách này chính là
 * lúc người ta dừng lại và tự hỏi màn hình ấy đã có thật chưa.
 */
class NotificationUrlReferenceTest extends TestCase
{
    /**
     * Bộ định tuyến của giao diện, chép từ `client/src/App.tsx`.
     *
     * `:tham_so` khớp với một đoạn bất kỳ không chứa dấu gạch chéo.
     */
    private const TUYEN_GIAO_DIEN = [
        '/admin/dashboard',
        '/admin/tours',
        '/admin/tours/create',
        '/admin/tours/:id/edit',
        '/admin/tours/:id',
        '/admin/tour-schedules/:id/attendance',
        '/admin/attendance-reports',
        '/admin/schedules',
        '/admin/notifications',
        '/admin/audit-logs',
        '/admin/incidents',
        '/admin/handovers',
        '/admin/change-requests',
        '/admin/cancellation-policies',
        '/admin/bookings',
        '/admin/group-bookings',
        '/admin/discount-codes',
        '/admin/guides',
        '/admin/services',
        '/admin/categories',
        '/admin/reviews',
        '/admin/users',
        '/admin/transactions',
        '/admin/refunds',
        '/admin/receivables',
        '/admin/contact-messages',
        '/guide/dashboard',
        '/guide/assignments',
        '/guide/tours',
        '/guide/bookings',
        '/guide/attendance/:id',
        '/guide/incidents',
        '/guide/handovers',
        '/guide/notifications',
    ];

    public function test_moi_duong_dan_trong_thong_bao_deu_tro_toi_mot_man_hinh_co_that(): void
    {
        /*
         * Thay tham số bằng một dấu hiệu TRƯỚC khi thoát, không phải sau.
         *
         * `preg_quote` thoát cả dấu hai chấm, nên `:id` thành `\:id`; thay sau đó thì còn lại dấu
         * gạch chéo ngược dính vào `[^/]+` và biến nó thành một dấu ngoặc vuông theo nghĩa đen.
         * Biểu thức vẫn hợp lệ nên không có lỗi nào được ném ra — nó chỉ lặng lẽ không khớp gì.
         *
         * Dấu hiệu phải là chữ cái. Ký tự NUL thì `preg_quote` đổi thành chuỗi `\000`, và lúc ấy
         * không còn gì để tìm mà thay lại — cùng một cái bẫy, chỉ khác chỗ.
         */
        $mau = array_map(
            static function (string $tuyen): string {
                $coCho = preg_replace('#:[a-zA-Z_]+#', 'ZTHAMSOZ', $tuyen);

                return '#^' . str_replace('ZTHAMSOZ', '[^/]+', preg_quote($coCho, '#')) . '$#';
            },
            self::TUYEN_GIAO_DIEN,
        );

        $loi = [];

        foreach ($this->tepPhp(app_path()) as $tep) {
            foreach (file($tep) as $soDong => $dong) {
                // Bỏ qua dòng chú thích: địa chỉ nhắc trong lời giải thích không phải mã chạy.
                $sach = ltrim($dong);
                if ($sach === '' || str_starts_with($sach, '*') || str_starts_with($sach, '//') || str_starts_with($sach, '/*')) {
                    continue;
                }

                /*
                 * Lấy MỌI chuỗi trên dòng, theo thứ tự, rồi mới dựng lại địa chỉ.
                 *
                 * Địa chỉ hay được ghép từ ba mảnh: `'/admin/tour-schedules/' . $id . '/attendance'`.
                 * Chỉ soi mảnh đầu thì phần đuôi biến mất và bài kiểm báo sai chỗ đúng.
                 */
                if (!preg_match_all("#'([^']*)'#", $dong, $khop)) {
                    continue;
                }

                $chuoi = $khop[1];

                foreach ($chuoi as $i => $duongDan) {
                    if (!str_starts_with($duongDan, '/admin/') && !str_starts_with($duongDan, '/guide/')) {
                        continue;
                    }

                    $thu = $duongDan;

                    // Kết thúc bằng "/" nghĩa là ghép thêm một id, rồi có thể còn một đoạn đuôi nữa.
                    if (str_ends_with($thu, '/')) {
                        $thu .= '1';

                        $ke = $chuoi[$i + 1] ?? '';
                        if (str_starts_with($ke, '/')) {
                            $thu .= $ke;
                        }
                    }

                    foreach ($mau as $m) {
                        if (preg_match($m, $thu)) {
                            continue 2;
                        }
                    }

                    $loi[] = sprintf(
                        '%s:%d — "%s" không khớp tuyến nào của giao diện.',
                        str_replace(base_path() . DIRECTORY_SEPARATOR, '', $tep),
                        $soDong + 1,
                        $thu,
                    );
                }
            }
        }

        $this->assertSame([], $loi, "Đường dẫn dẫn tới trang 404:\n" . implode("\n", $loi));
    }

    /** @return iterable<string> */
    private function tepPhp(string $thuMuc): iterable
    {
        $duyet = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($thuMuc, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($duyet as $tep) {
            if ($tep->isFile() && $tep->getExtension() === 'php') {
                yield $tep->getPathname();
            }
        }
    }
}
