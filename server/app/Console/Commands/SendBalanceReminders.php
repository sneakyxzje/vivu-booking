<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Mail\BalanceReminderMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\BookingPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Nhắc khách trả nốt phần còn lại, hai lần trước hạn.
 *
 * ## Vì sao đây là việc bắt buộc, không phải tiện ích
 *
 * Từ khi bán theo cọc, quá hạn trả nốt nghĩa là đơn bị hủy và khách mất tiền cọc. Để chuyện đó xảy
 * ra với một người chưa từng được nhắc là lấy tiền của họ vì một cái hạn mà chỉ mình hệ thống nhớ.
 *
 * Trước lệnh này, hệ thống không nhắc tiền một lần nào: thư nhắc khởi hành chỉ nói điểm đón, giờ
 * tập trung và giấy tờ cần mang.
 *
 * ## Hai lần, hai giọng
 *
 * Lần đầu còn cả tuần nên chỉ là lời nhắc. Lần sau còn vài ngày và nói thẳng hậu quả. Một lần duy
 * nhất thì ai lỡ bỏ qua đúng lá thư ấy là mất cọc mà không kịp biết.
 *
 * ## Vì sao quét cả khoảng thay vì đúng một ngày
 *
 * Cùng lý do với `SendDepartureReminders`: lệnh chạy mỗi ngày một lần, và một hôm không chạy được
 * sẽ làm cả nhóm khách của ngày ấy vĩnh viễn không nhận thư. Hai cột đánh dấu lo việc không gửi
 * trùng, nên quét rộng là an toàn.
 */
class SendBalanceReminders extends Command
{
    protected $signature = 'bookings:send-balance-reminders';

    protected $description = 'Nhắc khách trả nốt phần còn lại trước hạn thanh toán';

    public function __construct(private readonly BookingPaymentService $payments)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $soNgayNhac = (int) config('booking.balance_reminder_days', 7);
        $soNgayCuoi = (int) config('booking.balance_final_notice_days', 2);

        $daGui = 0;

        foreach ($this->donConNo() as $booking) {
            $han = $booking->balanceDueAt();

            if (!$han) {
                continue;
            }

            $conBaoNhieuNgay = now()->diffInDays($han, false);
            /*
             * Chỉ người ĐÃ ĐẶT CỌC mới thuộc diện được cứu bằng lá thư muộn.
             *
             * Cùng phép lọc mà lệnh hủy dùng, và cùng lý do: đơn mà sổ ghi 0 đồng nhiều khả năng là
             * đơn đã trả tiền thật nhưng ai đó xác nhận tay mà quên ghi sổ. Gửi cho họ một lá đòi
             * tiền là đòi lần hai. Lệnh hủy vốn đã không đụng tới nhóm này, nên lá thư cũng chẳng
             * cứu họ khỏi điều gì — nó chỉ có thể gây hiểu nhầm.
             */
            $chuaTungNhac = $booking->balance_reminder_sent_at === null
                && $booking->balance_final_notice_at === null
                && $booking->payments()->whereIn('kind', BookingPayment::THU)->exists();

            /*
             * Đã quá hạn thì thôi nhắc — TRỪ người chưa từng nhận lá nào.
             *
             * Với người đã bỏ qua hai lá thư, một lời nhắc "hãy trả trước ngày hôm qua" chỉ làm họ
             * bối rối, và thư đúng lúc ấy là thư báo hủy.
             *
             * Nhưng có một nhóm rơi vào đây mà chưa hề được nhắc lần nào: đơn được chuyển sang
             * chuyến gần hơn. Hạn trả nốt tính theo chuyến đích nên nó nằm ở quá khứ ngay lúc
             * chuyển, và điều kiện cũ vắt qua đầu họ — không thư nhắc, rồi hôm sau lệnh hủy quét
             * trúng. Người bị dời ngày đi mất luôn chuyến vì một cái hạn không ai kịp làm gì.
             *
             * Nên với họ, lá này là lá đầu tiên và cũng là lá cuối. Lệnh hủy chờ hết khoảng ân hạn
             * kể từ lá thư mới đụng tới đơn, nên gửi ở đây không phải hình thức: nó mở ra đúng
             * khoảng thời gian mà người trong luồng thường vẫn được hưởng.
             */
            if ($conBaoNhieuNgay < 0 && !$chuaTungNhac) {
                continue;
            }

            $laCanhBaoCuoi = $conBaoNhieuNgay <= $soNgayCuoi;
            $cot = $laCanhBaoCuoi ? 'balance_final_notice_at' : 'balance_reminder_sent_at';

            /*
             * Đơn đoàn không nhận lá cảnh báo cuối.
             *
             * Lá ấy nói thẳng "quá hạn thì đơn bị hủy và mất cọc", mà lệnh hủy lại cố ý bỏ qua đơn
             * đoàn — nên với họ đó là một lời dọa không bao giờ được thực hiện. Nói với khách đoàn
             * một điều mình không làm là thứ tệ hơn cả im lặng: kế toán bên họ đọc xong thư ấy sẽ
             * gọi cho điều hành, và điều hành phải đính chính chính sách của công ty mình.
             *
             * Lá nhắc nhẹ thì vẫn gửi — nó chỉ nêu số còn thiếu và cách chuyển khoản, đúng thứ có
             * ích, và không hứa hẹn gì.
             */
            if ($laCanhBaoCuoi && $booking->isGroup()) {
                continue;
            }

            // Chưa tới lượt, hoặc đã gửi lá này rồi.
            if ($conBaoNhieuNgay > $soNgayNhac || $booking->{$cot} !== null) {
                continue;
            }

            $email = $booking->customer?->email ?: $booking->customer_email;

            if (!$email) {
                continue;
            }

            try {
                Mail::to($email)->send(new BalanceReminderMail($booking, $laCanhBaoCuoi));

                // Đóng mốc SAU khi gửi được, cùng lý do với thư nhắc khởi hành: đóng trước thì một
                // lỗi máy chủ thư biến thành "đã nhắc rồi" vĩnh viễn.
                $booking->forceFill([$cot => now()])->save();
                $daGui++;

                $this->line(sprintf(
                    '  %s cho %s (đơn #%d, còn %d ngày)',
                    $laCanhBaoCuoi ? 'Cảnh báo cuối' : 'Nhắc',
                    $email,
                    $booking->id,
                    $conBaoNhieuNgay,
                ));
            } catch (Throwable $e) {
                Log::warning('Không gửi được thư nhắc trả nốt.', [
                    'booking_id' => $booking->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                $this->warn("  Không gửi được cho {$email}");
            }
        }

        $this->info("Đã gửi {$daGui} thư nhắc thanh toán.");

        return self::SUCCESS;
    }

    /**
     * Đơn còn nợ tiền của chuyến chưa hủy, chưa kết thúc.
     *
     * Lọc phần "còn nợ" ngay trong SQL bằng cùng điều kiện mà màn công nợ phải thu dùng: tổng các
     * khoản THU nhỏ hơn giá đơn, trừ nhóm đơn cũ không có bút toán nào mà đã đóng mốc thanh toán.
     * Đọc `paid_at` không thôi thì đơn dựng bằng seeder hay nhập từ hệ thống cũ bị nhắc oan.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    private function donConNo()
    {
        $daThu = '(SELECT COALESCE(SUM(bp.amount), 0) FROM booking_payments bp'
            . ' WHERE bp.booking_id = bookings.id AND bp.kind IN (?, ?))';

        return Booking::query()
            ->with(['tour:id,title', 'schedule:id,start_date', 'customer:id,email'])
            ->whereIn('status', BookingStatus::paidValues())
            ->whereRaw($daThu . ' < bookings.total_amount', BookingPayment::THU)
            ->where(fn ($q) => $q
                ->whereHas('payments', fn ($p) => $p->whereIn(
                    'kind',
                    [...BookingPayment::THU, BookingPayment::HOAN],
                ))
                ->orWhereNull('paid_at'))
            ->whereHas('schedule', fn ($q) => $q
                ->whereNotIn('status', [ScheduleStatus::Cancelled->value, ScheduleStatus::Completed->value])
                ->where('start_date', '>', now()))
            ->get();
    }
}
