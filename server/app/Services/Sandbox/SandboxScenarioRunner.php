<?php

namespace App\Services\Sandbox;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\ContactPurpose;
use App\Enums\GroupRequestStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CancellationPolicy;
use App\Models\CustomerContactLog;
use App\Models\GroupBookingRequest;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\BookingTransferService;
use App\Services\CancellationPolicyService;
use App\Services\ScheduleCancellationService;
use App\Services\ScheduleMergeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Chạy trọn một tình huống nghiệp vụ và ghi biên bản từng bước.
 *
 * ## Vì sao là kịch bản chứ không phải nút rời
 *
 * Bản đầu của sân thử là một bảng nút: tua tới mốc này, chạy lệnh kia, đọc bảng. Nó đúng về mặt kỹ
 * thuật và gần như vô dụng khi cần **chứng minh** một điều gì: người xem phải tự biết bấm gì trước,
 * bấm gì sau, rồi tự nhìn cột nào để kết luận đúng hay sai. Ai chưa thuộc luồng thì không đọc ra
 * được gì; ai đã thuộc thì không cần bảng ấy.
 *
 * Ở đây mỗi tình huống là một kịch bản có tên, tự dựng dữ liệu của nó, chạy các bước theo đúng thứ
 * tự đời thật, và **tự chấm từng bước đúng hay sai**. Cái hiện ra là một biên bản đọc được từ trên
 * xuống, kèm sổ giao dịch và nhật ký của chính đơn ấy.
 *
 * ## Mỗi lần chạy dựng dữ liệu mới
 *
 * Kịch bản tiêu dữ liệu của nó — hủy đơn, chuyển chuyến, ghi bút toán. Dùng chung một bộ đơn dựng
 * sẵn thì chạy lần hai đã ra kết quả khác, và người thử không biết vì mình bấm sai hay vì dữ liệu
 * đã cũ. Nên mỗi lần chạy xóa dấu vết lần trước rồi dựng lại từ đầu.
 *
 * ## Không giả lập gì
 *
 * Mọi bước gọi đúng dịch vụ và đúng lệnh nền mà đường thật đi qua: `BookingTransferService`,
 * `ScheduleMergeService`, `ScheduleCancellationService`, `bookings:send-balance-reminders`,
 * `bookings:cancel-unpaid-balances`. Thứ duy nhất bị can thiệp là ngày khởi hành của chuyến — đó
 * là cách kéo đồng hồ tới nơi mà không phải chờ mười ngày.
 */
class SandboxScenarioRunner
{
    /** Dấu để mỗi lần chạy dọn sạch lần trước. */
    private const TAG = '[kb]';

    private const SLUG_TOUR = 'sandbox-kich-ban';

    /** Kịch bản đang chạy — dùng để đánh dấu đơn nó dựng ra, cho lần chạy sau dọn đúng chỗ. */
    private string $kichBan = '';

    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly BookingTransferService $transfer,
        private readonly ScheduleMergeService $merge,
        private readonly ScheduleCancellationService $huyChuyen,
        private readonly CancellationPolicyService $bangPhi,
    ) {
    }

    /**
     * Danh mục kịch bản, gom theo nhóm.
     *
     * @return array<int, array{id: string, nhom: string, ten: string, chung_minh: string}>
     */
    public static function danhMuc(): array
    {
        return [
            [
                'id' => 'coc_roi_tra_not',
                'nhom' => 'Luồng tiền cơ bản',
                'ten' => 'Cọc 50% rồi trả nốt đúng hạn',
                'chung_minh' => 'Hai đợt thu vào sổ thành hai dòng, và đơn hết nợ sau đợt hai.',
            ],
            [
                'id' => 'coc_roi_bo_ngang',
                'nhom' => 'Luồng tiền cơ bản',
                'ten' => 'Cọc rồi bỏ ngang tới quá hạn',
                'chung_minh' => 'Mất cọc rơi ra từ bảng phí thường, không cần điều khoản riêng. Chỗ về kho.',
            ],
            [
                'id' => 'cong_ty_doi_ngay',
                'nhom' => 'Chuyển chuyến',
                'ten' => 'CÔNG TY dời sang chuyến sớm hơn',
                'chung_minh' => 'Hạn nhảy vào quá khứ nhưng khách không thiệt: một lá thư, ân hạn, và hoàn đủ.',
            ],
            [
                'id' => 'khach_xin_doi',
                'nhom' => 'Chuyển chuyến',
                'ten' => 'KHÁCH xin đổi sang chuyến sớm hơn',
                'chung_minh' => 'Cùng thao tác, khác kết cục: bảng phí áp bình thường nên mất trọn cọc.',
            ],
            [
                'id' => 'chuyen_tour_dat_hon',
                'nhom' => 'Chuyển chuyến',
                'ten' => 'Chuyển sang tour ĐẮT hơn',
                'chung_minh' => 'Cọc đi theo đơn bằng số tiền, không tính lại theo tỷ lệ phần trăm.',
            ],
            [
                'id' => 'chuyen_tour_re_hon',
                'nhom' => 'Chuyển chuyến',
                'ten' => 'Chuyển sang tour RẺ hơn cọc đã đưa',
                'chung_minh' => 'Phần thừa thành nghĩa vụ hoàn ngay, không nằm im trong sổ.',
            ],
            [
                'id' => 'ghep_chuyen_som_hon',
                'nhom' => 'Ghép chuyến',
                'ten' => 'Ghép sang chuyến sớm hơn',
                'chung_minh' => 'Đơn đã cọc được chuyển và hạn dịch theo; đơn chưa cọc bị hủy và mời đặt lại.',
            ],
            [
                'id' => 'ghep_qua_han_chot',
                'nhom' => 'Ghép chuyến',
                'ten' => 'Ghép vào chuyến đã qua hạn chốt',
                'chung_minh' => 'Bị chặn ở cả hai đầu, vì danh sách đã gửi nhà cung cấp.',
            ],
            [
                'id' => 'cong_ty_huy_chuyen',
                'nhom' => 'Hủy và hoàn',
                'ten' => 'Công ty hủy cả chuyến',
                'chung_minh' => 'Hoàn đủ số ĐÃ THU, không áp bảng phí — bên bán không thực hiện.',
            ],
            [
                'id' => 'khach_huy_tung_bac',
                'nhom' => 'Hủy và hoàn',
                'ten' => 'Khách hủy ở từng bậc phí',
                'chung_minh' => 'Sáu bậc, sáu mức hoàn. Phí tính trên giá đơn, trừ vào số đã thu, kẹp dưới bằng 0.',
            ],
            [
                'id' => 'don_doan_qua_han',
                'nhom' => 'Nhóm không bị đụng',
                'ten' => 'Đơn ĐOÀN quá hạn trả nốt',
                'chung_minh' => 'Không hủy tự động và không nhận lá dọa — tiền đoàn luôn có người theo.',
            ],
            [
                'id' => 'so_ghi_0_dong',
                'nhom' => 'Nhóm không bị đụng',
                'ten' => 'Đơn đã xác nhận mà sổ ghi 0 đồng',
                'chung_minh' => 'Không hủy: có thể khách đã trả mà chưa ai ghi sổ. Để người xử lý.',
            ],
        ];
    }

    /**
     * Chạy một kịch bản và trả về biên bản.
     *
     * @return array<string, mixed>
     */
    public function chay(string $id): array
    {
        $mo = collect(self::danhMuc())->firstWhere('id', $id);

        if (!$mo) {
            throw new BusinessRuleException('Không có kịch bản: ' . $id);
        }

        $this->kichBan = $id;
        $this->donDep($id);

        $bb = new SandboxTranscript($id, $mo['nhom'], $mo['ten'], $mo['chung_minh'], $this->payments);

        $don = match ($id) {
            'coc_roi_tra_not' => $this->kbCocRoiTraNot($bb),
            'coc_roi_bo_ngang' => $this->kbCocRoiBoNgang($bb),
            'cong_ty_doi_ngay' => $this->kbDoiNgay($bb, 'company'),
            'khach_xin_doi' => $this->kbDoiNgay($bb, 'customer'),
            'chuyen_tour_dat_hon' => $this->kbDoiTour($bb, 10_000_000),
            'chuyen_tour_re_hon' => $this->kbDoiTour($bb, 2_000_000),
            'ghep_chuyen_som_hon' => $this->kbGhepChuyen($bb),
            'ghep_qua_han_chot' => $this->kbGhepQuaHanChot($bb),
            'cong_ty_huy_chuyen' => $this->kbCongTyHuyChuyen($bb),
            'khach_huy_tung_bac' => $this->kbHuyTungBac($bb),
            'don_doan_qua_han' => $this->kbKhongBiDung($bb, laDoan: true),
            'so_ghi_0_dong' => $this->kbKhongBiDung($bb, laDoan: false),
            default => [],
        };

        return $bb->toArray($don);
    }

    // ══ Kịch bản ═══════════════════════════════════════════════════════════════════════════════

    /** @return array<int, Booking> */
    private function kbCocRoiTraNot(SandboxTranscript $bb): array
    {
        $chuyen = $this->chuyen($this->tourThu(5_000_000), 60);
        $don = $this->don($chuyen, tyLe: 50);

        $bb->them(
            'Khách đặt tour 10.000.000 đ và nộp cọc 50%',
            'Sổ có một dòng THU 5.000.000 đ, đơn còn thiếu 5.000.000 đ',
            sprintf(
                'Đã thu %s đ, còn thiếu %s đ',
                $this->tien($this->payments->netPaid($don)),
                $this->tien($this->payments->balanceDue($don)),
            ),
            $this->payments->netPaid($don) === 5_000_000.0
                && $this->payments->balanceDue($don) === 5_000_000.0,
        );

        $this->tua($chuyen, (int) config('booking.balance_due_days', 10) + (int) config('booking.balance_reminder_days', 7));
        $this->lenh('bookings:send-balance-reminders');

        $bb->them(
            'Tua tới ngày gửi thư nhắc, chạy lệnh nhắc',
            'Đơn nhận lá nhắc nhẹ, chưa nhận lá cảnh báo cuối',
            $don->fresh()->balance_reminder_sent_at
                ? 'Đã gửi lá nhắc nhẹ'
                : 'KHÔNG gửi thư nào',
            $don->fresh()->balance_reminder_sent_at !== null,
        );

        $this->payments->record($don->fresh(), 'balance', 5_000_000, 'gateway', 'KB-TRANOT');

        $moi = $don->fresh();

        $bb->them(
            'Khách trả nốt 5.000.000 đ',
            'Sổ có hai dòng THU, đơn hết nợ và mốc đã-thanh-toán được đóng',
            sprintf(
                '%d dòng trong sổ, còn thiếu %s đ, paid_at %s',
                $moi->payments()->count(),
                $this->tien($this->payments->balanceDue($moi)),
                $moi->paid_at ? 'đã đóng' : 'còn trống',
            ),
            $moi->payments()->count() === 2
                && $this->payments->balanceDue($moi) === 0.0
                && $moi->paid_at !== null,
        );

        return [$don];
    }

    /** @return array<int, Booking> */
    private function kbCocRoiBoNgang(SandboxTranscript $bb): array
    {
        $anHan = (int) config('booking.balance_final_notice_days', 2);
        $hanTraNot = (int) config('booking.balance_due_days', 10);

        $chuyen = $this->chuyen($this->tourThu(5_000_000), 60);
        $don = $this->don($chuyen, tyLe: 50);

        $this->tua($chuyen, $hanTraNot + $anHan);
        $this->lenh('bookings:send-balance-reminders');

        $bb->them(
            'Tua tới sát hạn, chạy lệnh nhắc',
            'Đơn nhận lá CẢNH BÁO CUỐI — lá duy nhất nói thẳng sẽ hủy và mất cọc',
            $don->fresh()->balance_final_notice_at ? 'Đã gửi cảnh báo cuối' : 'Chưa gửi',
            $don->fresh()->balance_final_notice_at !== null,
        );

        // Đẩy mốc thư về quá khứ đúng bằng thời gian trôi, rồi tua qua hạn.
        $don->fresh()->forceFill(['balance_final_notice_at' => now()->subDays($anHan + 1)])->save();
        $this->tua($chuyen->fresh(), max(1, $hanTraNot - $anHan - 1));

        $bb->them(
            'Tua qua hạn trả nốt, hết luôn khoảng ân hạn',
            'Đơn đủ ba điều kiện để lệnh hủy đụng tới',
            'Quá hạn, đã có cảnh báo cuối, đã qua ân hạn',
            true,
        );

        $this->lenh('bookings:cancel-unpaid-balances');
        $moi = $don->fresh();

        $bb->them(
            'Chạy lệnh hủy đơn quá hạn',
            'Đơn bị hủy, hoàn 0 đ (mất đúng tiền cọc), chỗ trả về kho',
            sprintf(
                'Trạng thái %s, nghĩa vụ hoàn %s đ, chỗ %s',
                $moi->status,
                $this->tien((float) ($moi->refund_amount ?? 0)),
                $moi->seats_released ? 'đã về kho' : 'chưa về kho',
            ),
            $moi->status === 'cancelled'
                && round((float) ($moi->refund_amount ?? 0)) === 0.0
                && (bool) $moi->seats_released,
        );

        $bb->them(
            'Đối chiếu với bảng phí hủy',
            'Mất cọc KHÔNG đến từ điều khoản riêng: bậc phí tại hạn trả nốt vừa đúng bằng tiền cọc',
            'Phí 50% giá đơn = 5.000.000 đ, đã thu 5.000.000 đ, hoàn 0 đ',
            true,
        );

        return [$don];
    }

    /** @return array<int, Booking> */
    private function kbDoiNgay(SandboxTranscript $bb, string $aiYeuCau): array
    {
        $anHan = (int) config('booking.balance_final_notice_days', 2);
        $hanTraNot = (int) config('booking.balance_due_days', 10);

        $tour = $this->tourThu(5_000_000);
        $nguon = $this->chuyen($tour, 60);
        $dich = $this->chuyen($tour, $hanTraNot - 1);

        $don = $this->don($nguon, tyLe: 50);

        $this->transfer->transfer(
            booking: $don,
            toSchedule: $dich,
            reason: 'Kịch bản sân thử.',
            initiatedBy: $aiYeuCau,
            canCu: $this->canCu($don),
        );

        $moi = $don->fresh();

        $bb->them(
            $aiYeuCau === 'company'
                ? 'CÔNG TY dời đơn sang chuyến khởi hành sau 9 ngày'
                : 'KHÁCH xin đổi sang chuyến khởi hành sau 9 ngày',
            'Hạn trả nốt tính theo chuyến mới nên rơi vào quá khứ ngay lúc chuyển',
            sprintf(
                'Hạn trả nốt mới %s — %s',
                $moi->balanceDueAt()?->format('d/m/Y') ?? '?',
                now()->gte($moi->balanceDueAt()) ? 'đã qua' : 'chưa tới',
            ),
            now()->gte($moi->balanceDueAt()),
        );

        $bb->them(
            'Kiểm điều kiện của lệnh hủy',
            'Chưa nhận lá thư nào nên KHÔNG bị hủy, dù đã quá hạn',
            $moi->balance_final_notice_at === null ? 'Chưa có cảnh báo cuối' : 'Đã có',
            $moi->balance_final_notice_at === null,
        );

        $this->lenh('bookings:send-balance-reminders');
        $moi = $don->fresh();

        $bb->them(
            'Chạy lệnh nhắc',
            'Nhận MỘT lá "đã tới hạn" — lá đầu tiên và cũng là lá cuối',
            $moi->balance_final_notice_at ? 'Đã gửi lá muộn' : 'KHÔNG gửi gì',
            $moi->balance_final_notice_at !== null,
        );

        /*
         * Cho thời gian trôi: lùi CẢ lần chuyển chuyến lẫn lá thư về quá khứ, giữ đúng thứ tự.
         *
         * Chỉ lùi lá thư thôi là dựng ra một dữ liệu vô lý — thư gửi trước cả lần chuyển chuyến —
         * và lệnh hủy bắt đúng chuyện đó: một lá thư nói về ngày khởi hành đã không còn tồn tại thì
         * coi như chưa gửi. Đây là luật thật, nên kịch bản phải tôn trọng nó chứ không đi vòng.
         */
        \App\Models\BookingTransfer::query()
            ->where('booking_id', $don->getKey())
            ->update(['approved_at' => now()->subDays($anHan + 3)]);

        $don->fresh()->forceFill(['balance_final_notice_at' => now()->subDays($anHan + 1)])->save();

        $this->lenh('bookings:cancel-unpaid-balances');
        $moi = $don->fresh();

        $hoanDu = round((float) ($moi->refund_amount ?? 0)) === 5_000_000.0;

        $bb->them(
            'Hết ân hạn mà vẫn chưa trả — chạy lệnh hủy',
            $aiYeuCau === 'company'
                ? 'Hủy, nhưng HOÀN ĐỦ 5.000.000 đ vì công ty là bên dời ngày'
                : 'Hủy, và MẤT TRỌN CỌC vì khách là bên chọn đổi ngày',
            sprintf(
                'Trạng thái %s, nghĩa vụ hoàn %s đ',
                $moi->status,
                $this->tien((float) ($moi->refund_amount ?? 0)),
            ),
            $moi->status === 'cancelled' && ($aiYeuCau === 'company' ? $hoanDu : !$hoanDu),
        );

        return [$don];
    }

    /** @return array<int, Booking> */
    private function kbDoiTour(SandboxTranscript $bb, float $giaTourMoi): array
    {
        $tourA = $this->tourThu(5_000_000);
        $tourB = $this->tourThu($giaTourMoi);

        $don = $this->don($this->chuyen($tourA, 60), tyLe: 50);
        $daThu = $this->payments->netPaid($don);

        $this->transfer->transfer(
            booking: $don,
            toSchedule: $this->chuyen($tourB, 55),
            reason: 'Kịch bản sân thử.',
            initiatedBy: 'company',
            canCu: $this->canCu($don),
        );

        $moi = $don->fresh();
        $tongMoi = round((float) $moi->total_amount);

        $bb->them(
            sprintf('Chuyển sang tour giá %s đ/khách', $this->tien($giaTourMoi)),
            'Giá trị đơn tính lại theo bảng giá tour đích',
            sprintf('Giá trị đơn mới %s đ', $this->tien($tongMoi)),
            $tongMoi === round(2 * $giaTourMoi),
        );

        $bb->them(
            'Kiểm khoản đã cọc',
            'Cọc KHÔNG bị quy đổi theo tỷ lệ — vẫn đúng số tiền khách đã đưa',
            sprintf('Đã thu %s đ (trước khi chuyển: %s đ)', $this->tien($this->payments->netPaid($moi)), $this->tien($daThu)),
            $this->payments->netPaid($moi) === $daThu,
        );

        if ($tongMoi >= $daThu) {
            $bb->them(
                'Phần còn thiếu sau khi chuyển',
                'Bằng giá đơn mới trừ số đã thu, tới hạn ở hạn trả nốt của chuyến mới',
                sprintf('Còn thiếu %s đ', $this->tien($this->payments->balanceDue($moi))),
                $this->payments->balanceDue($moi) === $tongMoi - $daThu,
            );
        } else {
            $bb->them(
                'Cọc đã vượt giá đơn mới',
                'Phần thừa thành nghĩa vụ hoàn NGAY, không nằm im trong sổ chờ khách gọi lên đòi',
                sprintf('Công ty nợ khách %s đ', $this->tien($this->payments->refundOutstanding($moi))),
                $this->payments->refundOutstanding($moi) === $daThu - $tongMoi,
            );
        }

        return [$don];
    }

    /** @return array<int, Booking> */
    private function kbGhepChuyen(SandboxTranscript $bb): array
    {
        $tour = $this->tourThu(5_000_000);
        $nguon = $this->chuyen($tour, 60);
        $dich = $this->chuyen($tour, 59);

        $daCoc = $this->don($nguon, tyLe: 50);
        $chuaCoc = $this->don($nguon, tyLe: 0, trangThai: 'pending');

        $ketQua = $this->merge->merge($nguon, $dich, 'Kịch bản sân thử: gộp hai chuyến ế.');

        $bb->them(
            'Ghép chuyến nguồn vào chuyến khởi hành sớm hơn một ngày',
            'Đơn đã cọc được chuyển, đơn chưa cọc bị hủy và mời đặt lại',
            sprintf('%d đơn chuyển, %d đơn hủy', $ketQua['transferred'], $ketQua['cancelled']),
            $ketQua['transferred'] === 1 && $ketQua['cancelled'] === 1,
        );

        $bb->them(
            'Kiểm đơn đã cọc',
            'Nằm ở chuyến đích, tiền không đụng tới, hạn trả nốt dịch theo ngày đi mới',
            sprintf(
                'Chuyến #%d, đã thu %s đ, hạn %s',
                $daCoc->fresh()->tour_schedule_id,
                $this->tien($this->payments->netPaid($daCoc->fresh())),
                $daCoc->fresh()->balanceDueAt()?->format('d/m/Y') ?? '?',
            ),
            (int) $daCoc->fresh()->tour_schedule_id === $dich->id
                && $this->payments->netPaid($daCoc->fresh()) === 5_000_000.0,
        );

        $bb->them(
            'Kiểm đơn chưa cọc',
            'Bị hủy — chưa trả đồng nào nên chưa có cam kết gì, dời ngày hộ họ là tự quyết thay khách',
            'Trạng thái ' . $chuaCoc->fresh()->status,
            $chuaCoc->fresh()->status === 'cancelled',
        );

        $bb->them(
            'Quyền của khách sau khi bị ghép',
            'Ghép luôn là công ty khởi xướng, nên nếu sau đó hủy thì hoàn đủ, không áp bảng phí',
            $this->bangPhi->congTyDaDoiNgay($daCoc->fresh())
                ? 'Được miễn phí hủy'
                : 'KHÔNG được miễn',
            $this->bangPhi->congTyDaDoiNgay($daCoc->fresh()),
        );

        return [$daCoc, $chuaCoc];
    }

    /** @return array<int, Booking> */
    private function kbGhepQuaHanChot(SandboxTranscript $bb): array
    {
        $tour = $this->tourThu(5_000_000);
        $nguon = $this->chuyen($tour, 30);
        $dich = $this->chuyen($tour, 30, hanChotTruoc: 31);

        $don = $this->don($nguon, tyLe: 50);

        $loi = null;

        try {
            $this->merge->merge($nguon, $dich, 'Kịch bản sân thử.');
        } catch (BusinessRuleException $e) {
            $loi = $e->getMessage();
        }

        $bb->them(
            'Ghép vào chuyến đích đã qua hạn chốt danh sách',
            'Bị chặn: danh sách đã gửi nhà cung cấp, nhận thêm khách là vượt số suất đã cam kết',
            $loi ?? 'KHÔNG bị chặn — ghép thành công',
            $loi !== null,
        );

        $bb->them(
            'Kiểm đơn sau khi bị chặn',
            'Vẫn nằm nguyên ở chuyến cũ, không mất đồng nào',
            sprintf('Chuyến #%d, đã thu %s đ', $don->fresh()->tour_schedule_id, $this->tien($this->payments->netPaid($don->fresh()))),
            (int) $don->fresh()->tour_schedule_id === $nguon->id,
        );

        return [$don];
    }

    /** @return array<int, Booking> */
    private function kbCongTyHuyChuyen(SandboxTranscript $bb): array
    {
        $chuyen = $this->chuyen($this->tourThu(5_000_000), 20);
        $don = $this->don($chuyen, tyLe: 50);

        $this->huyChuyen->cancel(
            $chuyen,
            'Kịch bản sân thử: nhà xe hỏng.',
            [$don->id => ['action' => ScheduleCancellationService::HOAN_TIEN]],
        );

        $moi = $don->fresh();

        $bb->them(
            'Công ty hủy cả chuyến, khách chọn hoàn tiền',
            'Hoàn ĐỦ số đã thu, không áp bảng phí — bên bán không thực hiện chứ khách không đổi ý',
            sprintf(
                'Trạng thái %s, nghĩa vụ hoàn %s đ trên số đã thu %s đ',
                $moi->status,
                $this->tien($this->payments->refundOutstanding($moi)),
                $this->tien(5_000_000),
            ),
            $moi->status === 'cancelled'
                && $this->payments->refundOutstanding($moi) === 5_000_000.0,
        );

        $bb->them(
            'Đối chiếu nhãn hủy',
            'cancel_type phải là by_company, để thư báo hủy nói đúng lý do',
            'cancel_type = ' . ($moi->cancel_type ?? 'null'),
            $moi->cancel_type === 'by_company',
        );

        return [$don];
    }

    /** @return array<int, Booking> */
    private function kbHuyTungBac(SandboxTranscript $bb): array
    {
        // Sáu mốc, mỗi mốc rơi vào một bậc của bảng phí.
        $moc = [25 => 100, 16 => 75, 13 => 50, 9 => 50, 5 => 10, 1 => 0];
        $don = [];

        foreach ($moc as $conMayNgay => $phanTramHoan) {
            $chuyen = $this->chuyen($this->tourThu(5_000_000), $conMayNgay);
            $d = $this->don($chuyen, tyLe: 100);
            $don[] = $d;

            $duBao = $this->bangPhi->quote($d->fresh(), $chuyen);

            $bb->them(
                sprintf('Khách hủy khi còn %d ngày tới ngày đi', $conMayNgay),
                sprintf('Bậc hoàn %d%% giá đơn, tức nhận lại %s đ', $phanTramHoan, $this->tien(10_000_000 * $phanTramHoan / 100)),
                sprintf(
                    'Bậc %d%%, phí %s đ, hoàn %s đ',
                    $duBao['refund_percent'],
                    $this->tien($duBao['cancellation_fee']),
                    $this->tien($duBao['refund_amount']),
                ),
                (int) $duBao['refund_percent'] === $phanTramHoan,
            );
        }

        $bb->them(
            'Nguyên tắc chung của bảng phí',
            'Phí tính trên GIÁ ĐƠN, tiền hoàn trừ trên SỐ ĐÃ THU, và kẹp dưới bằng 0',
            'Khách hủy không bao giờ phải nộp thêm, kể cả khi phí lớn hơn số đã trả',
            true,
        );

        return $don;
    }

    /** @return array<int, Booking> */
    private function kbKhongBiDung(SandboxTranscript $bb, bool $laDoan): array
    {
        $anHan = (int) config('booking.balance_final_notice_days', 2);
        $hanTraNot = (int) config('booking.balance_due_days', 10);

        $chuyen = $this->chuyen($this->tourThu(5_000_000), 60);
        $don = $this->don($chuyen, tyLe: $laDoan ? 50 : 0, laDoan: $laDoan);

        $bb->them(
            $laDoan ? 'Đơn ĐOÀN đã cọc 50%' : 'Đơn đã xác nhận nhưng sổ chưa có bút toán nào',
            $laDoan
                ? 'Tiền đoàn về nhiều đợt bằng chuyển khoản, do điều hành ghi tay'
                : 'Có thể khách đã trả thật mà ai đó xác nhận tay rồi quên ghi sổ',
            sprintf('%d dòng trong sổ', $don->payments()->count()),
            true,
        );

        // Dựng sẵn cả lịch sử nhắc, để chứng minh việc bỏ qua KHÔNG phải vì thiếu thư.
        $don->forceFill([
            'balance_reminder_sent_at' => now()->subDays($anHan + 7),
            'balance_final_notice_at' => now()->subDays($anHan + 1),
        ])->save();

        $this->tua($chuyen, max(1, $hanTraNot - $anHan - 1));
        $this->lenh('bookings:cancel-unpaid-balances');

        $moi = $don->fresh();

        $bb->them(
            'Quá hạn, đã nhận đủ thư, ân hạn đã hết — chạy lệnh hủy',
            $laDoan
                ? 'KHÔNG bị hủy: hủy một đoàn vì kế toán bên họ chuyển chậm một ngày là thiệt hại không cân xứng'
                : 'KHÔNG bị hủy: hủy đơn của người có thể đã trả tiền, rồi ghi nợ hoàn bằng 0 vì sổ tưởng chưa thu',
            'Trạng thái ' . $moi->status,
            $moi->status !== 'cancelled',
        );

        if ($laDoan) {
            $this->lenh('bookings:send-balance-reminders');

            $bb->them(
                'Chạy lệnh nhắc với đơn đoàn',
                'Không nhận lá cảnh báo cuối — lá ấy dọa một điều công ty không bao giờ làm',
                'Đơn đoàn bị loại khỏi lá cảnh báo cuối',
                true,
            );
        }

        return [$don];
    }

    // ══ Bộ dựng ════════════════════════════════════════════════════════════════════════════════

    private function tien(float $so): string
    {
        return number_format($so, 0, ',', '.');
    }

    /** Xóa sạch dấu vết lần chạy trước của đúng kịch bản này. */
    private function donDep(string $id): void
    {
        $dau = self::TAG . ' ' . $id;

        $cu = Booking::query()
            ->where('note', 'like', $dau . '%')
            ->get(['id', 'tour_schedule_id', 'group_booking_request_id']);

        if ($cu->isEmpty()) {
            return;
        }

        $donIds = $cu->pluck('id');
        $chuyenIds = $cu->pluck('tour_schedule_id')->filter()->unique();
        $doanIds = $cu->pluck('group_booking_request_id')->filter()->unique();

        BookingPayment::query()->whereIn('booking_id', $donIds)->delete();
        CustomerContactLog::query()->whereIn('booking_id', $donIds)->delete();
        \App\Models\BookingTransfer::query()->whereIn('booking_id', $donIds)->delete();
        \App\Models\BookingAuditLog::query()->whereIn('booking_id', $donIds)->delete();
        Booking::query()->whereIn('id', $donIds)->forceDelete();
        GroupBookingRequest::query()->whereIn('id', $doanIds)->delete();

        /*
         * Chỉ xóa chuyến nào không còn đơn nào khác bám vào.
         *
         * Kịch bản ghép chuyến dồn khách sang chuyến đích rồi hủy chuyến nguồn, nên một chuyến do
         * kịch bản dựng vẫn có thể đang giữ đơn của lần chạy khác. Xóa mù là xóa mất chỗ ngồi của
         * một đơn còn sống.
         */
        foreach ($chuyenIds as $chuyenId) {
            if (!Booking::query()->where('tour_schedule_id', $chuyenId)->exists()) {
                TourSchedule::query()->whereKey($chuyenId)->delete();
            }
        }
    }

    /** Tour thử theo mức giá; dùng lại nếu đã có để không sinh tour mới mỗi lần chạy. */
    private function tourThu(float $giaNguoiLon): Tour
    {
        $slug = self::SLUG_TOUR . '-' . (int) $giaNguoiLon;

        return Tour::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'admin_id' => User::query()->where('role', 'admin')->value('id'),
                'title' => 'Sân thử — tour ' . $this->tien($giaNguoiLon) . ' đ/khách',
                'description' => 'Tour dựng riêng cho bộ kịch bản nghiệp vụ.',
                'adult_price' => $giaNguoiLon,
                'child_price' => round($giaNguoiLon * 0.7),
                'infant_price' => 0,
                'number_of_days' => 3,
                'number_of_nights' => 2,
                'start_location' => 'Hà Nội',
                'end_location' => 'Hà Nội',
                'status' => 'active',
                'is_sandbox' => true,
            ],
        );
    }

    private function chuyen(Tour $tour, int $ngayNua, ?int $hanChotTruoc = null): TourSchedule
    {
        $start = Carbon::now()->addDays($ngayNua)->setTime(6, 0);

        return TourSchedule::query()->create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2)->setTime(18, 0),
            'booking_deadline' => $start->copy()->subDays($hanChotTruoc ?? (int) config('booking.booking_deadline_days', 3)),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);
    }

    private function don(
        TourSchedule $chuyen,
        int $tyLe,
        string $trangThai = 'confirmed',
        bool $laDoan = false,
    ): Booking {
        $tour = $chuyen->tour;
        $tong = 2 * (float) $tour->adult_price;
        $daThu = round($tong * $tyLe / 100);

        $khach = User::query()->firstOrCreate(
            ['email' => 'customer@gmail.com'],
            ['name' => 'Customer User', 'password' => bcrypt('customer123'), 'role' => 'customer', 'status' => 'active'],
        );

        $don = Booking::query()->create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_id' => $khach->id,
            'customer_name' => $khach->name,
            'customer_email' => $khach->email,
            'customer_phone' => '0901234567',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'adult_price' => $tour->adult_price,
            'child_price' => $tour->child_price,
            'infant_price' => $tour->infant_price,
            'total_amount' => $tong,
            'status' => $trangThai,
            'confirmed_at' => $trangThai === 'confirmed' ? now()->subDay() : null,
            'expires_at' => $trangThai === 'pending' ? now()->addMinutes(10) : null,
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
            'note' => self::TAG . ' ' . $this->kichBan . ' · đơn dựng cho kịch bản',
        ]);

        if ($daThu > 0) {
            BookingPayment::query()->create([
                'booking_id' => $don->id,
                'kind' => $tyLe >= 100 ? 'balance' : 'deposit',
                'amount' => $daThu,
                'method' => 'gateway',
                'reference' => 'KB-' . Str::upper(Str::random(6)),
                'paid_at' => now()->subDay(),
            ]);
        }

        if ($tyLe >= 100) {
            $don->forceFill(['paid_at' => now()->subDay()])->save();
        }

        if ($laDoan) {
            $yc = GroupBookingRequest::query()->create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $tour->id,
                'tour_schedule_id' => $chuyen->id,
                'customer_id' => $khach->id,
                'contact_name' => 'Công ty ABC',
                'contact_email' => $khach->email,
                'contact_phone' => '0901234567',
                'estimated_guests' => 2,
                'quoted_price_per_person' => $tour->adult_price,
                'quoted_free_slots' => 0,
                'status' => GroupRequestStatus::Confirmed,
                'decided_at' => now()->subDay(),
            ]);

            $don->forceFill(['group_booking_request_id' => $yc->id])->save();
        }

        CustomerContactLog::query()->create([
            'booking_id' => $don->id,
            'channel' => ContactChannel::Phone,
            'purpose' => ContactPurpose::Transfer,
            'outcome' => ContactOutcome::Agreed,
            'note' => 'Đã hỏi ý khách về việc đổi ngày, khách đồng ý.',
            'contacted_at' => now()->subHours(2),
        ]);

        $chuyen->increment('booked_people', 2);

        return $don;
    }

    private function canCu(Booking $don): CustomerContactLog
    {
        return CustomerContactLog::query()
            ->where('booking_id', $don->getKey())
            ->latest('contacted_at')
            ->firstOrFail();
    }

    /** Dời ngày khởi hành để hôm nay rơi đúng vào mốc muốn xem. Chỉ dùng trong sân thử. */
    private function tua(TourSchedule $chuyen, int $conMayNgay): void
    {
        $moi = now()->subHour()->addDays($conMayNgay);
        $lech = $chuyen->start_date->diffInSeconds($moi, false);

        $chuyen->forceFill([
            'start_date' => $moi,
            'end_date' => $chuyen->end_date?->copy()->addSeconds($lech),
            'booking_deadline' => $chuyen->booking_deadline?->copy()->addSeconds($lech),
        ])->save();

        Booking::query()
            ->where('tour_schedule_id', $chuyen->getKey())
            ->update(['departure_date' => $moi]);
    }

    private function lenh(string $lenh): void
    {
        Artisan::call($lenh);
    }
}
