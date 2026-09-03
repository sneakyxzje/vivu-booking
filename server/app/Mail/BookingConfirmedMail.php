<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\BookingPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Thư báo đơn đã được xác nhận — và nói rõ đơn ấy còn nợ tiền hay không.
 *
 * Lá này gửi từ bốn đường: lệnh chốt chuyến, quản trị thu tay, hướng dẫn viên xác nhận, và luồng
 * hủy chuyến. Cả bốn đều lọc theo TRẠNG THÁI đơn, mà từ khi bán theo cọc thì `confirmed` mang hai
 * nghĩa: đã trả xong, hoặc mới cọc và chỗ đã được giữ.
 *
 * Nói với người mới cọc rằng đơn "đã được xác nhận, vui lòng có mặt trước giờ khởi hành 30 phút" là
 * bảo họ việc duy nhất còn lại là đi đúng giờ. Đây lại là lá thư gần hạn trả nốt nhất mà họ nhận
 * được — vài tuần sau đơn bị hủy vì chưa trả nốt, và họ mất cọc sau khi đã đọc một lá thư nói mọi
 * thứ đều ổn.
 */
class BookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    private function conThieu(): float
    {
        return app(BookingPaymentService::class)->balanceDue($this->booking);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->conThieu() > 0
                ? 'Chỗ của Quý khách đã được giữ - còn khoản cần thanh toán - Vivu Booking'
                : 'Đặt tour #' . $this->booking->id . ' đã được xác nhận - Vivu Booking',
        );
    }

    public function content(): Content
    {
        $conThieu = $this->conThieu();

        return new Content(
            view: 'emails.bookings.confirmed',
            with: [
                'booking' => $this->booking,
                'conThieu' => $conThieu,
                'daThu' => app(BookingPaymentService::class)->netPaid($this->booking),
                'hanTraNot' => $conThieu > 0 ? $this->booking->balanceDueAt() : null,
                'frontendBookingUrl' => rtrim(
                    config('app.frontend_url'),
                    '/'
                ) . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}