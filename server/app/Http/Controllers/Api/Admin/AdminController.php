<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function dashboardData(Request $request): JsonResponse
    {
        return $this->success([], 'Placeholder: Admin dashboard data endpoint');
    }
}
