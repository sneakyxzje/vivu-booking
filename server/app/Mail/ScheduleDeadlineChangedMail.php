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
 * Báo cho khách rằng hạn chốt danh sách của chuyến vừa dịch.
 *
 * Thư này không nói về một con số ngày tháng, nó nói về QUYỀN của khách: tới lúc nào thì họ còn tự
 * sửa được tên hành khách, còn xin đổi chuyến, còn hủy mà được trả chỗ. Vì thế nội dung đổi theo
 * hướng dịch - rút ngắn là mất quyền nên phải nói thẳng, gia hạn là được thêm thời gian.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md mục 10.
 */
class ScheduleDeadlineChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?Carbon $hanChotCu,
        public ?Carbon $hanChotMoi,
        public ?string $lyDo,
    ) {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->rutNgan()
                ? 'Danh sách khách chốt sớm hơn dự kiến - Vivu Booking'
                : 'Thay đổi hạn chốt danh sách chuyến đi - Vivu Booking',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedules.deadline_changed',
            with: [
                'booking' => $this->booking,
                'hanChotCu' => $this->hanChotCu,
                'hanChotMoi' => $this->hanChotMoi,
                'lyDo' => $this->lyDo,
                'rutNgan' => $this->rutNgan(),
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }

    /**
     * Mốc mới sớm hơn mốc cũ hay không.
     *
     * Thiếu một trong hai mốc thì coi như không rút ngắn: chưa đủ căn cứ để dọa khách rằng họ vừa
     * mất quyền, mà báo nhầm chiều còn tệ hơn báo chung chung.
     */
    private function rutNgan(): bool
    {
        return $this->hanChotCu !== null
            && $this->hanChotMoi !== null
            && $this->hanChotMoi->lt($this->hanChotCu);
    }
}
