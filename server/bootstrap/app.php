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
    ->withMiddleware(function (Middleware $middleware): void {
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
