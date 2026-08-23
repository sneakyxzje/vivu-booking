<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\CostBearer;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\SurchargeKind;
use App\Enums\SurchargeStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingSurcharge;
use App\Models\ScheduleIncident;
use App\Models\TourSchedule;
use App\Models\User;
use App\Support\GioVietNam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * O - Sự cố dọc đường và chi phí phát sinh.
 *
 * Tình huống hội đồng nêu: đoàn đi tàu ra biển gặp bão, phải đổi sang chương trình khác đắt hơn.
 *
 * **Luật cốt lõi của nhóm này là tách quyền, không phải tính tiền.**
 *
 *   - Hướng dẫn viên **báo cáo** những gì nhìn thấy, kèm ảnh hiện trường. Không nhập số tiền,
 *     không quyết ai chịu, không tạo khoản thu.
 *   - Điều hành **quyết** phương án thay thế và phân bổ chi phí cho từng đơn.
 *   - Khoản chưa duyệt thì chưa có hiệu lực với khách.
 *
 * Lý do không phải hình thức. Người ở hiện trường đang chịu áp lực của khách đang mệt và đang
 * bực; để họ vừa quyết mức thu vừa cầm tiền là đặt họ vào chỗ không ai nên bị đặt vào. Đây cũng
 * là nguyên tắc kiểm soát nội bộ đã áp cho luồng hoàn tiền: tách người báo, người duyệt và người
 * thu.
 *
 * Điều nhóm này cố ý KHÔNG làm: tự tính số tiền hoàn khi chương trình bị cắt. Muốn tính đúng thì
 * phải có giá vốn từng dịch vụ, mà tầng cung ứng nằm ngoài phạm vi đồ án. Nên con số do điều hành
 * nhập, kèm diễn giải - hệ thống giữ vết và bắt buộc có lý do, chứ không giả vờ tính được.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.
 */
class IncidentService
{
    /** Báo sau mốc này so với lúc xảy ra thì đánh dấu là ghi bù. */
    private const GIO_COI_LA_GHI_BU = 6;

    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
        private readonly BookingAuditLogger $auditLogger,
    ) {
    }

    /**
     * Hướng dẫn viên báo cáo sự cố.
     *
     * @param  array{type: string, severity: string, occurred_at: string, description: string, tour_itinerary_id?: int|null}  $duLieu
     */
    public function report(TourSchedule $schedule, array $duLieu, User $guide): ScheduleIncident
    {
        $this->assertGuidePhuTrach($schedule, $guide);
        $this->assertChuyenDaKhoiHanh($schedule);

        $xayRa = Carbon::parse($duLieu['occurred_at']);

        /*
         * So với giờ Việt Nam, không phải với now().
         *
         * Ứng dụng chạy UTC còn ô nhập trên trình duyệt gửi lên giờ treo tường Việt Nam, nên
         * so thẳng với now() thì mọi thời điểm trong 7 tiếng vừa qua đều bị coi là tương lai -
         * tức gần như cả ngày làm việc. Xem GioVietNam.
         */
        $bayGio = GioVietNam::bayGio();

        if ($xayRa->gt($bayGio)) {
            throw new BusinessRuleException('Thời điểm xảy ra sự cố không thể nằm ở tương lai.');
        }

        // Mất sóng giữa biển hoặc trên núi là chuyện thường, nên báo muộn được chấp nhận. Nhưng
        // phải đánh dấu, vì đối chiếu về sau cần biết con số này ghi tại chỗ hay nhớ lại.
        $ghiBu = $bayGio->diffInHours($xayRa, true) > self::GIO_COI_LA_GHI_BU;

        return ScheduleIncident::query()->create([
            'tour_schedule_id' => $schedule->getKey(),
            'tour_itinerary_id' => $duLieu['tour_itinerary_id'] ?? null,
            'type' => IncidentType::from($duLieu['type']),
            'severity' => IncidentSeverity::from($duLieu['severity']),
            'status' => IncidentStatus::Reported,
            'occurred_at' => $xayRa,
            'reported_late' => $ghiBu,
            'description' => trim($duLieu['description']),
            'reported_by' => $guide->getKey(),
        ]);
    }

    /**
     * Điều hành quyết phương án và phân bổ chi phí.
     *
     * Các khoản tiền được tạo cùng lúc với phương án chứ không tách rời: một phương án không nói
     * rõ ai trả bao nhiêu thì chưa phải phương án.
     *
     * `who_bears` trên phương án chỉ là **mặc định gợi ý**. Người chịu thật nằm trên từng khoản,
     * vì một cơn bão sinh ra cả khoản hãng chịu lẫn khoản khách chịu - xem migration
     * 2026_08_23_000001 và khối chú thích của CostBearer.
     *
     * @param  array{resolution: string, cost_delta?: float|null, who_bears?: string|null}  $phuongAn
     * @param  array<int, array{booking_id: int, kind: string, amount: float, reason: string, who_bears?: string|null}>  $khoanTien
     */
    public function resolve(
        ScheduleIncident $incident,
        array $phuongAn,
        array $khoanTien,
        User $actor,
    ): ScheduleIncident {
        return DB::transaction(function () use ($incident, $phuongAn, $khoanTien, $actor) {
            $khoa = ScheduleIncident::query()->whereKey($incident->getKey())->lockForUpdate()->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy báo cáo sự cố.', 404);
            }

            $bearer = isset($phuongAn['who_bears']) ? CostBearer::from($phuongAn['who_bears']) : null;

            $this->assertKhoanTienHopLe($khoa, $khoanTien, $bearer);

            $khoa->forceFill([
                'resolution' => trim($phuongAn['resolution']),
                'cost_delta' => $phuongAn['cost_delta'] ?? null,
                'who_bears' => $bearer,
                'status' => IncidentStatus::Reviewed,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            // Quyết lại thì thay toàn bộ các khoản đang chờ duyệt. Khoản đã duyệt hoặc đã thu thì
            // giữ nguyên: đó là thứ đã nói với khách rồi, sửa lặng lẽ là sai.
            BookingSurcharge::query()
                ->where('schedule_incident_id', $khoa->getKey())
                ->where('status', SurchargeStatus::Pending->value)
                ->delete();

            foreach ($khoanTien as $khoan) {
                BookingSurcharge::query()->create([
                    'booking_id' => (int) $khoan['booking_id'],
                    'schedule_incident_id' => $khoa->getKey(),
                    'kind' => SurchargeKind::from($khoan['kind']),
                    // Dòng nào không nói rõ thì lấy mặc định của phương án. Không ép phải điền:
                    // gợi ý đúng cho phần lớn khoản, và bắt gõ lại cùng một giá trị năm lần là
                    // cách chắc chắn để người ta bấm bừa cho xong.
                    'who_bears' => isset($khoan['who_bears'])
                        ? CostBearer::from($khoan['who_bears'])
                        : $bearer,
                    'amount' => round((float) $khoan['amount'], 2),
                    'reason' => trim($khoan['reason']),
                    // Tạo ra ở trạng thái chờ duyệt, kể cả khi chính điều hành vừa nhập: duyệt là
                    // một thao tác riêng, để bước nói với khách và bước chốt số không lẫn vào nhau.
                    'status' => SurchargeStatus::Pending,
                ]);
            }

            return $khoa->fresh(['surcharges']);
        });
    }

    /** Duyệt một khoản: từ lúc này nó mới có hiệu lực với khách. */
    public function approveSurcharge(BookingSurcharge $surcharge, User $actor): BookingSurcharge
    {
        if ($surcharge->status !== SurchargeStatus::Pending) {
            throw new BusinessRuleException(sprintf(
                'Khoản này đang ở trạng thái "%s" nên không duyệt lại được.',
                $surcharge->status->label(),
            ));
        }

        $surcharge->forceFill([
            'status' => SurchargeStatus::Approved,
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
        ])->save();

        return $surcharge->fresh();
    }

    /**
     * Miễn một khoản khách phải trả.
     *
     * Khách không đồng ý thì điều hành quyết miễn hoặc xử lý theo hợp đồng - nhưng **không bao
     * giờ bỏ khách lại**. Xem tài liệu 04 mục 6.4.
     */
    public function waiveSurcharge(BookingSurcharge $surcharge, string $lyDo, User $actor): BookingSurcharge
    {
        if ($surcharge->status === SurchargeStatus::Paid) {
            throw new BusinessRuleException('Khoản đã thu rồi thì không miễn được, phải xử lý bằng hoàn tiền.');
        }

        $surcharge->forceFill([
            'status' => SurchargeStatus::Waived,
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
            'reason' => $surcharge->reason . ' — Đã miễn: ' . trim($lyDo),
        ])->save();

        return $surcharge->fresh();
    }

    /** Ghi nhận khách đã đồng ý với khoản phải trả. */
    public function recordConsent(BookingSurcharge $surcharge, ?string $ghiChu = null): BookingSurcharge
    {
        if ($surcharge->kind !== SurchargeKind::Surcharge) {
            throw new BusinessRuleException('Chỉ khoản khách phải trả mới cần khách xác nhận.');
        }

        if ($surcharge->status === SurchargeStatus::Waived) {
            throw new BusinessRuleException('Khoản đã miễn thì không cần khách xác nhận nữa.');
        }

        $surcharge->forceFill([
            'customer_consent_at' => now(),
            'consent_note' => $ghiChu ? trim($ghiChu) : null,
        ])->save();

        return $surcharge->fresh();
    }

    /**
     * Tất toán một khoản: ghi tiền vào sổ giao dịch và đóng khoản lại.
     *
     * Đây là bước còn thiếu của cả nhóm O. Trước đây khoản duyệt xong thì nằm mãi ở "đã duyệt" -
     * `SurchargeStatus::Paid` có khai báo nhưng không dòng mã nào đặt giá trị ấy, và sổ giao dịch
     * không hề biết đến số tiền này. Tức là hệ thống lập một khoản phải trả rồi bỏ đó.
     *
     * Đi cả hai chiều, vì tiền của sự cố đi cả hai chiều:
     *
     *   - `SurchargeKind::Surcharge` - khách trả thêm, ghi một dòng thu.
     *   - `SurchargeKind::Refund`    - hãng trả lại khách, ghi một dòng chi.
     *
     * Dùng loại bút toán riêng chứ không mượn `balance`/`refund` của giá tour: xem khối chú thích
     * ở đầu `BookingPayment`.
     *
     * Thu tiền mặt dọc đường là chuyện bình thường - hướng dẫn viên hoặc điều hành thu rồi ghi
     * lại. Nên đây là thao tác **ghi nhận**, không phải một cổng thanh toán.
     */
    public function settleSurcharge(
        BookingSurcharge $surcharge,
        User $actor,
        ?string $method = null,
        ?string $reference = null,
        ?string $note = null,
    ): BookingSurcharge {
        return DB::transaction(function () use ($surcharge, $actor, $method, $reference, $note) {
            /*
             * Khóa rồi đọc lại rồi mới kiểm. Hai người cùng bấm "đã thu" trên hai máy thì người
             * sau phải thấy trạng thái đã đổi và bị từ chối - nếu chỉ kiểm trên bản đọc trước khi
             * khóa thì cả hai cùng qua và sổ có hai dòng thu cho một lần thu.
             */
            $khoa = BookingSurcharge::query()
                ->whereKey($surcharge->getKey())
                ->lockForUpdate()
                ->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy khoản này.', 404);
            }

            if ($khoa->status === SurchargeStatus::Paid) {
                throw new BusinessRuleException('Khoản này đã tất toán rồi.');
            }

            if ($khoa->status !== SurchargeStatus::Approved) {
                throw new BusinessRuleException(sprintf(
                    'Khoản đang ở trạng thái "%s". Phải duyệt trước khi thu hoặc hoàn.',
                    $khoa->status->label(),
                ));
            }

            /*
             * Khách phải biết mình đang trả cái gì trước khi trả. Đây không phải thủ tục giấy tờ:
             * người ở hiện trường đang mệt và đang bực, và một khoản thu không ai giải thích là
             * thứ sinh ra khiếu nại. Khoản hoàn thì không cần - không ai phản đối việc được trả
             * lại tiền.
             */
            if ($khoa->kind === SurchargeKind::Surcharge && $khoa->customer_consent_at === null) {
                throw new BusinessRuleException(
                    'Chưa ghi nhận khách đồng ý với khoản này. Phải nói với khách và ghi lại '
                        . 'trước khi thu tiền.',
                );
            }

            $payment = BookingPayment::query()->create([
                'booking_id' => $khoa->booking_id,
                'booking_surcharge_id' => $khoa->getKey(),
                'kind' => $khoa->kind === SurchargeKind::Surcharge
                    ? BookingPayment::PHU_THU
                    : BookingPayment::PHU_THU_HOAN,
                'amount' => round((float) $khoa->amount, 2),
                'method' => $method,
                'reference' => $reference,
                // Sổ phải tự đọc được mà không cần mở bảng khác: diễn giải của khoản đi theo dòng.
                'note' => $note !== null && trim($note) !== ''
                    ? trim($note)
                    : $khoa->reason,
                'paid_at' => now(),
                'recorded_by' => $actor->getKey(),
            ]);

            $khoa->forceFill(['status' => SurchargeStatus::Paid])->save();

            $this->auditLogger->log(
                $khoa->booking,
                BookingAuditAction::PaymentRecorded,
                null,
                [
                    'surcharge_id' => $khoa->getKey(),
                    'kind' => $payment->kind,
                    'amount' => round((float) $khoa->amount),
                    'method' => $method,
                ],
                $khoa->reason,
            );

            return $khoa->fresh(['booking']);
        });
    }

    /**
     * Hướng dẫn viên này có phụ trách chuyến đó không.
     *
     * Một chuyến có thể có nhiều hướng dẫn viên; ai trong số đó cũng báo cáo được.
     */
    private function assertGuidePhuTrach(TourSchedule $schedule, User $guide): void
    {
        if (!$schedule->hasGuide((int) $guide->getKey())) {
            throw new BusinessRuleException('Bạn không phụ trách chuyến đi này nên không báo cáo sự cố được.');
        }
    }

    /**
     * Chuyến phải đã lên đường.
     *
     * Sự cố dọc đường theo định nghĩa là chuyện xảy ra khi đoàn đang đi. Chuyến chưa khởi hành mà
     * có vấn đề thì đó là chuyện của luồng hủy chuyến hoặc đổi lịch, không phải luồng này.
     */
    private function assertChuyenDaKhoiHanh(TourSchedule $schedule): void
    {
        $trangThai = $this->lifecycle->effectiveStatus($schedule);

        if (!$trangThai->isRunning() && $trangThai !== \App\Enums\ScheduleStatus::Completed) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đang ở trạng thái "%s". Sự cố dọc đường chỉ ghi được khi đoàn đã lên đường; '
                    . 'chuyến chưa đi mà có vấn đề thì xử lý theo luồng hủy chuyến hoặc dời lịch.',
                $trangThai->label(),
            ));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $khoanTien
     */
    private function assertKhoanTienHopLe(ScheduleIncident $incident, array $khoanTien, ?CostBearer $bearer): void
    {
        if ($khoanTien === []) {
            return;
        }

        $donCuaChuyen = Booking::query()
            ->where('tour_schedule_id', $incident->tour_schedule_id)
            ->whereIn('status', array_merge(
                BookingStatus::paidValues(),
                [BookingStatus::Completed->value, BookingStatus::NoShow->value],
            ))
            ->pluck('id')
            ->all();

        foreach ($khoanTien as $khoan) {
            $bookingId = (int) ($khoan['booking_id'] ?? 0);

            if (!in_array($bookingId, $donCuaChuyen, true)) {
                throw new BusinessRuleException(sprintf(
                    'Đơn BK-%d không thuộc chuyến gặp sự cố này.',
                    $bookingId,
                ));
            }

            if ((float) ($khoan['amount'] ?? 0) <= 0) {
                throw new BusinessRuleException('Số tiền của mỗi khoản phải lớn hơn 0.');
            }

            if (trim((string) ($khoan['reason'] ?? '')) === '') {
                throw new BusinessRuleException(
                    'Mỗi khoản phải có diễn giải, vì khách sẽ đọc đúng dòng này khi được yêu cầu trả thêm.',
                );
            }

            /*
             * Ghi "hãng chịu" mà vẫn thu tiền khách là mâu thuẫn ngay trong một dòng, và là loại
             * mâu thuẫn không ai phát hiện cho tới lúc khách khiếu nại.
             *
             * Kiểm theo người chịu CỦA DÒNG NÀY, lùi về mặc định của phương án khi dòng để trống.
             * Trước đây chỉ có một giá trị cho cả sự cố nên phép kiểm này chặt tay: đặt "hãng
             * chịu" cho cơn bão là không lập nổi khoản khách trả cho đêm phòng ở thêm, dù đêm
             * phòng ấy đúng là khách chịu.
             */
            $cuaDong = isset($khoan['who_bears'])
                ? CostBearer::from($khoan['who_bears'])
                : $bearer;

            if (
                ($khoan['kind'] ?? null) === SurchargeKind::Surcharge->value
                && $cuaDong !== null
                && !$cuaDong->khachPhaiTra()
            ) {
                throw new BusinessRuleException(sprintf(
                    'Khoản "%s" ghi người chịu là "%s" nên không lập được khoản thu thêm của khách.',
                    trim((string) ($khoan['reason'] ?? '')),
                    $cuaDong->label(),
                ));
            }
        }
    }
}
