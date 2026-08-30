<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thanh toán thành công đơn đặt tour #' . $this->booking->id . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.paid',
            with: [
                'booking' => $this->booking,
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
                /*
                 * G03 - Liên kết khai danh sách hành khách.
                 *
                 * Danh sách nay khai sau khi đặt, nên thư này là chỗ khách chắc chắn tìm lại
                 * được. Thiếu nó thì họ phải nhớ mã tra cứu rồi tự mò đường.
                 */
                'frontendPassengerUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/bookings/' . $this->booking->public_token . '/passengers',
            ],
        );
    }
}