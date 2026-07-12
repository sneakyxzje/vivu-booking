<?php




use Illuminate\Support\Facades\Route;



// Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TourController;
use App\Models\Category;
use App\Models\Service;

// Customer
use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;

// Guide
use App\Http\Controllers\Api\Guide\GuideController;
use App\Http\Controllers\Api\Guide\TourController as GuideTourController;
use App\Http\Controllers\Api\Guide\BookingController as GuideBookingController;

// Admin
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminTourController;
use App\Http\Controllers\Api\Admin\AdminGuideController;
use App\Http\Controllers\Api\Admin\AdminBookingController;

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
    'data' => Service::orderBy('name')->get(),
]));
Route::post('/bookings', [CustomerBookingController::class, 'store']);
Route::get('/vnpay/return', [CustomerBookingController::class, 'vnpayReturn']);

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
        Route::get('/my-bookings', [CustomerBookingController::class, 'myBookings']);
        Route::post('/tours/{id}/reviews', [TourController::class, 'review']);
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
        Route::get('/available-guides', [AdminTourController::class, 'availableGuides']);
        Route::put('/tour-schedules/{id}/assign-guide', [AdminTourController::class, 'assignScheduleGuide']);

        Route::apiResource('guides', AdminGuideController::class);

        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);

        Route::get('/admin-only', function () {
            return response()->json([
                'message' => 'Admin route'
            ]);
        });
    });
});


