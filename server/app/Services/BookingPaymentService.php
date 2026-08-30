<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sổ giao dịch của một đơn hàng: mọi khoản thu và mọi khoản hoàn, cho MỌI loại đơn.
 *
 * ## Vì sao tách ra khỏi `GroupBookingService`
 *
 * Sổ này ra đời cùng booking đoàn nên nằm nhờ trong dịch vụ của đoàn, và ở đó có một câu chặn:
 * đơn lẻ không được ghi sổ, "đơn lẻ thanh toán một lần qua cổng". Lý do khi ấy đúng — mở sổ cho
 * đơn lẻ là hai nguồn sự thật về cùng một khoản tiền.
 *
 * Lý do đó hết hiệu lực từ khi đơn lẻ cũng trả nhiều lần: cọc trước, phần còn lại sau, và có thể
 * bằng chuyển khoản hay tiền mặt tại văn phòng chứ không qua cổng nào cả. Lúc đó câu hỏi "đơn này
 * đã thu bao nhiêu" không còn trả lời được bằng một cột `paid_at`.
 *
 * Nên sổ trở thành nguồn duy nhất cho mọi đơn, và `GroupBookingService` gọi sang đây.
 *
 * ## Bốn quy tắc, giữ nguyên từ bản cũ
 *
 * 1. Chỉ thêm dòng, không sửa dòng cũ. Ghi nhầm thì ghi một dòng ngược lại.
 * 2. Số tiền luôn dương; `kind` quyết định dấu.
 * 3. Thu thì đơn phải còn sống; hoàn thì được cả sau khi hủy — đó mới là lúc thường phải hoàn.
 * 4. Không bao giờ hoàn quá số đã thu.
 */
class BookingPaymentService
{
    public function __construct(private readonly BookingAuditLogger $auditLogger)
    {
    }

    /**
     * Ghi một khoản thu hoặc hoàn vào sổ.
     *
     * `$actor` nhận null cho các khoản do hệ thống ghi — cổng thanh toán báo tiền về chẳng hạn.
     * Ở đó không có con người nào bấm nút, và ghi bừa một người vào cột `recorded_by` là nói dối
     * đúng cái cột dùng để quy trách nhiệm.
     */
    public function record(
        Booking $booking,
        string $kind,
        float $amount,
        ?string $method = null,
        ?string $reference = null,
        ?string $note = null,
        ?User $actor = null,
    ): BookingPayment {
        if (!in_array($kind, [...BookingPayment::THU, BookingPayment::HOAN], true)) {
            throw new BusinessRuleException('Loại bút toán không hợp lệ.');
        }

        if ($amount <= 0) {
            throw new BusinessRuleException(
                'Số tiền phải lớn hơn 0. Muốn ghi hoàn thì chọn loại "hoàn tiền", không ghi số âm.',
            );
        }

        return DB::transaction(function () use ($booking, $kind, $amount, $method, $reference, $note, $actor) {
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if (in_array($kind, BookingPayment::THU, true)
                && in_array($fresh->status, ['cancelled', 'transferred'], true)) {
                throw new BusinessRuleException(
                    'Đơn đã ' . ($fresh->status === 'cancelled' ? 'hủy' : 'chuyển đi')
                    . ', không ghi thêm khoản thu được nữa. Ghi hoàn thì vẫn được.',
                );
            }

            $daThu = $this->netPaid($fresh);

            if ($kind === BookingPayment::HOAN && round($amount) > $daThu) {
                throw new BusinessRuleException(sprintf(
                    'Không hoàn quá số đã thu: đơn này mới thu thực %s đ.',
                    number_format($daThu, 0, ',', '.'),
                ));
            }

            $payment = BookingPayment::query()->create([
                'booking_id' => $fresh->getKey(),
                'kind' => $kind,
                'amount' => round($amount, 2),
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'paid_at' => now(),
                'recorded_by' => $actor?->getKey(),
            ]);

            /*
             * Thu đủ thì đóng mốc `paid_at` — một lần, không lùi.
             *
             * Mốc này nghĩa là "đã từng thu đủ giá tour". Các luồng sẵn có đọc nó như với đơn trả
             * một lần: chặn khách tự hủy, bắt yêu cầu hủy phải qua duyệt. Hoàn tiền về sau không
             * xóa mốc — số thực còn giữ nằm ở sổ, không ở cột này.
             */
            if ($fresh->paid_at === null && $this->netPaid($fresh) >= round((float) $fresh->total_amount)) {
                $fresh->forceFill(['paid_at' => now()])->save();
            }

            $this->auditLogger->log($fresh, BookingAuditAction::PaymentRecorded, null, [
                'kind' => $kind,
                'amount' => round($amount),
                'method' => $method,
                'net_paid_after' => $this->netPaid($fresh),
            ], $note);

            return $payment;
        });
    }

    /**
     * Số đã thu thực cho GIÁ TOUR: tổng các khoản thu trừ tổng các khoản hoàn.
     *
     * Cộng trên quan hệ đã nạp nếu có, để một màn hình liệt kê nhiều đơn không sinh hai truy vấn
     * cho mỗi đơn. Đường GHI không bao giờ rơi vào nhánh ấy: `record()` luôn đọc lại đơn dưới
     * khóa dòng, và bản đọc lại đó chưa nạp quan hệ nào — nên nó không thể cộng nhầm trên một
     * danh sách cũ.
     */
    public function netPaid(Booking $booking): float
    {
        if ($booking->relationLoaded('payments')) {
            $rows = $booking->getRelation('payments');

            return round(
                (float) $rows->whereIn('kind', BookingPayment::THU)->sum('amount')
                - (float) $rows->where('kind', BookingPayment::HOAN)->sum('amount'),
            );
        }

        $thu = (float) $booking->payments()->whereIn('kind', BookingPayment::THU)->sum('amount');
        $hoan = (float) $booking->payments()->where('kind', BookingPayment::HOAN)->sum('amount');

        return round($thu - $hoan);
    }

    /** Tổng đã hoàn cho giá tour. Dùng để biết một khoản hoàn đã trả xong chưa. */
    public function refunded(Booking $booking): float
    {
        if ($booking->relationLoaded('payments')) {
            return round(
                (float) $booking->getRelation('payments')->where('kind', BookingPayment::HOAN)->sum('amount'),
            );
        }

        return round((float) $booking->payments()->where('kind', BookingPayment::HOAN)->sum('amount'));
    }

    /**
     * Số tiền còn nợ khách sau khi hủy.
     *
     * `refund_amount` là nghĩa vụ chốt tại thời điểm hủy; các dòng `refund` trong sổ là những gì
     * đã thực trả. Hiệu số là thứ kế toán còn phải chi, và là thứ màn hình "Chờ hoàn tiền" đọc.
     *
     * Đơn chưa hủy thì `refund_amount` là null và hàm này trả 0 — không có nghĩa vụ nào.
     */
    public function refundOutstanding(Booking $booking): float
    {
        return max(0.0, round((float) ($booking->refund_amount ?? 0)) - $this->refunded($booking));
    }

    /** Còn thiếu bao nhiêu so với tổng giá trị đơn. */
    public function balanceDue(Booking $booking): float
    {
        return max(0.0, round((float) $booking->total_amount) - $this->netPaid($booking));
    }
}
