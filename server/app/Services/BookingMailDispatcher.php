<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Mail\BalanceReminderMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingCreatedMail;
use App\Mail\BookingPaidMail;
use App\Mail\DepartureReminderMail;
use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi lại một lá thư của đơn, ngay lập tức, theo yêu cầu của người vận hành.
 *
 * ## Vì sao cần
 *
 * Mọi lá thư quan trọng của hệ thống đều do một sự kiện hoặc một tác vụ nền phát ra, và cả hai đều
 * gắn với thời điểm: thư nhắc trả nốt chỉ đi vào đúng cửa sổ của nó, thư báo hủy chỉ đi khi đơn bị
 * hủy thật. Nên muốn **cho ai đó xem** một lá thư — hội đồng chấm, khách gọi lên bảo không nhận
 * được, kế toán cần bản sao — thì không có đường nào ngoài chờ đúng hoàn cảnh xảy ra lần nữa.
 *
 * ## Vì sao không đơn giản là "gửi bất cứ thư nào cho bất cứ đơn nào"
 *
 * Một lá thư sai hoàn cảnh còn tệ hơn không có thư. Gửi "đơn đã được xác nhận" cho đơn vừa bị hủy,
 * hay "cảnh báo cuối trước khi hủy" cho người đã trả đủ tiền, là nói với khách một điều không đúng
 * — và họ tin, vì thư đến từ công ty.
 *
 * Nên mỗi loại thư mang theo điều kiện của chính nó. Không thỏa thì từ chối kèm lý do đọc được,
 * thay vì gửi đi rồi mới biết.
 *
 * Nội dung thư vẫn do chính lớp Mailable dựng, đọc từ dữ liệu hiện tại của đơn. Đây là gửi lại một
 * lá thư thật, không phải bản xem trước.
 */
class BookingMailDispatcher
{
    /**
     * Các loại thư gửi lại được: nhãn hiện trên nút, và điều kiện của từng loại.
     *
     * @return array<string, string>
     */
    public static function danhSach(): array
    {
        return [
            'created' => 'Xác nhận đã nhận đơn (kèm liên kết thanh toán)',
            'confirmed' => 'Đơn đã được xác nhận / chỗ đã được giữ',
            'paid' => 'Đã nhận thanh toán',
            'balance_reminder' => 'Nhắc trả nốt — lá nhẹ',
            'balance_final' => 'Nhắc trả nốt — lá cảnh báo cuối',
            'departure' => 'Nhắc trước ngày khởi hành',
            'cancelled' => 'Báo đơn đã hủy',
        ];
    }

    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly VNPayService $vnpay,
    ) {
    }

    /**
     * Dựng và gửi. Trả về địa chỉ đã gửi tới, để màn hình nói lại cho người bấm.
     *
     * @return array{loai: string, mo_ta: string, gui_toi: string}
     */
    public function gui(Booking $booking, string $loai): array
    {
        $danhSach = self::danhSach();

        if (!array_key_exists($loai, $danhSach)) {
            throw new BusinessRuleException('Loại thư không hợp lệ: ' . $loai);
        }

        $email = $booking->customer?->email ?: $booking->customer_email;

        if (!$email) {
            throw new BusinessRuleException('Đơn này không có địa chỉ thư điện tử để gửi.');
        }

        $booking->loadMissing(['tour', 'schedule']);

        Mail::to($email)->send($this->dungThu($booking, $loai));

        return [
            'loai' => $loai,
            'mo_ta' => $danhSach[$loai],
            'gui_toi' => $email,
        ];
    }

    private function dungThu(Booking $booking, string $loai): Mailable
    {
        $conThieu = $this->payments->balanceDue($booking);
        $daHuy = $booking->status === 'cancelled';

        return match ($loai) {
            'created' => $this->thuTaoDon($booking, $daHuy),

            'confirmed' => $daHuy
                ? throw new BusinessRuleException(
                    'Đơn này đã hủy nên không gửi được thư xác nhận — thư sẽ nói với khách rằng '
                    . 'chuyến đi của họ vẫn còn.',
                )
                : new BookingConfirmedMail($booking),

            'paid' => $this->payments->netPaid($booking) <= 0
                ? throw new BusinessRuleException(
                    'Sổ của đơn này chưa ghi nhận khoản thu nào, nên thư báo đã nhận thanh toán sẽ '
                    . 'nói sai. Ghi khoản thu vào sổ giao dịch trước.',
                )
                : new BookingPaidMail($booking),

            'balance_reminder', 'balance_final' => $this->thuNhacTraNot($booking, $conThieu, $daHuy, $loai),

            'departure' => $daHuy
                ? throw new BusinessRuleException('Đơn đã hủy thì không nhắc khởi hành.')
                : new DepartureReminderMail($booking),

            'cancelled' => $daHuy
                ? new BookingCancelledMail($booking)
                : throw new BusinessRuleException(
                    'Đơn này chưa bị hủy. Gửi thư báo hủy cho một đơn còn hiệu lực là báo tin sai '
                    . 'cho khách về chính chuyến đi họ sắp tham gia.',
                ),

            default => throw new BusinessRuleException('Loại thư không hợp lệ: ' . $loai),
        };
    }

    private function thuTaoDon(Booking $booking, bool $daHuy): Mailable
    {
        if ($daHuy) {
            throw new BusinessRuleException(
                'Đơn này đã hủy nên không dựng được liên kết thanh toán cho thư.',
            );
        }

        /*
         * Liên kết dựng lại theo số PHẢI TRẢ LẦN NÀY, đúng cách `BookingController::store()` làm.
         *
         * Dựng theo cả giá tour thì nút trong thư đòi một số, cổng đòi một số khác — đúng lỗi mà lá
         * thư này vừa được sửa để thôi mắc phải.
         */
        $traLanNay = $this->payments->nextPaymentAmount($booking);

        $lienKet = $traLanNay > 0 && !$booking->isGroup()
            ? $this->vnpay->createPayment($booking, $traLanNay)
            : null;

        return new BookingCreatedMail($booking, $lienKet);
    }

    private function thuNhacTraNot(Booking $booking, float $conThieu, bool $daHuy, string $loai): Mailable
    {
        if ($daHuy) {
            throw new BusinessRuleException('Đơn đã hủy thì không đòi tiền nữa.');
        }

        if ($conThieu <= 0) {
            throw new BusinessRuleException(
                'Đơn này không còn thiếu đồng nào. Gửi thư nhắc trả nốt cho người đã trả đủ là làm '
                . 'họ tưởng mình còn nợ và gọi lên hỏi.',
            );
        }

        return new BalanceReminderMail($booking, laCanhBaoCuoi: $loai === 'balance_final');
    }
}
