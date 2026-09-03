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
 * Thư nhắc khách trả nốt phần còn lại.
 *
 * Gửi hai lần với hai giọng khác nhau, vì hai lần ấy đứng ở hai chỗ khác nhau trên trục thời gian:
 *
 *   - **Nhắc thường** — còn cả tuần, chỉ là một lời nhắc để khách khỏi quên.
 *   - **Cảnh báo cuối** — còn vài ngày, và phải nói thẳng hậu quả: quá hạn thì đơn bị hủy và tiền
 *     cọc không lấy lại được.
 *
 * Lá thứ hai cố ý nặng lời hơn. Mất cọc là mất tiền thật, nên một lá thư nhẹ nhàng đứng ngay trước
 * nó là không đủ: người đọc lướt qua sẽ không nhận ra đây là lần cuối.
 */
class BalanceReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  bool  $laCanhBaoCuoi  Lá cuối trước khi đơn bị hủy.
     */
    public function __construct(
        public Booking $booking,
        public bool $laCanhBaoCuoi = false,
    ) {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        $han = $this->booking->balanceDueAt()?->format('d/m') ?? '';
        $tenTour = $this->booking->tour?->title ?? 'Vivu Booking';

        return new Envelope(
            subject: $this->laCanhBaoCuoi
                ? sprintf('Còn %s để thanh toán, nếu không đơn sẽ bị hủy - %s', $han, $tenTour)
                : sprintf('Nhắc thanh toán phần còn lại trước %s - %s', $han, $tenTour),
        );
    }

    public function content(): Content
    {
        $payments = app(BookingPaymentService::class);
        $conThieu = $payments->balanceDue($this->booking);

        return new Content(
            view: 'emails.bookings.balance_reminder',
            with: [
                'booking' => $this->booking,
                'laCanhBaoCuoi' => $this->laCanhBaoCuoi,
                'daThu' => $payments->netPaid($this->booking),
                'conThieu' => $conThieu,
                'hanTraNot' => $this->booking->balanceDueAt(),
                /*
                 * Liên kết trả nốt dựng ngay trong thư, đúng phần còn thiếu.
                 *
                 * Bắt khách mở trang tra cứu rồi tự tìm nút là thêm hai bước vào đúng việc mà cả lá
                 * thư này sinh ra để giục.
                 */
                'paymentUrl' => $conThieu > 0 && !$this->booking->isGroup()
                    ? app(\App\Services\VNPayService::class)->createPayment($this->booking, $conThieu)
                    : null,
                'frontendBookingUrl' => rtrim(config('app.frontend_url'), '/')
                    . '/booking-success/' . $this->booking->public_token,
            ],
        );
    }
}
