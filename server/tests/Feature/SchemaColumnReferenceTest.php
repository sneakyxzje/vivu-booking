<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Mọi tên cột nhắc trong mã phải có thật trong lược đồ.
 *
 * ## Vì sao cần một bài riêng cho việc này
 *
 * Bộ kiểm thử chạy SQLite, máy chạy thật dùng MySQL. Hai hệ xử lý một định danh không tồn tại theo
 * hai cách hoàn toàn khác nhau:
 *
 *   - **SQLite**: một định danh trong nháy kép mà không khớp cột nào thì được hiểu thành CHUỖI.
 *     `order by "min_hours_before"` trở thành sắp theo một hằng số — không lỗi, không cảnh báo, chỉ
 *     là không sắp gì cả. `select "expected_at"` trả về đúng chữ `expected_at`.
 *   - **MySQL**: định danh bọc bằng dấu huyền, không có đường lùi nào. Lỗi 1054, cả điểm cuối chết.
 *
 * Nghĩa là một câu truy vấn hỏng có thể đi qua **toàn bộ** bộ kiểm thử mà vẫn xanh, rồi vỡ đúng lúc
 * demo trên máy MySQL. Dự án này đã dính đúng hai lần: bản in hợp đồng (`min_hours_before`, cột đã
 * bị bỏ khi bậc phí đổi từ giờ sang ngày) và báo cáo điểm danh sau chuyến (`expected_at`, cột chưa
 * bao giờ được tạo).
 *
 * Bài này quét mã nguồn thay vì chạy truy vấn, nên nó bắt được lỗi ở cả những nhánh không có bài
 * kiểm thử nào đi qua.
 */
class SchemaColumnReferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Những chuỗi trông giống `quan_he:cot1,cot2` nhưng không phải.
     *
     * Luật kiểm tra dữ liệu của Laravel dùng chung cú pháp hai chấm — `in:cash,bank_transfer`,
     * `exists:users,id` — nên phải loại ra, nếu không mọi giá trị enum đều bị báo là cột lạ.
     *
     * @var array<int, string>
     */
    private const TIEN_TO_KHONG_PHAI_QUAN_HE = [
        'in', 'not_in', 'exists', 'unique', 'required_with', 'required_without', 'required_if',
        'after', 'after_or_equal', 'before', 'before_or_equal', 'date_format', 'mimes', 'mimetypes',
        'regex', 'starts_with', 'ends_with', 'between', 'digits_between', 'size', 'max', 'min',
        'different', 'same', 'gt', 'gte', 'lt', 'lte', 'http', 'https', 'H', 'Y', 'd', 'i',
    ];

    /** @return array<string, true> */
    private function moiCotTrongLuocDo(): array
    {
        $cot = [];

        foreach (Schema::getTables() as $bang) {
            $ten = is_array($bang) ? ($bang['name'] ?? null) : ($bang->name ?? null);

            if (!$ten) {
                continue;
            }

            foreach (Schema::getColumnListing($ten) as $c) {
                $cot[$c] = true;
            }
        }

        return $cot;
    }

    /** @return array<int, \SplFileInfo> */
    private function tepPhp(string $thuMuc): array
    {
        $ds = [];
        $duyet = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($thuMuc));

        foreach ($duyet as $tep) {
            if (!$tep->isDir() && $tep->getExtension() === 'php') {
                $ds[] = clone $tep;
            }
        }

        return $ds;
    }

    /**
     * `with(['quanHe:cot1,cot2'])` chỉ được liệt kê cột có thật.
     *
     * Đây là dạng đã làm chết báo cáo điểm danh: cột lạ nằm trong danh sách chọn.
     */
    public function test_danh_sach_cot_trong_eager_load_deu_ton_tai(): void
    {
        $cot = $this->moiCotTrongLuocDo();
        $viPham = [];

        foreach ($this->tepPhp(app_path()) as $tep) {
            $dong = explode("\n", (string) file_get_contents($tep->getPathname()));

            foreach ($dong as $i => $line) {
                if (!preg_match_all("/'([A-Za-z_][A-Za-z_.]*):([A-Za-z_][A-Za-z_,]*)'/", $line, $khop, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($khop as $mot) {
                    if (in_array($mot[1], self::TIEN_TO_KHONG_PHAI_QUAN_HE, true)) {
                        continue;
                    }

                    foreach (explode(',', $mot[2]) as $ten) {
                        $ten = trim($ten);

                        if ($ten === '' || $ten === '*' || isset($cot[$ten])) {
                            continue;
                        }

                        $viPham[] = sprintf(
                            '%s:%d nhắc cột "%s" (trong "%s") — không có cột này trong lược đồ.',
                            str_replace(base_path() . DIRECTORY_SEPARATOR, '', $tep->getPathname()),
                            $i + 1,
                            $ten,
                            $mot[0],
                        );
                    }
                }
            }
        }

        $this->assertSame([], $viPham, "\n" . implode("\n", $viPham) . "\n");
    }

    /**
     * `orderBy('cot')` và họ hàng chỉ được nhắc cột có thật.
     *
     * Đây là dạng đã làm chết bản in hợp đồng: cột lạ nằm trong mệnh đề sắp xếp — chỗ SQLite im
     * lặng nhất, vì sắp theo một hằng số không đổi thứ tự gì cả nên kết quả trông vẫn hợp lý.
     *
     * Bí danh do `withCount`/`withSum` dựng ra (`tours_count`, `total_booked`...) là cột thật tại
     * thời điểm truy vấn chạy, nên chúng được bỏ qua theo hậu tố.
     */
    public function test_cot_trong_order_by_deu_ton_tai(): void
    {
        $cot = $this->moiCotTrongLuocDo();
        $viPham = [];

        $laBiDanhTinhToan = static fn (string $ten): bool => str_ends_with($ten, '_count')
            || str_ends_with($ten, '_sum')
            || str_ends_with($ten, '_avg')
            || str_starts_with($ten, 'total_');

        foreach ($this->tepPhp(app_path()) as $tep) {
            $dong = explode("\n", (string) file_get_contents($tep->getPathname()));

            foreach ($dong as $i => $line) {
                $mau = "/->(orderBy|orderByDesc|whereNull|whereNotNull|latest|oldest)\('([A-Za-z_][A-Za-z_]*)'\)/";

                if (!preg_match_all($mau, $line, $khop, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($khop as $mot) {
                    $ten = $mot[2];

                    if (isset($cot[$ten]) || $laBiDanhTinhToan($ten)) {
                        continue;
                    }

                    $viPham[] = sprintf(
                        '%s:%d gọi %s(\'%s\') — không có cột này trong lược đồ.',
                        str_replace(base_path() . DIRECTORY_SEPARATOR, '', $tep->getPathname()),
                        $i + 1,
                        $mot[1],
                        $ten,
                    );
                }
            }
        }

        $this->assertSame([], $viPham, "\n" . implode("\n", $viPham) . "\n");
    }
}
