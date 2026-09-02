<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Tài khoản bị khóa thì không làm gì được nữa, kể cả với token đã phát hành trước đó.
 *
 * Phép kiểm này vốn nằm trong `RoleMiddleware`, tức chỉ chạy ở các tuyến có khai vai trò. Những
 * tuyến chỉ có `auth:sanctum` — xem hồ sơ, đổi mật khẩu, viết đánh giá — thì không đi qua nó, nên
 * một tài khoản vừa bị khóa vẫn dùng được bình thường miễn là còn giữ token cũ.
 *
 * Tách ra thành middleware riêng và áp cho cả nhóm đăng nhập, để câu "khóa tài khoản" có đúng một
 * nghĩa. `RoleMiddleware` giữ lại phép kiểm của nó: hai lớp cùng chặn không hại gì, còn gỡ đi thì
 * mọi tuyến khai vai trò lại phụ thuộc vào việc nhớ khai thêm middleware này.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa.',
            ], 403);
        }

        return $next($request);
    }
}
