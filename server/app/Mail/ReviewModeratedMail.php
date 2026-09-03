<?php

namespace App\Mail;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho người viết biết đánh giá của họ đã được duyệt hay bị từ chối.
 *
 * ## Vì sao phải gửi thư, không dùng hộp thông báo
 *
 * Hộp thông báo trong hệ thống chỉ mở cho điều hành và hướng dẫn viên (`role:admin,guide`); khách
 * không có màn hình nào để đọc. Nên với họ, thư là đường duy nhất.
 *
 * ## Vì sao đáng gửi
 *
 * Người viết vẫn thấy trạng thái và lý do từ chối khi mở lại trang tour, nên đây không phải một
 * lỗ hổng — nhưng nó đòi họ phải chủ động quay lại đúng trang ấy và để ý một dòng chữ nhỏ. Đánh
 * giá là thứ người ta bỏ công viết rồi chờ xem có được đăng không; im lặng khiến họ nghĩ bấm gửi
 * không ăn và gửi lại lần nữa — đúng cái mà chú thích ở `ReviewController` đã lo.
 *
 * Từ chối thì cần hơn cả duyệt: chỉ có lá thư này mang lý do tới tận nơi, và cho họ biết vẫn sửa
 * lại rồi gửi lần nữa được.
 */
class ReviewModeratedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Review $review)
    {
        $this->review->loadMissing(['tour:id,title,slug', 'user:id,name']);
    }

    public function envelope(): Envelope
    {
        $ten = $this->review->tour?->title ?? 'tour';

        return new Envelope(
            subject: $this->review->status === ReviewStatus::Approved
                ? 'Đánh giá của bạn về ' . $ten . ' đã được đăng - Vivu Booking'
                : 'Về đánh giá của bạn cho ' . $ten . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        $goc = rtrim(config('app.frontend_url'), '/');
        $slug = $this->review->tour?->slug ?? $this->review->tour_id;

        return new Content(
            view: 'emails.reviews.moderated',
            with: [
                'review' => $this->review,
                'daDuyet' => $this->review->status === ReviewStatus::Approved,
                'tourUrl' => $goc . '/tours/' . $slug,
            ],
        );
    }
}
