<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{
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

        if (isset($filters['min_rating']) && (float) $filters['min_rating'] > 0) {
            // Rating thật chưa được lưu aggregate trong DB, giữ tham số để frontend/backlog không vỡ luồng.
        }

        match ($filters['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('adult_price')->orderByDesc('created_at'),
            'price_desc' => $query->orderByDesc('adult_price')->orderByDesc('created_at'),
            'newest' => $query->latest(),
            'rating' => $query->orderByDesc('is_featured')->latest(),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tour thành công',
            'data' => TourResource::collection($query->get()),
        ]);
    }

    /**
     * Chi tiết tour
     */
    public function show(int $id): JsonResponse
    {
        $tour = Tour::with([
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules'
        ])->whereIn('status', ['active', 'full'])->find($id);

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

    /**
     * Đánh giá tour
     */
    public function review(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Submit review for tour ' . $id
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