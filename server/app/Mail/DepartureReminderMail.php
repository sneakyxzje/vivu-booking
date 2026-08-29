<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Thư nhắc trước ngày khởi hành.
 *
 * Đây là thư khách mong nhất và là thư cắt được nhiều cuộc gọi hỏi nhất: mấy giờ tập trung, đón ở
 * đâu, ai dẫn đoàn, số điện thoại người dẫn. Bốn câu ấy nằm rải rác ở thư đặt tour (gửi có khi
 * hàng tháng trước) và ở trang tra cứu, nên khách vẫn gọi lên hỏi.
 *
 * Chỉ gửi cho đơn ĐÃ THU ĐỦ và chuyến CHƯA HỦY — xem `SendDepartureReminders`.
 */
class DepartureReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule.guides']);
    }

    public function envelope(): Envelope
    {
        $ngay = $this->booking->schedule?->start_date?->format('d/m')
            ?? optional($this->booking->departure_date)->format('d/m');

        return new Envelope(
            subject: sprintf('Nhắc lịch khởi hành %s - %s', $ngay, $this->booking->tour?->title ?? 'Vivu Booking'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.departure_reminder',
            with: [
                'booking' => $this->booking,
                'schedule' => $this->booking->schedule,
                'guides' => $this->booking->schedule?->guides ?? collect(),
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}
