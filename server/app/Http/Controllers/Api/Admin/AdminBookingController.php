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
     * C03 - Các đơn đã hủy nhưng chỗ chưa được trả về kho.
     *
     * Đây là ghế chết: chỗ trống về mặt vật lý nhưng chưa bán lại được vì phòng, ghế và suất ăn
     * đã chốt theo danh sách gửi nhà cung cấp. Điều hành nhìn màn hình này để quyết định có xin
     * thêm suất rồi mở bán lại hay chấp nhận lỗ.
     *
     * Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 3.
     */
    public function heldSeats(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->withHeldSeats()
            ->with(['tour:id,title', 'schedule:id,start_date,booking_deadline,max_people,booked_people'])
            ->orderBy('cancelled_at')
            ->paginate(10);

        $tongCho = (int) Booking::query()->withHeldSeats()->sum('guests');

        return $this->success([
            'bookings' => $bookings,
            'total_held_seats' => $tongCho,
        ], 'Lấy danh sách chỗ chưa mở bán lại thành công');
    }

    /**
     * Mở lại chỗ của một đơn đã hủy sau hạn chốt, trả chỗ về kho để bán tiếp.
     */
    public function releaseHeldSeats(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()->find($id);

        if (!$booking) {
            return $this->error('Không tìm thấy đơn đặt hàng', 404);
        }

        $daMoLai = $this->holdService->releaseHeldSeats($booking, $request->user()?->id);

        if (!$daMoLai) {
            return $this->error(
                'Đơn này không ở trạng thái mở lại chỗ được. Chỉ đơn đã hủy mà chỗ chưa trả về kho mới mở lại được.',
                400,
            );
        }

        // Mở lại ghế chết là quyết định của con người sau khi xin thêm suất từ nhà cung cấp,
        // nên phải ghi lại ai quyết định và vào lúc nào.
        $this->auditLogger->log(
            $booking,
            BookingAuditAction::SeatsReleased,
            ['seats_released' => false],
            ['seats_released' => true, 'guests' => (int) $booking->guests],
        );

        return $this->success(
            $booking->fresh(['tour:id,title', 'schedule:id,start_date,max_people,booked_people']),
            'Đã mở lại chỗ để bán tiếp.',
        );
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

    /**
     * =========================================================================
     * TASK X07a: Mở lại đơn đặt tour bị hủy nhầm trong 24 giờ (Edge Case C06)
     * =========================================================================
     * Cho phép Quản trị viên khôi phục lại các đơn hàng có trạng thái `cancelled`
     * nếu thời gian hủy chưa vượt quá 24 giờ và chuyến khởi hành còn đủ chỗ trống.
     * 
     * @param Request $request chứa lý do khôi phục (reopen_reason - bắt buộc)
     * @param int $id ID của đơn đặt tour
     * @return JsonResponse
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        // 1. Validate lý do khôi phục đơn từ phía Quản trị viên
        $validated = $request->validate([
            'reopen_reason' => 'required|string|max:500',
        ], [
            'reopen_reason.required' => 'Vui lòng nhập lý do mở lại đơn đặt tour.',
        ]);

        $target = Booking::query()->find($id);

        if (!$target) {
            return $this->error('Không tìm thấy đơn đặt hàng.', 404);
        }

        // 2. Kiểm tra điều kiện đơn phải ở trạng thái cancelled
        if ($target->status !== 'cancelled') {
            return $this->error('Chỉ có thể mở lại những đơn đang ở trạng thái Đã hủy (cancelled).', 400);
        }

        // 3. Kiểm tra sơ bộ giới hạn 24 giờ. Kiểm tra thật nằm trong transaction bên dưới.
        if ($this->reopenWindowExpired($target)) {
            return $this->error('Đơn đặt tour đã bị hủy quá 24 giờ, không thể mở lại.', 400);
        }

        try {
            $updatedBooking = DB::transaction(function () use ($target, $validated, $request) {
                // Khóa dòng chuyến khởi hành để kiểm tra số chỗ trống khả dụng
                $schedule = TourSchedule::query()
                    ->whereKey($target->tour_schedule_id)
                    ->lockForUpdate()
                    ->first();

                if (!$schedule) {
                    throw new \Exception('Chuyến khởi hành của đơn này không còn tồn tại.');
                }

                // Kiểm tra chuyến đã khởi hành chưa
                if (\Carbon\Carbon::parse($schedule->start_date)->isPast()) {
                    throw new \Exception('Chuyến khởi hành này đã xuất phát, không thể khôi phục đơn.');
                }

                $booking = Booking::query()->lockForUpdate()->find($target->id);

                // Đọc lại sau khi khóa dòng. Hai quản trị viên bấm mở lại cùng lúc thì cả hai
                // đều qua được phần kiểm tra phía trên; người vào sau phải thấy đơn đã mở rồi
                // và dừng lại, nếu không booked_people bị cộng hai lượt.
                if (!$booking || $booking->status !== 'cancelled') {
                    throw new \Exception('Đơn này đã được mở lại hoặc không còn ở trạng thái đã hủy.');
                }

                if ($this->reopenWindowExpired($booking)) {
                    throw new \Exception('Đơn đặt tour đã bị hủy quá 24 giờ, không thể mở lại.');
                }

                // Chỉ đơn đã trả chỗ về kho mới cần chỗ trống để quay lại.
                // Đơn hủy sau hạn chốt giữ nguyên chỗ (seats_released = false), tức booked_people
                // vẫn đang tính chỗ đó, nên đòi thêm chỗ trống là đòi hai lần cho cùng một chỗ.
                if ($booking->seats_released) {
                    $availableSeats = (int) $schedule->max_people - (int) $schedule->booked_people;

                    if ($availableSeats < $booking->guests) {
                        throw new \Exception("Chuyến khởi hành chỉ còn {$availableSeats} chỗ trống, không đủ để khôi phục đơn {$booking->guests} chỗ này.");
                    }

                    $schedule->increment('booked_people', $booking->guests);
                    $schedule->refresh();

                    // Chỗ vừa lấy lại có thể lấp đầy chuyến, khi đó phải đóng bán.
                    if ($schedule->status === ScheduleStatus::Open
                        && $schedule->booked_people >= $schedule->max_people) {
                        $this->scheduleLifecycle->transitionTo(
                            $schedule,
                            ScheduleStatus::Closed,
                            'Tự động đóng bán do mở lại đơn đã hủy khiến chuyến đầy chỗ.',
                            $request->user()?->id,
                        );
                    }
                }

                // Khôi phục trạng thái: Nếu đã có giao dịch VNPAY thành công thì về confirmed, ngược lại về pending
                $nextStatus = $booking->vnpay_transaction_no ? 'confirmed' : 'pending';

                $booking->update([
                    'status' => $nextStatus,
                    'reopen_reason' => $validated['reopen_reason'],
                    'reopened_at' => now(),
                    'reopened_by' => $request->user()?->id,
                    'seats_released' => false,
                    'seats_released_at' => null,
                    'seats_released_by' => null,
                ]);

                $this->auditLogger->logStatusChange(
                    $booking,
                    BookingAuditAction::Reopened,
                    'cancelled',
                    $nextStatus,
                    $validated['reopen_reason'],
                );

                return $booking;
            });

            return $this->success(
                $updatedBooking->fresh(['tour', 'customer', 'schedule', 'paymentLogs']),
                'Đã mở lại đơn đặt tour thành công và khôi phục số chỗ trên chuyến.'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Đơn này đã quá hạn 24 giờ để mở lại chưa.
     *
     * Đơn hủy trước khi có cột cancelled_at thì lấy updated_at làm mốc thay thế. Nếu bỏ qua
     * kiểm tra khi cột trống thì mọi đơn đã hủy từ thời đó đều mở lại được vô thời hạn, tức là
     * giới hạn 24 giờ chỉ có tác dụng với dữ liệu mới.
     */
    private function reopenWindowExpired(Booking $booking): bool
    {
        $cancelledAt = $booking->cancelled_at ?? $booking->updated_at;

        if (!$cancelledAt) {
            return true;
        }

        return \Carbon\Carbon::parse($cancelledAt)->diffInHours(now()) > 24;
    }

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
