<?php

use App\Http\Controllers\ContractPrintController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
 * Q - Trang in hợp đồng.
 *
 * Nằm ở routes/web chứ không phải routes/api vì nó trả về HTML để in, và phải mở được bằng một
 * thẻ <a> trong tab mới. Bảo vệ bằng `signed` thay vì Sanctum: thẻ <a> không gắn được tiêu đề
 * Authorization. Chữ ký hết hạn sau 24 giờ, xem ContractService::printUrl().
 */
Route::get('/contracts/{contract}/print', ContractPrintController::class)
    ->middleware('signed')
    ->name('contracts.print');

/*
 * Sơ đồ trang. Ở routes/web vì nó trả XML cho máy tìm kiếm đọc trực tiếp, không phải JSON cho
 * ứng dụng gọi. Các địa chỉ bên trong trỏ về giao diện React, xem SitemapController.
 */
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Vivu Booking API Server',
        'status' => 'active',
        'timestamp' => now()->toIso8601String()
    ]);
});
