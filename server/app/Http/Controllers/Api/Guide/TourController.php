<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;
        // Một chuyến có thể có nhiều hướng dẫn viên, nên lọc qua bảng nối.
        $assignedSchedules = fn ($query) => $query
            ->whereHas('guides', fn ($q) => $q->whereKey($guideId))
            ->with('guides:id,name,email,phone,status');

        $tours = Tour::query()
            ->whereHas('schedules', fn ($query) => $query
                ->whereHas('guides', fn ($q) => $q->whereKey($guideId)))
            ->with([
                'categories',
                'services',
                'images',
                'itineraries',
                'schedules' => $assignedSchedules,
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách chuyến được phân công thành công',
            'data' => TourResource::collection($tours),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $guideId = $request->user()->id;

        $tour = Tour::query()
            ->whereHas('schedules', fn ($query) => $query
                ->whereHas('guides', fn ($q) => $q->whereKey($guideId)))
            ->with([
                'categories',
                'services',
                'images',
                'itineraries',
                'schedules' => fn ($query) => $query
                    ->whereHas('guides', fn ($q) => $q->whereKey($guideId))
                    ->with('guides:id,name,email,phone,status'),
            ])
            ->find($id);

        if (! $tour) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy chuyến được phân công',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lấy chi tiết chuyến được phân công thành công',
            'data' => new TourResource($tour),
        ]);
    }
}