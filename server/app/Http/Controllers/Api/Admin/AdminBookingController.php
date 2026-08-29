<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingAuditAction;
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
use App\Services\BookingPolicyService;
use App\Services\CancellationPolicyService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with(['tour:id,title', 'customer:id,name,email,phone', 'schedule:id,start_date'])
            ->latest()
            ->paginate(10);

        return $this->success($bookings, 'Lấy danh sách đơn đặt hàng thành công');
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
     * Admin xác nhận đơn đang chờ (ví dụ khách chuyển khoản tay / thanh toán tại quầy).
     * Chỗ đã được giữ từ lúc đặt nên chỉ cần chốt trạng thái và bỏ hạn tự hủy.
     */
    public function confirm(int $id): JsonResponse
    {
        $booking = DB::transaction(function () use ($id) {
            $booking = Booking::query()->lockForUpdate()->find($id);

            if (!$booking || $booking->status !== 'pending') {
                return null;
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
