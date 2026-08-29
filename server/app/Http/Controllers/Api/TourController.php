<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Models\Review;
use App\Models\Tour;
use App\Services\BookingHoldService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{
    public function __construct(private BookingHoldService $holdService)
    {
    }

    /** Số tour mỗi trang khi lời gọi không nói gì. Ba hàng ba cột trên màn hình rộng. */
    private const PER_PAGE_MAC_DINH = 12;

    /** Chặn trên của `per_page`, để một lời gọi không kéo được cả bảng về. */
    private const PER_PAGE_TOI_DA = 48;

    /**
     * Danh sách tour kèm tìm kiếm, lọc và sắp xếp.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'start_location' => ['nullable', 'string', 'max:255'],
            'categories' => ['nullable', 'string'],
            'services' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'in:all,1,2-3,4+'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            // Khoảng ngày khách rảnh. Lọc theo chuyến CÒN ĐẶT ĐƯỢC trong khoảng đó, xem dưới.
            'departure_from' => ['nullable', 'date'],
            'departure_to' => ['nullable', 'date', 'after_or_equal:departure_from'],
            'sort' => ['nullable', 'string', 'in:featured,price_asc,price_desc,rating,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::PER_PAGE_TOI_DA],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'departure_to.after_or_equal' => 'Ngày về phải từ ngày đi trở đi.',
        ]);

        /*
         * Điểm đánh giá trung bình, dựng một lần rồi dùng lại cho cả lọc lẫn sắp xếp.
         *
         * Trước đây hai việc ấy làm bằng PHP sau khi đã `->get()` toàn bộ, với lý do cho SQLite và
         * MySQL chạy giống nhau. Cách ấy không sống chung được với phân trang: lọc sau khi cắt
         * trang thì trang 1 có 12 kết quả, trang 2 có 4, và tổng số đếm được không phải tổng thật.
         *
         * Truy vấn con dưới đây chạy như nhau trên cả hai hệ, và Laravel nhận nó ở cả `where` lẫn
         * `orderBy`, nên không cần `HAVING` — thứ mà SQLite chỉ chấp nhận khi có `GROUP BY`.
         */
        $diemTrungBinh = Review::query()
            ->approved()
            ->selectRaw('coalesce(avg(rating), 0)')
            ->whereColumn('reviews.tour_id', 'tours.id');

        $query = Tour::query()
            ->with([
                'admin',
                'categories',
                'services',
                'images',
                'itineraries',
                'schedules',
            ])
            ->withAvg('approvedReviews as rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->whereIn('status', ['active', 'full']);

        if (!empty($filters['q'])) {
            $keyword = trim($filters['q']);
            if (mb_strlen($keyword) < 2) {
                return response()->json([
                    'success' => true,
                    'message' => 'Từ khóa tìm kiếm cần ít nhất 2 ký tự',
                    'data' => [],
                    // Kèm `meta` cả ở nhánh này để giao diện không phải xử lý hai hình dạng
                    // phản hồi khác nhau cho cùng một điểm cuối.
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => (int) ($filters['per_page'] ?? self::PER_PAGE_MAC_DINH),
                        'total' => 0,
                    ],
                ]);
            }

            // Chỉ tìm theo tên tour và điểm đi/đến (đúng như gợi ý của ô tìm kiếm).
            // Không quét mô tả, danh mục hay dịch vụ vì gõ "ăn sáng" sẽ ra gần hết tour.
            $query->where(function ($builder) use ($keyword) {
                $builder->where('title', 'like', "%{$keyword}%")
                    ->orWhere('start_location', 'like', "%{$keyword}%")
                    ->orWhere('end_location', 'like', "%{$keyword}%");
            });
        }

        if (!empty($filters['start_location'])) {
            $startLocation = trim($filters['start_location']);
            $query->where('start_location', 'like', "%{$startLocation}%");
        }
        $categorySlugs = $this->parseCsvFilter($filters['categories'] ?? null);
        if ($categorySlugs) {
            $query->whereHas('categories', function ($categoryQuery) use ($categorySlugs) {
                $categoryQuery->whereIn('slug', $categorySlugs);
            });
        }

        $serviceIds = $this->parseCsvFilter($filters['services'] ?? null);
        if ($serviceIds) {
            $query->whereHas('services', function ($serviceQuery) use ($serviceIds) {
                $serviceQuery->whereIn('services.id', $serviceIds);
            });
        }

        if (($filters['duration'] ?? 'all') !== 'all') {
            match ($filters['duration']) {
                '1' => $query->where('number_of_days', 1),
                '2-3' => $query->whereBetween('number_of_days', [2, 3]),
                '4+' => $query->where('number_of_days', '>=', 4),
                default => null,
            };
        }

        if (isset($filters['min_price'])) {
            $query->where('adult_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && (float) $filters['max_price'] > 0) {
            $query->where('adult_price', '<=', $filters['max_price']);
        }

        if (isset($filters['min_rating']) && (float) $filters['min_rating'] > 0) {
            $query->where($diemTrungBinh, '>=', (float) $filters['min_rating']);
        }

        /*
         * "Tôi rảnh từ ngày X tới ngày Y, có tour nào đi không."
         *
         * Điều kiện đặt lên CHUYẾN chứ không lên tour, và chuyến ấy phải còn đặt được — bằng đúng
         * bộ luật của `TourSchedule::isBookable()`, xem `scopeBookable`. Lọc theo chuyến bất kỳ
         * trong khoảng ngày thì kết quả trả về cả tour mà chuyến duy nhất trong khoảng đó đã đầy
         * chỗ hoặc đã qua hạn chốt: khách bấm vào và không đặt được, tức bộ lọc nói dối.
         *
         * Chỉ khai một đầu cũng chạy: "từ 20/12" nghĩa là từ đó trở đi, "tới 31/12" nghĩa là mọi
         * chuyến còn bán từ giờ tới hết ngày đó.
         */
        if (!empty($filters['departure_from']) || !empty($filters['departure_to'])) {
            $query->whereHas('schedules', function ($scheduleQuery) use ($filters) {
                $scheduleQuery->bookable();

                if (!empty($filters['departure_from'])) {
                    $scheduleQuery->whereDate('start_date', '>=', $filters['departure_from']);
                }

                if (!empty($filters['departure_to'])) {
                    $scheduleQuery->whereDate('start_date', '<=', $filters['departure_to']);
                }
            });
        }

        match ($filters['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('adult_price')->orderByDesc('created_at'),
            'price_desc' => $query->orderByDesc('adult_price')->orderByDesc('created_at'),
            'rating' => $query->orderByDesc($diemTrungBinh)->orderByDesc('created_at'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $tours = $query
            ->paginate($filters['per_page'] ?? self::PER_PAGE_MAC_DINH)
            ->withQueryString();

        /*
         * `data` vẫn là mảng phẳng như trước, phân trang đi ở khóa `meta` bên cạnh.
         *
         * Nhét cả đối tượng phân trang vào `data` cũng được, nhưng khi ấy mọi nơi đang đọc
         * `data` như một danh sách - trang chủ, ô gợi ý tìm kiếm - đều phải sửa cùng lúc, và
         * chúng chỉ cần vài tour đầu chứ không quan tâm trang thứ mấy.
         */
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tour thành công',
            'data' => TourResource::collection($tours->getCollection()),
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
            ],
        ]);
    }

    /**
     * Chi tiết tour, tra được bằng id hoặc bằng slug.
     *
     * Slug là dạng dùng trên đường dẫn kể từ khi trang chi tiết đổi sang `/tours/tour-ha-long-3n2d`
     * — địa chỉ đọc được là thứ người ta dán cho nhau và là thứ Google xếp hạng, còn `/tours/17`
     * thì không nói gì về nội dung trang.
     *
     * Vẫn nhận id vì hai lý do: mọi liên kết đã gửi đi trước đây đều ở dạng số, và các màn hình
     * nội bộ (điều hành, hướng dẫn viên) chỉ cầm id trong tay.
     */
    public function show(string $idOrSlug): JsonResponse
    {
        $laSo = ctype_digit($idOrSlug);

        // Nhả chỗ của các đơn quá hạn trước khi trả dữ liệu, để số
        // "còn lại X chỗ" khách nhìn thấy luôn là số thật.
        $tourId = $laSo
            ? (int) $idOrSlug
            : (int) Tour::query()->where('slug', $idOrSlug)->value('id');

        if ($tourId) {
            $this->holdService->releaseOverdueForTour($tourId);
        }

        $tour = Tour::with([
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules',
            'schedules.guides:id,name',
            // Khách phải đọc được điều khoản hủy trước khi đặt, không phải sau khi muốn hủy.
            'cancellationPolicy.rules',
        ])
            ->withAvg('approvedReviews as rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->whereIn('status', ['active', 'full'])
            ->when($laSo, fn ($q) => $q->whereKey((int) $idOrSlug))
            ->when(!$laSo, fn ($q) => $q->where('slug', $idOrSlug))
            ->first();

        if (!$tour) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tour'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết tour',
            'data' => new TourResource($tour)
        ]);
    }

    private function parseCsvFilter(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}