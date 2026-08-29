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

    /**
     * @param  float  $balanceDue  Số còn phải trả sau lần thu này. 0 nghĩa là đã trả đủ.
     *
     * Truyền vào thay vì để mẫu thư tự tính: thư gửi đi bằng hàng đợi, và tới lúc worker dựng nội
     * dung thì đơn có thể đã nhận thêm một khoản khác. Con số trong thư phải là con số tại thời
     * điểm khoản tiền này về, chứ không phải lúc thư được gửi.
     */
    public function __construct(public Booking $booking, public float $balanceDue = 0.0)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->balanceDue > 0
                ? 'Đã nhận tiền cọc đơn đặt tour #' . $this->booking->id . ' - Vivu Booking'
                : 'Thanh toán thành công đơn đặt tour #' . $this->booking->id . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.paid',
            with: [
                'booking' => $this->booking,
                'balanceDue' => $this->balanceDue,
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