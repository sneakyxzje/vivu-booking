<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'server_time' => now()->format('Y-m-d H:i:s'),
            'environment' => app()->environment(),
            'app_name' => config('app.name', 'Vivu Booking API')
        ]);
    }
 
}
