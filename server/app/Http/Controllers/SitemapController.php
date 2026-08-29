<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Http\Response;

/**
 * Sơ đồ trang cho công cụ tìm kiếm.
 *
 * ## Vì sao nó nằm ở máy chủ chứ không phải một tệp tĩnh
 *
 * Danh sách tour đổi mỗi lần điều hành thêm, ngừng bán hay xóa một tour. Một tệp `sitemap.xml`
 * viết tay là thứ luôn cũ hơn thực tế, và cái sai của nó im lặng: Google vẫn đi theo, gặp 404 ở
 * những tour đã gỡ, rồi giảm tin cậy vào cả sơ đồ.
 *
 * ## Địa chỉ trong sơ đồ là địa chỉ của GIAO DIỆN
 *
 * Máy chủ này chỉ trả JSON, không có trang nào cho người đọc. Thứ cần được lập chỉ mục là ứng dụng
 * React, nên mọi `<loc>` dựng từ `config('app.frontend_url')`.
 *
 * ## Giới hạn còn lại, nói thẳng
 *
 * Giao diện là ứng dụng một trang: máy tìm kiếm nào không chạy JavaScript sẽ chỉ thấy khung HTML
 * rỗng dù có sơ đồ trang. Muốn xếp hạng thật thì phải dựng sẵn HTML (prerender hoặc render phía
 * máy chủ) - đó là một việc khác, không phải việc của tệp này.
 */
class SitemapController extends Controller
{
    /** Trang tĩnh, kèm mức ưu tiên và tần suất đổi nội dung ước lượng. */
    private const TRANG_TINH = [
        ['', '1.0', 'daily'],
        ['/tours', '0.9', 'daily'],
        ['/group-booking', '0.5', 'monthly'],
        ['/about', '0.3', 'yearly'],
        ['/contact', '0.3', 'yearly'],
        ['/chinh-sach', '0.3', 'monthly'],
    ];

    public function __invoke(): Response
    {
        $goc = rtrim(config('app.frontend_url'), '/');

        $urls = [];

        foreach (self::TRANG_TINH as [$duongDan, $uuTien, $tanSuat]) {
            $urls[] = [
                'loc' => $goc . $duongDan,
                'priority' => $uuTien,
                'changefreq' => $tanSuat,
                'lastmod' => null,
            ];
        }

        // Chỉ tour đang bán. Tour `inactive` hoặc đã xóa mềm không được nằm trong sơ đồ: dẫn máy
        // tìm kiếm tới một trang trả 404 là tự hạ điểm chính mình.
        Tour::query()
            ->whereIn('status', ['active', 'full'])
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Tour $tour) use (&$urls, $goc) {
                $urls[] = [
                    'loc' => $goc . '/tours/' . $tour->slug,
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                    'lastmod' => $tour->updated_at?->toAtomString(),
                ];
            });

        Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['slug'])
            ->each(function (Category $category) use (&$urls, $goc) {
                $urls[] = [
                    'loc' => $goc . '/tours?categories=' . $category->slug,
                    'priority' => '0.6',
                    'changefreq' => 'weekly',
                    'lastmod' => null,
                ];
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
