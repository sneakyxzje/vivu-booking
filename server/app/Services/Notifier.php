<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Gửi một việc cần biết, tới điều hành hoặc tới một người cụ thể.
 *
 * Gom vào một chỗ vì mọi nơi gọi tới đều cần cùng một câu trả lời cho hai câu hỏi:
 *
 * **Gửi cho ai:** với điều hành là *mọi tài khoản admin đang hoạt động*. Không có khái niệm "điều
 * hành phụ trách chuyến này" trong hệ thống, và đặt ra một khái niệm như vậy chỉ để chia thông báo
 * thì lại là một cơ chế nữa cho một việc rất nhỏ.
 *
 * **Hỏng thì sao:** nuốt lỗi và ghi log. Thông báo là việc phụ; hướng dẫn viên từ chối chuyến là
 * việc chính và đã xong rồi. Để một lỗi gửi thông báo kéo ngược giao dịch nghiệp vụ về là bắt
 * người dùng chịu hậu quả của một sự cố không liên quan tới họ — cùng nguyên tắc đang áp ở
 * `BookingAuditLogger`.
 */
class Notifier
{
    /** Mọi điều hành đang hoạt động. */
    public function toiDieuHanh(string $kind, string $title, string $body, ?string $url = null): void
    {
        $this->gui(
            User::query()->where('role', 'admin')->where('status', 'active')->get(),
            $kind,
            $title,
            $body,
            $url,
        );
    }

    /**
     * Một người cụ thể — dùng cho hướng dẫn viên.
     *
     * Nhận `?User` để nơi gọi không phải kiểm null: phân công cho một id không còn tồn tại là
     * chuyện hiếm nhưng có thật, và bắt mỗi chỗ gọi tự canh là cách bỏ sót một chỗ.
     */
    public function toiNguoiDung(?User $nguoi, string $kind, string $title, string $body, ?string $url = null): void
    {
        if (!$nguoi) {
            return;
        }

        /*
         * Nạp lại nếu model thiếu cột `status`.
         *
         * Nơi gọi thường lấy người nhận từ một quan hệ đã giới hạn cột - `with('toGuide:id,name,
         * phone')` chẳng hạn. Khi đó `status` là null, phép kiểm bên dưới coi như tài khoản đã
         * ngừng hoạt động và bỏ qua thông báo mà không báo lỗi gì.
         *
         * Đây đúng loại hỏng khó tìm nhất: nghiệp vụ chạy xong, test nghiệp vụ xanh, chỉ có thông
         * báo là không tới. Hỏi lại cơ sở dữ liệu một lần rẻ hơn nhiều so với bắt mọi nơi gọi phải
         * nhớ liệt kê thêm một cột.
         */
        if (!array_key_exists('status', $nguoi->getAttributes())) {
            $nguoi = User::query()->find($nguoi->getKey());
        }

        if (!$nguoi || $nguoi->status !== 'active') {
            return;
        }

        $this->gui(collect([$nguoi]), $kind, $title, $body, $url);
    }

    /** @param  \Illuminate\Support\Collection<int, User>  $nguoiNhan */
    private function gui($nguoiNhan, string $kind, string $title, string $body, ?string $url): void
    {
        try {
            if ($nguoiNhan->isEmpty()) {
                return;
            }

            Notification::send($nguoiNhan, new Alert($kind, $title, $body, $url));
        } catch (\Throwable $e) {
            Log::error('Không gửi được thông báo', [
                'kind' => $kind,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
