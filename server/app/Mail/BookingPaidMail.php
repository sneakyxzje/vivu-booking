<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thanh toan thanh cong booking #' . $this->booking->id . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.paid',
            with: [
                'booking' => $this->booking,
                'frontendBookingUrl' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}