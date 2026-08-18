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
use App\Http\Controllers\Api\Customer\GroupBookingController as CustomerGroupBookingController;
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
use App\Http\Controllers\Api\Admin\AdminGroupBookingController;
use App\Http\Controllers\Api\Admin\AdminGuideHandoverController;
use App\Http\Controllers\Api\Admin\AdminIncidentController;
use App\Http\Controllers\Api\Guide\AssignmentController as GuideAssignmentController;
use App\Http\Controllers\Api\Guide\IncidentController as GuideIncidentController;
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
// 14 - Booking theo đoàn: gửi yêu cầu, tra cứu bằng mã, rút yêu cầu. Không cần tài khoản,
// cùng cơ chế mã tra cứu ngẫu nhiên với đơn lẻ.
Route::post('/group-bookings', [CustomerGroupBookingController::class, 'store']);
Route::get('/group-bookings/{publicToken}', [CustomerGroupBookingController::class, 'show']);
Route::put('/group-bookings/{publicToken}/withdraw', [CustomerGroupBookingController::class, 'withdraw']);
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
        // Sửa thông tin liên hệ nhập nhầm. Không khóa theo hạn chốt: đây là số công ty gọi khách.
        Route::put('/my-bookings/{id}/contact', [CustomerBookingController::class, 'updateContact']);
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

        // Chuyến được phân công: xác nhận, hoặc từ chối kèm lý do khi chuyến chưa khởi hành.
        Route::get('/assignments', [GuideAssignmentController::class, 'index']);
        Route::get('/assignments/declines', [GuideAssignmentController::class, 'myDeclines']);
        Route::put('/assignments/{schedule}/accept', [GuideAssignmentController::class, 'accept']);
        Route::put('/assignments/{schedule}/decline', [GuideAssignmentController::class, 'decline']);

        // Biên bản bàn giao: người mới đọc tình trạng đoàn, người cũ xem lại mình đã giao gì.
        Route::get('/handovers', [GuideIncidentController::class, 'handovers']);
        Route::put('/handovers/{id}/acknowledge', [GuideIncidentController::class, 'acknowledgeHandover']);

        // Xin được bàn giao. Không chọn người thay: đó là việc xếp lịch của điều hành.
        Route::get('/handover-requests', [GuideIncidentController::class, 'myHandoverRequests']);
        Route::post('/schedules/{schedule}/handover-request', [GuideIncidentController::class, 'requestHandover']);
        Route::put('/handover-requests/{id}/withdraw', [GuideIncidentController::class, 'withdrawHandoverRequest']);

        // O - Báo cáo sự cố tại hiện trường. Cố ý không có trường tiền nào ở đây.
        Route::get('/incidents', [GuideIncidentController::class, 'index']);
        Route::post('/schedules/{schedule}/incidents', [GuideIncidentController::class, 'store']);
        Route::post('/incidents/{id}/photos', [GuideIncidentController::class, 'uploadPhoto']);
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
        // K06 - Xóa tour. Xem trước trước đã: cơ sở dữ liệu xóa theo cả đơn hàng nên phải biết
        // hậu quả trước khi bấm. Tour đã có lịch sử thì đi đường ngừng bán.
        Route::get('/tours/{id}/delete-preview', [AdminTourController::class, 'deletePreview']);
        Route::delete('/tours/{id}', [AdminTourController::class, 'destroy']);
        Route::put('/tours/{id}/retire', [AdminTourController::class, 'retire']);
        Route::get('/available-guides', [AdminTourController::class, 'availableGuides']);
        Route::put('/tour-schedules/{id}/assign-guide', [AdminTourController::class, 'assignScheduleGuide']);
        // Ai đã từ chối chuyến này. Đọc lúc xếp người, để khỏi gán lại đúng người vừa nói không.
        Route::get('/tour-schedules/{id}/guide-declines', [AdminTourController::class, 'scheduleGuideDeclines']);
        // 17 - Ai phù hợp dẫn chuyến này, không chỉ ai đang rảnh. Trả cả người bị chặn kèm lý do.
        Route::get('/tour-schedules/{id}/guide-suitability', [AdminTourController::class, 'scheduleGuideSuitability']);
        Route::get('/tour-schedules/{id}/attendance', [AdminAttendanceController::class, 'show']);
        Route::get('/attendance-reports', [AdminAttendanceController::class, 'report']);

        // H13a — Báo cáo chi tiết điểm danh sau chuyến (5 phần theo tài liệu 04 §5.5).
        Route::get('/schedules/{id}/attendance-report', [AdminAttendanceController::class, 'scheduleReport']);


        // A10 — Đổi trạng thái chuyến thủ công (open ↔ closed, → confirmed, → cancelled).
        Route::patch('/schedules/{id}/status', [AdminTourController::class, 'updateScheduleStatus']);

        // Đổi hướng dẫn viên giữa chừng. Tách khỏi phân công thường vì bắt buộc kèm biên bản.
        Route::get('/schedules/{id}/handovers', [AdminGuideHandoverController::class, 'index']);
        Route::post('/schedules/{id}/handover', [AdminGuideHandoverController::class, 'store']);
        // Duyệt yêu cầu của hướng dẫn viên. Duyệt đi qua đúng đường bàn giao ở trên, không tự làm.
        Route::get('/handovers', [AdminGuideHandoverController::class, 'history']);
        Route::get('/handover-requests', [AdminGuideHandoverController::class, 'pendingRequests']);
        Route::put('/handover-requests/{id}/approve', [AdminGuideHandoverController::class, 'approveRequest']);
        Route::put('/handover-requests/{id}/reject', [AdminGuideHandoverController::class, 'rejectRequest']);

        // O - Sự cố dọc đường. Chỉ ở đây mới quyết được tiền; hướng dẫn viên chỉ báo cáo.
        Route::get('/incidents', [AdminIncidentController::class, 'index']);
        Route::get('/incidents/{id}', [AdminIncidentController::class, 'show']);
        Route::post('/incidents/{id}/resolve', [AdminIncidentController::class, 'resolve']);
        Route::put('/surcharges/{id}/approve', [AdminIncidentController::class, 'approveSurcharge']);
        Route::put('/surcharges/{id}/waive', [AdminIncidentController::class, 'waiveSurcharge']);

        // 14 - Booking đoàn: báo giá, chốt thành đơn, sổ thu tiền nhiều đợt, giảm số khách.
        Route::get('/group-bookings', [AdminGroupBookingController::class, 'index']);
        Route::put('/group-bookings/{id}/quote', [AdminGroupBookingController::class, 'quote']);
        Route::put('/group-bookings/{id}/confirm', [AdminGroupBookingController::class, 'confirm']);
        Route::put('/group-bookings/{id}/reject', [AdminGroupBookingController::class, 'reject']);
        Route::get('/bookings/{id}/payments', [AdminGroupBookingController::class, 'payments']);
        Route::post('/bookings/{id}/payments', [AdminGroupBookingController::class, 'storePayment']);
        Route::put('/bookings/{id}/reduce-guests', [AdminGroupBookingController::class, 'reduceGuests']);

        // K - Hủy cả chuyến. Đi đường riêng vì phải gán phương án cho từng đơn đã thanh toán.
        Route::get('/schedules/{id}/cancel-preview', [AdminScheduleCancellationController::class, 'preview']);
        Route::post('/schedules/{id}/cancel', [AdminScheduleCancellationController::class, 'store']);

        // Dời hạn chốt danh sách, kèm xem trước tác động trước khi lưu.
        Route::get('/schedules/{id}/deadline-impact', [AdminScheduleDeadlineController::class, 'preview']);
        Route::patch('/schedules/{id}/deadline', [AdminScheduleDeadlineController::class, 'update']);


        // 17 - Hồ sơ năng lực. Đường riêng vì sửa nghề nghiệp khác với sửa tài khoản đăng nhập.
        Route::put('/guides/{id}/profile', [AdminGuideController::class, 'updateProfile']);
        Route::apiResource('guides', AdminGuideController::class);

        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
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
        Route::put('/bookings/{id}/contact', [AdminBookingController::class, 'updateContact']);
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






