<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Task X06a - Mailable gửi lại danh sách mã tra cứu cho khách vãng lai (Edge Case A16).
 * Gửi email liệt kê toàn bộ các mã tra cứu đơn hàng tương ứng với Email khách hàng đã đặt tour.
 */
class ResendLookupCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection $bookings Danh sách các đơn đặt tour của khách hàng
     * @param string $customerEmail Email của khách hàng nhận thư
     */
    public function __construct(
        public Collection $bookings,
        public string $customerEmail
    ) {
    }

    /**
     * Cấu hình tiêu đề email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Danh sách mã tra cứu đơn đặt tour - Vivu Booking',
        );
    }

    /**
     * Cấu hình template HTML hiển thị nội dung email.
     */
    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.bookings.resend_lookup_code',
            with: [
                'bookings' => $this->bookings,
                'customerEmail' => $this->customerEmail,
                'frontendUrl' => $frontendUrl,
            ],
        );
    }
}
