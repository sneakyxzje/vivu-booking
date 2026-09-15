<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingChangeProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingProposalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public BookingChangeProposal $proposal
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'VivuBooking - Quan trọng: Đề xuất thay đổi dịch vụ/lịch trình cho Đơn hàng #' . $this->booking->public_token,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_proposal',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
