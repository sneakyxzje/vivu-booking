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

class BookingCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $paymentUrl = null
    ) {
        $this->booking->loadMissing(['tour', 'schedule']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác nhận đặt tour #' . $this->booking->id . ' - Vivu Booking',
        );
    }

    public function content(): Content
    {
        /*
         * Số phải trả NGAY, không phải giá tour.
         *
         * Nút trong thư dẫn tới đúng liên kết mà `BookingController::store()` đã dựng, và liên kết
         * ấy đòi tiền cọc chứ không đòi cả giá. In giá tour lên nút là hứa một con số rồi đưa khách
         * sang một con số khác — đúng thứ mà chú thích cạnh cái nút ấy nói nó sinh ra để tránh.
         *
         * Tính lại ở đây thay vì nhận qua tham số: thư có thể được dựng lại từ hàng đợi sau khi đơn
         * đã có thêm bút toán, và lúc ấy con số chụp sẵn lúc tạo thư đã cũ.
         */
        $tienPhaiTraNgay = app(BookingPaymentService::class)->nextPaymentAmount($this->booking);
        $conNo = max(0.0, round((float) $this->booking->total_amount) - $tienPhaiTraNgay);

        return new Content(
            view: 'emails.bookings.created',
            with: [
                'booking' => $this->booking,
                'paymentUrl' => $this->paymentUrl,
                'tienPhaiTraNgay' => $tienPhaiTraNgay,
                // Phần còn lại và hạn của nó. Bằng 0 khi đơn phải trả đủ ngay — chuyến sát ngày
                // không có đợt hai, xem `BookingPaymentService::nextPaymentAmount()`.
                'conNo' => $conNo,
                'hanTraNot' => $conNo > 0 ? $this->booking->balanceDueAt() : null,

                'frontendBookingUrl' => rtrim(
                    config('app.frontend_url'),
                    '/'
                ) . '/booking-success/' . $this->booking->public_token,

                // `/booking-lookup`, không phải `/tra-cuu-don` — đường dẫn kia chưa bao giờ tồn
                // tại trong bộ định tuyến, nên mọi khách bấm vào đều rơi vào trang 404, đúng lúc
                // họ đang tìm cách tra lại đơn của mình.
                'lookupUrl' => rtrim(
                    config('app.frontend_url'),
                    '/'
                ) . '/booking-lookup',
            ],
        );
    }
}