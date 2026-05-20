<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\Host\HostController;
use App\Http\Controllers\Api\Host\TourController as HostTourController;
use App\Http\Controllers\Api\Host\BookingController as HostBookingController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminTourController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider/App configuration and
| all of them will be assigned to the "api" middleware group.
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tours', [TourController::class, 'index']);
Route::get('/tours/{id}', [TourController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // API dùng chung cho mọi user đã đăng nhập 
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    /*
    | Role CUSTOMER
    */
    Route::middleware('role:customer')->group(function () {
        Route::post('/bookings', [CustomerBookingController::class, 'store']);
        Route::get('/my-bookings', [CustomerBookingController::class, 'myBookings']);
        Route::post('/tours/{id}/reviews', [TourController::class, 'review']);
    });
    /*
    | Role HOST 
    */
    Route::middleware('role:host')->prefix('host')->group(function () {
        Route::get('/dashboard', [HostController::class, 'dashboardData']);
        Route::apiResource('/my-tours', HostTourController::class);
        Route::get('/bookings', [HostBookingController::class, 'index']);
        Route::put('/bookings/{id}/confirm', [HostBookingController::class, 'confirm']);
    });
    /*
    | ADMIN 
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboardData']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::put('/users/{id}/status', [AdminUserController::class, 'toggleStatus']);
        Route::put('/tours/{id}/approve', [AdminTourController::class, 'approve']);
    });
});
