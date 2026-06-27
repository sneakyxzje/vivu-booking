<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Guide view bookings endpoint'
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Guide confirm booking endpoint for ' . $id
        ]);
    }
}
