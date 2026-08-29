<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Nhận nhiều vai, ví dụ `role:admin,guide`.
     *
     * Trước đây chỉ nhận một. Hộp thông báo là chỗ đầu tiên cần hai vai cùng vào được — mỗi người
     * chỉ thấy thông báo của chính mình, nên mở cho cả hai không nới rộng quyền gì.
     *
     * Cách khác là chép controller ra hai bản dưới hai nhóm route. Hai bản của cùng một logic là
     * kiểu lỗi dự án này đã gặp nhiều lần.
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!in_array($user->role, $roles, true)) {
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