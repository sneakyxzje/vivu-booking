<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCreatedMail extends Mailable
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
                    env('FRONTEND_URL', 'http://localhost:5173'),
                    '/'
                ) . '/booking-success/' . $this->booking->public_token,

                'lookupUrl' => rtrim(
                    env('FRONTEND_URL', 'http://localhost:5173'),
                    '/'
                ) . '/tra-cuu-don',
            ],
        );
    }
}