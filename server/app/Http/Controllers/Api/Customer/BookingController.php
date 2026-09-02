<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Enums\BookingAuditAction;
use App\Enums\ScheduleStatus;
use App\Mail\BookingCreatedMail;
use App\Mail\BookingPaidMail;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\PaymentLog;
use App\Models\TourSchedule;
use App\Services\BookingAuditLogger;
use App\Services\BookingContactService;
use App\Services\BookingHoldService;
use App\Services\BookingPaymentService;
use App\Services\BookingPolicyService;
use App\Services\CancellationPolicyService;
use App\Services\PassengerPolicyService;
use App\Services\RefundAccountService;
use App\Services\ScheduleLifecycleService;
use App\Services\VNPayCallbackService;
use App\Services\VNPayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingController extends Controller
{
    /**
     * Khoảng thời gian coi hai lần bấm đặt giống nhau là một.
     *
     * Sáu mươi giây theo docs/nghiep-vu/08-danh-muc-edge-case.md tình huống A04. Đủ dài để phủ
     * một lần bấm lại vì sốt ruột, đủ ngắn để không chặn người thật sự muốn đặt đơn thứ hai.
     */
    private const DUPLICATE_WINDOW_SECONDS = 60;

    public function __construct(
        private VNPayService $vnpayService,
        private BookingHoldService $holdService,
        private BookingPolicyService $bookingPolicy,
        private ScheduleLifecycleService $scheduleLifecycle,
        private CancellationPolicyService $cancellationPolicy,
        private PassengerPolicyService $passengerPolicy,
        private BookingAuditLogger $auditLogger,
        private BookingPaymentService $paymentService,
        private VNPayCallbackService $callbackService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'tour_schedule_id' => 'required|exists:tour_schedules,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'adult_count' => 'required|integer|min:1',
            'child_count' => 'nullable|integer|min:0',
            'infant_count' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:1000',
            'discount_code' => 'nullable|string|max:50',
            'passengers' => 'nullable|array|max:50',
            'passengers.*.name' => 'required_with:passengers|string|max:255',
            'passengers.*.type' => 'required_with:passengers|in:adult,child,infant',
            'passengers.*.gender' => 'nullable|in:male,female,other',
            'passengers.*.date_of_birth' => 'nullable|date|before_or_equal:today',
            'passengers.*.identity_number' => 'nullable|string|max:50',
            'passengers.*.id_type' => 'nullable|in:cccd,cmnd,passport,birth_certificate',
            'passengers.*.nationality' => 'nullable|string|max:60',
            'passengers.*.phone' => 'nullable|string|max:20',
            'passengers.*.special_request' => 'nullable|string|max:500',
            'passengers.*.is_contact' => 'nullable|boolean',
            'passengers.*.note' => 'nullable|string|max:255',
        ]);

        $user = auth('sanctum')->user();

        if ($user && $user->role !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ khách hàng mới có thể đặt tour.',
            ], 403);
        }

        $guestId = null;
        $guestCookie = null;

        if (!$user) {
            $guestId = $request->cookie('guest_id') ?: (string) Str::uuid();

            if (!$request->cookie('guest_id')) {
                $guestCookie = cookie(
                    'guest_id',
                    $guestId,
                    60 * 24 * 30,
                    '/',
                    null,
                    app()->environment('production'),
                    true,
                    false,
                    'Lax'
                );
            }
        }

        // Nhả chỗ của các đơn quá hạn thanh toán trước khi kiểm tra chỗ trống,
        // để khách mới dùng được ngay slot vừa được trả lại.
        $this->holdService->releaseOverdueForSchedule((int) $data['tour_schedule_id']);

        // Đặt ngoài giao dịch để biết được kết quả sau khi giao dịch đóng: đơn trùng thì không
        // gửi lại thư và không tạo lại liên kết thanh toán.
        $laDonTrung = false;

        // Mã giảm giá vừa hết lượt trong lúc khách điền thông tin. Đơn vẫn tạo theo giá gốc,
        // nhưng phải nói cho khách biết vì họ đang chờ thấy con số đã giảm.
        $thongBaoMaGiam = null;

        $booking = DB::transaction(function () use ($data, $user, $guestId, &$laDonTrung, &$thongBaoMaGiam) {
            $schedule = TourSchedule::query()
                ->where('id', $data['tour_schedule_id'])
                ->where('tour_id', $data['tour_id'])
                ->lockForUpdate()
                ->first();

            if (!$schedule) {
                throw ValidationException::withMessages([
                    'tour_schedule_id' => 'Lịch khởi hành không thuộc tour đã chọn.',
                ]);
            }

            if (!$schedule->isBookable() || $schedule->tour?->status !== 'active') {
                throw ValidationException::withMessages([
                    'tour_schedule_id' => 'Lịch khởi hành này hiện không khả dụng.',
                ]);
            }
            $adultCount = (int) $data['adult_count'];
            $childCount = (int) ($data['child_count'] ?? 0);
            $infantCount = (int) ($data['infant_count'] ?? 0);
            $totalGuests = $adultCount + $childCount + $infantCount;
            $availableSeats = $schedule->max_people - $schedule->booked_people;

            if ($totalGuests > $availableSeats) {
                throw ValidationException::withMessages([
                    'adult_count' => 'Số chỗ còn lại không đủ cho booking này.',
                ]);
            }

            $tour = $schedule->tour;
            $subtotalAmount = ($adultCount * (float) $tour->adult_price)
                + ($childCount * (float) $tour->child_price)
                + ($infantCount * (float) $tour->infant_price);
            $discount = $this->resolveDiscount($data['discount_code'] ?? null, (float) $subtotalAmount);
            $totalAmount = max(0, $subtotalAmount - $discount['amount']);
            $thongBaoMaGiam = $discount['notice'];

            /*
             * X01 - Khách bấm đặt hai lần.
             *
             * Mạng chậm, khách bấm rồi không thấy gì nên bấm lại. Không chặn thì thành hai đơn
             * giống hệt nhau, trừ hai lần số chỗ, gửi hai thư. Khách gọi lên bảo chỉ đặt một, và
             * nếu đã qua hạn chốt thì đơn thừa còn thành ghế chết do lỗi của chính hệ thống.
             *
             * Nhận diện theo email, chuyến và tổng tiền trong 60 giây. Ba yếu tố cùng khớp trong
             * một phút gần như chắc chắn là một lần đặt bị gửi hai lần; còn người thật muốn đặt
             * thêm một đơn y hệt thì chờ một phút, cái giá đó rẻ hơn nhiều so với đơn trùng.
             *
             * Kiểm bên trong giao dịch đã khóa dòng chuyến, nên hai yêu cầu gửi cùng lúc cũng
             * phải xếp hàng và người vào sau nhìn thấy đơn của người vào trước.
             */
            $donTrung = Booking::query()
                ->where('customer_email', $data['customer_email'])
                ->where('tour_schedule_id', $schedule->id)
                ->where('total_amount', $totalAmount)
                ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
                ->latest('id')
                ->first();

            if ($donTrung) {
                $laDonTrung = true;

                return $donTrung->load(['tour', 'schedule']);
            }

            $booking = Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $tour->id,
                'customer_id' => $user?->id,
                'guest_id' => $user ? null : $guestId,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'departure_date' => $schedule->start_date,
                'guests' => $totalGuests,
                'adult_count' => $adultCount,
                'child_count' => $childCount,
                'infant_count' => $infantCount,
                'total_amount' => $totalAmount,
                'discount_code_id' => $discount['model']?->id,
                'discount_code' => $discount['model']?->code,
                'discount_amount' => $discount['amount'],
                'status' => 'pending',
                'expires_at' => now()->addMinutes($this->holdService->holdMinutes()),
                'note' => $data['note'] ?? null,
                /*
                 * Chép chính sách hủy vào đơn ngay lúc đặt.
                 *
                 * Cả hệ thống chỉ còn MỘT bảng phí, nên bản chép luôn giống bản gốc - cho tới
                 * ngày ai đó sửa bảng phí. Từ giây ấy, đơn này vẫn giữ đúng điều khoản khách đã
                 * đồng ý, còn đơn mới theo bảng mới. Bỏ dòng chép đi thì sửa một con số là hồi tố
                 * lên toàn bộ đơn đã bán.
                 */
                'cancellation_policy_id' => \App\Models\CancellationPolicy::dangApDung()?->id,
            ]);
            // Ghi qua PassengerPolicyService để danh sách khai lúc đặt chịu đúng những luật mà
            // danh sách sửa về sau phải chịu. Hai đường ghi mà hai bộ luật thì sớm muộn cũng có
            // đơn lọt qua đường này với số giấy tờ trùng nhau.
            if (!empty($data['passengers'])) {
                $this->passengerPolicy->validateList($booking, $data['passengers']);
                $this->passengerPolicy->replaceList($booking, $data['passengers']);
            }

            if ($discount['model']) {
                $discount['model']->increment('used_count');
            }

            $schedule->increment('booked_people', $totalGuests);
            $schedule->refresh();

            if ($schedule->booked_people >= $schedule->max_people) {
                $this->scheduleLifecycle->transitionTo(
                    $schedule,
                    ScheduleStatus::Closed,
                    'Tự động đóng bán do booking vừa lấp đầy số chỗ.',
                );
            }

            $this->holdService->refreshTourAvailability($schedule);

            return $booking->load(['tour', 'schedule']);
        });

        // Đơn lẻ thu đủ một lần. Trả từng phần là chuyện của đơn đoàn, và ở đó tiền về qua sổ
        // giao dịch do điều hành ghi chứ không qua cổng.
        $paymentUrl = $this->vnpayService->createPayment($booking, (float) $booking->total_amount);

        // Đơn trùng thì không gửi thư lần hai. Nhận hai thư xác nhận cho một lần đặt làm khách
        // tưởng mình vừa đặt hai chuyến và gọi lên hỏi, đúng thứ mà luật chống trùng sinh ra để
        // tránh.
        if (!$laDonTrung) {
            $this->sendBookingCreatedMailAfterResponse($booking, $paymentUrl);
        }

        $thongBao = $laDonTrung
            ? 'Đơn đặt tour của bạn đã được ghi nhận trước đó. Vui lòng thanh toán trong '
                . $this->holdService->holdMinutes() . ' phút để giữ chỗ.'
            : 'Đặt tour thành công. Vui lòng thanh toán trong '
                . $this->holdService->holdMinutes() . ' phút để giữ chỗ.';

        if ($thongBaoMaGiam) {
            $thongBao = $thongBaoMaGiam . ' ' . $thongBao;
        }

        $response = response()->json([
            'success' => true,
            'message' => $thongBao,
            'data' => [
                'booking' => $booking,
                'payment_url' => $paymentUrl,
                // Tách riêng để giao diện làm nổi được, thay vì trộn vào câu thông báo chung.
                'discount_notice' => $thongBaoMaGiam,
            ],
        ], 201);

        return $guestCookie ? $response->cookie($guestCookie) : $response;
    }

    public function myBookings(Request $request): JsonResponse
    {
        Booking::query()
            ->where('customer_id', $request->user()->id)
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->each(fn (Booking $booking) => $this->holdService->releaseIfOverdue($booking));

        /*
         * Kèm cả khoản phụ thu sự cố, nhưng CHỈ những khoản đã có hiệu lực.
         *
         * Khoản đang chờ duyệt là con số điều hành còn đang cân nhắc; hiện nó ra là nói với khách
         * một mức tiền có thể đổi hoặc bị bỏ, và khách sẽ nhớ đúng con số đầu tiên họ đọc được.
         *
         * Khoản đã miễn cũng không hiện: nó không còn là thứ khách phải trả.
         */
        $bookings = Booking::query()
            ->with([
                'tour',
                'schedule.guides:id,name,phone',
                'passengers',
                'payments',
                'surcharges' => fn ($q) => $q->coHieuLuc()->latest('id'),
            ])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->get();

        /*
         * Kèm số đã thu, số còn thiếu và liên kết thanh toán cho từng đơn.
         *
         * Trước đây màn "Đơn của tôi" không có đường nào để trả tiền: khách đăng nhập, thấy đơn
         * đang chờ thanh toán, và không có nút nào bấm — muốn trả phải quay ra trang tra cứu và
         * nhập lại mã. Liên kết vốn chỉ có ở đó.
         *
         * `payments` nạp sẵn ở trên để không sinh một truy vấn cho mỗi đơn.
         */
        $bookings->each(function (Booking $booking) {
            $conThieu = $this->paymentService->balanceDue($booking);

            $booking->setAttribute('net_paid', $this->paymentService->netPaid($booking));
            $booking->setAttribute('balance_due', $conThieu);

            if ($conThieu > 0
                && !$booking->isGroup()
                && in_array($booking->status, ['pending', 'confirmed'], true)) {
                $booking->setAttribute(
                    'payment_url',
                    $this->vnpayService->createPayment($booking, $conThieu),
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    public function show(string $publicToken): JsonResponse
    {
        // Cùng bộ lọc như myBookings: chỉ khoản đã có hiệu lực. Hai cửa vào một đơn phải nói
        // giống nhau, nếu không thì tra cứu bằng mã lại thấy khác lúc đăng nhập xem.
        $booking = Booking::query()
            ->with([
                'tour',
                'schedule.guides:id,name,phone',
                'passengers',
                'surcharges' => fn ($q) => $q->coHieuLuc()->latest('id'),
            ])
            ->where('public_token', $publicToken)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin đặt tour.',
            ], 404);
        }

        $this->holdService->releaseIfOverdue($booking);

        /*
         * Liên kết thanh toán thu phần CÒN THIẾU, đọc từ sổ giao dịch.
         *
         * Đơn lẻ thu đủ một lần nên gần như luôn bằng tổng đơn. Không viết thẳng `total_amount`
         * vì có một trường hợp thật khác: khách chuyển khoản thiếu, điều hành ghi vào sổ đúng số
         * đã nhận rồi xác nhận đơn. Lúc ấy đơn `confirmed` mà vẫn còn nợ, và khách cần đường trả
         * nốt đúng phần thiếu chứ không phải trả lại từ đầu.
         */
        $conThieu = $this->paymentService->balanceDue($booking);

        /*
         * Đơn ĐOÀN không nhận liên kết cổng thanh toán.
         *
         * Đó là quyết định có sẵn của luồng đoàn, không phải sơ suất: không kế toán nào duyệt
         * chuyển tám mươi triệu qua cổng trong mười phút, nên tiền đoàn về nhiều đợt bằng chuyển
         * khoản và điều hành ghi vào sổ. Dựng liên kết ở đây là mời họ đi một đường mà cả hai bên
         * đã thống nhất không dùng.
         */
        if ($conThieu > 0
            && !$booking->isGroup()
            && in_array($booking->status, ['pending', 'confirmed'], true)) {
            $booking->setAttribute('payment_url', $this->vnpayService->createPayment($booking, $conThieu));
        }

        $booking->setAttribute('net_paid', $this->paymentService->netPaid($booking));
        $booking->setAttribute('balance_due', $conThieu);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Mức hoàn dự kiến nếu hủy đơn này ngay bây giờ.
     *
     * Hiển thị cho khách TRƯỚC khi họ bấm hủy. Doc 03 mục 5.2 nêu rõ bước này là bắt buộc,
     * vì phần lớn khiếu nại sau hủy đến từ việc khách không biết mình sẽ mất bao nhiêu.
     *
     * Tra theo mã tra cứu nên khách vãng lai cũng xem được, không cần đăng nhập.
     */
    public function refundQuote(string $publicToken): JsonResponse
    {
        $booking = Booking::with(['schedule', 'cancellationPolicy.rules'])
            ->where('public_token', $publicToken)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt tour.',
            ], 404);
        }

        $quote = $this->cancellationPolicy->quote($booking);

        return response()->json([
            'success' => true,
            'data' => $quote + [
                'policy_name' => $booking->cancellationPolicy?->name,
                'rules' => $booking->cancellationPolicy?->rules->map(fn ($rule) => [
                    'window' => $rule->windowLabel(),
                    'refund_percent' => $rule->refund_percent,
                    'note' => $rule->note,
                ]),
            ],
        ]);
    }

    /**
     * Khách tự sửa thông tin liên hệ đã nhập nhầm.
     *
     * Không khóa theo hạn chốt danh sách, khác với sửa danh sách hành khách: thông tin liên hệ
     * không gửi cho nhà cung cấp, nó là số công ty gọi khách. Xem BookingContactService.
     */
    public function updateContact(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(BookingContactService::validationRules());

        $booking = Booking::query()
            ->where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt tour của bạn.',
            ], 404);
        }

        $daSua = app(BookingContactService::class)->update($booking, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thông tin liên hệ.',
            'data' => $daSua->only(['id', 'customer_name', 'customer_email', 'customer_phone']),
        ]);
    }

    /**
     * Khách nhập tài khoản nhận tiền hoàn, bằng mã tra cứu.
     *
     * Dùng cho các khoản hoàn do CÔNG TY khởi xướng - hủy chuyến, hoặc điều hành hủy đơn. Ở những
     * đường ấy khách không mở form nào cả, nên trước đây hệ thống lập ra một khoản phải trả mà
     * không biết chuyển đi đâu.
     *
     * Không đòi đăng nhập, vì đặt tour vốn không đòi: khách vãng lai bị hủy chuyến cũng phải nhận
     * lại được tiền của mình. `RefundAccountService` chỉ nhận khi đơn thật sự đang còn nợ khách.
     */
    public function updateRefundAccount(Request $request, string $publicToken): JsonResponse
    {
        $validated = $request->validate(
            RefundAccountService::validationRules(),
            RefundAccountService::validationMessages(),
        );

        $booking = Booking::query()->where('public_token', $publicToken)->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt tour.',
            ], 404);
        }

        app(RefundAccountService::class)->update($booking, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu tài khoản nhận tiền hoàn. Chúng tôi sẽ chuyển trong thời gian sớm nhất.',
        ]);
    }

    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy đơn hàng.',
        ]);

        $booking = Booking::with(['schedule', 'tour', 'discountCode'])->where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt tour của bạn.',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đơn đặt tour đang ở trạng thái chờ duyệt (pending).',
            ], 400);
        }

        $cancelled = DB::transaction(function () use ($booking, $validated) {
            $schedule = $booking->tour_schedule_id
                ? TourSchedule::query()
                    ->whereKey($booking->tour_schedule_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if (!$fresh) {
                return false;
            }

            // Kiểm tra trên bản ghi vừa khóa, trước khi xét trạng thái đơn, để khách nhận được
            // đúng lý do "chuyến đã khởi hành" thay vì thông báo chung chung.
            $this->bookingPolicy->assertCancellable($fresh, $schedule);

            if ($fresh->status !== 'pending') {
                return false;
            }

            $fresh->update([
                'status' => 'cancelled',
                'cancel_reason' => $validated['cancel_reason'],
                'cancel_type' => 'by_customer',
                'cancelled_at' => now(),
                'cancelled_by' => $fresh->customer_id,
            ]);

            $this->holdService->releaseHold($fresh, $schedule);

            $this->auditLogger->logStatusChange(
                $fresh,
                BookingAuditAction::Cancelled,
                'pending',
                'cancelled',
                $validated['cancel_reason'],
                ['seats_released' => (bool) $fresh->fresh()->seats_released],
            );

            return true;
        });

        if (!$cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đơn đặt tour đang ở trạng thái chờ duyệt (pending).',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Hủy đơn đặt tour thành công.',
            'data' => $booking->refresh(),
        ]);
    }

    /**
     * IPN — VNPay gọi thẳng máy chủ ta để báo kết quả.
     *
     * Đây mới là đường ghi nhận tiền đáng tin. `vnpayReturn` bên dưới đi qua trình duyệt của khách,
     * nên nó không chạy khi khách tắt app ngân hàng, hết pin hay rớt mạng — và trước khi có tuyến
     * này, đúng những đơn ấy bị tác vụ nhả chỗ hủy sau mười phút trong khi tiền đã nằm trong tài
     * khoản công ty.
     *
     * Trả JSON theo đúng định dạng VNPay chờ đợi; họ đọc `RspCode` để biết có phải gọi lại không.
     *
     * Cấu hình: khai địa chỉ tuyến này trong cổng quản trị VNPay (mục URL nhận IPN). Không khai thì
     * VNPay không gọi, và hệ thống lại chỉ còn một tai để nghe.
     */
    public function vnpayIpn(Request $request): JsonResponse
    {
        $ketQua = $this->callbackService->handle($request->query());

        if ($ketQua['booking'] && $ketQua['rsp_code'] === VNPayCallbackService::RSP_THANH_CONG) {
            $this->sendBookingPaidMailAfterResponse($ketQua['booking']);
        }

        return response()->json([
            'RspCode' => $ketQua['rsp_code'],
            'Message' => match ($ketQua['rsp_code']) {
                VNPayCallbackService::RSP_THANH_CONG => 'Confirm Success',
                VNPayCallbackService::RSP_KHONG_TIM_THAY_DON => 'Order not found',
                VNPayCallbackService::RSP_DA_XU_LY => 'Order already confirmed',
                VNPayCallbackService::RSP_SAI_CHU_KY => 'Invalid signature',
                default => 'Unknown error',
            },
        ]);
    }

    /**
     * Trình duyệt khách quay về sau khi trả tiền.
     *
     * Từ khi có IPN, tuyến này chỉ còn nhiệm vụ ĐƯA KHÁCH VỀ đúng trang. Nó vẫn gọi cùng một service
     * xử lý, vì đường nào tới trước cũng phải ghi nhận được — IPN có thể chậm vài giây, và khách
     * quay về trước thì không có lý do bắt họ nhìn màn hình "chưa thanh toán".
     *
     * Gọi hai lần không nhân đôi thứ gì: service chặn theo mã giao dịch, xem `VNPayCallbackService`.
     */
    public function vnpayReturn(Request $request)
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $ketQua = $this->callbackService->handle($request->query());

        $bookingId = $ketQua['booking_id'];
        $isSuccessful = $ketQua['successful'];
        $paidBooking = $ketQua['booking'];

        if ($paidBooking && $ketQua['rsp_code'] === VNPayCallbackService::RSP_THANH_CONG) {
            $this->sendBookingPaidMailAfterResponse($paidBooking);
        }

        if ($bookingId) {
            $publicToken = $paidBooking?->public_token
                ?? Booking::query()->where('id', $bookingId)->value('public_token');

            if ($publicToken) {
                return redirect()->away(URL::query($frontendUrl . '/booking-success/' . $publicToken, [
                    'payment_status' => $isSuccessful ? 'success' : 'failed',
                ]));
            }
        }

        return redirect()->away(URL::query($frontendUrl . '/payment-result', [
            'status' => $isSuccessful ? 'success' : 'failed',
        ]));
    }

    /**
     * X03 - Kiểm lại mã giảm giá ngay trong giao dịch tạo đơn.
     *
     * Khách nhập mã ở bước xem giá, rồi điền thông tin vài phút mới bấm đặt. Trong khoảng đó
     * mã hoàn toàn có thể hết lượt vì người khác vừa dùng nốt, hoặc vừa qua hạn. Kiểm lại dưới
     * khóa dòng là bắt buộc, nếu không hai người cùng dùng lượt cuối và cả hai đều qua.
     *
     * Hai loại hỏng, hai cách xử lý khác nhau:
     *
     * - Mã không tồn tại: khách gõ sai, từ chối và nói rõ để họ sửa.
     * - Mã có thật nhưng vừa hết lượt hoặc hết hạn: KHÔNG từ chối đơn. Khách đã điền xong hết
     *   thông tin, chặn ở bước cuối vì lý do không phải lỗi của họ là cách chắc chắn nhất để
     *   mất một đơn hàng. Tạo đơn giá gốc và nói rõ trong thông báo trả về.
     *
     * Xem docs/nghiep-vu/08-danh-muc-edge-case.md tình huống A11.
     *
     * @return array{model: DiscountCode|null, amount: float, notice: string|null}
     */
    private function resolveDiscount(?string $code, float $subtotalAmount): array
    {
        if (! $code) {
            return ['model' => null, 'amount' => 0.0, 'notice' => null];
        }

        $discountCode = DiscountCode::query()
            ->where('code', Str::upper($code))
            ->lockForUpdate()
            ->first();

        if (! $discountCode) {
            throw ValidationException::withMessages([
                'discount_code' => 'Mã giảm giá không tồn tại. Vui lòng kiểm tra lại.',
            ]);
        }

        if (! $discountCode->isUsableFor($subtotalAmount)) {
            return [
                'model' => null,
                'amount' => 0.0,
                'notice' => sprintf(
                    'Mã giảm giá %s không còn áp dụng được nên đơn được tạo theo giá gốc.',
                    $discountCode->code,
                ),
            ];
        }

        return [
            'model' => $discountCode,
            'amount' => $discountCode->calculateDiscount($subtotalAmount),
            'notice' => null,
        ];
    }

    private function sendBookingCreatedMailAfterResponse(Booking $booking, ?string $paymentUrl): void
    {
        app()->terminating(function () use ($booking, $paymentUrl) {
            $this->sendBookingCreatedMail($booking, $paymentUrl);
        });
    }
    private function sendBookingCreatedMail(Booking $booking, ?string $paymentUrl): void
    {
        try {
            Mail::to($booking->customer_email)->send(new BookingCreatedMail($booking, $paymentUrl));
        } catch (Throwable $exception) {
            Log::warning('Could not send booking confirmation email.', [
                'booking_id' => $booking->id,
                'customer_email' => $booking->customer_email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendBookingPaidMailAfterResponse(Booking $booking): void
    {
        app()->terminating(function () use ($booking) {
            $this->sendBookingPaidMail($booking);
        });
    }
    private function sendBookingPaidMail(Booking $booking): void
    {
        try {
            Mail::to($booking->customer_email)->send(new BookingPaidMail($booking));
        } catch (Throwable $exception) {
            Log::warning('Could not send paid booking confirmation email.', [
                'booking_id' => $booking->id,
                'customer_email' => $booking->customer_email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * =========================================================================
     * TASK X06a: Gửi lại mã tra cứu về email đã dùng khi đặt tour (Edge Case A16)
     * =========================================================================
     * Khách vãng lai khi mất mã tra cứu có thể nhập Email & SĐT đã đặt tour.
     * Hệ thống tìm kiếm các đơn hàng tương ứng và tự động gửi Mail thông báo.
     * 
     * @param Request $request chứa email (bắt buộc) và phone (tùy chọn)
     * @return JsonResponse
     */
    public function resendLookupCode(Request $request): JsonResponse
    {
        // 1. Validate dữ liệu đầu vào từ phía khách hàng
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $email = trim($validated['email']);
        $phone = !empty($validated['phone']) ? trim($validated['phone']) : null;

        // 2. Tìm kiếm các đơn đặt tour trùng khớp thông tin email (và phone nếu có)
        $query = Booking::query()
            ->where('customer_email', $email)
            ->where('status', '!=', 'cancelled')
            ->with(['tour:id,title', 'schedule:id,start_date'])
            ->latest();

        if ($phone) {
            $query->where('customer_phone', $phone);
        }

        $bookings = $query->get();

        // 3. Nếu tìm thấy ít nhất 1 đơn, tiến hành gửi Email chứa mã tra cứu
        if ($bookings->isNotEmpty()) {
            try {
                Mail::to($email)->send(new \App\Mail\ResendLookupCodeMail($bookings, $email));
            } catch (Throwable $exception) {
                Log::warning('Lỗi khi gửi email mã tra cứu (Task X06a):', [
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // 4. Trả về thông báo thành công chung (ngay cả khi không tìm thấy đơn để tránh kẻ xấu lợi dụng dò Email)
        return response()->json([
            'success' => true,
            'message' => 'Nếu email tồn tại trong hệ thống, danh sách mã tra cứu đã được gửi về hòm thư của bạn. Vui lòng kiểm tra email (bao gồm cả mục Spam/Thư rác).',
        ]);
    }
}




