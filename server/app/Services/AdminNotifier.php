<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Gửi một việc cần biết tới toàn bộ điều hành.
 *
 * Gom vào một chỗ vì ba nơi gọi tới nó — từ chối chuyến, xin bàn giao, báo sự cố — đều cần cùng
 * một câu trả lời cho hai câu hỏi: *gửi cho ai* và *hỏng thì sao*.
 *
 * **Gửi cho ai:** mọi tài khoản `admin` đang hoạt động. Không có khái niệm "điều hành phụ trách
 * chuyến này" trong hệ thống, và đặt ra một khái niệm như vậy chỉ để chia thông báo thì lại là
 * một cơ chế nữa cho một việc rất nhỏ.
 *
 * **Hỏng thì sao:** nuốt lỗi và ghi log. Thông báo là việc phụ; hướng dẫn viên từ chối chuyến là
 * việc chính và đã xong rồi. Để một lỗi gửi thông báo kéo ngược giao dịch nghiệp vụ về là bắt
 * người dùng chịu hậu quả của một sự cố không liên quan tới họ — cùng nguyên tắc đang áp ở
 * `BookingAuditLogger`.
 */
class AdminNotifier
{
    public function guiToiDieuHanh(string $kind, string $title, string $body, ?string $url = null): void
    {
        try {
            $dieuHanh = User::query()
                ->where('role', 'admin')
                ->where('status', 'active')
                ->get();

            if ($dieuHanh->isEmpty()) {
                return;
            }

            Notification::send($dieuHanh, new AdminAlert($kind, $title, $body, $url));
        } catch (\Throwable $e) {
            Log::error('Không gửi được thông báo cho điều hành', [
                'kind' => $kind,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
