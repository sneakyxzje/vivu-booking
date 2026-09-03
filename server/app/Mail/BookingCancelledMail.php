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

class BookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Đơn đặt tour #' . $this->booking->id . ' đã được hủy - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.cancelled',
            with: [
                'booking' => $this->booking,
                /*
                 * Số khách đã thực đưa, đọc từ sổ chứ không đọc mốc `paid_at`.
                 *
                 * Mốc ấy chỉ đóng khi thu ĐỦ, nên nó là null ở đúng nhóm cần lá thư này nhất: người
                 * mới cọc rồi quá hạn trả nốt. Trước đây nhánh "không được hoàn tiền" gác sau
                 * `paid_at`, nên người vừa mất năm triệu nhận một lá thư không có lấy một dòng nào
                 * về tiền của họ.
                 */
                'daThu' => app(BookingPaymentService::class)->netPaid($this->booking),
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}
