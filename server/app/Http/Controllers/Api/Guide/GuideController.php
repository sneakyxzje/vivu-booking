<?php

namespace App\Http\Controllers\Api\Guide;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GuideController extends Controller
{
    public function dashboardData(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Guide dashboard data endpoint'
        ]);
    }
}
