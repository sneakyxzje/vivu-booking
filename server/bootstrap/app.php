<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    /*
     * Điểm xác thực kênh WebSocket.
     *
     * Đặt dưới tiền tố `api` và middleware `auth:sanctum` vì giao diện là ứng dụng tách rời, đăng
     * nhập bằng token Bearer chứ không bằng phiên. Để nguyên mặc định `/broadcasting/auth` với
     * middleware `web` thì mọi lượt đăng ký kênh đều trả 401 — trình duyệt không gửi cookie phiên
     * nào cả, và lỗi ấy chỉ hiện trong console chứ không làm gì vỡ, nên rất khó lần ra.
     */
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Trần chung cho toàn bộ /api.
         *
         * Truyền TÊN nhóm chứ không truyền thẳng "60,1": con số nằm ở `config/rate_limit.php`, khai
         * cùng chỗ với hạn mức của /login và các tuyến gửi thư, và tắt được bằng một dòng .env khi
         * ngồi thử tay. Nhóm này khai trong `AppServiceProvider`.
         */
        $middleware->throttleApi('api');

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Trả về JSON chuẩn khi validation thất bại 
        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->errors(),
            ], 422);
        });

        // Trả về JSON khi không tìm thấy model 
        $exceptions->render(function (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy dữ liệu.',
            ], 404);
        });

        // Trả về JSON khi chưa đăng nhập 
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa xác thực. Vui lòng đăng nhập.',
            ], 401);
        });
    })->create();