<?php

namespace App\Mail;

use App\Enums\GroupRequestStatus;
use App\Models\GroupBookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Thư báo tiến triển của một yêu cầu đặt đoàn.
 *
 * ## Vì sao một lớp cho cả bốn bước
 *
 * Yêu cầu đoàn đi qua bốn mốc — nhận yêu cầu, có báo giá, chốt thành đơn, bị từ chối — và với người
 * nhận thì cả bốn là cùng một loại thư: *yêu cầu của tôi đang ở đâu, và tôi cần làm gì tiếp*. Dựng
 * bốn lớp để rồi cả bốn trả về cùng một hình dạng là đúng thứ bộ máy mà dự án này đã bỏ bớt ở nhóm
 * bàn giao. `ScheduleDeadlineChangedMail` cũng đang đổi nội dung theo hướng dịch của mốc thời gian
 * bằng đúng cách này.
 *
 * ## Vì sao luồng đoàn cần thư, không chỉ cần điện thoại
 *
 * Trước lá thư này, mã tra cứu của yêu cầu đoàn chỉ tồn tại trong phản hồi API ngay lúc bấm gửi:
 * đóng tab là mất, và không có tuyến "gửi lại mã" nào cho đoàn như đơn lẻ đã có. Người đại diện
 * đoàn thường là nhân viên hành chính đặt hộ cả công ty — họ gửi yêu cầu rồi chuyển việc khác, và
 * khi sếp hỏi "đến đâu rồi" thì không còn đường nào tra.
 *
 * Điều hành vẫn gọi điện báo giá như trước; thư này không thay việc đó, nó chỉ để lại một bản viết
 * mà hai bên cùng mở lại được.
 */
class GroupBookingUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public GroupBookingRequest $yeuCau)
    {
        $this->yeuCau->loadMissing(['tour', 'schedule', 'booking']);
    }

    public function envelope(): Envelope
    {
        $ten = $this->yeuCau->tour?->title ?? 'tour';

        return new Envelope(
            subject: match ($this->yeuCau->status) {
                GroupRequestStatus::PendingQuote => 'Đã nhận yêu cầu đặt đoàn - ' . $ten,
                GroupRequestStatus::Quoted => 'Báo giá cho đoàn của Quý khách - ' . $ten,
                GroupRequestStatus::Confirmed => 'Đã chốt đoàn - ' . $ten,
                GroupRequestStatus::Rejected => 'Về yêu cầu đặt đoàn của Quý khách - ' . $ten,
                default => 'Cập nhật yêu cầu đặt đoàn - ' . $ten,
            },
        );
    }

    public function content(): Content
    {
        $goc = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.group.update',
            with: [
                'yeuCau' => $this->yeuCau,
                'trangThai' => $this->yeuCau->status,
                // Trang tra cứu yêu cầu đoàn, mở bằng mã. Đây là thứ khách cần giữ nhất.
                'traCuuUrl' => $goc . '/group-booking?token=' . $this->yeuCau->public_token,
                // Sau khi chốt thì mọi theo dõi chuyển sang chính đơn hàng.
                'donUrl' => $this->yeuCau->booking
                    ? $goc . '/booking-success/' . $this->yeuCau->booking->public_token
                    : null,
            ],
        );
    }
}
