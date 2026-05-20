<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Customer store booking endpoint'
        ]);
    }

    public function myBookings(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Customer view my bookings endpoint'
        ]);
    }
}
