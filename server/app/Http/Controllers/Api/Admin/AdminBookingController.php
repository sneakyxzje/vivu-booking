<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\TourSchedule;
use App\Services\BookingAuditLogger;
use App\Services\BookingContactService;
use App\Services\BookingHoldService;
use App\Services\BookingPaymentService;
use App\Services\BookingPolicyService;
use App\Services\CancellationPolicyService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class AdminBookingController extends Controller
{
    public function __construct(
        private BookingHoldService $holdService,
        private BookingPolicyService $bookingPolicy,
        private ScheduleLifecycleService $scheduleLifecycle,
        private CancellationPolicyService $cancellationPolicy,
        private BookingAuditLogger $auditLogger,
        private BookingContactService $contactService,
        private BookingPaymentService $payments,
    ) {
    }

    /**
     * Danh sách đơn — tìm, lọc và sắp xếp đều ở máy chủ.
     *
     * ## Vì sao không để trình duyệt tự lọc
     *
     * Điểm cuối này vốn trả về đúng mười đơn mới nhất, còn màn hình thì lọc và sắp xếp chính mười
     * dòng ấy. Nhìn dữ liệu mẫu thì không thấy gì sai, nhưng với dữ liệu thật thì đó là một cái bẫy:
     *
     *   - Gõ "BK-19" ra "không tìm thấy" nếu đơn ấy nằm ở trang 4. Người dùng kết luận đơn không
     *     tồn tại, trong khi nó vẫn ở đó.
     *   - "Tổng giá giảm dần" chỉ sắp trong mười dòng đang nhìn thấy. Đơn to nhất của trang 1 không
     *     phải đơn to nhất của cửa hàng, mà màn hình không nói ra điều đó.
     *   - Lọc "đã hủy" ra ba đơn rồi bấm sang trang 2 thì bộ lọc chạy lại trên mười dòng khác — số
     *     kết quả nhảy lung tung theo trang.
     *
     * Cùng nguyên tắc đã áp ở sổ giao dịch: thứ tự và con số phải tính trên TOÀN BỘ bộ lọc, không
     * phải trên trang đang xem.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->docBoLoc($request);

        $bookings = $this->sapXep($this->truyVan($filters), $filters['sort'] ?? null)
            // `payments` nạp sẵn để hai con số tiền bên dưới không sinh một cặp truy vấn cho mỗi dòng.
            ->with(['tour:id,title', 'customer:id,name,email,phone', 'schedule:id,start_date', 'payments'])
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        /*
         * Mỗi dòng mang theo đã thu và còn thiếu.
         *
         * Danh sách vốn chỉ hiện `total_amount`, nên một đơn 4 triệu mới thu 1,2 triệu trông y hệt
         * một đơn 4 triệu chưa thu đồng nào. Bộ lọc "chưa thanh toán" chia được hai nhóm ấy, nhưng
         * nhìn vào bảng thì không biết thiếu bao nhiêu — mà đó mới là con số người ta cần khi gọi
         * điện nhắc khách.
         */
        $bookings->getCollection()->each(function (Booking $booking) {
            $booking->setAttribute('net_paid', $this->payments->netPaid($booking));
            $booking->setAttribute('balance_due', $this->payments->balanceDue($booking));
            $booking->unsetRelation('payments');
        });

        return $this->success($bookings->toArray() + [
            'summary' => $this->tongKet($filters),
        ], 'Lấy danh sách đơn đặt hàng thành công');
    }

    /** @return array<string, mixed> */
    private function docBoLoc(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(BookingStatus::liveValues())],
            'payment' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'sort' => ['nullable', Rule::in(self::THU_TU)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /** Các kiểu sắp xếp màn hình được phép yêu cầu. */
    private const THU_TU = [
        'latest',
        'oldest',
        'amount-desc',
        'amount-asc',
        'departure-asc',
        'departure-desc',
    ];

    /**
     * Truy vấn gốc, dùng chung cho cả danh sách lẫn phần tổng.
     *
     * Một chỗ duy nhất: hai nơi mà mỗi nơi tự dựng bộ lọc thì sớm muộn con số tổng sẽ không khớp
     * với danh sách ngay bên dưới nó, và không ai biết bên nào đúng.
     *
     * @param  array<string, mixed>  $filters
     */
    private function truyVan(array $filters): Builder
    {
        return Booking::query()
            ->when($filters['q'] ?? null, fn (Builder $q, string $tuKhoa) => $this->timKiem($q, $tuKhoa))
            ->when($filters['status'] ?? null, fn (Builder $q, string $trangThai) => $q->where('status', $trangThai))
            /*
             * "Đã thanh toán" đọc `paid_at`, không đọc `vnpay_transaction_no`.
             *
             * Cột kia chỉ có khi tiền về qua cổng. Khách chuyển khoản rồi điều hành ghi nhận tay là
             * đường thu tiền có thật và phổ biến, mà lọc theo mã cổng thì những đơn ấy nằm hết ở
             * nhóm "chưa thanh toán" - tức bộ lọc nói sai về chính số tiền đã nằm trong tài khoản.
             */
            ->when(($filters['payment'] ?? null) === 'paid', fn (Builder $q) => $q->whereNotNull('paid_at'))
            ->when(($filters['payment'] ?? null) === 'unpaid', fn (Builder $q) => $q->whereNull('paid_at'));
    }

    /**
     * Tìm theo đúng những thứ người ta cầm trên tay khi tra một đơn.
     *
     * Mã đơn đi đường riêng: "BK-19" là mã hiển thị, còn cột `id` không có tiền tố. Gõ đúng một mã
     * đơn thì trả về đúng đơn ấy, không kèm mọi đơn có số 19 nằm đâu đó trong số điện thoại - người
     * gõ mã đơn là người đã biết mình tìm gì.
     */
    private function timKiem(Builder $query, string $tuKhoa): Builder
    {
        $tuKhoa = trim($tuKhoa);

        if (preg_match('/^bk[-\s]?(\d+)$/i', $tuKhoa, $khop)) {
            return $query->whereKey((int) $khop[1]);
        }

        return $query->where(function (Builder $sub) use ($tuKhoa) {
            $sub->where('customer_name', 'like', "%{$tuKhoa}%")
                ->orWhere('customer_email', 'like', "%{$tuKhoa}%")
                ->orWhere('customer_phone', 'like', "%{$tuKhoa}%")
                ->orWhereHas('tour', fn (Builder $t) => $t->where('title', 'like', "%{$tuKhoa}%"));

            // Gõ trần một con số thì nó vừa có thể là mã đơn, vừa có thể là một mẩu số điện thoại.
            // Nhận cả hai, vì bắt người dùng đoán ý máy là chỗ dễ bỏ cuộc nhất.
            if (ctype_digit($tuKhoa)) {
                $sub->orWhere('id', (int) $tuKhoa);
            }
        });
    }

    /**
     * Thứ tự luôn có mốc phụ là `id`.
     *
     * Hai đơn cùng số tiền, hoặc cùng ngày khởi hành, mà không có mốc phụ thì cơ sở dữ liệu được
     * tự do xếp khác nhau ở mỗi lần chạy - và phân trang trên một thứ tự không ổn định làm đơn nhảy
     * qua nhảy lại giữa các trang, có đơn hiện hai lần, có đơn không bao giờ hiện.
     */
    private function sapXep(Builder $query, ?string $kieu): Builder
    {
        return match ($kieu) {
            'oldest' => $query->orderBy('id'),
            'amount-desc' => $query->orderByDesc('total_amount')->orderByDesc('id'),
            'amount-asc' => $query->orderBy('total_amount')->orderByDesc('id'),
            'departure-asc' => $query->orderBy('departure_date')->orderByDesc('id'),
            'departure-desc' => $query->orderByDesc('departure_date')->orderByDesc('id'),
            // Mặc định là mới nhất trước. Xếp theo `id` chứ không `created_at`: đơn seed hoặc đơn
            // nhập tay có thể trùng giây, mà `id` thì không bao giờ trùng.
            default => $query->orderByDesc('id'),
        };
    }

    /**
     * Các con số trên đầu trang, tính trên toàn bộ bộ lọc đang xem.
     *
     * Trước đây chúng đếm trên mười dòng của trang hiện tại, và nhãn trên giao diện ghi thẳng
     * "(Trang này)" - tức là màn hình biết mình đang nói một con số vô nghĩa và chọn cách chú thích
     * thay vì sửa. Lọc ra 40 đơn đã hủy mà ô thống kê ghi "3 đơn" thì con số ấy không dùng được vào
     * việc gì.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float>
     */
    private function tongKet(array $filters): array
    {
        $dem = fn (?string $trangThai = null): int => $this->truyVan($filters)
            ->when($trangThai, fn (Builder $q, string $tt) => $q->where('status', $tt))
            ->count();

        // Nạp đúng các cột cần cho hai phép cộng bên dưới, không kéo cả bản ghi về.
        $donTinhDoanhThu = $this->truyVan($filters)
            ->whereIn('status', BookingStatus::revenueValues())
            ->get(['id', 'total_amount', 'paid_at']);

        return [
            'total' => $dem(),
            'pending' => $dem(BookingStatus::Pending->value),
            'confirmed' => $dem(BookingStatus::Confirmed->value),
            'cancelled' => $dem(BookingStatus::Cancelled->value),
            'paid' => $this->truyVan($filters)->whereNotNull('paid_at')->count(),
            /*
             * Hai con số khác nhau, và trước đây chỉ có một — cái sai.
             *
             * `revenue` giờ là tiền THỰC THU, cộng từ sổ giao dịch. Đó là con số đối chiếu được với
             * số dư tài khoản, và là thứ người ta tưởng mình đang đọc khi nhìn chữ "doanh thu".
             *
             * `contracted_value` là tổng giá trị đơn — cái cũ. Vẫn có ích (nó nói đã bán được bao
             * nhiêu), nhưng nó gồm cả đơn vừa xác nhận mà khách chưa trả đồng nào, nên gọi nó là
             * doanh thu thì mọi báo cáo đều cao hơn tiền thật.
             */
            'revenue' => $this->payments->sumPaidForTour($donTinhDoanhThu),
            'contracted_value' => round((float) $donTinhDoanhThu->sum('total_amount')),
        ];
    }

    /**
     * E04 - Dòng thời gian của một đơn: ai làm gì, lúc nào, vì sao.
     *
     * Câu hỏi cần trả lời được ở đây: ai đã duyệt khoản hoàn này, trước đó đơn ở trạng thái nào,
     * và chỗ có quay lại kho không. Trước khi có nhật ký, những mảnh đó nằm rải rác ở cancelled_by,
     * reviewed_by, seats_released_by và không ai ghép lại được theo thứ tự thời gian.
     */
    public function history(int $id): JsonResponse
    {
        $booking = Booking::query()->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $logs = BookingAuditLog::query()
            ->where('booking_id', $booking->getKey())
            ->with('actor:id,name,email')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (BookingAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'touches_money' => $log->action->touchesMoney(),
                'actor_name' => $log->actor?->name,
                // Vai trò chép lại lúc thao tác, không đọc từ tài khoản hiện tại.
                'actor_role' => $log->actor_role,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return $this->success($logs, 'Lấy lịch sử đơn đặt hàng thành công');
    }

    public function show(int $id): JsonResponse
    {
        $booking = Booking::with(['tour', 'customer', 'schedule', 'paymentLogs', 'passengers'])->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        return $this->success($booking, 'Lấy chi tiết đơn đặt hàng thành công');
    }

    /**
     * Admin xác nhận đơn đang chờ (khách chuyển khoản tay / thanh toán tại quầy).
     *
     * **Xác nhận là một tuyên bố về TIỀN, nên phải kèm số tiền.** Trước đây hàm này chỉ đổi trạng
     * thái: đơn vào danh sách đoàn, khóa quyền trả chỗ, cộng vào doanh thu — trong khi sổ giao dịch
     * vẫn ghi đã thu 0 đồng. Hủy đơn đó thì khách được hoàn 0, dù họ vừa đưa tiền mặt tại quầy.
     *
     * Nên số tiền và hình thức thu là bắt buộc, và chúng đi thẳng vào sổ trong cùng giao dịch với
     * việc đổi trạng thái. Không còn đường nào xác nhận một đơn mà không nói đã thu bao nhiêu.
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $target = Booking::query()->find($id);

        /*
         * Số tiền bắt buộc, TRỪ KHI sổ đã ghi nhận khoản thu từ trước.
         *
         * Có một luồng hợp lệ đi ngược thứ tự: khách chuyển khoản, kế toán ghi vào sổ qua màn hình
         * giao dịch, rồi mới có người vào bấm xác nhận. Lúc ấy tiền đã nằm trong sổ và đòi nhập lại
         * là bắt ghi hai lần cho một lần thu.
         *
         * Điều kiện vẫn đóng đúng lỗ hổng cũ: không có đường nào đưa một đơn sang "đã xác nhận"
         * trong khi sổ của nó ghi 0 đồng.
         */
        $daCoTienTrongSo = $target && $this->payments->netPaid($target) > 0;
        $batBuoc = $daCoTienTrongSo ? 'nullable' : 'required';

        $data = $request->validate([
            'amount' => [$batBuoc, 'numeric', 'min:1'],
            'method' => [$batBuoc, 'in:cash,bank_transfer,gateway'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Nhập số tiền đã thu của khách. Xác nhận đơn mà không ghi tiền thì '
                . 'sổ giao dịch vẫn báo đơn này chưa thu đồng nào.',
            'method.required' => 'Chọn hình thức thu: tiền mặt, chuyển khoản, hay qua cổng.',
        ]);

        $booking = DB::transaction(function () use ($id, $data, $request) {
            $booking = Booking::query()->lockForUpdate()->find($id);

            if (!$booking || $booking->status !== 'pending') {
                return null;
            }

            if (!empty($data['amount'])) {
                $this->payments->recordManualCollection(
                    $booking,
                    (float) $data['amount'],
                    $data['method'],
                    $data['reference'] ?? null,
                    $data['note'] ?? null,
                    $request->user(),
                );
            }

            $booking->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            $this->auditLogger->logStatusChange(
                $booking,
                BookingAuditAction::Confirmed,
                'pending',
                'confirmed',
                $data['note'] ?? null,
                empty($data['amount'])
                    ? ['collected_earlier' => true]
                    : ['amount' => round((float) $data['amount']), 'method' => $data['method']],
            );

            return $booking;
        });

        if (!$booking) {
            return $this->error('Chỉ có thể xác nhận đơn đang ở trạng thái chờ (pending).', 400);
        }

        $this->sendConfirmedMailAfterResponse($booking);

        return $this->success(
            $booking->fresh(['tour', 'customer', 'schedule', 'paymentLogs']),
            'Đã xác nhận đơn đặt tour.'
        );
    }

    /**
     * Admin hủy đơn (pending hoặc confirmed) kèm lý do — trả lại chỗ và lượt mã giảm giá.
     */
    /**
     * Dự báo hậu quả của việc hủy một đơn, trả về trước khi quản trị bấm xác nhận.
     *
     * Ba câu hỏi mà người bấm nút cần biết trước, không phải sau: đơn này có hủy được không,
     * khách được hoàn bao nhiêu, và chỗ có quay lại kho để bán tiếp không.
     *
     * Đặc biệt là câu thứ ba. Hủy sau hạn chốt danh sách thì chỗ ở lại với đơn dưới dạng ghế
     * chết, và người hủy phải biết điều đó trước khi hủy chứ không phải phát hiện ra khi thấy
     * số chỗ không nhúc nhích.
     */
    public function cancelPreview(int $id): JsonResponse
    {
        $booking = Booking::query()
            ->with(['schedule', 'cancellationPolicy.rules'])
            ->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $schedule = $booking->schedule;

        $coTheHuy = true;
        $lyDoChan = null;

        try {
            $this->bookingPolicy->assertCancellable($booking, $schedule);
        } catch (BusinessRuleException $e) {
            $coTheHuy = false;
            $lyDoChan = $e->getMessage();
        }

        $duBao = $this->cancellationPolicy->quote($booking, $schedule);

        return $this->success($duBao + [
            'can_cancel' => $coTheHuy,
            'blocked_reason' => $lyDoChan,
            // Dự báo, không phải cam kết: tới lúc bấm hủy thật thì lớp dịch vụ tính lại trên
            // bản ghi đã khóa. Hai kết quả chỉ lệch nhau nếu hạn chốt rơi đúng vào khoảng giữa.
            'seats_will_be_released' => $this->holdService->shouldReleaseSeats($booking, $schedule),
            'policy_name' => $booking->cancellationPolicy?->name,
        ], 'Lấy dự báo hủy đơn thành công');
    }

    /**
     * Sửa thông tin liên hệ của người đặt.
     *
     * Không áp hạn chốt danh sách: số điện thoại là thứ hướng dẫn viên gọi khách, càng sát ngày
     * càng cần đúng. Xem BookingContactService.
     */
    public function updateContact(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(BookingContactService::validationRules());

        $booking = Booking::query()->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $daSua = $this->contactService->update($booking, $validated, $request->user());

        return $this->success(
            $daSua->only(['id', 'customer_name', 'customer_email', 'customer_phone']),
            'Đã cập nhật thông tin liên hệ.',
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy đơn.',
        ]);

        $target = Booking::query()->find($id);

        if (!$target) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $booking = DB::transaction(function () use ($target, $validated, $request) {
            $schedule = $target->tour_schedule_id
                ? TourSchedule::query()
                    ->whereKey($target->tour_schedule_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $booking = Booking::query()->lockForUpdate()->find($target->id);

            if (!$booking) {
                return null;
            }

            // Kiểm tra trên bản ghi vừa khóa: chuyến đã khởi hành thì quản trị cũng không hủy được.
            $this->bookingPolicy->assertCancellable($booking, $schedule);

            if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
                return null;
            }

            $trangThaiCu = (string) $booking->status;

            /*
             * Chốt số tiền hoàn trước khi đổi trạng thái.
             *
             * Bậc hoàn tính theo số giờ còn lại tới giờ khởi hành, nên đọc muộn một ngày là ra
             * một con số khác. Nhật ký phải giữ đúng con số tại thời điểm bấm hủy, nếu không thì
             * ba tháng sau không ai đối chiếu được với khoản đã chi.
             *
             * Đường khách xin hủy đã ghi khoản này từ đầu; đường quản trị hủy thẳng thì không, và
             * đó lại chính là đường chạm tiền mà không qua bước duyệt nào.
             */
            $duBao = $this->cancellationPolicy->quote($booking, $schedule);

            $booking->update([
                'status' => 'cancelled',
                'cancel_reason' => $validated['cancel_reason'],
                'cancel_type' => 'by_company',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()?->id,
                /*
                 * Ghi số tiền hoàn lên chính đơn, không chỉ vào nhật ký.
                 *
                 * Nhật ký là nơi đọc lại chuyện đã xảy ra; nghĩa vụ trả tiền thì phải nằm ở chỗ
                 * truy vấn được. Thiếu dòng này, đường quản trị hủy thẳng - đúng đường chạm tiền
                 * mà không qua bước duyệt nào - không để lại nghĩa vụ nào cho kế toán, và đơn đó
                 * không bao giờ xuất hiện trong danh sách chờ hoàn.
                 *
                 * Hai đường hủy kia đã ghi từ trước: yêu cầu hủy của khách (BookingChangeRequest-
                 * Service) và hủy cả chuyến (ScheduleCancellationService).
                 */
                'refund_amount' => $duBao['refund_amount'],
            ]);

            $this->holdService->releaseHold($booking, $schedule);

            $this->auditLogger->logStatusChange(
                $booking,
                BookingAuditAction::Cancelled,
                $trangThaiCu,
                'cancelled',
                $validated['cancel_reason'],
                [
                    'refund_amount' => $duBao['refund_amount'],
                    'refund_percent' => $duBao['refund_percent'],
                    // Chỗ có về kho hay thành ghế chết là hệ quả quan trọng nhất của lần hủy này,
                    // và người đọc nhật ký sau này không tự tính lại được vì hạn chốt đã trôi qua.
                    'seats_released' => (bool) $booking->fresh()->seats_released,
                ],
            );

            return $booking;
        });

        if (!$booking) {
            return $this->error('Đơn này đã bị hủy trước đó.', 400);
        }

        $this->sendCancelledMailAfterResponse($booking);

        // Nói đúng chuyện vừa xảy ra. Câu cũ khẳng định luôn là đã trả lại chỗ, sai kể từ khi
        // có quy tắc giữ chỗ sau hạn chốt: đơn hủy muộn để lại ghế chết, và người vừa bấm hủy
        // cần biết ngay để còn xử lý với nhà cung cấp.
        $daTraCho = (bool) $booking->fresh()->seats_released;

        return $this->success(
            $booking->fresh(['tour', 'customer', 'schedule', 'paymentLogs']),
            $daTraCho
                ? 'Đã hủy đơn đặt tour và trả lại chỗ cho lịch khởi hành.'
                : 'Đã hủy đơn đặt tour. Đơn hủy sau hạn chốt danh sách nên chỗ chưa được trả về kho, '
                    . 'xem mục Chỗ đã hủy chưa mở bán lại để quyết định mở bán tiếp.'
        );
    }

    /*
     * ĐÃ GỠ: mở lại đơn đã hủy.
     *
     * Hủy là trạng thái kết thúc. Cho hoàn tác thì "đã hủy" không còn nghĩa gì chắc chắn: chỗ đã
     * trả về kho có thể đã bán cho người khác, thư báo hủy đã gửi đi, và tiền hoàn có thể đã
     * chuyển. Kéo đơn trở lại là dựng dậy một thứ mà phần còn lại của thế giới đã đi tiếp.
     *
     * Cách xử lý đúng khi hủy nhầm là **đặt lại đơn mới** — mất một phút, và để lại đúng một dòng
     * lịch sử thay vì một đơn có hai vòng đời.
     *
     * KHÔNG gỡ, và đừng nhầm nó với chuyện này: đường khôi phục trong `BookingController::vnpayReturn`,
     * khi khách bấm thanh toán trước lúc đơn hết hạn giữ chỗ nhưng tiền về sau đó. Ở đó tiền đã
     * nằm trong tài khoản công ty — không nhận lại đơn nghĩa là cầm tiền mà không giao gì.
     */

    private function sendConfirmedMailAfterResponse(Booking $booking): void
    {
        app()->terminating(function () use ($booking) {
            try {
                Mail::to($booking->customer_email)->send(new BookingConfirmedMail($booking->fresh(['tour', 'schedule'])));
            } catch (Throwable $exception) {
                Log::warning('Không gửi được email xác nhận đơn.', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->customer_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function sendCancelledMailAfterResponse(Booking $booking): void
    {
        app()->terminating(function () use ($booking) {
            try {
                Mail::to($booking->customer_email)->send(new BookingCancelledMail($booking->fresh(['tour', 'schedule'])));
            } catch (Throwable $exception) {
                Log::warning('Không gửi được email thông báo hủy đơn.', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->customer_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
