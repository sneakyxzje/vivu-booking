<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Đơn đặt tour #' . $this->booking->id . ' đã được xác nhận - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.confirmed',
            with: [
                'booking' => $this->booking,
                'frontendBookingUrl' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}
