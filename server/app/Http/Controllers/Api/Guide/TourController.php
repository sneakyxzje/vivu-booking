<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\TourService;
use App\Services\CloudinaryService;
use App\Http\Resources\TourResource;

class TourController extends Controller
{
    protected $tourService;
    protected $cloudinaryService;

    public function __construct(TourService $tourService, CloudinaryService $cloudinaryService)
    {
        $this->tourService = $tourService;
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(Request $request): JsonResponse
    {
        $tours = $request->user()
            ->tours()
            ->with(['categories', 'services', 'images', 'itineraries', 'schedules'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tour của guide thành công',
            'data' => TourResource::collection($tours),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tours,slug',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lte:price',
            'thumbnail' => 'nullable|string|max:255',
            'thumbnail_file' => 'nullable|image|max:5120',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'number_of_days' => 'required|integer|min:1',
            'number_of_nights' => 'required|integer|min:0',
            'start_location' => 'required|string|max:255',
            'end_location' => 'nullable|string|max:255',
            'is_featured' => 'nullable|boolean',

            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'itineraries' => 'nullable|array',
            'itineraries.*.day_number' => 'required_with:itineraries|integer|min:1',
            'itineraries.*.title' => 'required_with:itineraries|string|max:255',
            'itineraries.*.content' => 'required_with:itineraries|string',
            'schedules' => 'nullable|array',
            'schedules.*.start_date' => 'required_with:schedules|date',
            'schedules.*.max_people' => 'required_with:schedules|integer|min:1',
        ]);

        if ($request->hasFile('thumbnail_file')) {
            $data['thumbnail'] = $this->cloudinaryService->uploadImage(
                $request->file('thumbnail_file')
            );
        }

        if ($request->hasFile('images')) {
            $data['image_paths'] = [];

            foreach ($request->file('images') as $image) {
                $data['image_paths'][] = $this->cloudinaryService->uploadImage(
                    $image,
                    'vivu-booking/tour-gallery'
                );
            }
        }

        $tour = $this->tourService->create($data, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Tạo tour thành công. Tour đang chờ duyệt.',
            'data' => new TourResource($tour)
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Guide show tour endpoint for ' . $id
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Guide update tour endpoint for ' . $id
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Guide destroy tour endpoint for ' . $id
        ]);
    }
}
