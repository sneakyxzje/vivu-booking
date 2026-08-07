<?php




use Illuminate\Support\Facades\Route;



// Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\DiscountCodeController;
use App\Models\Category;
use App\Models\Service;

// Customer
use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\Customer\ReviewController;

// Guide
use App\Http\Controllers\Api\Guide\GuideController;
use App\Http\Controllers\Api\Guide\TourController as GuideTourController;
use App\Http\Controllers\Api\Guide\BookingController as GuideBookingController;
use App\Http\Controllers\Api\Guide\AttendanceController;

// Admin
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminTourController;
use App\Http\Controllers\Api\Admin\AdminGuideController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\AdminDiscountCodeController;
use App\Http\Controllers\Api\Admin\AdminServiceController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tours', [TourController::class, 'index']);
Route::get('/tours/{id}', [TourController::class, 'show']);
Route::get('/categories', fn() => response()->json([
    'success' => true,
    'data' => Category::where('is_active', true)->orderBy('name')->get(),
]));
Route::get('/services', fn() => response()->json([
    'success' => true,
    // Chỉ trả về dịch vụ đang hoạt động (is_active = true) cho phía khách hàng xem
    'data' => Service::where('is_active', true)->orderBy('name')->get(),
]));
Route::post('/bookings', [CustomerBookingController::class, 'store']);
Route::get('/bookings/{publicToken}', [CustomerBookingController::class, 'show']);
Route::post('/discount-codes/validate', [DiscountCodeController::class, 'validateCode']);
Route::get('/vnpay/return', [CustomerBookingController::class, 'vnpayReturn']);
Route::get('/reviews/{tour}', [ReviewController::class,'index']);

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
    Route::put('/profile/password', [UserController::class, 'changePassword']);

    // Đánh giá tour (mọi user đã đăng nhập)
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:customer')->group(function () {
        Route::get('/my-bookings', [CustomerBookingController::class, 'myBookings']);
        Route::put('/my-bookings/{id}/cancel', [CustomerBookingController::class, 'cancelBooking']);
    });

    /*
    |--------------------------------------------------------------------------
    | GUIDE
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:guide')->prefix('guide')->group(function () {
        Route::get('/dashboard', [GuideController::class, 'dashboardData']);
        Route::get('/my-tours', [GuideTourController::class, 'index']);
        Route::get('/my-tours/{id}', [GuideTourController::class, 'show']);
        Route::get('/bookings', [GuideBookingController::class, 'index']);
        Route::put('/bookings/{id}/confirm', [GuideBookingController::class, 'confirm']);
        Route::get('/schedules/{schedule}/attendance', [AttendanceController::class, 'show']);
        Route::put('/schedules/{schedule}/itineraries/{itinerary}/attendance', [AttendanceController::class, 'update']);
        Route::post('/schedules/{schedule}/itineraries/{itinerary}/checkin-photo', [AttendanceController::class, 'uploadPhoto']);
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
        Route::get('/tours', [AdminTourController::class, 'index']);
        Route::get('/tours/create', [AdminTourController::class, 'create']);
        Route::get('/tours/{id}', [AdminTourController::class, 'show']);
        Route::post('/tours', [AdminTourController::class, 'store']);
        Route::put('/tours/{id}', [AdminTourController::class, 'update']);
        Route::post('/tours/{id}', [AdminTourController::class, 'update']);
        Route::get('/available-guides', [AdminTourController::class, 'availableGuides']);
        Route::put('/tour-schedules/{id}/assign-guide', [AdminTourController::class, 'assignScheduleGuide']);

        Route::apiResource('guides', AdminGuideController::class);

        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        Route::put('/bookings/{id}/confirm', [AdminBookingController::class, 'confirm']);
        Route::put('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);
        Route::apiResource('discount-codes', AdminDiscountCodeController::class);

        // Quản lý dịch vụ phát sinh (khách sạn, ăn uống, ...)
        Route::apiResource('services', AdminServiceController::class);
    });
});






