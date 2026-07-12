<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guideId = $request->user()->id;

        $bookings = Booking::query()
            ->with(['tour:id,title', 'schedule:id,start_date,guide_id'])
            ->whereHas('schedule', fn ($query) => $query->where('guide_id', $guideId))
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'tour_id' => $booking->tour_id,
                'tour_schedule_id' => $booking->tour_schedule_id,
                'tour_title' => $booking->tour?->title ?? '',
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'departure_date' => $booking->departure_date,
                'guests' => (int) $booking->guests,
                'total_amount' => (float) $booking->total_amount,
                'status' => $booking->status,
                'created_at' => $booking->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách đặt chỗ của các chuyến được phân công thành công',
            'data' => $bookings,
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()
            ->whereHas('schedule', fn ($query) => $query->where('guide_id', $request->user()->id))
            ->find($id);

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đặt chỗ thuộc chuyến được phân công',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể xác nhận đặt chỗ đang chờ.',
            ], 400);
        }

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Xác nhận đặt chỗ thành công',
            'data' => $booking->fresh(['tour', 'schedule']),
        ]);
    }
}