<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $customerName;
    public string $customerEmail;
    public ?string $customerPhone;
    public ?string $tourTitle;

    /**
     * Khởi tạo mail xác thực OTP.
     */
    public function __construct(
        string $otpCode,
        string $customerName,
        string $customerEmail,
        ?string $customerPhone = null,
        ?string $tourTitle = null
    ) {
        $this->otpCode = $otpCode;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        $this->tourTitle = $tourTitle;
    }

    /**
     * Tiêu đề email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Mã xác nhận OTP đặt tour - Vivu Booking [' . $this->otpCode . ']',
        );
    }

    /**
     * Giao diện HTML template.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp_verification',
            with: [
                'otpCode' => $this->otpCode,
                'customerName' => $this->customerName,
                'customerEmail' => $this->customerEmail,
                'customerPhone' => $this->customerPhone,
                'tourTitle' => $this->tourTitle,
            ],
        );
    }

    /**
     * File đính kèm (nếu có).
     */
    public function attachments(): array
    {
        return [];
    }
}
