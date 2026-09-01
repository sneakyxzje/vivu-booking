<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dời hạn chốt danh sách của một chuyến khởi hành.
 *
 * Hạn chốt là ngày công ty gửi danh sách khách cho nhà cung cấp và chốt số phòng, số ghế, số
 * suất ăn. Nó là một cái vạch trên trục thời gian, và bên trái vạch khác bên phải vạch ở năm
 * điểm: bán chỗ mới, sửa tên hành khách, chuyển chuyến, ghép chuyến, và chỗ có quay về kho khi
 * khách hủy hay không.
 *
 * Dời hạn chốt tức là kéo cái vạch ấy. Vì thế mọi đường ghi đều phải đi qua service này, để
 * luật kiểm tra và việc ghi nhật ký nằm ở đúng một chỗ - form sửa tour và endpoint sửa nhanh
 * không được phép có hai hành vi khác nhau.
 *
 * Điều service này cố ý KHÔNG làm: tính lại các đơn đã xử lý. Kết quả của mỗi lần hủy đã được
 * ghi cứng vào đơn tại thời điểm hủy (cột seats_released), không phải phép tính chạy lại mỗi
 * lần mở màn hình. Kéo vạch chỉ có hiệu lực từ lúc kéo trở đi.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md.
 */
class ScheduleDeadlineService
{
    /**
     * Độ dài tối thiểu của lý do dời hạn chốt.
     *
     * Không phải con số thần thánh gì: nó chỉ chặn "ok", "." và dấu cách. Câu hỏi mà nhật ký phải
     * trả lời được là *vì sao mốc bị dời*, và một ký tự thì không trả lời được câu nào.
     */
    private const LY_DO_TOI_THIEU = 10;

    public function __construct(
        private readonly ScheduleAuditLogger $auditLogger,
    ) {
    }

    /**
     * Tác động của hạn chốt mới, tính trước khi lưu.
     *
     * Điều hành phải thấy hậu quả trước khi bấm chứ không phải bấm rồi mới biết - cùng cách làm
     * với xem trước hủy đơn và xem trước ghép chuyến.
     *
     * @return array<string, mixed>
     */
    public function impact(TourSchedule $schedule, ?Carbon $moi): array
    {
        $hienTai = $schedule->booking_deadline;
        $hieuLucCu = $hienTai ?? $schedule->defaultBookingDeadline();
        $hieuLucMoi = $moi ?? $schedule->defaultBookingDeadline();

        $quaHanTruoc = $hieuLucCu !== null && now()->gte($hieuLucCu);
        $quaHanSau = $hieuLucMoi !== null && now()->gte($hieuLucMoi);

        $huong = $this->huongDoi($hieuLucCu, $hieuLucMoi);

        $trongDanhSach = $this->demKhach($schedule, BookingStatus::manifestValues());
        $choThanhToan = $this->demKhach($schedule, [BookingStatus::Pending->value]);

        $gheChet = Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->withHeldSeats()
            ->get(['id', 'guests']);

        $canMoBanTay = $huong === 'later'
            && !$quaHanSau
            && $schedule->status === ScheduleStatus::Closed
            && $schedule->booked_people < $schedule->max_people;

        $canTro = $this->lyDoChan($schedule, $moi);

        return [
            'current_deadline' => $hienTai,
            'new_deadline' => $moi,
            'effective_current_deadline' => $hieuLucCu,
            'effective_new_deadline' => $hieuLucMoi,
            'direction' => $huong,
            'currently_past' => $quaHanTruoc,
            'will_be_past' => $quaHanSau,
            'manifest_bookings' => $trongDanhSach['bookings'],
            'manifest_guests' => $trongDanhSach['guests'],
            'pending_bookings' => $choThanhToan['bookings'],
            'held_seat_bookings' => $gheChet->count(),
            'held_seats' => (int) $gheChet->sum('guests'),
            'needs_manual_reopen' => $canMoBanTay,
            'can_change' => $canTro === null,
            'blocked_reason' => $canTro,
            'warnings' => $this->canhBao(
                $huong,
                $quaHanSau,
                $canMoBanTay,
                $trongDanhSach['bookings'],
                $gheChet->count(),
                (int) $gheChet->sum('guests'),
            ),
        ];
    }

    /**
     * Ghi hạn chốt mới.
     *
     * Khóa dòng rồi đọc lại trước khi kiểm tra: hai người cùng sửa một chuyến thì người sau phải
     * thấy giá trị người trước vừa ghi, không phải giá trị lúc họ mở màn hình.
     *
     * `$lyDo` không có giá trị mặc định: mọi nơi gọi phải nghĩ tới nó. Lý do rỗng chỉ được chấp
     * nhận khi hạn chốt không thực sự đổi, xem phần kiểm bên dưới.
     */
    public function change(
        TourSchedule $schedule,
        ?Carbon $moi,
        ?string $lyDo,
        ?User $actor = null,
    ): TourSchedule {
        return DB::transaction(function () use ($schedule, $moi, $lyDo, $actor) {
            $khoa = TourSchedule::query()
                ->whereKey($schedule->getKey())
                ->lockForUpdate()
                ->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy chuyến khởi hành.');
            }

            $canTro = $this->lyDoChan($khoa, $moi);

            if ($canTro !== null) {
                throw new BusinessRuleException($canTro);
            }

            $cu = $khoa->booking_deadline;

            // Không đổi gì thì không ghi một dòng nhật ký rỗng. Form sửa tour gửi lại toàn bộ
            // danh sách chuyến mỗi lần lưu, nên phần lớn lần gọi tới đây là không có thay đổi.
            if ($this->bangNhau($cu, $moi)) {
                return $khoa;
            }

            /*
             * Lý do bắt buộc, và bắt buộc ở đây chứ không ở luật validate của controller.
             *
             * Ở controller thì mỗi đường ghi phải tự nhớ, mà quên một đường chính là khuôn của
             * phần lớn lỗi đã gặp trong dự án này. Ở đây thì nút "Sửa hạn chốt" lẫn form sửa tour
             * đều không đi vòng được.
             *
             * Đặt SAU phép so bằng bên trên là chủ ý: form sửa tour gửi lại toàn bộ danh sách
             * chuyến mỗi lần lưu, nên phần lớn lần gọi tới đây không đổi gì. Đòi lý do cho một lần
             * lưu không đổi gì thì luật này chỉ tổ phiền, và người dùng sẽ gõ bừa cho xong - lúc ấy
             * cột `reason` có chữ nhưng vẫn không trả lời được câu hỏi nào.
             */
            $lyDo = $lyDo !== null ? trim($lyDo) : null;

            if ($lyDo === null || mb_strlen($lyDo) < self::LY_DO_TOI_THIEU) {
                throw new BusinessRuleException(sprintf(
                    'Phải ghi lý do dời hạn chốt, ít nhất %d ký tự. Ba tháng nữa, người đọc nhật ký '
                    . 'cần biết vì sao mốc bị dời và lúc đó không ai nhớ lại giúp được.',
                    self::LY_DO_TOI_THIEU,
                ));
            }

            $khoa->forceFill(['booking_deadline' => $moi])->save();

            $this->auditLogger->log(
                $khoa,
                ScheduleAuditAction::DeadlineChanged,
                ['booking_deadline' => $cu?->toIso8601String()],
                ['booking_deadline' => $moi?->toIso8601String()],
                $lyDo,
                $actor,
            );

            return $khoa;
        });
    }

    /** Lý do không sửa được, hoặc null khi sửa được. */
    private function lyDoChan(TourSchedule $schedule, ?Carbon $moi): ?string
    {
        if ($schedule->isOperationallyLocked()) {
            return sprintf(
                'Không sửa được hạn chốt khi chuyến đang ở trạng thái "%s".',
                $schedule->status->label(),
            );
        }

        if ($moi !== null && $schedule->start_date && $moi->gte($schedule->start_date)) {
            return sprintf(
                'Hạn chốt phải trước ngày khởi hành %s.',
                $schedule->start_date->format('d/m/Y H:i'),
            );
        }

        return null;
    }

    private function huongDoi(?Carbon $cu, ?Carbon $moi): string
    {
        if ($this->bangNhau($cu, $moi)) {
            return 'unchanged';
        }

        if ($cu === null || $moi === null) {
            return 'unknown';
        }

        return $moi->gt($cu) ? 'later' : 'earlier';
    }

    private function bangNhau(?Carbon $a, ?Carbon $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return $a->equalTo($b);
    }

    /**
     * @param  array<int, string>  $trangThai
     * @return array{bookings: int, guests: int}
     */
    private function demKhach(TourSchedule $schedule, array $trangThai): array
    {
        $row = Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->whereIn('status', $trangThai)
            ->selectRaw('COUNT(*) as so_don, COALESCE(SUM(guests), 0) as so_khach')
            ->first();

        return [
            'bookings' => (int) ($row->so_don ?? 0),
            'guests' => (int) ($row->so_khach ?? 0),
        ];
    }

    /** @return array<int, string> */
    private function canhBao(
        string $huong,
        bool $quaHanSau,
        bool $canMoBanTay,
        int $trongDanhSach,
        int $soDonGheChet,
        int $soGheChet,
    ): array {
        $canhBao = [];

        if ($quaHanSau) {
            $canhBao[] = 'Hạn chốt mới nằm ở quá khứ nên có hiệu lực ngay: chuyến ngừng nhận đặt mới.';

            if ($trongDanhSach > 0) {
                $canhBao[] = sprintf(
                    '%d đơn đang trong danh sách đoàn sẽ không sửa được tên hành khách và không '
                    . 'chuyển sang chuyến khác được nữa.',
                    $trongDanhSach,
                );
            }

            $canhBao[] = 'Từ giờ khách hủy thì chỗ không quay lại kho.';
        }

        if ($canMoBanTay) {
            $canhBao[] = 'Chuyến đang đóng bán và sẽ không tự mở lại. Sau khi lưu, bấm "Mở bán" '
                . 'ở chuyến này thì khách mới đặt được.';
        }

        if ($huong === 'later' && $soDonGheChet > 0) {
            $canhBao[] = sprintf(
                '%d chỗ của %d đơn đã hủy vẫn đang bị giữ và không tự trả về kho. Muốn bán lại '
                . 'thì mở lại từng đơn ở màn hình quản lý đặt chỗ.',
                $soGheChet,
                $soDonGheChet,
            );
        }

        // Hai câu này luôn hiện, vì đây đúng là hai điều người bấm hay lo nhất.
        $canhBao[] = 'Các đơn đã hủy trước đây giữ nguyên kết quả cũ, không tính lại.';
        $canhBao[] = 'Số tiền hoàn của mọi đơn không đổi: phần trăm hoàn tính theo số giờ trước '
            . 'giờ khởi hành, không đọc hạn chốt.';

        return $canhBao;
    }
}
