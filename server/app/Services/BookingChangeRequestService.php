<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F02, F03 - Khách xin hủy, điều hành duyệt.
 *
 * Vì sao không cho khách tự hủy đơn đã thanh toán: hủy đơn đã thu tiền là một quyết định chi
 * tiền. Bảng phân quyền ở docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.1 tách rõ người
 * yêu cầu và người duyệt, vì trên hệ thống thật đó phải là hai người.
 *
 * Đơn CHƯA thanh toán không đi đường này. Khách tự hủy được ngay, không có tiền nào để hoàn và
 * cũng chưa có cam kết nào với nhà cung cấp.
 */
class BookingChangeRequestService
{
    public function __construct(
        private BookingPolicyService $bookingPolicy,
        private CancellationPolicyService $cancellationPolicy,
        private BookingHoldService $holdService,
        private BookingAuditLogger $auditLogger,
    ) {
    }

    /**
     * Khách gửi yêu cầu hủy một đơn đã thanh toán.
     *
     * Khóa dòng rồi mới kiểm tra: cùng lúc đó điều hành có thể đang hủy chính đơn này, hoặc
     * khách bấm gửi hai lần.
     */
    public function requestCancellation(
        Booking $booking,
        string $lyDo,
        ?User $nguoiGui = null,
    ): BookingChangeRequest {
        return DB::transaction(function () use ($booking, $lyDo, $nguoiGui) {
            $locked = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new BusinessRuleException('Không tìm thấy đơn đặt tour.', 404);
            }

            $schedule = $locked->tour_schedule_id
                ? TourSchedule::query()->whereKey($locked->tour_schedule_id)->first()
                : null;

            $this->assertCoTheGuiYeuCau($locked, $schedule);

            // Chốt mức hoàn tại thời điểm gửi. Xem chú thích trong migration F01 để biết vì sao
            // không để tới lúc duyệt mới tính.
            $duBao = $this->cancellationPolicy->quote($locked, $schedule);

            $yeuCau = BookingChangeRequest::query()->create([
                'booking_id' => $locked->getKey(),
                'type' => ChangeRequestType::Cancel,
                'payload' => [
                    'seats_will_be_released' => $this->holdService->shouldReleaseSeats($locked, $schedule),
                    'hours_before' => $duBao['hours_before'],
                ],
                'estimated_refund' => $duBao['refund_amount'],
                'estimated_refund_percent' => $duBao['refund_percent'],
                'status' => ChangeRequestStatus::Pending,
                'requested_by' => $nguoiGui?->getKey() ?? $locked->customer_id,
                'requested_email' => $locked->customer_email,
                'request_note' => $lyDo,
            ]);

            $this->auditLogger->log(
                $locked,
                BookingAuditAction::CancelRequested,
                null,
                [
                    'request_id' => $yeuCau->getKey(),
                    'estimated_refund' => $duBao['refund_amount'],
                    'refund_percent' => $duBao['refund_percent'],
                ],
                $lyDo,
            );

            return $yeuCau;
        });
    }

    /**
     * Điều hành duyệt yêu cầu và hệ thống thực thi việc hủy ngay trong cùng giao dịch.
     *
     * Duyệt xong mà việc hủy hỏng thì phải quay lại cả hai, nếu không sẽ có yêu cầu ghi là đã
     * duyệt trong khi đơn vẫn còn hiệu lực - loại lệch dữ liệu không ai phát hiện ra cho tới
     * lúc khách gọi hỏi tiền.
     */
    public function approve(
        BookingChangeRequest $request,
        User $nguoiDuyet,
        ?string $ghiChu = null,
    ): BookingChangeRequest {
        return DB::transaction(function () use ($request, $nguoiDuyet, $ghiChu) {
            $locked = BookingChangeRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertConDuyetDuoc($locked);

            $schedule = $locked->booking?->tour_schedule_id
                ? TourSchedule::query()
                    ->whereKey($locked->booking->tour_schedule_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $booking = Booking::query()
                ->whereKey($locked->booking_id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                throw new BusinessRuleException('Không tìm thấy đơn đặt tour.', 404);
            }

            /*
             * Kiểm tra lại luật chặn tại thời điểm duyệt, không tin vào lúc gửi.
             *
             * Yêu cầu nằm chờ có thể vài ngày, và chuyến hoàn toàn có thể đã khởi hành trong
             * lúc đó. Duyệt một yêu cầu của chuyến đang chạy là hủy chỗ của người đang ngồi
             * trên xe, đúng thứ nhóm D sinh ra để chặn.
             */
            $this->bookingPolicy->assertCancellable($booking, $schedule);

            $trangThaiCu = (string) $booking->status;

            $booking->update([
                'status' => BookingStatus::Cancelled->value,
                'cancel_reason' => $locked->request_note,
                'cancel_type' => 'by_customer',
                'cancelled_at' => now(),
                'cancelled_by' => $locked->requested_by,
                // Số khách nhận là số đã chốt lúc gửi, không phải số tính lại bây giờ.
                'refund_amount' => $locked->estimated_refund,
            ]);

            $this->holdService->releaseHold($booking, $schedule);

            $this->auditLogger->logStatusChange(
                $booking,
                BookingAuditAction::CancelRequestApproved,
                $trangThaiCu,
                BookingStatus::Cancelled->value,
                $ghiChu,
                [
                    'request_id' => $locked->getKey(),
                    'refund_amount' => $locked->estimated_refund,
                    // Ghi lại chỗ có về kho hay không, vì đó là thứ quyết định còn bán được nữa
                    // hay không và người đọc nhật ký sau này không tính lại được.
                    'seats_released' => (bool) $booking->fresh()->seats_released,
                ],
            );

            $locked->update([
                'status' => ChangeRequestStatus::Approved,
                'reviewed_by' => $nguoiDuyet->getKey(),
                'reviewed_at' => now(),
                'review_note' => $ghiChu,
            ]);

            return $locked->fresh(['booking']);
        });
    }

    /** Từ chối thì bắt buộc có lý do, vì khách sẽ hỏi tại sao. */
    public function reject(
        BookingChangeRequest $request,
        User $nguoiDuyet,
        string $lyDo,
    ): BookingChangeRequest {
        return DB::transaction(function () use ($request, $nguoiDuyet, $lyDo) {
            $locked = BookingChangeRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertConDuyetDuoc($locked);

            $locked->update([
                'status' => ChangeRequestStatus::Rejected,
                'reviewed_by' => $nguoiDuyet->getKey(),
                'reviewed_at' => now(),
                'review_note' => $lyDo,
            ]);

            if ($locked->booking) {
                $this->auditLogger->log(
                    $locked->booking,
                    BookingAuditAction::CancelRequestRejected,
                    null,
                    ['request_id' => $locked->getKey()],
                    $lyDo,
                );
            }

            return $locked->fresh(['booking']);
        });
    }

    /** Khách rút lại yêu cầu khi chưa ai duyệt. Đơn giữ nguyên, không đụng gì tới chỗ. */
    public function withdraw(BookingChangeRequest $request): BookingChangeRequest
    {
        return DB::transaction(function () use ($request) {
            $locked = BookingChangeRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertConDuyetDuoc($locked);

            $locked->update(['status' => ChangeRequestStatus::CancelledByCustomer]);

            if ($locked->booking) {
                $this->auditLogger->log(
                    $locked->booking,
                    BookingAuditAction::CancelRequestWithdrawn,
                    null,
                    ['request_id' => $locked->getKey()],
                );
            }

            return $locked->fresh();
        });
    }

    private function assertCoTheGuiYeuCau(Booking $booking, ?TourSchedule $schedule): void
    {
        // Đơn chưa thanh toán tự hủy được, không cần xin phép ai.
        if (!$this->daThanhToan($booking)) {
            throw new BusinessRuleException(
                'Đơn này chưa thanh toán nên bạn tự hủy được ngay, không cần gửi yêu cầu.',
            );
        }

        $this->bookingPolicy->assertCancellable($booking, $schedule);

        $dangCho = BookingChangeRequest::query()
            ->where('booking_id', $booking->getKey())
            ->where('type', ChangeRequestType::Cancel->value)
            ->pending()
            ->exists();

        if ($dangCho) {
            throw new BusinessRuleException(
                'Đơn này đã có một yêu cầu hủy đang chờ duyệt. Vui lòng đợi kết quả hoặc rút lại yêu cầu cũ.',
            );
        }
    }

    private function assertConDuyetDuoc(?BookingChangeRequest $request): void
    {
        if (!$request) {
            throw new BusinessRuleException('Không tìm thấy yêu cầu.', 404);
        }

        if ($request->status->isClosed()) {
            throw new BusinessRuleException(sprintf(
                'Yêu cầu này đã được xử lý rồi, trạng thái hiện tại là "%s".',
                $request->status->label(),
            ));
        }
    }

    /**
     * Đơn đã có tiền vào hay chưa.
     *
     * Hỏi paid_at và confirmed_at chứ không hỏi status, giống hasEnteredManifest ở
     * BookingHoldService: một đơn quản trị xác nhận tay cũng đã vào danh sách đoàn dù chưa có
     * giao dịch trực tuyến nào.
     */
    private function daThanhToan(Booking $booking): bool
    {
        return $booking->paid_at !== null || $booking->confirmed_at !== null;
    }
}
