<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PassengerOtpMail extends Mailable
{
    public function __construct(public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã xác thực thông tin hành khách - ViVu');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.passenger_otp');
    }
}
