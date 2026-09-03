<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * K - Hủy cả chuyến khi đã có khách trả tiền.
 *
 * Trước đây nút "Hủy chuyến" chỉ ghi trạng thái, lý do và người hủy lên chuyến, còn đơn của khách
 * thì không đụng tới. Kết quả là đơn vẫn nằm ở "đã xác nhận", trỏ vào một chuyến đã hủy, không ai
 * được hoàn đồng nào và không ai được báo. Trên màn hình lại trông như đã xử lý xong - tệ hơn cả
 * việc chưa có nút nào, vì nó khiến người dùng tin là xong.
 *
 * Nhóm này bắt buộc ba bước: xem tác động, gán phương án cho **từng** đơn đã thanh toán, rồi mới
 * xác nhận. Còn một đơn chưa có phương án thì không hủy được.
 *
 * Hai nguyên tắc đền bù, và cả hai đều xuất phát từ một câu: **lỗi không thuộc về khách.**
 *
 *   1. Hoàn đủ 100% số tiền đã thu. Không áp bảng phí hủy - bảng đó dùng khi khách đổi ý, còn
 *      đây là hãng đơn phương không thực hiện.
 *   2. Chuyển chuyến miễn phí, không tính hạn báo trước, và không bị luật hạn chốt của chuyến
 *      nguồn chặn: chuyến ấy không chạy nữa nên chỗ của nó không còn ý nghĩa tồn kho. Luật ở
 *      chuyến ĐÍCH thì vẫn nguyên.
 *
 * Đơn chưa thanh toán không cần phương án: hủy và mời đặt lại, chỗ về kho vì chưa có cam kết nào.
 */
class ScheduleCancellationService
{
    /** Hoàn đủ số tiền khách đã trả. */
    public const HOAN_TIEN = 'refund';

    /** Chuyển sang chuyến khác, miễn phí. */
    public const CHUYEN_CHUYEN = 'transfer';

    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
        private readonly BookingTransferService $transferService,
        private readonly BookingHoldService $holdService,
        private readonly BookingAuditLogger $auditLogger,
        private readonly ScheduleAuditLogger $scheduleAuditLogger,
        private readonly BookingPaymentService $payments,
    ) {
    }

    /**
     * Tác động của việc hủy chuyến, tính trước khi bấm.
     *
     * @return array<string, mixed>
     */
    public function preview(TourSchedule $schedule): array
    {
        $canTro = $this->lyDoChan($schedule);

        $daTra = $this->donDaThanhToan($schedule);
        $chuaTra = $this->donChuaThanhToan($schedule);

        return [
            'can_cancel' => $canTro === null,
            'blocked_reason' => $canTro,
            'start_date' => $schedule->start_date,
            'hours_until_departure' => $schedule->start_date
                ? (int) now()->diffInHours($schedule->start_date, false)
                : null,

            // Nhóm bắt buộc phải có phương án.
            'paid_bookings' => $daTra->map(fn (Booking $don) => [
                'booking_id' => $don->id,
                'customer_name' => $don->customer_name,
                'customer_email' => $don->customer_email,
                'guests' => (int) $don->guests,
                'paid_amount' => $this->soTienDaTra($don),
            ])->values(),

            // Nhóm hệ thống tự xử lý, không cần hỏi.
            'unpaid_bookings' => $chuaTra->count(),
            'unpaid_guests' => (int) $chuaTra->sum('guests'),

            'total_paid_bookings' => $daTra->count(),
            'total_paid_guests' => (int) $daTra->sum('guests'),
            'total_refund_if_all_refunded' => $daTra->sum(fn (Booking $don) => $this->soTienDaTra($don)),

            'transfer_options' => $this->chuyenCoTheChuyenSang($schedule),
        ];
    }

    /**
     * Hủy chuyến và thực thi phương án của từng đơn.
     *
     * @param  array<int, array{action: string, to_schedule_id?: int|null}>  $phuongAn  khóa là booking_id
     * @return array<string, mixed>
     */
    public function cancel(
        TourSchedule $schedule,
        string $reason,
        array $phuongAn,
        ?User $actor = null,
    ): array {
        /*
         * Thư gửi SAU khi giao dịch chốt, không gửi ở giữa.
         *
         * Thư đã bay đi thì không gọi về được. Nếu gửi trong giao dịch rồi một đơn phía sau ném
         * lỗi, cả giao dịch quay lại nhưng khách đã nhận thư báo hủy một chuyến vẫn đang chạy —
         * và đó là loại nhầm lẫn tốn cả buổi gọi điện đính chính.
         *
         * Nên gom danh sách trong giao dịch, gửi ở ngoài.
         */
        $canBao = [];

        $ketQua = DB::transaction(function () use ($schedule, $reason, $phuongAn, $actor, &$canBao) {
            $khoa = TourSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy chuyến khởi hành.', 404);
            }

            $canTro = $this->lyDoChan($khoa);

            if ($canTro !== null) {
                throw new BusinessRuleException($canTro);
            }

            $daTra = $this->donDaThanhToan($khoa);

            $this->assertDuPhuongAn($daTra, $phuongAn);

            $daHoan = 0;
            $daChuyen = 0;
            $tongHoan = 0.0;

            foreach ($daTra as $don) {
                $chon = $phuongAn[$don->id];

                if (($chon['action'] ?? null) === self::CHUYEN_CHUYEN) {
                    $this->chuyenSangChuyenKhac($don, (int) $chon['to_schedule_id'], $reason, $actor);
                    $daChuyen++;
                    $canBao[] = ['booking_id' => $don->id, 'kieu' => 'chuyen'];

                    continue;
                }

                $tongHoan += $this->hoanDu($don, $khoa, $reason);
                $daHoan++;
                $canBao[] = ['booking_id' => $don->id, 'kieu' => 'huy'];
            }

            $chuaTra = $this->donChuaThanhToan($khoa);

            foreach ($chuaTra as $don) {
                $this->huyDonChuaTra($don, $khoa, $reason);
                $canBao[] = ['booking_id' => $don->id, 'kieu' => 'huy'];
            }

            // Đổi trạng thái sau cùng: máy trạng thái chặn chuyển đơn ra khỏi chuyến đã hủy, nên
            // đổi trước thì chính bước xử lý đơn ở trên bị luật của mình chặn lại.
            $khoa->refresh();
            $daHuy = $this->lifecycle->transitionTo(
                $khoa,
                ScheduleStatus::Cancelled,
                $reason,
                $actor?->getKey(),
            );

            $this->scheduleAuditLogger->log(
                $daHuy,
                ScheduleAuditAction::Cancelled,
                ['status' => ScheduleStatus::Open->value, 'booked_people' => (int) $schedule->booked_people],
                [
                    'status' => ScheduleStatus::Cancelled->value,
                    'refunded_bookings' => $daHoan,
                    'refund_total' => $tongHoan,
                    'transferred_bookings' => $daChuyen,
                    'cancelled_unpaid' => $chuaTra->count(),
                ],
                $reason,
                $actor,
            );

            return [
                'schedule_id' => $daHuy->id,
                'refunded' => $daHoan,
                'refund_total' => $tongHoan,
                'transferred' => $daChuyen,
                'cancelled_unpaid' => $chuaTra->count(),
            ];
        });

        $ketQua['notified'] = $this->baoChoKhach($canBao);

        return $ketQua;
    }

    /**
     * Báo cho từng khách sau khi mọi thứ đã chốt.
     *
     * Đây là bước thiếu lâu nhất của nhóm K: hệ thống hủy chuyến, hoàn đủ tiền, ghi nhật ký đầy
     * đủ - rồi không nói với ai. Khách vẫn tưởng mai đi.
     *
     * Thư hỏng không được làm hỏng nghiệp vụ đã chốt. Chuyến đã hủy và tiền đã hoàn là sự thật
     * rồi; máy chủ thư không gửi được thì ghi ra log để người ta gọi điện, chứ không ném lỗi
     * ngược lên làm người dùng tưởng thao tác thất bại.
     *
     * @param  array<int, array{booking_id: int, kieu: string}>  $canBao
     */
    private function baoChoKhach(array $canBao): int
    {
        $daGui = 0;

        foreach ($canBao as $muc) {
            $don = Booking::query()->with(['tour', 'schedule'])->find($muc['booking_id']);

            if (!$don || !$don->customer_email) {
                continue;
            }

            try {
                /*
                 * Đơn chuyển chuyến KHÔNG bị hủy - nó đổi sang chuyến khác. Gửi thư hủy cho họ là
                 * nói sai. Thư xác nhận in ra chuyến mới, đúng thứ họ cần biết.
                 */
                Mail::to($don->customer_email)->send(
                    $muc['kieu'] === 'chuyen'
                        ? new BookingConfirmedMail($don)
                        : new BookingCancelledMail($don),
                );

                $daGui++;
            } catch (\Throwable $e) {
                Log::error('Không gửi được thư hủy chuyến cho đơn BK-' . $don->id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $daGui;
    }

    /** Lý do không hủy được, hoặc null khi hủy được. */
    private function lyDoChan(TourSchedule $schedule): ?string
    {
        $hienTai = $this->lifecycle->effectiveStatus($schedule);

        if ($hienTai === ScheduleStatus::Cancelled) {
            return 'Chuyến này đã bị hủy trước đó.';
        }

        if ($hienTai->isRunning()) {
            return 'Đoàn đã lên đường nên không hủy chuyến được. Chi phí đã phát sinh và nhà cung '
                . 'cấp đã phục vụ; xử lý theo luồng sự cố và ghi nhận vắng mặt.';
        }

        if ($hienTai->isFinal()) {
            return sprintf('Chuyến đang ở trạng thái "%s" nên không hủy được nữa.', $hienTai->label());
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Booking>  $daTra
     * @param  array<int, array<string, mixed>>  $phuongAn
     */
    private function assertDuPhuongAn($daTra, array $phuongAn): void
    {
        $thieu = $daTra->filter(fn (Booking $don) => !isset($phuongAn[$don->id]));

        if ($thieu->isNotEmpty()) {
            throw new BusinessRuleException(sprintf(
                'Còn %d đơn đã thanh toán chưa chọn phương án (%s). Mỗi đơn phải được hoàn đủ '
                    . 'hoặc chuyển sang chuyến khác trước khi hủy chuyến.',
                $thieu->count(),
                $thieu->map(fn (Booking $don) => 'BK-' . $don->id)->implode(', '),
            ));
        }

        foreach ($phuongAn as $bookingId => $chon) {
            $hanhDong = $chon['action'] ?? null;

            if (!in_array($hanhDong, [self::HOAN_TIEN, self::CHUYEN_CHUYEN], true)) {
                throw new BusinessRuleException(sprintf('Đơn BK-%d có phương án không hợp lệ.', $bookingId));
            }

            if ($hanhDong === self::CHUYEN_CHUYEN && empty($chon['to_schedule_id'])) {
                throw new BusinessRuleException(sprintf('Đơn BK-%d chọn chuyển chuyến nhưng chưa chọn chuyến đích.', $bookingId));
            }
        }
    }

    /**
     * Hoàn đủ số khách đã trả và trả chỗ về kho.
     *
     * Không đọc bảng phí hủy: bảng ấy dành cho khách đổi ý. Ở đây hãng là bên không thực hiện.
     *
     * Chỗ luôn về kho kể cả đã qua hạn chốt. Ghế chết sinh ra để giữ đúng con số suất đã cam kết
     * của một chuyến **vẫn chạy**; chuyến này không chạy nữa nên con số đó không còn nghĩa gì.
     */
    private function hoanDu(Booking $don, TourSchedule $schedule, string $reason): float
    {
        /*
         * Nghĩa vụ GỘP: tổng đã THU, chưa trừ phần đã hoàn.
         *
         * `soTienDaTra()` trả về số RÒNG (thu trừ hoàn). Ghi số ròng vào cột này rồi để
         * `refundOutstanding()` trừ tiếp phần đã hoàn là trừ hai lần — đơn từng được hoàn một phần
         * vì chuyển sang chuyến rẻ hơn sẽ bị chi thiếu đúng bằng phần ấy.
         */
        $soTien = $this->payments->nghiaVuHoanGop($don, $this->soTienDaTra($don));
        $trangThaiCu = (string) $don->status;

        $lyDo = 'Công ty hủy chuyến. ' . $reason . ' Quý khách được hoàn đủ số tiền đã thanh toán.';

        $don->forceFill([
            'status' => BookingStatus::Cancelled->value,
            'cancel_type' => 'by_company',
            'cancel_reason' => $lyDo,
            'cancelled_at' => now(),
            // Số này là thứ thư báo cho khách đọc, và là thứ kế toán đối chiếu. Trước đây nó chỉ
            // nằm trong nhật ký, tức phải mở bảng khác mới biết đơn được hoàn bao nhiêu.
            'refund_amount' => $soTien,
            'seats_released' => true,
            'seats_released_at' => now(),
        ])->save();

        $this->holdService->releaseDiscountUsage($don);

        $schedule->decrement('booked_people', min($don->seatsTaken(), (int) $schedule->booked_people));
        $schedule->refresh();

        $this->auditLogger->logStatusChange(
            $don,
            BookingAuditAction::Cancelled,
            $trangThaiCu,
            BookingStatus::Cancelled->value,
            $lyDo,
            [
                'refund_amount' => $soTien,
                // Ghi rõ 100 để sau này đọc nhật ký khỏi tưởng ai đó tính nhầm bảng phí.
                'refund_percent' => 100,
                'seats_released' => true,
                'cancelled_schedule_id' => $schedule->getKey(),
            ],
        );

        return $soTien;
    }

    /*
     * ĐÃ GỠ: ghi thẳng một dòng `refund` vào sổ ngay lúc hủy chuyến.
     *
     * Dòng ấy nói "đã trả lại tiền cho khách", trong khi lúc hủy chuyến thì chưa ai chuyển đồng
     * nào — kế toán còn phải ra ngân hàng. Hậu quả là nghĩa vụ tự triệt tiêu ngay khi vừa sinh ra:
     * `refundOutstanding()` lấy `refund_amount` trừ tổng các dòng `refund`, hai số bằng nhau nên ra
     * 0, và đơn KHÔNG bao giờ xuất hiện ở màn hình "Chờ hoàn tiền". Không ai biết còn nợ khách.
     *
     * Giờ luồng này làm đúng như hai luồng hủy kia (quản trị hủy đơn, và duyệt yêu cầu hủy của
     * khách): chỉ ghi `refund_amount` — tức NGHĨA VỤ. Dòng `refund` trong sổ sinh ra khi tiền thực
     * sự rời tài khoản công ty, qua `POST /admin/bookings/{id}/payments`. Đó cũng là lúc `netPaid()`
     * giảm xuống, đúng theo nghĩa "số công ty đang giữ của khách".
     */

    /** Chuyển miễn phí sang chuyến khách chọn, do hãng khởi xướng. */
    private function chuyenSangChuyenKhac(Booking $don, int $toScheduleId, string $reason, ?User $actor): void
    {
        $dich = TourSchedule::query()->with('tour')->find($toScheduleId);

        if (!$dich) {
            throw new BusinessRuleException(sprintf('Không tìm thấy chuyến đích của đơn BK-%d.', $don->id));
        }

        $this->transferService->transfer(
            $don,
            $dich,
            'Công ty hủy chuyến cũ. ' . $reason,
            $actor,
            'company',
            nguonBiHuy: true,
        );
    }

    private function huyDonChuaTra(Booking $don, TourSchedule $schedule, string $reason): void
    {
        $lyDo = 'Công ty hủy chuyến. ' . $reason
            . ' Đơn chưa thanh toán nên được hủy, mời quý khách đặt lại chuyến khác.';

        $don->forceFill([
            'status' => BookingStatus::Cancelled->value,
            'cancel_type' => 'by_company',
            'cancel_reason' => $lyDo,
            'cancelled_at' => now(),
            'seats_released' => true,
            'seats_released_at' => now(),
        ])->save();

        $this->holdService->releaseDiscountUsage($don);

        // Đánh dấu seats_released thôi chưa đủ: phải trừ thật thì lệnh đối chiếu số chỗ mới khớp.
        $schedule->decrement('booked_people', min($don->seatsTaken(), (int) $schedule->booked_people));
        $schedule->refresh();

        $this->auditLogger->logStatusChange(
            $don,
            BookingAuditAction::Cancelled,
            BookingStatus::Pending->value,
            BookingStatus::Cancelled->value,
            $lyDo,
            ['seats_released' => true, 'refund_amount' => 0],
        );
    }

    /** @return \Illuminate\Support\Collection<int, Booking> */
    private function donDaThanhToan(TourSchedule $schedule)
    {
        return Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->whereIn('status', BookingStatus::paidValues())
            ->orderBy('id')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Booking> */
    private function donChuaThanhToan(TourSchedule $schedule)
    {
        return Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->where('status', BookingStatus::Pending->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * Số tiền khách đã thực đưa cho đơn này.
     *
     * Trước đây hàm này đọc `paid_at` rồi trả về nguyên giá đơn. Sai theo cả hai chiều, và chiều
     * nào cũng là tiền thật:
     *
     *   - `paid_at` chỉ đóng khi đã thu ĐỦ (xem `BookingPaymentService::record()`). Đơn đoàn mới
     *     đóng cọc 30% vẫn mang trạng thái `confirmed` nên vẫn nằm trong nhóm "đã thanh toán" của
     *     luồng này, nhưng `paid_at` là null — hàm trả về 0, và khách bị hoàn 0 đồng cho một chuyến
     *     mà CÔNG TY hủy.
     *   - Ngược lại, đơn đã trả đủ rồi được hoàn bớt một phần vẫn bị tính nguyên giá đơn, tức chi
     *     vượt đúng bằng phần đã hoàn trước đó.
     *
     * Giờ đọc sổ giao dịch qua nguồn dùng chung, giống hệt bảng phí hủy và bản in hợp đồng.
     */
    private function soTienDaTra(Booking $don): float
    {
        return $this->payments->paidForTour($don);
    }

    /**
     * Các chuyến nhận được khách của chuyến này.
     *
     * Lọc sẵn ở máy chủ để màn hình chỉ việc hiển thị: cùng tour, chưa khởi hành, đang mở bán và
     * còn trước hạn chốt của chính nó.
     *
     * @return array<int, array<string, mixed>>
     */
    private function chuyenCoTheChuyenSang(TourSchedule $schedule): array
    {
        return TourSchedule::query()
            ->with('tour:id,title,number_of_days')
            ->where('tour_id', $schedule->tour_id)
            ->whereKeyNot($schedule->getKey())
            ->where('status', ScheduleStatus::Open->value)
            ->where('start_date', '>', now())
            ->orderBy('start_date')
            ->get()
            ->filter(fn (TourSchedule $dich) => $dich->isBookable())
            ->map(fn (TourSchedule $dich) => [
                'schedule_id' => $dich->id,
                'start_date' => $dich->start_date,
                'remaining_seats' => $dich->remainingSeats(),
            ])
            ->values()
            ->all();
    }
}
