<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Exceptions\BusinessRuleException;
use App\Mail\BookingCancelledMail;
use App\Mail\ScheduleMergedMail;
use App\Models\Booking;
use App\Models\BookingTransfer;
use App\Models\TourSchedule;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * L01, L02 - Ghép hai chuyến của cùng một tour.
 *
 * Câu số 16 của hội đồng. Luật ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 2.1.
 *
 * Tình huống: hai chuyến khởi hành gần nhau, mỗi chuyến 4 khách, không chuyến nào đủ mức tối
 * thiểu để chạy. Dồn về một chuyến thì cả hai đoàn đều được đi thay vì cả hai cùng bị hủy.
 *
 * Đây là chuyển chuyến hàng loạt, nên dùng lại đúng cách khóa hai chuyến của nhóm I: sắp id
 * tăng dần trước khi khóa.
 */
class ScheduleMergeService
{
    /** Chênh lệch ngày khởi hành tối đa giữa hai chuyến được ghép. */
    public const MAX_DAY_GAP = 2;

    public function __construct(
        private ScheduleLifecycleService $lifecycle,
        private BookingHoldService $holdService,
        private BookingAuditLogger $auditLogger,
        private BookingPaymentService $payments,
        private Notifier $notifier,
    ) {
    }

    /**
     * Đơn nào vừa ghép sang mà còn nợ tiền, lại quá sát ngày để tự động thu nốt, thì gọi người.
     *
     * Ghép chuyến là đường duy nhất trong hệ thống dời ngày đi của khách mà KHÔNG hỏi họ. Khách đã
     * cọc cho chuyến ngày 20, giờ ngồi trên chuyến ngày 8 — hạn trả nốt của chuyến mới đã trôi qua
     * từ lâu, và cả dây chuyền nhắc rồi hủy không còn đủ thời gian để chạy hết.
     *
     * Trước đây chỗ này im lặng, nên đơn ấy trôi tới ngày khởi hành mà không ai biết nó còn thiếu
     * tiền. Bây giờ điều hành nhận thông báo ngay lúc vừa bấm ghép, tức lúc còn kịp gọi khách.
     *
     * Luật "thế nào là quá sát" dùng chung với luồng chuyển chuyến — xem
     * `BookingPaymentService::tuDongThuNotKhongKip()`.
     *
     * @param  array<int, int>  $ids
     */
    private function canhBaoDonConNoSatNgay(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $don = Booking::query()
            ->whereIn('id', $ids)
            // `booking_deadline` phải nằm trong danh sách cột: `tuDongThuNotKhongKip()` đo tới hạn
            // chốt, và cột thiếu thì nó đọc null rồi lặng lẽ lùi về mặc định ngày-đi-trừ-ba. Chuyến
            // có hạn chốt thương lượng riêng — đúng nhóm mà mốc này quan trọng nhất — bị đo sai về
            // phía muộn hơn, nên cảnh báo bắn trễ hoặc không bắn.
            ->with(['schedule:id,start_date,booking_deadline', 'tour:id,title'])
            ->get();

        foreach ($don as $booking) {
            if (!$this->payments->tuDongThuNotKhongKip($booking)) {
                continue;
            }

            $this->notifier->toiDieuHanh(
                Alert::CON_NO_SAT_NGAY,
                sprintf(
                    'Đơn #%d còn thiếu %s đ mà chuyến khởi hành %s',
                    $booking->id,
                    number_format($this->payments->balanceDue($booking), 0, ',', '.'),
                    $booking->schedule?->start_date?->format('d/m') ?? 'rất gần',
                ),
                sprintf(
                    '%s · đơn vừa được ghép sang chuyến này nên quy trình nhắc và hủy tự động không '
                        . 'còn kịp chạy. Gọi khách thu nốt, hoặc duyệt cho đi rồi thu sau.',
                    $booking->tour?->title ?? 'Tour',
                ),
                '/admin/bookings',
            );
        }
    }

    /**
     * Xem trước tác động: bao nhiêu đơn chuyển, bao nhiêu đơn bị hủy, còn đủ chỗ không.
     *
     * @return array{can_merge: bool, blocked_reason: string|null, transferring: int, transferring_guests: int, cancelling: int, remaining_seats: int}
     */
    public function preview(TourSchedule $from, TourSchedule $to): array
    {
        $coThe = true;
        $lyDo = null;

        try {
            $this->assertCanMerge($from, $to);
        } catch (BusinessRuleException $e) {
            $coThe = false;
            $lyDo = $e->getMessage();
        }

        $chuyenDi = $this->bookingsToTransfer($from);
        $huyDi = $this->bookingsToCancel($from);

        return [
            'can_merge' => $coThe,
            'blocked_reason' => $lyDo,
            'transferring' => $chuyenDi->count(),
            // Số NGƯỜI cho màn hình đọc; số ghế dùng để kiểm sức chứa ở `assertCanMerge`.
            'transferring_guests' => (int) $chuyenDi->sum('guests'),
            'cancelling' => $huyDi->count(),
            'remaining_seats' => (int) $to->max_people - (int) $to->booked_people,
        ];
    }

    /**
     * Ghép chuyến nguồn vào chuyến đích.
     *
     * @return array{transferred: int, cancelled: int}
     */
    public function merge(
        TourSchedule $from,
        TourSchedule $to,
        string $reason,
        ?User $actor = null,
    ): array {
        return DB::transaction(function () use ($from, $to, $reason, $actor) {
            // Cùng thứ tự khóa với BookingTransferService: id tăng dần, để hai thao tác ghép
            // chéo nhau không chờ nhau vô hạn.
            $ids = collect([$from->getKey(), $to->getKey()])->unique()->sort()->values();

            $schedules = TourSchedule::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $nguon = $schedules->get($from->getKey());
            $dich = $schedules->get($to->getKey());

            if (!$nguon || !$dich) {
                throw new BusinessRuleException('Không tìm thấy chuyến khởi hành.', 404);
            }

            $this->assertCanMerge($nguon, $dich);

            $chuyenDi = $this->bookingsToTransfer($nguon);
            $huyDi = $this->bookingsToCancel($nguon);

            $tongKhachChuyen = (int) $chuyenDi->sum(fn (Booking $don) => $don->seatsTaken());

            // Kiểm lại số chỗ trên bản ghi vừa khóa. Giữa lúc xem trước và lúc bấm, chuyến đích
            // hoàn toàn có thể vừa nhận thêm khách.
            if ((int) $dich->max_people - (int) $dich->booked_people < $tongKhachChuyen) {
                throw new BusinessRuleException(sprintf(
                    'Chuyến đích chỉ còn %d chỗ, không đủ cho %d khách của chuyến nguồn.',
                    max(0, (int) $dich->max_people - (int) $dich->booked_people),
                    $tongKhachChuyen,
                ));
            }

            foreach ($chuyenDi as $booking) {
                $this->moveBooking($booking, $nguon, $dich, $reason, $actor);
            }

            /*
             * Đơn chưa thanh toán thì hủy thay vì chuyển.
             *
             * Khách chưa trả tiền nên chưa có cam kết nào; chuyển họ sang một ngày khác mà họ
             * chưa từng đồng ý là tự quyết thay khách. Hủy và mời đặt lại đúng hơn.
             */
            foreach ($huyDi as $booking) {
                $this->cancelUnpaid($booking, $nguon, $reason);
            }

            $dich->increment('booked_people', $tongKhachChuyen);
            $dich->refresh();

            if ($dich->booked_people >= $dich->max_people
                && $this->lifecycle->currentStatus($dich) === ScheduleStatus::Open) {
                $this->lifecycle->transitionTo(
                    $dich,
                    ScheduleStatus::Closed,
                    'Tự động đóng bán do vừa nhận khách từ chuyến được ghép vào.',
                    $actor?->getKey(),
                );
            }

            // Chuyến nguồn không còn khách nào. Đặt về 0 thay vì trừ dần, vì mọi đơn của nó đều
            // vừa được xử lý ở trên.
            $nguon->forceFill([
                'booked_people' => 0,
                'merged_into_schedule_id' => $dich->getKey(),
            ])->save();

            $this->lifecycle->transitionTo(
                $nguon,
                ScheduleStatus::Cancelled,
                $reason,
                $actor?->getKey(),
            );

            /*
             * Báo cho khách, sau khi giao dịch đã chốt.
             *
             * Ghép chuyến đổi ngày đi của người đã trả tiền mà không hỏi họ - đó là quyết định vận
             * hành, và chấp nhận được. Không báo lại mới là chỗ không chấp nhận được: khách biết
             * chuyện khi ra bến vào đúng ngày cũ.
             *
             * Ngày cũ phải chụp lại ở đây. Sau khi ghép, đơn đã trỏ sang chuyến đích nên không còn
             * đường nào đọc ngược ra ngày khách từng đặt.
             */
            $ngayCu = $nguon->start_date->copy();
            $ngayMoi = $dich->start_date->copy();
            $idDaDoi = $chuyenDi->pluck('id')->all();
            $idDaHuy = $huyDi->pluck('id')->all();

            DB::afterCommit(fn () => $this->baoChoKhach($idDaDoi, $idDaHuy, $ngayCu, $ngayMoi, $reason));
            DB::afterCommit(fn () => $this->canhBaoDonConNoSatNgay($idDaDoi));

            return [
                'transferred' => $chuyenDi->count(),
                'cancelled' => $huyDi->count(),
            ];
        });
    }

    /**
     * Thư cho hai nhóm khách của chuyến vừa bị ghép đi.
     *
     * Hai nội dung khác hẳn nhau nên không dùng chung một mẫu: người đã trả tiền cần biết ngày mới
     * và quyền từ chối; người chưa trả tiền thì đơn đã hủy, họ cần lời mời đặt lại.
     *
     * Thư hỏng thì ghi log rồi đi tiếp - việc ghép đã xong và đã có vết trong nhật ký, một máy chủ
     * thư trục trặc không được phép làm hỏng thứ đã làm xong.
     *
     * @param  array<int, int>  $idDaDoi
     * @param  array<int, int>  $idDaHuy
     */
    private function baoChoKhach(
        array $idDaDoi,
        array $idDaHuy,
        Carbon $ngayCu,
        Carbon $ngayMoi,
        string $lyDo,
    ): void {
        foreach ($this->donTheoId($idDaDoi) as $don) {
            $this->gui($don, new ScheduleMergedMail($don, $ngayCu, $ngayMoi, $lyDo));
        }

        foreach ($this->donTheoId($idDaHuy) as $don) {
            $this->gui($don, new BookingCancelledMail($don));
        }
    }

    /** @param  array<int, int>  $ids */
    private function donTheoId(array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        return Booking::query()
            ->whereIn('id', $ids)
            ->with(['customer:id,email', 'tour:id,title', 'schedule'])
            ->get();
    }

    private function gui(Booking $don, $thu): void
    {
        $email = $don->customer?->email ?: $don->customer_email;

        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send($thu);
        } catch (Throwable $e) {
            Log::warning('Không gửi được thư báo ghép chuyến.', [
                'booking_id' => $don->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bốn điều kiện theo tài liệu 04 mục 2.1.
     */
    public function assertCanMerge(TourSchedule $from, TourSchedule $to): void
    {
        if ((int) $from->getKey() === (int) $to->getKey()) {
            throw new BusinessRuleException('Chuyến nguồn và chuyến đích trùng nhau.');
        }

        // 1. Cùng tour. Ghép hai tour khác nhau là đổi hẳn sản phẩm khách đã mua.
        if ((int) $from->tour_id !== (int) $to->tour_id) {
            throw new BusinessRuleException('Chỉ ghép được hai chuyến của cùng một tour.');
        }

        $loai = TourType::tryFrom((string) ($from->tour?->type ?? TourType::Shared->value));

        if ($loai && !$loai->canMergeSchedules()) {
            throw new BusinessRuleException(
                'Tour riêng không ghép chuyến được, vì khách đã trả tiền để đi trọn chuyến của riêng họ.',
            );
        }

        // 2. Cả hai chưa khởi hành.
        foreach ([$from, $to] as $schedule) {
            $trangThai = $this->lifecycle->effectiveStatus($schedule);

            if (!in_array($trangThai, [ScheduleStatus::Open, ScheduleStatus::Closed, ScheduleStatus::Confirmed], true)) {
                throw new BusinessRuleException(sprintf(
                    'Chuyến #%d đang ở trạng thái "%s" nên không ghép được.',
                    $schedule->getKey(),
                    $trangThai->label(),
                ));
            }
        }

        /*
         * Cả hai chuyến phải còn trước hạn chốt danh sách.
         *
         * Mục đích của ghép là gửi MỘT danh sách đúng thay vì hai danh sách sai. Ghép sau khi
         * danh sách đã gửi đi thì không còn là ghép nữa, mà là đi vá: phải gọi hủy chuyến nguồn
         * và xin thêm suất cho chuyến đích, hai lần làm việc với nhà cung cấp và có thể bị từ chối.
         *
         * Về dữ liệu còn nghiêm trọng hơn. Ghép vào chuyến đích đã qua hạn chốt làm booked_people
         * của nó vượt quá số suất đã cam kết - phá đúng bất biến mà cả hệ thống dựa vào. Con số
         * chỉ đúng trở lại nếu điều hành thực sự xin được thêm suất, mà hệ thống không kiểm chứng
         * được điều đó.
         *
         * Chuyến nào tụt dưới mức tối thiểu sau hạn chốt thì chỉ còn hai đường: vẫn chạy, hoặc
         * hủy chuyến và đền bù. Ghép không còn là lựa chọn.
         */
        foreach ([['chuyến nguồn', $from], ['chuyến đích', $to]] as [$ten, $schedule]) {
            $hanChot = $schedule->booking_deadline ?? $schedule->defaultBookingDeadline();

            if ($hanChot && now()->gte($hanChot)) {
                throw new BusinessRuleException(sprintf(
                    'Đã qua hạn chốt danh sách của %s (#%d) ngày %s. Danh sách đã gửi nhà cung cấp '
                        . 'nên không ghép được nữa; nếu chuyến không đủ khách, xử lý theo luồng hủy chuyến.',
                    $ten,
                    $schedule->getKey(),
                    Carbon::parse($hanChot)->format('d/m/Y H:i'),
                ));
            }
        }

        // 3. Chuyến đích còn đủ chỗ cho toàn bộ khách của chuyến nguồn.
        $canChuyen = (int) $this->bookingsToTransfer($from)->sum(fn (Booking $don) => $don->seatsTaken());
        $conTrong = (int) $to->max_people - (int) $to->booked_people;

        if ($conTrong < $canChuyen) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đích chỉ còn %d chỗ, không đủ cho %d khách của chuyến nguồn.',
                max(0, $conTrong),
                $canChuyen,
            ));
        }

        // 4. Ngày khởi hành không lệch quá xa. Đổi ngày xa hơn ảnh hưởng lớn tới kế hoạch của
        // khách, và họ không có quyền từ chối vì đây là thay đổi do hãng.
        if ($from->start_date && $to->start_date) {
            $lech = abs(Carbon::parse($from->start_date)->diffInDays(Carbon::parse($to->start_date)));

            if ($lech > self::MAX_DAY_GAP) {
                throw new BusinessRuleException(sprintf(
                    'Hai chuyến lệch nhau %d ngày, vượt ngưỡng %d ngày cho phép khi ghép.',
                    (int) $lech,
                    self::MAX_DAY_GAP,
                ));
            }
        }
    }

    /** Đơn đã thanh toán, sẽ được chuyển sang chuyến đích. */
    private function bookingsToTransfer(TourSchedule $from)
    {
        return Booking::query()
            ->where('tour_schedule_id', $from->getKey())
            ->whereIn('status', BookingStatus::paidValues())
            ->get();
    }

    /** Đơn chưa thanh toán, sẽ bị hủy và mời đặt lại. */
    private function bookingsToCancel(TourSchedule $from)
    {
        return Booking::query()
            ->where('tour_schedule_id', $from->getKey())
            ->where('status', BookingStatus::Pending->value)
            ->get();
    }

    private function moveBooking(
        Booking $booking,
        TourSchedule $from,
        TourSchedule $to,
        string $reason,
        ?User $actor,
    ): void {
        $booking->forceFill([
            'tour_schedule_id' => $to->getKey(),
            'departure_date' => $to->start_date,
            'transfer_count' => (int) $booking->transfer_count + 1,
        ])->save();

        // Giá giữ nguyên: cùng tour nên cùng bảng giá, và đây là thay đổi do hãng nên không có
        // lý do gì thu thêm của khách.
        BookingTransfer::query()->create([
            'booking_id' => $booking->getKey(),
            'from_schedule_id' => $from->getKey(),
            'to_schedule_id' => $to->getKey(),
            'from_tour_id' => $from->tour_id,
            'to_tour_id' => $to->tour_id,
            'initiated_by' => 'company',
            'price_difference' => 0,
            'fee' => 0,
            'reason' => $reason,
            'approved_by' => $actor?->getKey(),
            'approved_at' => now(),
        ]);

        $this->auditLogger->log(
            $booking,
            BookingAuditAction::Transferred,
            ['tour_schedule_id' => $from->getKey()],
            [
                'tour_schedule_id' => $to->getKey(),
                'merged' => true,
                'initiated_by' => 'company',
            ],
            $reason,
        );
    }

    private function cancelUnpaid(Booking $booking, TourSchedule $from, string $reason): void
    {
        $lyDo = 'Chuyến đã được ghép sang chuyến khác. ' . $reason
            . ' Đơn chưa thanh toán nên được hủy, mời quý khách đặt lại chuyến mới.';

        $booking->forceFill([
            'status' => BookingStatus::Cancelled->value,
            'cancel_type' => 'by_company',
            'cancel_reason' => $lyDo,
            'cancelled_at' => now(),
            // Chỗ về kho: đơn chưa trả tiền nên chưa có cam kết nào với nhà cung cấp.
            'seats_released' => true,
            'seats_released_at' => now(),
        ])->save();

        $this->holdService->releaseDiscountUsage($booking);

        $this->auditLogger->logStatusChange(
            $booking,
            BookingAuditAction::Cancelled,
            BookingStatus::Pending->value,
            BookingStatus::Cancelled->value,
            $lyDo,
            ['seats_released' => true, 'merged_from_schedule_id' => $from->getKey()],
        );
    }

    /*
     * ĐÃ GỠ: `finalScheduleOf()`.
     *
     * Nó đi theo chuỗi `merged_into_schedule_id` để tìm chuyến cuối của một dây chuyền ghép, với
     * lý lẽ "khách của A phải nhìn thấy C, không phải B". Lý lẽ đúng — nhưng điều ấy đã được bảo
     * đảm bởi chính `moveBooking()`: mỗi lần ghép, đơn được trỏ thẳng sang chuyến đích, nên sau
     * A→B→C đơn đã nằm ở C. Không dòng nào trong ứng dụng gọi tới hàm này.
     *
     * Nguy hiểm của một hàm như vậy không phải ở chỗ nó thừa, mà ở chỗ nó **trông như một luật
     * đang chạy**. Bài kiểm thử của nó cũng xanh, nên nhìn vào càng tin — trong khi đường khách
     * thật sự đi thì không bài nào kiểm. `ScheduleMergeTest` nay kiểm đúng đường đó.
     *
     * Cột `merged_into_schedule_id` vẫn giữ: nó là dấu vết chuyến này đã bị ghép đi đâu.
     */
}
