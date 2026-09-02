<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\User;

/**
 * Tài khoản ngân hàng nhận tiền hoàn của một đơn.
 *
 * ## Vì sao cần một đường riêng
 *
 * Số tài khoản trước đây chỉ được hỏi ở đúng một chỗ: form khách tự xin hủy. Hợp lý với luồng ấy,
 * nhưng nghĩa vụ hoàn tiền sinh ra từ ba đường, và hai đường còn lại đều là công ty chủ động:
 *
 *   - Điều hành hủy thẳng một đơn đã thu tiền.
 *   - Công ty hủy cả chuyến, hoàn đủ cho mọi khách.
 *
 * Ở hai đường đó khách không hề mở form nào, nên hệ thống lập ra một khoản phải trả mà không biết
 * chuyển vào đâu. Kế toán mở màn hình "Chờ hoàn tiền" ra và thấy số tiền, tên khách, rồi phải gọi
 * điện xin số tài khoản - và ghi vào sổ tay riêng, vì không có ô nào để lưu.
 *
 * Nay thư báo hủy dẫn khách về trang tra cứu đơn, ở đó họ tự điền. Điều hành cũng điền hộ được khi
 * khách đọc qua điện thoại.
 *
 * ## Vì sao chỉ cho điền khi thật sự có nghĩa vụ
 *
 * Số tài khoản là dữ liệu nhạy cảm; không có gì để hoàn mà vẫn thu thập là giữ một thứ không dùng
 * tới. Ràng buộc này cũng chặn việc dùng điểm cuối công khai làm nơi ghi bừa vào đơn người khác.
 */
class RefundAccountService
{
    public function __construct(private readonly BookingPaymentService $payments)
    {
    }

    /** @return array<string, mixed> */
    public static function validationRules(): array
    {
        return [
            'refund_bank_account' => ['required', 'string', 'max:50'],
            'refund_bank_name' => ['required', 'string', 'max:120'],
            'refund_account_holder' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'refund_bank_account.required' => 'Vui lòng nhập số tài khoản để chúng tôi chuyển tiền hoàn.',
            'refund_bank_name.required' => 'Vui lòng nhập tên ngân hàng.',
            'refund_account_holder.required' => 'Vui lòng nhập tên chủ tài khoản, đúng như trên thẻ.',
        ];
    }

    /**
     * Đơn này có đang chờ được hoàn tiền không.
     *
     * Đọc phần CÒN NỢ chứ không đọc `refund_amount`: khoản đã trả xong rồi thì không cần số tài
     * khoản nữa, và để ngỏ thì đơn cũ nào cũng sửa được mãi mãi.
     */
    public function dangChoHoanTien(Booking $booking): bool
    {
        return $this->payments->refundOutstanding($booking) > 0;
    }

    /**
     * @param  array<string, mixed>  $duLieu
     */
    public function update(Booking $booking, array $duLieu, ?User $actor = null): Booking
    {
        if (!$this->dangChoHoanTien($booking)) {
            throw new BusinessRuleException(
                'Đơn này không có khoản nào đang chờ hoàn nên chưa cần số tài khoản. Nếu bạn vừa '
                . 'nhận được thư báo hủy, thử tải lại trang sau ít phút.',
            );
        }

        $cu = $booking->only(['refund_bank_account', 'refund_bank_name', 'refund_account_holder']);

        $booking->forceFill([
            'refund_bank_account' => trim((string) $duLieu['refund_bank_account']),
            'refund_bank_name' => trim((string) $duLieu['refund_bank_name']),
            'refund_account_holder' => trim((string) $duLieu['refund_account_holder']),
        ])->save();

        /*
         * Ghi nhật ký vì đây là nơi tiền sẽ chảy tới.
         *
         * Đổi số tài khoản của một đơn đang chờ hoàn là thao tác đáng để lại vết: nếu về sau tiền
         * đi nhầm chỗ, câu hỏi đầu tiên là ai đã nhập con số ấy và lúc nào.
         */
        app(BookingAuditLogger::class)->log(
            $booking,
            BookingAuditAction::RefundAccountUpdated,
            $cu,
            $booking->only(['refund_bank_account', 'refund_bank_name', 'refund_account_holder']),
            $actor ? 'Điều hành nhập hộ tài khoản nhận tiền hoàn.' : 'Khách tự nhập tài khoản nhận tiền hoàn.',
        );

        return $booking;
    }
}
