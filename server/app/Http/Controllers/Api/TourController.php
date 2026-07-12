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
     * Danh sách tất cả tour
     */
    public function index(Request $request): JsonResponse
    {
        $tours = Tour::with([
            'admin',
            'categories',
            'services',
            'images',
            'itineraries',
            'schedules'
        ])->whereIn('status', ['active', 'full'])->latest()->get();

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
}

