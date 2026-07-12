<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function dashboardData(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;
        $tourIds = Tour::where('guide_id', $guideId)->pluck('id');

        $bookings = Booking::whereIn('tour_id', $tourIds);

        return response()->json([
            'success' => true,
            'message' => 'Lấy dữ liệu tổng quan hướng dẫn viên thành công',
            'data' => [
                'total_tours' => $tourIds->count(),
                'active_tours' => Tour::where('guide_id', $guideId)->where('status', 'active')->count(),
                'full_tours' => Tour::where('guide_id', $guideId)->where('status', 'full')->count(),
                'total_bookings' => (clone $bookings)->count(),
                'pending_bookings' => (clone $bookings)->where('status', 'pending')->count(),
                'revenue' => (float) (clone $bookings)->where('status', 'confirmed')->sum('total_amount'),
            ],
        ]);
    }
}
