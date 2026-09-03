<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Sân thử nghiệm nghiệp vụ: ép các mốc thời gian tới ngay, rồi chạy đúng lệnh nền thật.
 *
 * ## Vấn đề mà lớp này giải
 *
 * Gần như mọi luật tiền bạc của hệ thống đều treo vào một cái mốc tính từ ngày khởi hành: hạn giữ
 * chỗ, hạn trả nốt, hai lá thư nhắc, hạn chốt danh sách. Nên muốn xem hệ thống xử lý một tình huống
 * ra sao thì phải **chờ tới đúng ngày** — và với hạn trả nốt là chờ hàng tuần.
 *
 * Không ai chứng minh được một quy trình mười ngày trong một buổi ngồi trước máy.
 *
 * ## Cách làm: tua ngày khởi hành, KHÔNG giả lập kết quả
 *
 * Nút bấm ở đây không vẽ ra kết quả mong muốn. Nó dời ngày khởi hành của chuyến về đúng khoảng cách
 * cần thiết, rồi gọi **chính lệnh nền chạy hằng đêm**. Thứ người xem nhìn thấy là hành vi thật của
 * hệ thống trên dữ liệu thật, chỉ khác ở chỗ đồng hồ được kéo tới nơi.
 *
 * Đó cũng là lý do phải làm theo hướng này chứ không phải "giả bộ hôm nay là ngày X": đổi giờ hệ
 * thống thì mọi thứ khác cùng trôi theo — mốc thanh toán đã ghi trong sổ, thời điểm gửi thư, nhật
 * ký — và cái nhìn thấy sẽ không còn là cái sẽ xảy ra thật.
 *
 * ## Vì sao phải khóa trong tour có cờ sandbox
 *
 * Dời ngày khởi hành của một chuyến đã có khách là đúng thao tác mà `AdminTourController` vừa cấm,
 * vì nó dời hạn trả nốt của từng đơn — làm trên dữ liệu thật thì khách mất cọc vì một cái hạn không
 * ai kịp làm gì. Quyền ấy chỉ được mở trong tour đánh dấu là sân thử, và mọi lối vào đều đi qua
 * `assertLaSanThu()`.
 */
class SandboxDemoService
{
    /**
     * Các mốc tua được, và ý nghĩa của từng mốc.
     *
     * Khóa là thứ giao diện gửi lên; giá trị là câu giải thích hiện trên nút để người bấm biết mình
     * sắp chứng minh điều gì.
     *
     * @var array<string, string>
     */
    public const MOC = [
        'toi_han_nhac' => 'Tới ngày gửi thư nhắc lần đầu',
        'toi_canh_bao_cuoi' => 'Tới ngày gửi thư cảnh báo cuối',
        'qua_han_tra_not' => 'Vừa quá hạn trả nốt',
        'du_an_han_de_huy' => 'Quá hạn trả nốt và hết luôn thời gian ân hạn',
        'toi_han_chot' => 'Tới hạn chốt danh sách',
        'sat_khoi_hanh' => 'Sát giờ khởi hành',
    ];

    /**
     * Các lệnh nền chạy được từ sân thử.
     *
     * Đúng những lệnh mà `routes/console.php` hẹn giờ chạy hằng ngày, không phải bản rút gọn.
     *
     * @var array<string, string>
     */
    public const LENH = [
        'bookings:send-balance-reminders' => 'Gửi thư nhắc trả nốt',
        'bookings:cancel-unpaid-balances' => 'Hủy đơn quá hạn trả nốt',
        'schedules:close-expired' => 'Đóng bán chuyến tới hạn chốt',
        'schedules:confirm-ready' => 'Chốt chuyến đủ khách',
        'bookings:release-expired' => 'Nhả chỗ của đơn quá hạn giữ',
    ];

    public function __construct(private readonly BookingPaymentService $payments)
    {
    }

    /** Chặn mọi thao tác của lớp này ra ngoài tour đã đánh dấu là sân thử. */
    public function assertLaSanThu(?Tour $tour): void
    {
        if (!$tour?->is_sandbox) {
            throw new BusinessRuleException(
                'Thao tác này chỉ dùng được trong tour thử nghiệm nghiệp vụ. Tua ngày khởi hành của '
                . 'một chuyến đã có khách sẽ dời hạn thanh toán của từng đơn, nên trên tour thật nó '
                . 'bị cấm — dùng chức năng ghép chuyến hoặc chuyển chuyến.',
            );
        }
    }

    /**
     * Dời chuyến sao cho HÔM NAY rơi đúng vào mốc muốn xem.
     *
     * Trả về số ngày đã dời và ngày khởi hành mới, để màn hình nói lại cho người bấm biết chuyện gì
     * vừa xảy ra thay vì chỉ đổi số trong im lặng.
     *
     * @return array{moc: string, so_ngay_da_doi: int, khoi_hanh_cu: string, khoi_hanh_moi: string}
     */
    public function tuaToiMoc(TourSchedule $schedule, string $moc): array
    {
        $this->assertLaSanThu($schedule->tour);

        if (!array_key_exists($moc, self::MOC)) {
            throw new BusinessRuleException('Mốc thử nghiệm không hợp lệ: ' . $moc);
        }

        if (!$schedule->start_date) {
            throw new BusinessRuleException('Chuyến này chưa có ngày khởi hành để tua.');
        }

        $cu = $schedule->start_date->copy();
        $moi = $this->ngayKhoiHanhChoMoc($moc);
        $soNgay = (int) $cu->copy()->startOfDay()->diffInDays($moi->copy()->startOfDay(), false);

        $this->doiNgayKhoiHanh($schedule, $moi);

        return [
            'moc' => self::MOC[$moc],
            'so_ngay_da_doi' => $soNgay,
            'khoi_hanh_cu' => $cu->format('d/m/Y H:i'),
            'khoi_hanh_moi' => $moi->format('d/m/Y H:i'),
        ];
    }

    /**
     * Ngày khởi hành cần thiết để hôm nay rơi đúng vào mốc.
     *
     * Mọi mốc đều suy ngược từ cùng bộ cấu hình mà nghiệp vụ thật dùng, nên đổi `balance_due_days`
     * là sân thử tự đi theo — không có con số nào viết riêng ở đây.
     */
    private function ngayKhoiHanhChoMoc(string $moc): Carbon
    {
        $hanTraNot = (int) config('booking.balance_due_days', 10);
        $nhacLanDau = (int) config('booking.balance_reminder_days', 7);
        $canhBaoCuoi = (int) config('booking.balance_final_notice_days', 2);
        $hanChot = (int) config('booking.booking_deadline_days', 3);

        /*
         * Neo vào một giờ TRƯỚC bây giờ, không neo vào đầu ngày.
         *
         * Các lệnh nền so mốc bằng số ngày lẻ chứ không làm tròn: lệnh nhắc bỏ qua đơn khi
         * `còn > 7 ngày`, và chỉ coi là cảnh báo cuối khi `còn <= 2 ngày`. Neo vào 06:00 hôm nay
         * thì bấm nút lúc một giờ sáng sẽ ra 7,2 ngày — rơi ra ngoài cửa sổ đúng một chút, và nút
         * "tua tới ngày gửi thư nhắc" lặng lẽ không gửi gì cả.
         *
         * Lùi một giờ đặt mọi mốc nằm gọn BÊN TRONG cửa sổ của nó, không phụ thuộc bấm lúc mấy giờ.
         */
        $homNay = now()->subHour();

        return match ($moc) {
            // Hôm nay = hạn trả nốt − số ngày nhắc trước ⇒ khởi hành = hôm nay + hạn + nhắc trước.
            'toi_han_nhac' => $homNay->copy()->addDays($hanTraNot + $nhacLanDau),
            'toi_canh_bao_cuoi' => $homNay->copy()->addDays($hanTraNot + $canhBaoCuoi),
            // Vừa quá hạn một ngày: đủ để lệnh nhắc thấy là quá hạn, chưa đủ để lệnh hủy đụng tới.
            'qua_han_tra_not' => $homNay->copy()->addDays($hanTraNot - 1),
            // Quá hạn và đã qua khoảng ân hạn kể từ lá cuối ⇒ lệnh hủy được phép hủy.
            'du_an_han_de_huy' => $homNay->copy()->addDays(max(1, $hanTraNot - $canhBaoCuoi - 1)),
            'toi_han_chot' => $homNay->copy()->addDays($hanChot),
            'sat_khoi_hanh' => $homNay->copy()->addDay(),
            default => $homNay->copy()->addDays($hanTraNot),
        };
    }

    /**
     * Dời ngày khởi hành và kéo theo mọi mốc phụ thuộc.
     *
     * Ba thứ phải đi cùng nhau, thiếu một là dữ liệu tự mâu thuẫn:
     *
     *   - `end_date` giữ nguyên độ dài chuyến.
     *   - `booking_deadline` giữ nguyên khoảng cách tới ngày đi, vì đó là thỏa thuận với nhà cung cấp.
     *   - `bookings.departure_date` là bản sao trên đơn; vài chỗ đọc cột ấy thay vì đọc qua chuyến,
     *     nên bỏ quên nó là để lại hai câu trả lời khác nhau cho cùng một câu hỏi.
     */
    private function doiNgayKhoiHanh(TourSchedule $schedule, Carbon $moi): void
    {
        DB::transaction(function () use ($schedule, $moi) {
            $cu = $schedule->start_date->copy();
            $lech = $cu->diffInSeconds($moi, false);

            $schedule->forceFill([
                'start_date' => $moi,
                'end_date' => $schedule->end_date?->copy()->addSeconds($lech),
                'arrival_at' => $schedule->arrival_at?->copy()->addSeconds($lech),
                'return_departure_at' => $schedule->return_departure_at?->copy()->addSeconds($lech),
                'booking_deadline' => $schedule->booking_deadline?->copy()->addSeconds($lech),
            ])->save();

            Booking::query()
                ->where('tour_schedule_id', $schedule->getKey())
                ->update(['departure_date' => $moi]);
        });
    }

    /**
     * Chạy một lệnh nền và trả về nguyên văn thứ nó in ra.
     *
     * In lại đầu ra thật thay vì tóm tắt: người xem cần thấy chính những dòng mà máy chủ ghi ra lúc
     * hai giờ sáng, kể cả khi nó nói "không có đơn nào".
     *
     * @return array{lenh: string, mo_ta: string, ket_qua: string}
     */
    public function chayLenh(string $lenh): array
    {
        if (!array_key_exists($lenh, self::LENH)) {
            throw new BusinessRuleException('Lệnh không nằm trong danh sách cho phép: ' . $lenh);
        }

        Artisan::call($lenh);

        return [
            'lenh' => $lenh,
            'mo_ta' => self::LENH[$lenh],
            'ket_qua' => trim(Artisan::output()),
        ];
    }

    /**
     * Ảnh chụp tình trạng mọi đơn của một chuyến, để so trước và sau khi bấm.
     *
     * Đây là thứ biến nút bấm thành bằng chứng: không có bảng này thì người xem chỉ thấy một thông
     * báo "đã chạy xong" và phải tự tin là có chuyện gì đó đã xảy ra.
     *
     * @return array<int, array<string, mixed>>
     */
    public function anhChup(TourSchedule $schedule): array
    {
        return Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->with('schedule:id,start_date,booking_deadline')
            ->orderBy('id')
            ->get()
            ->map(fn (Booking $don) => [
                'id' => $don->id,
                'ma' => 'BK-' . $don->id,
                'khach' => $don->customer_name,
                'trang_thai' => $don->status,
                'la_doan' => $don->isGroup(),
                'tong_don' => round((float) $don->total_amount),
                'da_thu' => $this->payments->netPaid($don),
                'con_thieu' => $this->payments->balanceDue($don),
                'phai_tra_lan_nay' => $this->payments->nextPaymentAmount($don),
                'nghia_vu_hoan' => $this->payments->refundOutstanding($don),
                'han_tra_not' => $don->balanceDueAt()?->format('d/m/Y'),
                'da_nhac' => $don->balance_reminder_sent_at?->format('d/m H:i'),
                'da_canh_bao_cuoi' => $don->balance_final_notice_at?->format('d/m H:i'),
                'cho_da_tra' => (bool) $don->seats_released,
            ])
            ->all();
    }
}
