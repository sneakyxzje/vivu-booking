<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use App\Services\BookingHoldService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{
    public function __construct(private BookingHoldService $holdService)
    {
    }

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
            'sort' => ['nullable', 'string', 'in:featured,price_asc,price_desc,rating,newest'],
        ]);

        $query = Tour::query()
            ->with([
                'admin',
                'categories',
                'services',
                'images',
                'itineraries',
                'schedules',
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews')
            ->whereIn('status', ['active', 'full']);

        if (!empty($filters['q'])) {
            $keyword = trim($filters['q']);
            if (mb_strlen($keyword) < 2) {
                return response()->json([
                    'success' => true,
                    'message' => 'Từ khóa tìm kiếm cần ít nhất 2 ký tự',
                    'data' => [],
                ]);
            }

            $query->where(function ($builder) use ($keyword) {
                $builder->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhere('start_location', 'like', "%{$keyword}%")
                    ->orWhere('end_location', 'like', "%{$keyword}%")
                    ->orWhereHas('categories', function ($categoryQuery) use ($keyword) {
                        $categoryQuery->where('name', 'like', "%{$keyword}%")
                            ->orWhere('slug', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('services', function ($serviceQuery) use ($keyword) {
                        $serviceQuery->where('name', 'like', "%{$keyword}%");
                    });
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

        match ($filters['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('adult_price')->orderByDesc('created_at'),
            'price_desc' => $query->orderByDesc('adult_price')->orderByDesc('created_at'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $tours = $query->get();

        // Lọc và sắp xếp theo điểm đánh giá thật (tính từ withAvg phía trên),
        // xử lý bằng PHP để đồng nhất trên cả SQLite lẫn MySQL.
        if (isset($filters['min_rating']) && (float) $filters['min_rating'] > 0) {
            $minRating = (float) $filters['min_rating'];
            $tours = $tours
                ->filter(fn ($tour) => (float) ($tour->rating ?? 0) >= $minRating)
                ->values();
        }

        if (($filters['sort'] ?? '') === 'rating') {
            $tours = $tours
                ->sortByDesc(fn ($tour) => (float) ($tour->rating ?? 0))
                ->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tour thành công',
            'data' => TourResource::collection($tours),
        ]);
    }

    /**
     * Chi tiết tour
     */
    public function show(int $id): JsonResponse
    {
        // Nhả chỗ của các đơn quá hạn trước khi trả dữ liệu, để số
        // "còn lại X chỗ" khách nhìn thấy luôn là số thật.
        $this->holdService->releaseOverdueForTour($id);

        $tour = Tour::with([
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules',
            'schedules.guide:id,name',
        ])
            ->withAvg('reviews as rating', 'rating')
            ->withCount('reviews')
            ->whereIn('status', ['active', 'full'])->find($id);

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