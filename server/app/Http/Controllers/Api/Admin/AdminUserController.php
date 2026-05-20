<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->success([], 'Placeholder: Admin view users list endpoint');
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        return $this->success([], 'Placeholder: Admin toggle user account status endpoint for user ' . $id);
    }
}
