<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        return $this->success([], 'Placeholder: Register endpoint');
    }

    public function login(Request $request): JsonResponse
    {
        return $this->success([], 'Placeholder: Login endpoint');
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->success([], 'Placeholder: Logout endpoint');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success([], 'Placeholder: Get current user endpoint');
    }
}
