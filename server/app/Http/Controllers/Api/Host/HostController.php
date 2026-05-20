<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HostController extends Controller
{
    public function dashboardData(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Placeholder: Host dashboard data endpoint'
        ]);
    }
}
