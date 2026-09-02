<?php

namespace App\Mail;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho khách rằng công ty đã dời đơn của họ sang một chuyến khác.
 *
 * Ghép chuyến là quyết định vận hành, khách không được hỏi trước - và đúng vì thế mà lá thư này
 * phải nói đủ hai điều: ngày đi đổi thành ngày nào, và họ có quyền từ chối. Người trả tiền cho
 * ngày 20 mà được giao ngày 23 thì việc từ chối không phải là hủy đơn tự nguyện, nên không có lý
 * gì họ chịu phí hủy.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 2.
 */
class ScheduleMergedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Carbon $ngayCu,
        public Carbon $ngayMoi,
        public string $lyDo,
    ) {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Chuyến đi của Quý khách đổi sang ngày ' . $this->ngayMoi->format('d/m/Y')
                . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedules.merged',
            with: [
                'booking' => $this->booking,
                'ngayCu' => $this->ngayCu,
                'ngayMoi' => $this->ngayMoi,
                'lyDo' => $this->lyDo,
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}
