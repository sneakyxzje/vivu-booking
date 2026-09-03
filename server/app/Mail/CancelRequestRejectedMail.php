<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho khách rằng yêu cầu hủy của họ bị từ chối.
 *
 * Lá thư này tồn tại vì im lặng ở đây tệ hơn im lặng ở bất cứ bước nào khác: khách đã gửi yêu cầu
 * và đang chờ, còn đơn thì **không đổi gì cả**. Không báo lại thì họ tin rằng yêu cầu vẫn đang treo,
 * và chỉ phát hiện ra vào ngày khởi hành — lúc chuyến vẫn tính họ là khách đã đăng ký.
 *
 * Nên thư phải nói đủ ba điều: yêu cầu bị từ chối, vì sao, và đơn vẫn còn hiệu lực nên họ vẫn đi
 * được. Lý do là chữ điều hành đã ghi, bắt buộc tối thiểu 10 ký tự ở tầng controller.
 */
class CancelRequestRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $lyDo,
    ) {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yêu cầu hủy đơn #' . $this->booking->id . ' chưa được chấp nhận - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bookings.cancel_request_rejected',
            with: [
                'booking' => $this->booking,
                'lyDo' => $this->lyDo,
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}
