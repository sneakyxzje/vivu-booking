<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TourController;

// Customer
use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;

// Host
use App\Http\Controllers\Api\Host\HostController;
use App\Http\Controllers\Api\Host\TourController as HostTourController;
use App\Http\Controllers\Api\Host\BookingController as HostBookingController;

// Admin
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminTourController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tours', [TourController::class, 'index']);
Route::get('/tours/{id}', [TourController::class, 'show']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // dùng chung cho tất cả user login
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [UserController::class, 'updateProfile']);

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:customer')->group(function () {
        Route::post('/bookings', [CustomerBookingController::class, 'store']);
        Route::get('/my-bookings', [CustomerBookingController::class, 'myBookings']);
        Route::post('/tours/{id}/reviews', [TourController::class, 'review']);
    });

    /*
    |--------------------------------------------------------------------------
    | HOST
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:host')->prefix('host')->group(function () {
        Route::get('/dashboard', [HostController::class, 'dashboardData']);
        Route::apiResource('/my-tours', HostTourController::class);
        Route::get('/bookings', [HostBookingController::class, 'index']);
        Route::put('/bookings/{id}/confirm', [HostBookingController::class, 'confirm']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboardData']);
Route::get('/users', [AdminUserController::class, 'index']);
        Route::put('/users/{id}/status', [AdminUserController::class, 'toggleStatus']);
        Route::put('/tours/{id}/approve', [AdminTourController::class, 'approve']);

        // route test admin (từ file thứ 2 bạn đưa)
        Route::get('/admin-only', function () {
            return response()->json([
                'message' => 'Admin route'
            ]);
        });

    });

});