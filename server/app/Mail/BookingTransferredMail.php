<?php

namespace App\Mail;

use App\Models\BookingTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Xác nhận đơn đã được chuyển sang chuyến khác.
 *
 * ## Vì sao cần, dù khách đã đồng ý qua điện thoại
 *
 * Chuyển chuyến bắt buộc phải có bản ghi khách đồng ý trước (xem
 * `BookingTransferService::assertDaHoiKhach`), nên khách **biết** chuyện sắp xảy ra. Nhưng biết
 * qua một cuộc gọi không giống có một bản viết: ngày đi mới là thứ người ta phải xin nghỉ phép,
 * đặt vé tàu tới điểm tập kết, báo lại người nhà. Nghe qua điện thoại rồi nhớ nhầm một ngày là
 * chuyện thường.
 *
 * Thư này cũng là nơi duy nhất khách đọc được phần chênh lệch tiền: chuyến mới đắt hơn thì họ phải
 * trả thêm, rẻ hơn thì công ty nợ lại họ. Không viết ra thì con số ấy chỉ tồn tại trong sổ nội bộ.
 *
 * Hai luồng chuyển hàng loạt — hủy cả chuyến và ghép chuyến — đã có thư riêng của chúng, nên thư
 * này chỉ gửi từ đường chuyển từng đơn.
 */
class BookingTransferredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public BookingTransfer $banGhi)
    {
        $this->banGhi->loadMissing([
            'booking.tour',
            'booking.schedule',
            'fromSchedule',
            'toSchedule',
        ]);
    }

    public function envelope(): Envelope
    {
        $ngayMoi = $this->banGhi->toSchedule?->start_date?->format('d/m/Y') ?? 'ngày mới';

        return new Envelope(
            subject: 'Chuyến đi của Quý khách đã đổi sang ngày ' . $ngayMoi . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        $chenh = round((float) $this->banGhi->price_difference + (float) $this->banGhi->fee);

        return new Content(
            view: 'emails.bookings.transferred',
            with: [
                'banGhi' => $this->banGhi,
                'booking' => $this->banGhi->booking,
                'ngayCu' => $this->banGhi->fromSchedule?->start_date,
                'ngayMoi' => $this->banGhi->toSchedule?->start_date,
                // Dương: khách trả thêm. Âm: công ty nợ lại khách. Bằng 0: không phát sinh.
                'chenhLech' => $chenh,
                'phiDoiLich' => round((float) $this->banGhi->fee),
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->banGhi->booking?->public_token,
            ],
        );
    }
}
