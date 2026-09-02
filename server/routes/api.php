<?php




use Illuminate\Support\Facades\Route;



// Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PasswordResetController;
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
use App\Http\Controllers\Api\Admin\AdminBookingPaymentController;
use App\Http\Controllers\Api\Admin\AdminChangeRequestController;
use App\Http\Controllers\Api\Admin\AdminContractController;
use App\Http\Controllers\Api\Admin\AdminPassengerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AdminGroupBookingController;
use App\Http\Controllers\Api\Admin\AdminGuideHandoverController;
use App\Http\Controllers\Api\Admin\AdminIncidentController;
use App\Http\Controllers\Api\Guide\AssignmentController as GuideAssignmentController;
use App\Http\Controllers\Api\Guide\IncidentController as GuideIncidentController;
use App\Http\Controllers\Api\Admin\AdminScheduleCancellationController;
use App\Http\Controllers\Api\Admin\AdminScheduleDeadlineController;
use App\Http\Controllers\Api\Admin\AdminScheduleMergeController;
use App\Http\Controllers\Api\Customer\PolicyController as CustomerPolicyController;
use App\Http\Controllers\Api\Admin\AdminContactLogController;
use App\Http\Controllers\Api\Admin\AdminTransferController;
use App\Http\Controllers\Api\Admin\AdminDiscountCodeController;
use App\Http\Controllers\Api\Admin\AdminAttendanceController;
use App\Http\Controllers\Api\Admin\AdminCancellationPolicyController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminContactMessageController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminServiceController;
use App\Http\Controllers\Api\Admin\AdminTransactionController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

/*
 * Cửa vào tài khoản — mỗi tuyến một hạn mức riêng, chặt hơn trần chung của /api.
 *
 * Trần chung đủ rộng cho việc bấm qua lại các màn hình, nhưng với ô đăng nhập thì rộng nghĩa là cho
 * phép dò mật khẩu. Bốn tuyến dưới đây đều là chỗ đoán được thứ gì đó của người khác - mật khẩu,
 * hoặc việc một địa chỉ email có tài khoản ở đây hay không - nên chúng phải trả giá theo số lần thử.
 *
 * Con số cụ thể không nằm ở đây mà ở `config/rate_limit.php`, để nới hay tắt lúc thử tay chỉ phải
 * sửa một chỗ. Tên nhóm đọc là hiểu: đổi luật của `email` là đổi cho cả ba tuyến gửi thư đi.
 */
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:email');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:reset');

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
// Task X06a - API gửi lại mã tra cứu về email khách vãng lai.
// Hạn mức riêng cùng lý do với nhóm tài khoản ở trên: đây là một đường dò xem email nào đã đặt tour.
Route::post('/bookings/resend-code', [CustomerBookingController::class, 'resendLookupCode'])
    ->middleware('throttle:email');
Route::get('/bookings/{publicToken}', [CustomerBookingController::class, 'show']);
/*
 * G03 - Khai danh sách hành khách bằng mã tra cứu, không cần đăng nhập.
 *
 * Đặt tour vốn không cần tài khoản, nên đường sửa hành khách cũng không được đòi. Trước đây chỉ
 * có đường `/my-bookings/{id}/passengers` sau `role:customer`, tức khách vãng lai đặt xong là
 * không bao giờ sửa được danh sách. Luật quyền sửa vẫn là luật cũ ở PassengerPolicyService.
 */
Route::get('/bookings/{publicToken}/passengers', [CustomerPassengerController::class, 'publicIndex']);
Route::put('/bookings/{publicToken}/passengers', [CustomerPassengerController::class, 'publicUpdate']);
// Mức hoàn dự kiến nếu hủy ngay bây giờ. Khách vãng lai cũng xem được bằng mã tra cứu.
Route::get('/bookings/{publicToken}/refund-quote', [CustomerBookingController::class, 'refundQuote']);
/*
 * Khách nhập tài khoản nhận tiền hoàn.
 *
 * Không đòi đăng nhập, cùng lý do với hai tuyến trên: khách vãng lai bị công ty hủy chuyến cũng
 * phải nhận lại được tiền. Chỉ mở khi đơn thật sự còn nợ khách, xem RefundAccountService.
 */
Route::put('/bookings/{publicToken}/refund-account', [CustomerBookingController::class, 'updateRefundAccount']);
Route::post('/discount-codes/validate', [DiscountCodeController::class, 'validateCode']);

/*
 * Chính sách công ty, đọc được mà không cần đăng nhập.
 *
 * Khách phải đọc được điều khoản hoàn tiền TRƯỚC khi đặt, tức trước khi có tài khoản. Bắt đăng
 * nhập mới xem được chính sách hủy là giấu điều khoản cho tới khi người ta đã cam kết.
 */
Route::get('/policies', [CustomerPolicyController::class, 'show']);
/*
 * Hai tai để nghe cùng một kết quả thanh toán.
 *
 * `/vnpay/return` là chỗ trình duyệt khách quay về — nó chỉ đáng tin cho việc ĐƯA KHÁCH VỀ đúng
 * trang, vì khách tắt app ngân hàng hay rớt mạng là nó không bao giờ chạy.
 *
 * `/vnpay/ipn` là chỗ máy chủ VNPay gọi thẳng máy chủ ta, không đi qua thiết bị của khách. Đây mới
 * là đường ghi nhận tiền. Phải khai địa chỉ này trong cổng quản trị VNPay thì họ mới gọi.
 *
 * Cả hai đi vào cùng một service và chặn trùng theo mã giao dịch, nên đường nào tới trước cũng
 * đúng và tới cả hai cũng chỉ ghi một lần. Xem VNPayCallbackService.
 */
Route::get('/vnpay/return', [CustomerBookingController::class, 'vnpayReturn']);
Route::get('/vnpay/ipn', [CustomerBookingController::class, 'vnpayIpn']);
// Đánh giá của một tour. Có phân trang, và người đang đăng nhập thấy thêm bài của chính mình
// dù bài đó còn chờ duyệt — nên tuyến này đọc `auth('sanctum')` dù không bắt buộc đăng nhập.
Route::get('/reviews/{tour}', [ReviewController::class, 'index']);
/*
 * Form liên hệ. Không cần đăng nhập — phần lớn người viết vào đây là người chưa đặt gì.
 *
 * Hạn mức riêng vì đây là một ô nhập chữ tự do mở cho mọi người: không giới hạn thì nó là chỗ để
 * gửi thư rác hàng loạt vào hộp thư của điều hành.
 */
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:email');

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

    // Đánh giá tour. Luật "chỉ khách đã đi xong chuyến này" nằm trong controller, không ở đây.
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    /*
     * Hộp thông báo — dùng chung cho điều hành và hướng dẫn viên.
     *
     * Một bộ điểm cuối cho hai vai, vì controller đã tự giới hạn theo `$request->user()`: bạn chỉ
     * bao giờ thấy hộp của chính bạn. Chép ra hai bản dưới hai nhóm route là hai bản của cùng một
     * logic, kiểu lỗi dự án này đã gặp nhiều lần.
     *
     * Khách chưa nhận thông báo nào nên chưa mở cho vai đó — mở ra thì phải trả lời câu "khách
     * được báo những gì", mà đó là một tính năng khác.
     *
     * `unread-count` tách riêng vì màn hình hỏi nó định kỳ khi không có WebSocket — kéo cả danh
     * sách về chỉ để đếm là lãng phí đúng vào lúc dễ thấy nhất.
     */
    Route::middleware('role:admin,guide')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

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

        // Xin được bàn giao. Không chọn người thay: đó là việc xếp lịch của điều hành.
        Route::get('/handover-requests', [GuideIncidentController::class, 'myHandoverRequests']);
        Route::post('/schedules/{schedule}/handover-request', [GuideIncidentController::class, 'requestHandover']);

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
        // K06 - Tour đã xóa. Phải khai trước /tours/{id}, nếu không "trashed" bị khớp vào {id}
        // và đi thẳng vào show() với id không phải số.
        Route::get('/tours/trashed', [AdminTourController::class, 'trashed']);
        Route::get('/tours/{id}', [AdminTourController::class, 'show']);
        Route::post('/tours', [AdminTourController::class, 'store']);
        Route::put('/tours/{id}', [AdminTourController::class, 'update']);
        Route::post('/tours/{id}', [AdminTourController::class, 'update']);
        // Xóa tour (thực hiện bằng xóa mềm) và khôi phục.
        Route::get('/tours/{id}/delete-preview', [AdminTourController::class, 'deletePreview']);
        Route::delete('/tours/{id}', [AdminTourController::class, 'destroy']);
        Route::put('/tours/{id}/restore', [AdminTourController::class, 'restore']);
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
        // Phiếu bàn giao: hai cách xử lý, không có luồng duyệt nhiều bước.
        Route::put('/handover-requests/{id}/resolve', [AdminGuideHandoverController::class, 'resolveRequest']);
        Route::put('/handover-requests/{id}/close', [AdminGuideHandoverController::class, 'closeRequest']);

        // O - Sự cố dọc đường. Chỉ ở đây mới quyết được tiền; hướng dẫn viên chỉ báo cáo.
        Route::get('/incidents', [AdminIncidentController::class, 'index']);
        Route::get('/incidents/{id}', [AdminIncidentController::class, 'show']);
        Route::post('/incidents/{id}/resolve', [AdminIncidentController::class, 'resolve']);
        /*
         * Vòng đời một khoản: duyệt → khách đồng ý → thu (hoặc hoàn). Miễn là lối thoát ở giữa.
         * Bốn thao tác tách rời vì ở hiện trường chúng xảy ra ở bốn thời điểm khác nhau.
         */
        Route::put('/surcharges/{id}/approve', [AdminIncidentController::class, 'approveSurcharge']);
        Route::put('/surcharges/{id}/consent', [AdminIncidentController::class, 'recordConsent']);
        Route::put('/surcharges/{id}/settle', [AdminIncidentController::class, 'settleSurcharge']);
        Route::put('/surcharges/{id}/waive', [AdminIncidentController::class, 'waiveSurcharge']);

        // 14 - Booking đoàn: báo giá, chốt thành đơn, giảm số khách.
        Route::get('/group-bookings', [AdminGroupBookingController::class, 'index']);
        Route::put('/group-bookings/{id}/quote', [AdminGroupBookingController::class, 'quote']);
        Route::put('/group-bookings/{id}/confirm', [AdminGroupBookingController::class, 'confirm']);
        Route::put('/group-bookings/{id}/reject', [AdminGroupBookingController::class, 'reject']);
        Route::put('/bookings/{id}/reduce-guests', [AdminGroupBookingController::class, 'reduceGuests']);

        /*
         * Sổ giao dịch — MỌI đơn, không riêng đơn đoàn.
         *
         * Hai tuyến đầu giữ nguyên đường dẫn cũ, chỉ đổi controller: từ khi đơn lẻ cũng trả nhiều
         * đợt (cọc trước, phần còn lại sau, có thể bằng chuyển khoản hoặc tiền mặt), sổ không còn
         * là chuyện riêng của đoàn.
         *
         * `/refunds` khai TRƯỚC `/bookings/{id}` không cần thiết vì khác tiền tố, nhưng để cạnh
         * nhau cho thấy chúng đọc cùng một nguồn: khoản hoàn là một dòng trong chính sổ này.
         */
        /*
         * Sổ tổng: mọi bút toán của mọi đơn, xếp theo thời gian.
         *
         * Khác `/bookings/{id}/payments` ngay dưới — cái đó trả lời "khách này đã trả chưa", cái
         * này trả lời "hôm nay thu bao nhiêu, từ những đơn nào", câu kế toán hỏi mỗi ngày.
         */
        Route::get('/transactions', [AdminTransactionController::class, 'index']);
        Route::get('/transactions/export', [AdminTransactionController::class, 'export']);

        Route::get('/refunds', [AdminBookingPaymentController::class, 'refundQueue']);
        Route::get('/bookings/{id}/payments', [AdminBookingPaymentController::class, 'index']);
        Route::post('/bookings/{id}/payments', [AdminBookingPaymentController::class, 'store']);
        // Nhập hộ tài khoản nhận tiền hoàn khi khách đọc qua điện thoại.
        Route::put('/bookings/{id}/refund-account', [AdminBookingPaymentController::class, 'updateRefundAccount']);

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
        // Q07 - Xuất tệp gửi khách sạn, nhà xe, và để hướng dẫn viên in cầm theo.
        Route::get('/schedules/{id}/manifest/export', [AdminPassengerController::class, 'exportManifest']);

        /*
         * Q - Hợp đồng du lịch. Bản in nằm ở tuyến web `contracts.print`, không phải ở đây:
         * nó trả HTML để in và mở bằng liên kết có chữ ký, xem routes/web.php.
         */
        Route::get('/bookings/{id}/contract', [AdminContractController::class, 'show']);
        Route::post('/bookings/{id}/contract', [AdminContractController::class, 'issue']);
        Route::put('/contracts/{id}/signed', [AdminContractController::class, 'markSigned']);

        // L03 - Ghép hai chuyến của cùng một tour.
        Route::get('/schedules/{id}/merge-candidates', [AdminScheduleMergeController::class, 'candidates']);
        Route::post('/schedules/{id}/merge', [AdminScheduleMergeController::class, 'store']);

        // E04 - Dòng thời gian thay đổi của một đơn.
        Route::get('/bookings/{id}/history', [AdminBookingController::class, 'history']);

        // Nhật ký hệ thống: gộp nhật ký đơn và nhật ký chuyến thành một dòng thời gian.
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);

        /*
         * Nhật ký liên hệ khách. Chỉ ghi và đọc - không sửa, không xóa.
         *
         * Đặt ngay trên phần chuyển chuyến vì đó là chỗ nó bắt buộc: không có bản ghi khách đồng ý
         * thì không chuyển được.
         */
        Route::get('/bookings/{id}/contact-logs', [AdminContactLogController::class, 'index']);
        Route::post('/bookings/{id}/contact-logs', [AdminContactLogController::class, 'store']);

        // I05 - Chuyển đơn sang chuyến khác.
        Route::get('/bookings/{id}/transfer-options', [AdminTransferController::class, 'options']);
        Route::post('/bookings/{id}/transfer', [AdminTransferController::class, 'store']);
        Route::get('/bookings/{id}/transfers', [AdminTransferController::class, 'history']);

        Route::get('/bookings/{id}/cancel-preview', [AdminBookingController::class, 'cancelPreview']);
        Route::put('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel']);
        /*
         * Không còn tuyến mở lại đơn đã hủy. Hủy là trạng thái kết thúc; hủy nhầm thì đặt lại đơn
         * mới. Xem chú thích ở AdminBookingController, chỗ hàm reopen() từng nằm.
         */
        Route::apiResource('discount-codes', AdminDiscountCodeController::class);

        // Quản lý dịch vụ phát sinh (khách sạn, ăn uống, ...)
        Route::apiResource('services', AdminServiceController::class);

        // Quản lý danh mục tour (biển đảo, nghỉ dưỡng, ...)
        Route::apiResource('categories', AdminCategoryController::class);

        /*
         * Chính sách hủy: **một bảng phí duy nhất**, không phải danh sách.
         *
         * Chỉ còn đọc và ghi — không tạo, không xóa. Đường dẫn giữ dạng số nhiều cho khỏi phải
         * sửa mọi chỗ đang gọi, nhưng phía sau nó là đúng một bản ghi.
         */
        Route::get('cancellation-policies', [AdminCancellationPolicyController::class, 'index']);
        Route::put('cancellation-policies', [AdminCancellationPolicyController::class, 'update']);

        /*
         * Kiểm duyệt đánh giá và trả lời khách.
         *
         * Từ chối không xóa bản ghi — xóa hẳn là quyền của chính người viết, qua tuyến
         * `DELETE /reviews/{id}` ở nhóm khách hàng.
         */
        /*
         * Hộp thư liên hệ và danh sách nhận bản tin.
         *
         * Trước đây ô "đăng ký nhận bản tin" ở trang chủ ghi vào `newsletter_subscribers` mà không
         * màn hình nào đọc bảng ấy — một nút bấm không dẫn tới đâu. Xuất CSV là cách giao danh
         * sách cho công cụ gửi thư hàng loạt; hệ thống này cố ý không tự gửi bản tin.
         */
        Route::get('contact-messages', [AdminContactMessageController::class, 'index']);
        Route::put('contact-messages/{id}/handled', [AdminContactMessageController::class, 'toggleHandled']);
        Route::get('newsletter-subscribers', [AdminContactMessageController::class, 'subscribers']);
        Route::get('newsletter-subscribers/export', [AdminContactMessageController::class, 'exportSubscribers']);

        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::put('reviews/{id}/approve', [AdminReviewController::class, 'approve']);
        Route::put('reviews/{id}/reject', [AdminReviewController::class, 'reject']);
        Route::put('reviews/{id}/reply', [AdminReviewController::class, 'reply']);
    });
});






