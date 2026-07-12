<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\TourImage;
use App\Models\Service;
use App\Models\Tour;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Resources\TourResource;

class AdminTourController extends Controller
{
    public function __construct(
        protected CloudinaryService $cloudinaryService
    ) {
    }


    public function index(): JsonResponse
    {
        $tours = Tour::with([
            'admin:id,name,email',
            'guide:id,name,email,phone,status',
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules',
        ])
            ->latest()
            ->get();

        return $this->success(TourResource::collection($tours), 'Lấy danh sách tour thành công');
    }

    public function create(): JsonResponse
    {
        return $this->success([
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'services' => Service::orderBy('name')->get(['id', 'name']),
        ], 'Lấy dữ liệu tạo tour thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string'],
            'thumbnail_file' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'number_of_days' => ['required', 'integer', 'min:1'],
            'number_of_nights' => ['required', 'integer', 'min:0'],
            'start_location' => ['required', 'string', 'max:255'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['exists:services,id'],
            'itineraries' => ['nullable', 'array'],
            'itineraries.*.day_number' => ['required_with:itineraries', 'integer', 'min:1'],
            'itineraries.*.title' => ['required_with:itineraries', 'string', 'max:255'],
            'itineraries.*.content' => ['required_with:itineraries', 'string'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.start_date' => ['required_with:schedules', 'date'],
            'schedules.*.max_people' => ['required_with:schedules', 'integer', 'min:1'],
        ]);

        $numberOfDay = (int) $validated['number_of_days'];
        $numberOfNight = (int) $validated['number_of_nights'];
        $price = (float) $validated['price'];
        $salePrice = isset($validated['discount_price'])
            ? (float) $validated['discount_price']
            : null;

        if ($numberOfNight > $numberOfDay) {
            return $this->error('Số đêm không được lớn hơn số ngày', 400);
        }

        if ($salePrice !== null && $salePrice > $price) {
            return $this->error('Giá giảm không được lớn hơn giá gốc', 400);
        }

        $categoryIds = $validated['category_ids'] ?? [];
        $serviceIds = $validated['service_ids'] ?? [];
        $itineraries = $validated['itineraries'] ?? [];
        $schedules = $validated['schedules'] ?? [];

        $tour = DB::transaction(function () use ($request, $validated, $categoryIds, $serviceIds, $itineraries, $schedules) {
            if ($request->hasFile('thumbnail_file')) {
                $validated['thumbnail'] = $this->cloudinaryService->uploadImage(
                    $request->file('thumbnail_file')
                );
            }

            unset($validated['thumbnail_file']);
            unset($validated['images'], $validated['category_ids'], $validated['service_ids'], $validated['itineraries'], $validated['schedules']);

            $tour = Tour::create([
                ...$validated,
                'admin_id' => $request->user()->id,
                'status' => 'active',
                'is_featured' => false,
                'slug' => Tour::query()->where('title', $validated['title'])->count()
                    ? Str::slug($validated['title']) . '-' . time()
                    : Str::slug($validated['title']),
            ]);

            if (! empty($categoryIds)) {
                $tour->categories()->sync($categoryIds);
            }

            if (! empty($serviceIds)) {
                $tour->services()->sync($serviceIds);
            }

            foreach ($itineraries as $item) {
                $tour->itineraries()->create([
                    'day_number' => $item['day_number'],
                    'title' => $item['title'],
                    'content' => $item['content'],
                ]);
            }

            foreach ($schedules as $item) {
                $tour->schedules()->create([
                    'start_date' => $item['start_date'],
                    'max_people' => $item['max_people'],
                    'booked_people' => 0,
                    'status' => 'active',
                ]);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $this->cloudinaryService->uploadImage(
                        $image,
                        'vivu-booking/tour-gallery'
                    );

                    TourImage::create([
                        'tour_id' => $tour->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            return $tour->load(['categories', 'services', 'images', 'itineraries', 'schedules']);
        });

        return $this->success([
            'tour' => new TourResource($tour),
        ], 'Tạo tour thành công và đã được kích hoạt');
    }

    public function assignGuide(Request $request, int $id): JsonResponse
    {
        $tour = Tour::find($id);

        if (!$tour) {
            return $this->error('Không tìm thấy tour', 404);
        }

        $validated = $request->validate([
            'guide_id' => ['nullable', 'exists:users,id']
        ]);

        if (!empty($validated['guide_id'])) {
            $guide = \App\Models\User::find($validated['guide_id']);
            if ($guide->role !== 'guide') {
                return $this->error('User được chọn không phải là hướng dẫn viên', 400);
            }
        }

        $tour->guide_id = $validated['guide_id'] ?? null;
        $tour->save();

        return $this->success($tour->load('guide'), 'Chỉ định hướng dẫn viên thành công');
    }
}



