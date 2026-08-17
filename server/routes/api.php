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
use App\Http\Controllers\Api\Customer\ChangeRequestController as CustomerChangeRequestController;
use App\Http\Controllers\Api\Customer\PassengerController as CustomerPassengerController;
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
use App\Http\Controllers\Api\Admin\AdminChangeRequestController;
use App\Http\Controllers\Api\Admin\AdminPassengerController;
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AdminScheduleCancellationController;
use App\Http\Controllers\Api\Admin\AdminScheduleDeadlineController;
use App\Http\Controllers\Api\Admin\AdminScheduleMergeController;
use App\Http\Controllers\Api\Admin\AdminTransferController;
use App\Http\Controllers\Api\Admin\AdminDiscountCodeController;
use App\Http\Controllers\Api\Admin\AdminAttendanceController;
use App\Http\Controllers\Api\Admin\AdminCancellationPolicyController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
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
// Task X06a - API gửi lại mã tra cứu về email khách vãng lai
Route::post('/bookings/resend-code', [CustomerBookingController::class, 'resendLookupCode']);
Route::get('/bookings/{publicToken}', [CustomerBookingController::class, 'show']);
// Mức hoàn dự kiến nếu hủy ngay bây giờ. Khách vãng lai cũng xem được bằng mã tra cứu.
Route::get('/bookings/{publicToken}/refund-quote', [CustomerBookingController::class, 'refundQuote']);
Route::post('/discount-codes/validate', [DiscountCodeController::class, 'validateCode']);
Route::get('/vnpay/return', [CustomerBookingController::class, 'vnpayReturn']);
Route::get('/reviews/{tour}', [ReviewController::class,'index']);
Route::post('/newsletter', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

    \App\Models\NewsletterSubscriber::query()->firstOrCreate(['email' => $validated['email']]);

    return response()->json([
        'success' => true,
        'message' => 'Đăng ký nhận bản tin thành công.',
    ]);
});

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
        // Chỉ dùng được cho đơn chưa thanh toán. Đơn đã thu tiền đi đường yêu cầu bên dưới.
        Route::put('/my-bookings/{id}/cancel', [CustomerBookingController::class, 'cancelBooking']);

        // F02 - Yêu cầu hủy của khách đã thanh toán, phải chờ điều hành duyệt.
        Route::get('/my-bookings/{id}/cancel-preview', [CustomerChangeRequestController::class, 'preview']);
        Route::post('/my-bookings/{id}/cancel-request', [CustomerChangeRequestController::class, 'store']);
        Route::get('/my-change-requests', [CustomerChangeRequestController::class, 'index']);
        Route::put('/my-change-requests/{id}/withdraw', [CustomerChangeRequestController::class, 'withdraw']);

        // G03 - Khách sửa danh sách hành khách, chỉ trước hạn chốt danh sách.
        Route::get('/my-bookings/{id}/passengers', [CustomerPassengerController::class, 'index']);
        Route::put('/my-bookings/{id}/passengers', [CustomerPassengerController::class, 'update']);
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
       Route::get(
            '/schedules/{schedule}/attendance',
            [AttendanceController::class, 'show']
        );

        Route::put(
            '/schedules/{schedule}/checkpoints/{checkpoint}/attendance',
            [AttendanceController::class, 'update']
        );

        Route::post(
            '/schedules/{schedule}/checkpoints/{checkpoint}/checkin-photo',
            [AttendanceController::class, 'uploadPhoto']
        );
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
        Route::get('/tour-schedules/{id}/attendance', [AdminAttendanceController::class, 'show']);
        Route::get('/attendance-reports', [AdminAttendanceController::class, 'report']);

        // H13a — Báo cáo chi tiết điểm danh sau chuyến (5 phần theo tài liệu 04 §5.5).
        Route::get('/schedules/{id}/attendance-report', [AdminAttendanceController::class, 'scheduleReport']);


        // A10 — Đổi trạng thái chuyến thủ công (open ↔ closed, → confirmed, → cancelled).
        Route::patch('/schedules/{id}/status', [AdminTourController::class, 'updateScheduleStatus']);

        // K - Hủy cả chuyến. Đi đường riêng vì phải gán phương án cho từng đơn đã thanh toán.
        Route::get('/schedules/{id}/cancel-preview', [AdminScheduleCancellationController::class, 'preview']);
        Route::post('/schedules/{id}/cancel', [AdminScheduleCancellationController::class, 'store']);

        // Dời hạn chốt danh sách, kèm xem trước tác động trước khi lưu.
        Route::get('/schedules/{id}/deadline-impact', [AdminScheduleDeadlineController::class, 'preview']);
        Route::patch('/schedules/{id}/deadline', [AdminScheduleDeadlineController::class, 'update']);


        Route::apiResource('guides', AdminGuideController::class);

        Route::get('/bookings', [AdminBookingController::class, 'index']);
        // Phải khai trước /bookings/{id}, nếu không "held-seats" sẽ bị khớp vào {id}.
        Route::get('/bookings/held-seats', [AdminBookingController::class, 'heldSeats']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        Route::put('/bookings/{id}/release-seats', [AdminBookingController::class, 'releaseHeldSeats']);
        Route::put('/bookings/{id}/confirm', [AdminBookingController::class, 'confirm']);
        // F03 - Duyệt yêu cầu thay đổi của khách. Khai trước /bookings/{id} không cần thiết vì
        // khác tiền tố, nhưng giữ cạnh nhóm đơn hàng cho dễ tìm.
        Route::get('/change-requests', [AdminChangeRequestController::class, 'index']);
        Route::get('/change-requests/{id}', [AdminChangeRequestController::class, 'show']);
        Route::put('/change-requests/{id}/approve', [AdminChangeRequestController::class, 'approve']);
        Route::put('/change-requests/{id}/reject', [AdminChangeRequestController::class, 'reject']);

        // G03, G05 - Danh sách hành khách. Điều hành sửa được cả sau hạn chốt.
        Route::get('/bookings/{id}/passengers', [AdminPassengerController::class, 'index']);
        Route::put('/bookings/{id}/passengers', [AdminPassengerController::class, 'update']);
        // Danh sách đoàn chia theo nhóm: mỗi đơn là một nhóm do người đại diện đăng ký.
        Route::get('/schedules/{id}/manifest', [AdminPassengerController::class, 'manifest']);

        // L03 - Ghép hai chuyến của cùng một tour.
        Route::get('/schedules/{id}/merge-candidates', [AdminScheduleMergeController::class, 'candidates']);
        Route::post('/schedules/{id}/merge', [AdminScheduleMergeController::class, 'store']);

        // E04 - Dòng thời gian thay đổi của một đơn.
        Route::get('/bookings/{id}/history', [AdminBookingController::class, 'history']);

        // Nhật ký hệ thống: gộp nhật ký đơn và nhật ký chuyến thành một dòng thời gian.
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);

        // I05 - Chuyển đơn sang chuyến khác.
        Route::get('/bookings/{id}/transfer-options', [AdminTransferController::class, 'options']);
        Route::post('/bookings/{id}/transfer', [AdminTransferController::class, 'store']);
        Route::get('/bookings/{id}/transfers', [AdminTransferController::class, 'history']);

        Route::get('/bookings/{id}/cancel-preview', [AdminBookingController::class, 'cancelPreview']);
        Route::put('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);
        // Task X07a - Mở lại đơn đã hủy nhầm trong 24h
        Route::put('/bookings/{id}/reopen', [AdminBookingController::class, 'reopen']);
        Route::apiResource('discount-codes', AdminDiscountCodeController::class);

        // Quản lý dịch vụ phát sinh (khách sạn, ăn uống, ...)
        Route::apiResource('services', AdminServiceController::class);

        // Quản lý danh mục tour (biển đảo, nghỉ dưỡng, ...)
        Route::apiResource('categories', AdminCategoryController::class);

        // Quản lý chính sách hủy theo mốc thời gian
        Route::apiResource('cancellation-policies', AdminCancellationPolicyController::class)
            ->except(['show']);
    });
});






