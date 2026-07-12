<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Tour;
use App\Http\Resources\TourResource;

class TourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tours = Tour::query()
            ->where('guide_id', $request->user()->id)
            ->with(['categories', 'services', 'images', 'itineraries', 'schedules'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách tour được phân công thành công',
            'data' => TourResource::collection($tours),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tour = Tour::query()
            ->where('guide_id', $request->user()->id)
            ->with(['categories', 'services', 'images', 'itineraries', 'schedules'])
            ->find($id);

        if (!$tour) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tour được phân công',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết tour được phân công thành công',
            'data' => new TourResource($tour),
        ]);
    }
}
