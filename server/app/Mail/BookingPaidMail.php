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
 * Thư báo tiền đã về, gửi sau mỗi lần cổng thanh toán ghi nhận thành công.
 *
 * Từ khi bán theo cọc, "tiền đã về" không còn đồng nghĩa với "đã trả xong". Lá thư nói *thanh toán
 * thành công* cho một người vừa cọc một nửa là nói với họ rằng không còn gì phải làm — rồi ba tuần
 * sau đơn của họ bị hủy vì chưa trả nốt.
 *
 * Nên thư tự nhận ra mình đang báo lần nào: còn nợ thì nói đây là tiền cọc và nhắc luôn hạn của
 * phần còn lại; hết nợ thì mới nói đã thanh toán đủ.
 */
class BookingPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    /** Đơn còn nợ tiền sau lần thanh toán vừa rồi. */
    private function conNo(): float
    {
        return app(BookingPaymentService::class)->balanceDue($this->booking);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->conNo() > 0
                ? 'Đã nhận tiền cọc đơn đặt tour #' . $this->booking->id . ' - Vivu Booking'
                : 'Thanh toán thành công đơn đặt tour #' . $this->booking->id . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        $conNo = $this->conNo();

        return new Content(
            view: 'emails.bookings.paid',
            with: [
                'booking' => $this->booking,
                'daThu' => app(BookingPaymentService::class)->netPaid($this->booking),
                'conNo' => $conNo,
                'hanTraNot' => $conNo > 0 ? $this->booking->balanceDueAt() : null,
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