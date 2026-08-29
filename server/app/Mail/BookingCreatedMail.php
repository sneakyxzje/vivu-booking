<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $paymentUrl = null
    ) {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác nhận đặt tour #' . $this->booking->id . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.created',
            with: [
                'booking' => $this->booking,
                'paymentUrl' => $this->paymentUrl,

                'frontendBookingUrl' => rtrim(
                    config('app.frontend_url'),
                    '/'
                ) . '/booking-success/' . $this->booking->public_token,

                // `/booking-lookup`, không phải `/tra-cuu-don` — đường dẫn kia chưa bao giờ tồn
                // tại trong bộ định tuyến, nên mọi khách bấm vào đều rơi vào trang 404, đúng lúc
                // họ đang tìm cách tra lại đơn của mình.
                'lookupUrl' => rtrim(
                    config('app.frontend_url'),
                    '/'
                ) . '/booking-lookup',
            ],
        );
    }
}