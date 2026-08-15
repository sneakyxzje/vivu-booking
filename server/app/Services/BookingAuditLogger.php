<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * E02 - Ghi nhật ký thay đổi đơn hàng.
 *
 * Gọi tường minh tại từng nơi thay vì bắt sự kiện của model. Sự kiện model biết đơn vừa đổi
 * nhưng không biết vì sao, ai bấm, và thao tác nghiệp vụ tên là gì: một lần lưu có thể là hủy
 * đơn, có thể là sửa ghi chú. Nhật ký mà không trả lời được câu hỏi vì sao thì đọc lại vô ích.
 *
 * Xem docs/nghiep-vu/07-thiet-ke-du-lieu.md mục 1.4.
 */
class BookingAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        Booking $booking,
        BookingAuditAction $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): ?BookingAuditLog {
        /*
         * Nhật ký hỏng không được làm hỏng nghiệp vụ.
         *
         * Ghi nhật ký là việc phụ. Nếu bảng nhật ký lỗi mà kéo theo giao dịch hủy đơn bị quay
         * lại thì khách chịu hậu quả của một sự cố không liên quan gì tới họ. Nuốt lỗi ở đây và
         * ghi ra log hệ thống để đội kỹ thuật biết.
         */
        try {
            $actor = Auth::user();

            return BookingAuditLog::query()->create([
                'booking_id' => $booking->getKey(),
                'actor_id' => $actor?->getKey(),
                // Chép vai trò tại thời điểm thao tác, vì tài khoản có thể đổi vai trò về sau.
                'actor_role' => $actor?->role,
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'reason' => $reason,
                'ip_address' => $this->clientIp(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Không ghi được nhật ký đơn hàng.', [
                'booking_id' => $booking->getKey(),
                'action' => $action->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ghi lại việc đổi trạng thái, kèm giá trị trước và sau.
     *
     * Đây là dạng hay dùng nhất nên tách riêng, để nơi gọi không phải tự dựng hai mảng mỗi lần
     * và không ai đặt nhầm thứ tự cũ với mới.
     */
    public function logStatusChange(
        Booking $booking,
        BookingAuditAction $action,
        string $tuTrangThai,
        string $sangTrangThai,
        ?string $reason = null,
        array $themVaoNewValues = [],
    ): ?BookingAuditLog {
        return $this->log(
            $booking,
            $action,
            ['status' => $tuTrangThai],
            ['status' => $sangTrangThai] + $themVaoNewValues,
            $reason,
        );
    }

    /**
     * Địa chỉ mạng của người thao tác.
     *
     * Trả null khi chạy từ dòng lệnh: tác vụ nền không có yêu cầu HTTP nào, và ghi địa chỉ của
     * chính máy chủ vào đó sẽ khiến người đọc nhật ký tưởng có ai đó thao tác thật.
     */
    private function clientIp(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::ip();
    }
}
