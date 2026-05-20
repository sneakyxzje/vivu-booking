<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Host view bookings endpoint'
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Host confirm booking endpoint for ' . $id
        ]);
    }
}
