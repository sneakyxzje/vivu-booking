<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\ScheduleDeadlineChangedMail;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Models\User;
use App\Notifications\Alert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
        private readonly Notifier $notifier,
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
            ->get(['id', 'guests', 'seats']);

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
            'held_seats' => (int) $gheChet->sum(fn (Booking $don) => $don->seatsTaken()),
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

            $cu = $khoa->booking_deadline;

            /*
             * Không đổi gì thì dừng ngay ở đây — trước cả luật chặn.
             *
             * Form sửa tour gửi lại toàn bộ danh sách chuyến mỗi lần lưu, nên phần lớn lần gọi tới
             * đây là gửi lại đúng cái mốc đang có. Kiểm luật trước phép so bằng thì một chuyến có
             * hạn chốt đã trôi qua - chuyện hoàn toàn bình thường với chuyến sắp khởi hành - làm
             * hỏng mọi lần lưu tour, kèm câu "hạn chốt mới nằm ở quá khứ" nói về một chuyến người
             * dùng không hề đụng tới.
             *
             * Luật chặn nói về việc DỜI mốc. Không dời thì không có gì để chặn, và cũng không có gì
             * để ghi nhật ký.
             */
            if ($this->bangNhau($cu, $moi)) {
                return $khoa;
            }

            $canTro = $this->lyDoChan($khoa, $moi);

            if ($canTro !== null) {
                throw new BusinessRuleException($canTro);
            }

            /*
             * Lý do bắt buộc, và bắt buộc ở đây chứ không ở luật validate của controller.
             *
             * Ở controller thì mỗi đường ghi phải tự nhớ, mà quên một đường chính là khuôn của
             * phần lớn lỗi đã gặp trong dự án này. Ở đây thì nút "Sửa hạn chốt" lẫn form sửa tour
             * đều không đi vòng được.
             *
             * Đặt sau phép so bằng ở trên, cùng lý do với luật chặn: một lần lưu không đổi gì thì
             * không phải giải trình gì. Đòi lý do cho nó chỉ tổ phiền, và người dùng sẽ gõ bừa cho
             * xong - lúc ấy cột `reason` có chữ nhưng vẫn không trả lời được câu hỏi nào.
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

            $nhatKy = $this->auditLogger->log(
                $khoa,
                ScheduleAuditAction::DeadlineChanged,
                ['booking_deadline' => $cu?->toIso8601String()],
                ['booking_deadline' => $moi?->toIso8601String()],
                $lyDo,
                $actor,
            );

            /*
             * Không ghi được vết thì không đổi.
             *
             * ScheduleAuditLogger cố ý nuốt lỗi để một sự cố ở bảng nhật ký không kéo ngược nghiệp
             * vụ về - đúng cho gần như mọi nơi gọi nó. Chỗ này là ngoại lệ: nhật ký CHÍNH LÀ cơ chế
             * kiểm soát duy nhất đặt lên quyền dời hạn chốt. Dời xong mà không dòng nào ghi lại thì
             * thao tác ấy vô hình, và vô hình còn tệ hơn là không làm được.
             *
             * Ném ở đây kéo cả giao dịch về, nên hạn chốt cũ giữ nguyên.
             */
            if (!$nhatKy) {
                throw new BusinessRuleException(
                    'Không ghi được nhật ký cho lần dời hạn chốt này nên hệ thống đã hủy thao tác. '
                    . 'Thử lại, nếu vẫn lỗi thì báo bộ phận kỹ thuật.',
                    500,
                );
            }

            /*
             * Báo cho khách - sau khi mọi thứ đã ghi xong thật.
             *
             * `afterCommit` chứ không gọi thẳng: form sửa tour bọc cả lần lưu trong một giao dịch
             * lớn hơn, nên đường ghi ấy vẫn có thể quay lại sau khi service này trả về. Gửi thư báo
             * một thay đổi rồi thay đổi ấy biến mất là loại sai khó đính chính nhất, vì thư đã nằm
             * trong hộp thư của khách rồi.
             *
             * Khách nhận mốc CÓ HIỆU LỰC chứ không phải giá trị cột: xóa hạn chốt riêng nghĩa là
             * quay về mốc mặc định, và khách quan tâm tới ngày thật chứ không quan tâm cột nào rỗng.
             */
            $hieuLucCu = $cu ?? $khoa->defaultBookingDeadline();
            $hieuLucMoi = $moi ?? $khoa->defaultBookingDeadline();

            DB::afterCommit(
                fn () => $this->baoChoNguoiLienQuan($khoa, $hieuLucCu, $hieuLucMoi, $lyDo),
            );

            return $khoa;
        });
    }

    /**
     * Báo cho khách của chuyến, và cho hướng dẫn viên đang phụ trách.
     *
     * Không có công tắc "sửa mà không báo", và đó là chủ ý. Hạn chốt quyết định tới lúc nào khách
     * còn tự sửa tên hành khách, còn xin đổi chuyến, còn hủy mà được trả chỗ. Dịch nó đi trong im
     * lặng thì khách chỉ biết vào lúc bấm không được - tức lúc đã muộn, và lúc đó việc đầu tiên họ
     * nghĩ là hệ thống hỏng.
     *
     * Thư hỏng thì ghi log rồi đi tiếp: hạn chốt đã đổi và đã có vết trong nhật ký, một máy chủ thư
     * trục trặc không được phép làm hỏng việc đã xong. Ngược lại với nhật ký, nơi không ghi được
     * thì cả thao tác bị hủy - nhật ký là bằng chứng, còn thư là lời báo.
     */
    private function baoChoNguoiLienQuan(
        TourSchedule $schedule,
        ?Carbon $cu,
        ?Carbon $moi,
        ?string $lyDo,
    ): void {
        $schedule->loadMissing(['tour:id,title', 'guides']);

        $donHang = Booking::query()
            ->where('tour_schedule_id', $schedule->getKey())
            // Kể cả đơn đang giữ chỗ chưa trả tiền: quyền khai và sửa danh sách hành khách của họ
            // cũng đóng lại theo đúng mốc này.
            ->whereIn('status', [...BookingStatus::manifestValues(), BookingStatus::Pending->value])
            ->with(['customer:id,email', 'tour:id,title'])
            ->get();

        foreach ($donHang as $don) {
            $email = $don->customer?->email ?: $don->customer_email;

            if (!$email) {
                continue;
            }

            try {
                Mail::to($email)->send(new ScheduleDeadlineChangedMail($don, $cu, $moi, $lyDo));
            } catch (Throwable $e) {
                Log::warning('Không gửi được thư báo dời hạn chốt.', [
                    'tour_schedule_id' => $schedule->getKey(),
                    'booking_id' => $don->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Hướng dẫn viên là người cầm danh sách đoàn đi gặp nhà cung cấp, nên mốc dịch thì họ phải
        // biết - không thì họ vẫn hứa với khách theo mốc cũ.
        foreach ($schedule->guides as $huongDanVien) {
            $this->notifier->toiNguoiDung(
                $huongDanVien,
                Alert::HAN_CHOT_DOI,
                sprintf('Chuyến #%d vừa đổi hạn chốt danh sách', $schedule->getKey()),
                sprintf(
                    '%s · %s%s',
                    $schedule->tour?->title ?? 'Tour',
                    $moi ? 'chốt lúc ' . $moi->format('H:i d/m/Y') : 'không còn hạn chốt',
                    $lyDo ? ' · ' . $lyDo : '',
                ),
                '/guide/tours',
            );
        }
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

        /*
         * Hạn chốt mới không được nằm ở quá khứ.
         *
         * Kéo vạch chỉ có hiệu lực từ lúc kéo trở đi. Một mốc đặt vào hôm qua tuyên bố một điều
         * chưa từng đúng: hôm qua chuyến vẫn bán chỗ, vẫn cho sửa tên, khách hủy vẫn được trả chỗ.
         * Ghi nó vào rồi ba tháng sau đọc nhật ký thì không dựng lại được chuyện đã xảy ra - mà đó
         * đúng là câu hỏi bảng nhật ký sinh ra để trả lời.
         *
         * Khóa danh sách ngay vẫn làm được, chỉ là phải nói đúng thứ mình làm: đặt mốc vào thời
         * điểm hiện tại. So với `startOfMinute()` vì ô chọn ngày giờ chỉ tới phút; không thì người
         * chọn đúng phút hiện tại bấm mãi không lưu được mà không hiểu vì sao.
         *
         * Xóa hạn chốt riêng (`$moi` là null) thì không rơi vào luật này: đó là quay về mốc mặc
         * định của hệ thống, không phải chọn một thời điểm.
         */
        if ($moi !== null && $moi->lt(now()->startOfMinute())) {
            return sprintf(
                'Hạn chốt mới (%s) nằm ở quá khứ. Muốn khóa danh sách ngay thì đặt vào thời điểm '
                . 'hiện tại trở đi, còn muốn ngừng bán mà chưa khóa danh sách thì dùng nút "Đóng bán".',
                $moi->format('d/m/Y H:i'),
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
        }

        /*
         * Rút ngắn cũng phải cảnh báo, không riêng trường hợp mốc mới đã trôi qua.
         *
         * Đặt hạn chốt vào quá khứ nay bị chặn, nên nếu chỉ cảnh báo theo `$quaHanSau` thì đúng
         * thao tác hay gặp nhất - kéo mốc về sát hôm nay - lại chẳng nhắc gì, trong khi nó tước
         * quyền của khách y hệt, chỉ chậm hơn vài giờ.
         */
        if ($quaHanSau || $huong === 'earlier') {
            if ($trongDanhSach > 0) {
                $canhBao[] = sprintf(
                    'Từ mốc mới trở đi, %d đơn trong danh sách đoàn không sửa được tên hành khách '
                    . 'và không chuyển sang chuyến khác được nữa.',
                    $trongDanhSach,
                );
            }

            $canhBao[] = 'Từ mốc mới trở đi, khách hủy thì chỗ không quay lại kho.';
        }

        if ($canMoBanTay) {
            $canhBao[] = 'Chuyến đang đóng bán và sẽ không tự mở lại. Sau khi lưu, bấm "Mở bán" '
                . 'ở chuyến này thì khách mới đặt được.';
        }

        if ($huong === 'later' && $soDonGheChet > 0) {
            /*
             * Câu cũ chỉ khách tới "mở lại từng đơn ở màn hình quản lý đặt chỗ" — nhưng tuyến mở
             * lại đơn đã hủy đã được gỡ có chủ đích (xem chú thích ở `AdminBookingController`, chỗ
             * hàm `reopen()` từng nằm). Nó chỉ người dùng đi tìm một cái nút không còn tồn tại.
             *
             * Đường đúng còn lại là tăng sức chứa của chuyến: một quyết định nhìn thấy rõ, và là
             * đúng thứ `BookingHoldService` mô tả khi giải thích vì sao ghế chết không tự mở lại.
             */
            $canhBao[] = sprintf(
                '%d chỗ của %d đơn đã hủy vẫn đang bị giữ và không tự trả về kho. Muốn bán thêm '
                . 'thì tăng số chỗ tối đa của chuyến, sau khi đã xin được suất từ nhà cung cấp.',
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
