<?php

namespace App\Console\Commands;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\TourSchedule;
use App\Notifications\Alert;
use App\Services\BookingAuditLogger;
use App\Services\BookingHoldService;
use App\Services\BookingPaymentService;
use App\Services\CancellationPolicyService;
use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Hủy đơn quá hạn trả nốt, và báo cho điều hành biết chuyến vừa trống chỗ.
 *
 * ## Vì sao khách mất cọc mà KHÔNG cần một điều khoản riêng
 *
 * Lệnh này không tự nghĩ ra mức phạt nào. Nó hủy đơn rồi áp đúng bảng phí hủy tại thời điểm ấy,
 * giống hệt mọi đường hủy khác trong hệ thống.
 *
 * Mất cọc là KẾT QUẢ của phép tính đó, không phải một luật thêm vào: hạn trả nốt và tỷ lệ cọc được
 * chọn sao cho bậc phí tại mốc ấy vừa đúng bằng tiền cọc. Với mặc định 50% và mười ngày, bậc phí là
 * 50% giá tour — khách đã đưa 50%, phí đúng 50%, hoàn bằng không. Xem chú thích ở config/booking.php.
 *
 * Cách này có một điểm quan trọng: nếu ai đó đổi tỷ lệ cọc hay dời hạn, con số vẫn tự khớp theo bảng
 * phí thay vì lệch với một hằng số viết cứng ở đâu đó.
 *
 * ## Vì sao chỗ vẫn về kho
 *
 * Hạn trả nốt (mười ngày trước khởi hành) nằm TRƯỚC hạn chốt danh sách (ba ngày), nên tại thời điểm
 * hủy chưa có gì gửi đi nhà cung cấp và chuyến vẫn đang nhận đặt. `BookingHoldService` tự nhận ra
 * điều đó và trả chỗ — không cần ngoại lệ nào ở đây.
 *
 * Đó cũng là lý do khoảng cách giữa hai mốc phải đủ rộng: nó chính là cửa sổ để bán lại cái chỗ vừa
 * trống ra.
 *
 * ## Vì sao đơn đoàn không bị đụng tới
 *
 * Tiền đoàn về nhiều đợt bằng chuyển khoản, do điều hành ghi tay, nên luôn có người theo dõi. Hủy tự
 * động một đoàn bốn mươi người vì kế toán bên họ chuyển chậm một ngày là mất khách lớn và mất uy tín
 * — thiệt hại không cân xứng với thứ luật này bảo vệ.
 */
class CancelUnpaidBalances extends Command
{
    protected $signature = 'bookings:cancel-unpaid-balances {--dry-run : Chỉ liệt kê, không hủy gì}';

    protected $description = 'Hủy đơn đã quá hạn thanh toán phần còn lại';

    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly CancellationPolicyService $cancellationPolicy,
        private readonly BookingHoldService $holdService,
        private readonly BookingAuditLogger $auditLogger,
        private readonly Notifier $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chiXem = (bool) $this->option('dry-run');
        $donQuaHan = $this->donQuaHan();

        if ($donQuaHan->isEmpty()) {
            $this->info('Không có đơn nào quá hạn thanh toán.');

            return self::SUCCESS;
        }

        $daHuy = 0;
        $daBao = [];
        $gheTrongTheoChuyen = [];

        foreach ($donQuaHan as $booking) {
            $conThieu = $this->payments->balanceDue($booking);

            $this->line(sprintf(
                '  Đơn #%d: đã thu %s / %s, quá hạn %s',
                $booking->id,
                number_format($this->payments->netPaid($booking), 0, ',', '.'),
                number_format((float) $booking->total_amount, 0, ',', '.'),
                $booking->balanceDueAt()?->format('d/m/Y') ?? '?',
            ));

            if ($chiXem) {
                continue;
            }

            try {
                $this->huyMotDon($booking, $conThieu);

                $daHuy++;
                $daBao[] = $booking->id;

                $scheduleId = (int) $booking->tour_schedule_id;
                $gheTrongTheoChuyen[$scheduleId] = ($gheTrongTheoChuyen[$scheduleId] ?? 0)
                    + $booking->seatsTaken();
            } catch (Throwable $e) {
                Log::error('Không hủy được đơn quá hạn thanh toán.', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);

                $this->warn("  Lỗi khi hủy đơn #{$booking->id}: {$e->getMessage()}");
            }
        }

        if ($chiXem) {
            $this->info("Có {$donQuaHan->count()} đơn quá hạn. Bỏ --dry-run để hủy thật.");

            return self::SUCCESS;
        }

        // Thư gửi sau khi mọi thứ đã ghi xong, cùng lý do với luồng hủy chuyến: thư đã bay đi thì
        // không gọi về được, còn giao dịch thì vẫn có thể quay lại.
        $this->baoChoKhach($daBao);
        $this->baoChoDieuHanh($gheTrongTheoChuyen);

        $this->info("Đã hủy {$daHuy} đơn quá hạn thanh toán.");

        return self::SUCCESS;
    }

    /**
     * Hủy một đơn, ghi nghĩa vụ hoàn theo đúng bảng phí, và trả chỗ nếu còn kịp.
     */
    private function huyMotDon(Booking $booking, float $conThieu): void
    {
        DB::transaction(function () use ($booking, $conThieu) {
            $schedule = $booking->tour_schedule_id
                ? TourSchedule::query()->whereKey($booking->tour_schedule_id)->lockForUpdate()->first()
                : null;

            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            /*
             * Đọc lại sau khi khóa.
             *
             * Khách hoàn toàn có thể vừa trả nốt trong lúc lệnh chạy, và hủy đơn của người vừa trả
             * tiền là mất tiền của người thật. Kiểm lại cả trạng thái lẫn số còn thiếu.
             */
            if (!$locked || !in_array($locked->status, BookingStatus::paidValues(), true)) {
                return;
            }

            if ($this->payments->balanceDue($locked) <= 0) {
                return;
            }

            $duBao = $this->cancellationPolicy->quote($locked, $schedule);
            $trangThaiCu = (string) $locked->status;

            $lyDo = sprintf(
                'Quá hạn thanh toán phần còn lại ngày %s. Đơn được hủy theo điều khoản đã thông báo; '
                    . 'khoản đã thanh toán được xử lý theo bảng phí hủy.',
                $locked->balanceDueAt()?->format('d/m/Y') ?? '',
            );

            $locked->update([
                'status' => BookingStatus::Cancelled->value,
                'cancel_type' => 'unpaid_balance',
                'cancel_reason' => $lyDo,
                'cancelled_at' => now(),
                'refund_amount' => $duBao['refund_amount'],
            ]);

            $this->holdService->releaseHold($locked, $schedule);

            $this->auditLogger->logStatusChange(
                $locked,
                BookingAuditAction::Cancelled,
                $trangThaiCu,
                BookingStatus::Cancelled->value,
                $lyDo,
                [
                    'refund_amount' => $duBao['refund_amount'],
                    'refund_percent' => $duBao['refund_percent'],
                    'balance_unpaid' => round($conThieu),
                    'seats_released' => (bool) $locked->fresh()->seats_released,
                ],
            );
        });
    }

    /** @param  array<int, int>  $ids */
    private function baoChoKhach(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $don = Booking::query()->whereIn('id', $ids)->with(['tour', 'schedule'])->get();

        foreach ($don as $booking) {
            $email = $booking->customer_email;

            if (!$email) {
                continue;
            }

            try {
                Mail::to($email)->send(new BookingCancelledMail($booking));
            } catch (Throwable $e) {
                Log::warning('Không gửi được thư báo hủy vì quá hạn thanh toán.', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Báo điều hành từng chuyến vừa trống ra bao nhiêu chỗ.
     *
     * Đây mới là phần có ích nhất của lệnh này. Chỗ đã về kho, nhưng bán tiếp hay chịu đi thiếu là
     * quyết định của con người — tùy mùa, tùy tour, tùy còn mấy ngày. Việc của hệ thống là nói cho
     * người quyết biết ngay, lúc còn kịp làm gì đó.
     *
     * @param  array<int, int>  $gheTrongTheoChuyen  khóa là id chuyến, giá trị là số ghế vừa trống
     */
    private function baoChoDieuHanh(array $gheTrongTheoChuyen): void
    {
        foreach ($gheTrongTheoChuyen as $scheduleId => $soGhe) {
            $schedule = TourSchedule::query()->with('tour:id,title')->find($scheduleId);

            if (!$schedule) {
                continue;
            }

            $conMayNgay = (int) now()->diffInDays($schedule->start_date, false);

            $this->notifier->toiDieuHanh(
                Alert::CHUYEN_TRONG_CHO,
                sprintf('Chuyến #%d vừa trống %d chỗ do khách không thanh toán', $scheduleId, $soGhe),
                sprintf(
                    '%s · khởi hành %s, còn %d ngày · chỗ đã trả về kho, cân nhắc bán tiếp hoặc '
                        . 'chấp nhận đi thiếu.',
                    $schedule->tour?->title ?? 'Tour',
                    $schedule->start_date?->format('d/m/Y') ?? 'chưa rõ',
                    max(0, $conMayNgay),
                ),
                '/admin/schedules',
            );
        }
    }

    /**
     * Đơn đã quá hạn trả nốt mà chưa thu đủ.
     *
     * Cùng điều kiện "còn nợ" với màn công nợ phải thu, cộng thêm mốc thời gian. Đơn đoàn loại ra:
     * xem khối chú thích ở đầu lớp.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    private function donQuaHan()
    {
        $soNgay = (int) config('booking.balance_due_days', 10);

        $daThu = '(SELECT COALESCE(SUM(bp.amount), 0) FROM booking_payments bp'
            . ' WHERE bp.booking_id = bookings.id AND bp.kind IN (?, ?))';

        return Booking::query()
            ->with(['tour:id,title', 'schedule:id,start_date,booking_deadline'])
            ->whereIn('status', BookingStatus::paidValues())
            ->whereNull('group_booking_request_id')
            ->whereRaw($daThu . ' < bookings.total_amount', BookingPayment::THU)
            ->where(fn ($q) => $q
                ->whereHas('payments', fn ($p) => $p->whereIn(
                    'kind',
                    [...BookingPayment::THU, BookingPayment::HOAN],
                ))
                ->orWhereNull('paid_at'))
            /*
             * Quá hạn = ngày khởi hành đã vào trong khoảng N ngày.
             *
             * Viết theo `start_date` thay vì tính hạn rồi so, để phép lọc chạy được ở SQL. Chuyến đã
             * khởi hành thì bỏ qua: lúc ấy hủy đơn là hủy chỗ của người có thể đang ngồi trên xe, và
             * đó là việc của luồng ghi nhận vắng mặt.
             */
            ->whereHas('schedule', fn ($q) => $q
                ->whereNotIn('status', [ScheduleStatus::Cancelled->value, ScheduleStatus::Completed->value])
                ->where('start_date', '>', now())
                ->where('start_date', '<=', now()->addDays($soNgay)))
            ->get();
    }
}
