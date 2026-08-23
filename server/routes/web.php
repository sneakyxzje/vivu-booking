<?php

use App\Http\Controllers\ContractPrintController;
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

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Vivu Booking API Server',
        'status' => 'active',
        'timestamp' => now()->toIso8601String()
    ]);
});
