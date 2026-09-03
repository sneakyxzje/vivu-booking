<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentLog;
use App\Models\TourSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Xử lý kết quả thanh toán VNPay báo về.
 *
 * ## Vì sao phải tách ra khỏi controller
 *
 * VNPay báo kết quả qua HAI đường, và trước đây hệ thống chỉ nghe một:
 *
 *   - **Return URL** — trình duyệt của khách quay về sau khi trả tiền. Không đáng tin: khách tắt
 *     app ngân hàng, hết pin, rớt mạng, hoặc đơn giản là bấm nút Home. Tiền đã trừ nhưng không có
 *     dòng mã nào của ta chạy.
 *   - **IPN** — máy chủ VNPay gọi thẳng máy chủ ta, không đi qua thiết bị của khách. Đây mới là
 *     đường ghi nhận tiền; Return URL chỉ để hiển thị cho khách xem.
 *
 * Khi chỉ có Return URL, một khách trả tiền xong mà không quay lại sẽ bị tác vụ nhả chỗ hủy đơn
 * sau đúng mười phút. Tiền nằm trong tài khoản công ty, chỗ bán cho người khác, và không có báo
 * cáo nào cho biết chuyện đó vừa xảy ra.
 *
 * Hai đường phải cho ra CÙNG một kết quả, nên luật nằm ở đây chứ không ở controller — và vì đường
 * nào tới trước cũng được, toàn bộ xử lý phải chạy lại được mà không nhân đôi thứ gì.
 *
 * ## Chạy lại bao nhiêu lần cũng chỉ ghi một lần
 *
 * `vnp_TransactionNo` là mã của MỘT lần chuyển tiền tại VNPay. Một mã chỉ được ghi vào sổ đúng một
 * lần, và đó là điều kiện duy nhất đáng tin để chống ghi trùng.
 *
 * Trước đây chỗ này dựa vào "đơn còn thiếu tiền không" để khỏi ghi hai lần. Nó đúng ở trường hợp
 * thường gặp nhưng không phải một cái khóa: sau khi đơn được hoàn bớt một phần, số còn thiếu dương
 * trở lại, và mở lại đúng cái liên kết cũ trong lịch sử trình duyệt — chữ ký vẫn hợp lệ, nó không
 * hết hạn — sẽ ghi thêm một khoản thu mà không có đồng nào vào tài khoản.
 */
class VNPayCallbackService
{
    /** Mã trả về cho IPN, theo tài liệu tích hợp của VNPay. */
    public const RSP_THANH_CONG = '00';
    public const RSP_KHONG_TIM_THAY_DON = '01';
    public const RSP_DA_XU_LY = '02';
    public const RSP_SAI_CHU_KY = '97';
    public const RSP_LOI_KHAC = '99';

    public function __construct(
        private readonly VNPayService $vnpay,
        private readonly BookingHoldService $holdService,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly BookingPaymentService $paymentService,
    ) {
    }

    /**
     * Xử lý một lần báo kết quả, từ đường nào cũng được.
     *
     * @param  array<string, mixed>  $query  toàn bộ tham số VNPay gửi lên
     * @return array{booking: Booking|null, booking_id: int|null, successful: bool, rsp_code: string}
     */
    public function handle(array $query): array
    {
        $chuKyHopLe = $this->vnpay->hasValidSignature($query);
        $bookingId = $this->vnpay->bookingIdFrom($query['vnp_TxnRef'] ?? null);

        $thanhCong = $chuKyHopLe
            && ($query['vnp_ResponseCode'] ?? null) === '00'
            && ($query['vnp_TransactionStatus'] ?? null) === '00';

        // Số tiền của đúng lần trả này. Với đơn trả nhiều đợt, nó không bằng giá trị đơn.
        $soTien = isset($query['vnp_Amount']) ? (float) $query['vnp_Amount'] / 100 : 0.0;
        $maGiaoDich = $query['vnp_TransactionNo'] ?? null;

        if (!$bookingId) {
            $this->ghiNhatKyCong(null, $query, $chuKyHopLe);

            return $this->ketQua(null, false, self::RSP_KHONG_TIM_THAY_DON);
        }

        return DB::transaction(function () use ($bookingId, $query, $chuKyHopLe, $thanhCong, $soTien, $maGiaoDich) {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            $this->ghiNhatKyCong($booking, $query, $chuKyHopLe);

            if (!$booking) {
                return $this->ketQua(null, false, self::RSP_KHONG_TIM_THAY_DON);
            }

            if (!$chuKyHopLe) {
                return $this->ketQua(null, false, self::RSP_SAI_CHU_KY, $booking->id);
            }

            /*
             * Đã ghi mã giao dịch này rồi thì dừng, nhưng báo THÀNH CÔNG.
             *
             * Đây là đường mà lần gọi thứ hai đi vào — IPN thử lại, hoặc khách bấm tải lại trang
             * quay về. Không có gì để làm thêm, và với VNPay thì "tôi đã nhận và đã xử lý" mới là
             * câu trả lời đúng; báo lỗi sẽ khiến họ gọi lại mãi.
             */
            if ($thanhCong && $maGiaoDich && $this->daGhiGiaoDich($booking, $maGiaoDich)) {
                return $this->ketQua($booking, true, self::RSP_DA_XU_LY, $booking->id);
            }

            $schedule = $booking->tour_schedule_id
                ? TourSchedule::query()->whereKey($booking->tour_schedule_id)->lockForUpdate()->first()
                : null;

            if ($booking->status === 'pending') {
                return $this->xuLyDonChoThanhToan($booking, $thanhCong, $soTien, $maGiaoDich);
            }

            /*
             * Khách trả nốt phần còn lại của một đơn đã xác nhận.
             *
             * Nhánh này là luồng bình thường từ khi đơn trả được nhiều đợt. Điều kiện "còn thiếu
             * tiền" ở đây KHÔNG còn gánh việc chống ghi trùng — mã giao dịch ở trên làm việc đó —
             * nó chỉ còn nói đúng nghĩa của mình: đơn này còn nợ nên khoản tiền vừa về là hợp lý.
             */
            if ($thanhCong
                && $booking->status === 'confirmed'
                && $this->paymentService->balanceDue($booking) > 0) {
                $this->ghiSoTienVe($booking, $soTien, $maGiaoDich);

                return $this->ketQua(
                    $booking->fresh(['tour', 'schedule', 'discountCode']),
                    true,
                    self::RSP_THANH_CONG,
                    $booking->id,
                );
            }

            if ($thanhCong && $this->laDonTuHuyViQuaHan($booking)) {
                return $this->khoiPhucDonQuaHan($booking, $schedule, $soTien, $maGiaoDich, $query);
            }

            /*
             * Tiền về thật nhưng không có chỗ nào để ghi. Phải kêu lên, cả hai nhánh.
             *
             * Trước đây chỉ nhánh thứ nhất được ghi log, nên trường hợp hay xảy ra hơn lại đi qua
             * im lặng: khách mở hai tab trả tiền hai lần cho một đơn. Lần thứ hai mang mã giao dịch
             * khác nên phép chống trùng không bắt, đơn thì đã `confirmed` và hết nợ nên không nhánh
             * nào nhận — hàm trả `RspCode 00` cho VNPay rồi bỏ qua. Kế toán đối chiếu sao kê thấy
             * một khoản không thuộc về đơn nào và không có gì trong hệ thống giải thích được.
             */
            if ($thanhCong) {
                Log::warning(
                    $booking->paid_at
                        ? 'Tiền về cho đơn đã thu đủ — nhiều khả năng khách trả hai lần, cần hoàn tay.'
                        : 'Thanh toán thành công cho đơn không còn hiệu lực (đã hủy) — cần hoàn tiền thủ công.',
                    [
                        'booking_id' => $booking->id,
                        'booking_status' => $booking->status,
                        'transaction_no' => $maGiaoDich,
                        'amount' => $soTien,
                        'net_paid' => $this->paymentService->netPaid($booking),
                        'total_amount' => (float) $booking->total_amount,
                    ],
                );
            }

            return $this->ketQua(null, $thanhCong, self::RSP_THANH_CONG, $booking->id);
        });
    }

    /**
     * Đơn đang chờ thanh toán: trả tiền thành công thì xác nhận, thất bại thì để nguyên.
     *
     * Không nhận `$schedule` nữa — từ khi lần trả tiền hỏng thôi không hủy đơn, nhánh này không
     * còn chạm tới kho chỗ của chuyến nữa.
     *
     * @return array{booking: Booking|null, booking_id: int|null, successful: bool, rsp_code: string}
     */
    private function xuLyDonChoThanhToan(
        Booking $booking,
        bool $thanhCong,
        float $soTien,
        ?string $maGiaoDich,
    ): array {
        /*
         * Trả tiền THẤT BẠI thì đơn giữ nguyên `pending`, không hủy.
         *
         * Trước đây nhánh này hủy đơn và nhả chỗ ngay lập tức. Nhưng "thất bại" ở cổng thanh toán
         * phần lớn là những chuyện khách sửa được trong một phút: gõ sai OTP, thẻ không đủ số dư,
         * chọn nhầm ngân hàng, hoặc bấm nút Hủy trên trang ngân hàng để quay ra đổi thẻ khác. Hủy
         * đơn ngay nghĩa là họ quay lại thì chỗ đã mất, và phải đặt lại từ đầu — có khi chỗ ấy vừa
         * bị người khác lấy trong đúng khoảng thời gian đó.
         *
         * Thời hạn giữ chỗ sinh ra chính là để đựng khoảng này. Đơn ở lại `pending` tới `expires_at`
         * rồi `BookingHoldService` tự dọn nếu khách thật sự bỏ cuộc — không cần một đường hủy thứ
         * hai chạy sớm hơn hạn mà cả hệ thống đang cam kết với khách.
         */
        if (!$thanhCong) {
            return $this->ketQua(null, false, self::RSP_THANH_CONG, $booking->id);
        }

        $booking->update([
            'status' => 'confirmed',
            'vnpay_transaction_no' => $maGiaoDich,
            'confirmed_at' => now(),
            // Hết mười phút giữ chỗ: đơn đã xác nhận, tác vụ nhả chỗ không được đụng tới.
            'expires_at' => null,
        ]);

        /*
         * `paid_at` KHÔNG đóng ở đây — sổ giao dịch đóng nó, và chỉ khi đã thu đủ giá tour. Đóng
         * ngay tại đây thì một đơn mới trả một phần lại mang mốc "đã thanh toán", và mọi luồng đọc
         * mốc đó (chặn khách tự hủy, tính tiền hoàn) sẽ tin rằng khách đã trả hết.
         */
        $this->ghiSoTienVe($booking, $soTien, $maGiaoDich);

        return $this->ketQua(
            $booking->fresh(['tour', 'schedule', 'discountCode']),
            true,
            self::RSP_THANH_CONG,
            $booking->id,
        );
    }

    /**
     * Tiền về đúng lúc đơn vừa bị tự hủy vì quá hạn giữ chỗ.
     *
     * Còn chỗ thì dựng lại đơn, hết chỗ thì giữ nguyên hủy và cảnh báo để người ta hoàn tiền tay.
     * Không nhận lại đơn khi vẫn còn chỗ nghĩa là cầm tiền mà không giao gì.
     *
     * @param  array<string, mixed>  $query
     * @return array{booking: Booking|null, booking_id: int|null, successful: bool, rsp_code: string}
     */
    private function khoiPhucDonQuaHan(
        Booking $booking,
        ?TourSchedule $schedule,
        float $soTien,
        ?string $maGiaoDich,
        array $query,
    ): array {
        $conTrong = $schedule ? (int) $schedule->max_people - (int) $schedule->booked_people : 0;

        $chuyenConNhan = $schedule && !in_array(
            $schedule->status instanceof ScheduleStatus
                ? $schedule->status
                : ScheduleStatus::tryFrom((string) $schedule->status),
            [ScheduleStatus::Cancelled, ScheduleStatus::Completed],
            true,
        );

        if (!$chuyenConNhan || $booking->seatsTaken() > $conTrong) {
            Log::warning('Thanh toán thành công cho đơn đã quá hạn nhưng không còn chỗ — cần hoàn tiền thủ công.', [
                'booking_id' => $booking->id,
                'transaction_no' => $maGiaoDich,
                'amount' => $soTien,
            ]);

            return $this->ketQua(null, true, self::RSP_THANH_CONG, $booking->id);
        }

        $schedule->increment('booked_people', $booking->seatsTaken());
        $schedule->refresh();

        if ($schedule->booked_people >= $schedule->max_people) {
            $this->scheduleLifecycle->transitionTo(
                $schedule,
                ScheduleStatus::Closed,
                'Tự động đóng bán do booking vừa lấp đầy số chỗ.',
            );
        }

        $this->holdService->refreshTourAvailability($schedule);

        // Lấy lại lượt mã giảm giá đã hoàn khi tự hủy.
        $booking->loadMissing('discountCode');
        $booking->discountCode?->increment('used_count');

        $booking->update([
            'status' => 'confirmed',
            'cancel_reason' => null,
            'vnpay_transaction_no' => $maGiaoDich,
            'confirmed_at' => now(),
            'expires_at' => null,
        ]);

        // Ghi sổ sau khi đơn đã về `confirmed`: sổ từ chối khoản thu cho đơn đang ở trạng thái hủy.
        $this->ghiSoTienVe($booking, $soTien, $maGiaoDich);

        return $this->ketQua(
            $booking->fresh(['tour', 'schedule', 'discountCode']),
            true,
            self::RSP_THANH_CONG,
            $booking->id,
        );
    }

    private function laDonTuHuyViQuaHan(Booking $booking): bool
    {
        return $booking->status === 'cancelled'
            && $booking->cancel_reason === BookingHoldService::EXPIRED_REASON
            && !$booking->paid_at;
    }

    /**
     * Mã giao dịch này đã có trong sổ chưa.
     *
     * Chỉ hỏi các loại bút toán THU: một mã giao dịch của cổng không bao giờ sinh ra dòng hoàn, và
     * hỏi cả sổ thì một ngày nào đó `reference` của khoản hoàn tay trùng mã cũ sẽ chặn nhầm.
     */
    private function daGhiGiaoDich(Booking $booking, string $maGiaoDich): bool
    {
        return $booking->payments()
            ->whereIn('kind', BookingPayment::THU)
            ->where('reference', $maGiaoDich)
            ->exists();
    }

    /**
     * Ghi khoản tiền cổng thanh toán vừa báo về vào sổ giao dịch.
     *
     * `recorded_by` để trống: không có con người nào bấm nút ở đây, và gán bừa một tài khoản là nói
     * dối đúng cái cột dùng để quy trách nhiệm.
     */
    private function ghiSoTienVe(Booking $booking, float $soTien, ?string $maGiaoDich): void
    {
        if ($soTien <= 0) {
            return;
        }

        /*
         * Sổ từ chối khoản thu vượt số còn thiếu — ở đây thì KHÔNG được ném lỗi ra ngoài.
         *
         * Tiền đã nằm trong tài khoản công ty rồi; ném lỗi chỉ làm giao dịch quay lại và VNPay
         * nhận mã lỗi rồi gọi lại mãi, trong khi lần gọi nào cũng sẽ hỏng như nhau. Việc đúng là
         * ghi một cảnh báo thật to để có người xử lý tay, giống hệt cách hàm `handle()` đang xử lý
         * khoản tiền về cho một đơn đã hủy.
         */
        try {
            $this->paymentService->record(
                $booking,
                'balance',
                $soTien,
                'gateway',
                $maGiaoDich,
                'Thanh toán qua VNPay',
                null,
            );
        } catch (BusinessRuleException $e) {
            Log::warning('Tiền về qua cổng vượt số đơn còn thiếu — cần đối chiếu và hoàn tay.', [
                'booking_id' => $booking->id,
                'transaction_no' => $maGiaoDich,
                'amount' => $soTien,
                'total_amount' => (float) $booking->total_amount,
                'net_paid' => $this->paymentService->netPaid($booking),
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /** @param  array<string, mixed>  $query */
    private function ghiNhatKyCong(?Booking $booking, array $query, bool $chuKyHopLe): void
    {
        PaymentLog::create([
            'booking_id' => $booking?->id,
            'provider' => 'vnpay',
            'transaction_no' => $query['vnp_TransactionNo'] ?? null,
            'bank_code' => $query['vnp_BankCode'] ?? null,
            'response_code' => $query['vnp_ResponseCode'] ?? null,
            'transaction_status' => $query['vnp_TransactionStatus'] ?? null,
            'amount' => isset($query['vnp_Amount']) ? $query['vnp_Amount'] / 100 : null,
            'is_valid_signature' => $chuKyHopLe,
            'raw_payload' => $query,
        ]);
    }

    /**
     * @return array{booking: Booking|null, booking_id: int|null, successful: bool, rsp_code: string}
     */
    private function ketQua(
        ?Booking $booking,
        bool $thanhCong,
        string $rspCode,
        ?int $bookingId = null,
    ): array {
        return [
            // Đơn vừa được ghi nhận tiền, dùng để gửi thư. Null nghĩa là không có gì mới xảy ra.
            'booking' => $booking,
            'booking_id' => $bookingId ?? $booking?->id,
            'successful' => $thanhCong,
            'rsp_code' => $rspCode,
        ];
    }
}
