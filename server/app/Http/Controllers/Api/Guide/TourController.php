<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use App\Services\ScheduleLifecycleService;
use App\Services\DemoClock;
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
            ->with('guides:id,name,email,phone,status')
            ->orderByDesc('start_date')->orderByDesc('id');

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
            ->withMax(['schedules' => fn ($query) => $query
                ->whereHas('guides', fn ($q) => $q->whereKey($guideId))], 'start_date')
            ->orderByDesc('schedules_max_start_date')
            ->latest()->orderByDesc('id')
            ->get();

        foreach ($tours as $tour) {
            foreach ($tour->schedules as $schedule) {
                $now = DemoClock::schedule($schedule);
                $schedule->setAttribute('server_now', $now->toIso8601String());
                $schedule->setAttribute('effective_status', app(ScheduleLifecycleService::class)->effectiveStatus($schedule, $now)->value);
            }
        }

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
                    ->with('guides:id,name,email,phone,status')
                    ->orderByDesc('start_date')->orderByDesc('id'),
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
