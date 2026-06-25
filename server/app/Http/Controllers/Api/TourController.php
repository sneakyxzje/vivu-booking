<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{
    /**
     * Danh sách tất cả tour
     */
    public function index(Request $request): JsonResponse
    {
        $tours = Tour::with([
            'host',
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules'
        ])->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tour thành công',
            'data' => $tours
        ]);
    }

    /**
     * Chi tiết tour
     */
    public function show(int $id): JsonResponse
{
    $tour = Tour::with([
        'host',
        'categories',
        'services',
        'images',
        'itineraries',
        'schedules'
    ])->find($id);

    if (!$tour) {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy tour'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Lấy chi tiết tour thành công',
        'data' => [
            'id' => $tour->id,
            'title' => $tour->title,
            'description' => $tour->description,
            'price' => $tour->price,
            'duration' => $tour->duration,
            'location' => $tour->location,
            'status' => $tour->status,

            'host' => $tour->host,
            'categories' => $tour->categories,
            'services' => $tour->services,
            'images' => $tour->images,
            'itineraries' => $tour->itineraries,
            'schedules' => $tour->schedules,

            'created_at' => $tour->created_at,
            'updated_at' => $tour->updated_at,
        ]
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
}