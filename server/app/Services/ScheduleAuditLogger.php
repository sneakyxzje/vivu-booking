<?php

namespace App\Services;

use App\Enums\ScheduleAuditAction;
use App\Models\ScheduleAuditLog;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Ghi nhật ký thay đổi ở mức chuyến khởi hành.
 *
 * Cùng nguyên tắc với BookingAuditLogger: gọi tường minh tại nơi thao tác chứ không bắt sự kiện
 * của model, vì sự kiện biết dữ liệu vừa đổi nhưng không biết vì sao và ai bấm.
 */
class ScheduleAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        TourSchedule $schedule,
        ScheduleAuditAction $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $actor = null,
    ): ?ScheduleAuditLog {
        /*
         * Nhật ký hỏng không được làm hỏng nghiệp vụ. Bảng nhật ký lỗi mà kéo theo việc sửa hạn
         * chốt bị quay lại thì điều hành chịu hậu quả của một sự cố không liên quan tới họ.
         */
        try {
            $actor ??= Auth::user();

            return ScheduleAuditLog::query()->create([
                'tour_schedule_id' => $schedule->getKey(),
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
            Log::warning('Không ghi được nhật ký chuyến khởi hành.', [
                'tour_schedule_id' => $schedule->getKey(),
                'action' => $action->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Trả null khi chạy từ dòng lệnh: tác vụ nền không có yêu cầu HTTP nào. */
    private function clientIp(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::ip();
    }
}
