<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Thư đặt lại mật khẩu.
 *
 * Dùng Mailable riêng thay vì thông báo `ResetPassword` mặc định của Laravel vì liên kết phải trỏ
 * về **giao diện React**, không phải về máy chủ: máy chủ ở đây chỉ có API, không có trang nhập mật
 * khẩu mới. Thông báo mặc định dựng liên kết bằng `route('password.reset')`, một tuyến không tồn
 * tại trong dự án này.
 */
class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Đặt lại mật khẩu - Vivu Booking',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.auth.password_reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $frontendUrl . '/reset-password?' . http_build_query([
                    'token' => $this->token,
                    'email' => $this->user->email,
                ]),
                // Hạn dùng lấy từ cấu hình broker chứ không viết cứng: sửa một chỗ, thư nói đúng.
                'expireMinutes' => (int) config('auth.passwords.users.expire', 60),
            ],
        );
    }
}
