<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)

    {
        $user = $request->user();

        if ($user->role !== $role) {

            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        // Khóa tài khoản phải có hiệu lực ngay cả với token đã phát hành trước đó.
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa.'
            ], 403);
        }

        return $next($request);
    }
}