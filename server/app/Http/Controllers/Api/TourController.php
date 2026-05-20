<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Get all tours endpoint'
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Get tour details for ' . $id
        ]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Submit review for tour ' . $id
        ]);
    }
}
