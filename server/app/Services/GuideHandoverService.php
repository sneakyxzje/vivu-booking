<?php

namespace App\Services;

use App\Enums\HandoverRequestStatus;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\GuideHandover;
use App\Models\GuideHandoverRequest;
use App\Notifications\Alert;
use App\Models\TourSchedule;
use App\Models\User;
use App\Support\GioVietNam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Đổi hướng dẫn viên giữa chừng chuyến.
 *
 * Đổi người vốn đã làm được: chỉ cần sửa danh sách phân công. Thứ thiếu là **vết**. Người mới
 * nhận đoàn mà không biết đoàn đang ở đâu, ai đã điểm danh tới chặng nào; và khi có khiếu nại về
 * một chặng thì không tra được lúc ấy ai phụ trách.
 *
 * Nên bàn giao là một thao tác riêng bắt buộc kèm hai thứ: **lý do** và **tình trạng đoàn**.
 *
 * ## Đã rút gọn
 *
 * Trước đây nhóm này có một luồng phê duyệt đầy đủ: bốn trạng thái phiếu, ba thao tác xử lý
 * (duyệt / từ chối / rút lại), thêm một bước người nhận xác nhận đã đọc, và một lối thoát "nhờ
 * hướng dẫn viên đoàn khác trông hộ" phá luật trùng lịch. Khoảng 750 dòng cho một việc mà thực tế
 * chỉ là: **ai đó cần được thay, điều hành chỉ định người mới.**
 *
 * Nay còn hai thứ. Một **phiếu** hai trạng thái, và một thao tác **bàn giao**. Điều hành bàn giao
 * thẳng cũng được, không cần phiếu.
 *
 * ## Ba luật giữ nguyên, vì đây mới là phần nghiệp vụ thật
 *
 *   1. Người cũ **mất quyền ghi ngay**, vì bị gỡ khỏi danh sách phân công. Không có trạng thái
 *      lửng lơ nào mà cả hai cùng ghi được.
 *   2. Người mới **không được trùng lịch** — một người không đứng ở hai đoàn cùng lúc.
 *   3. Dữ liệu người cũ đã ghi **giữ nguyên**. Bàn giao không xóa gì, chỉ chuyển quyền ghi tiếp.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 4.4.
 */
class GuideHandoverService
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
        private readonly ScheduleGuideService $guideService,
        private readonly ScheduleAuditLogger $auditLogger,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * Chuyển quyền phụ trách từ người này sang người khác.
     */
    public function handover(
        TourSchedule $schedule,
        int $fromGuideId,
        int $toGuideId,
        string $reason,
        string $note,
        ?Carbon $luc = null,
        ?User $actor = null,
    ): GuideHandover {
        $bienBan = DB::transaction(function () use ($schedule, $fromGuideId, $toGuideId, $reason, $note, $luc, $actor) {
            $khoa = TourSchedule::query()
                ->whereKey($schedule->getKey())
                ->with('tour')
                ->lockForUpdate()
                ->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy chuyến khởi hành.', 404);
            }

            $this->assertChuyenConBanGiaoDuoc($khoa);

            if ($fromGuideId === $toGuideId) {
                throw new BusinessRuleException('Người nhận và người giao không thể là một.');
            }

            if (!$khoa->hasGuide($fromGuideId)) {
                throw new BusinessRuleException(
                    'Người giao không nằm trong danh sách phụ trách chuyến này.',
                );
            }

            if ($khoa->hasGuide($toGuideId)) {
                throw new BusinessRuleException(
                    'Người nhận đã phụ trách chuyến này rồi, không cần bàn giao.',
                );
            }

            $nguoi = $this->guideService->assertValidGuides([$toGuideId]);

            $this->assertDoanKhongBiBoRoi($khoa);

            [$start, $end] = $this->guideService->periodOf($khoa);
            $vuong = $this->guideService->conflictFor($toGuideId, $start, $end, $khoa->getKey());

            if ($vuong) {
                throw new BusinessRuleException(
                    $this->guideService->moTaTrungLich($nguoi[$toGuideId]->name, $vuong),
                );
            }

            $banGiaoLuc = $luc ?? GioVietNam::bayGio();

            if ($banGiaoLuc->gt(GioVietNam::bayGio())) {
                throw new BusinessRuleException('Thời điểm bàn giao không thể nằm ở tương lai.');
            }

            // Gỡ người cũ trước rồi mới thêm người mới. Ngược lại thì có một khoảnh khắc cả hai
            // cùng có quyền ghi, và nếu giao dịch hỏng giữa chừng thì trạng thái đó nằm lại.
            $khoa->guides()->detach($fromGuideId);
            $khoa->guides()->attach($toGuideId);

            $bienBan = GuideHandover::query()->create([
                'tour_schedule_id' => $khoa->getKey(),
                'from_guide_id' => $fromGuideId,
                'to_guide_id' => $toGuideId,
                'handed_over_at' => $banGiaoLuc,
                'reason' => trim($reason),
                'handover_note' => trim($note),
                'created_by' => $actor?->getKey(),
            ]);

            $this->auditLogger->log(
                $khoa,
                ScheduleAuditAction::GuideHandover,
                ['guide_id' => $fromGuideId],
                [
                    'guide_id' => $toGuideId,
                    'handed_over_at' => $banGiaoLuc->toDateTimeString(),
                    'handover_id' => $bienBan->getKey(),
                ],
                trim($reason),
                $actor,
            );

            return $bienBan->fresh(['fromGuide:id,name,phone', 'toGuide:id,name,phone']);
        });

        /*
         * Báo người nhận, sau khi giao dịch đã chốt.
         *
         * Đây là thông báo gấp nhất phía hướng dẫn viên: họ vừa nhận một đoàn có thể đang trên
         * đường, và cho tới lúc mở màn hình ra thì không biết gì cả. Đoạn "tình trạng đoàn" đi
         * kèm chính là thứ họ cần để bắt nhịp.
         *
         * Ngoài giao dịch, cùng lý do đang áp ở `ScheduleCancellationService`: thông báo đã gửi
         * thì không gọi về được, còn giao dịch thì vẫn có thể quay lại.
         */
        $schedule->loadMissing('tour:id,title');

        $this->notifier->toiNguoiDung(
            $bienBan->toGuide,
            Alert::NHAN_BAN_GIAO,
            sprintf('Bạn vừa nhận đoàn của chuyến #%d', $schedule->getKey()),
            sprintf(
                '%s · từ %s · %s',
                $schedule->tour?->title ?? 'Tour',
                $bienBan->fromGuide?->name ?? 'hướng dẫn viên trước',
                $bienBan->handover_note,
            ),
            '/guide/handovers',
        );

        return $bienBan;
    }

    /**
     * Đoàn đang trên đường thì không được để trống người phụ trách.
     *
     * Chuyến chưa khởi hành thì đổi ai cũng được: người mới còn thời gian tới điểm tập kết, và
     * đoàn chưa có gì để bỏ rơi.
     *
     * Chuyến **đang chạy** thì khác hẳn. Gỡ người dẫn duy nhất ra khỏi một đoàn đang giữa đường
     * nghĩa là đoàn không có ai trong suốt quãng người mới di chuyển tới - có thể vài giờ, và đó
     * là lúc khách cần người nhất. Trên giấy tờ thì "đã bàn giao", ngoài thực địa thì ba mươi
     * khách đứng ở bến tàu không biết hỏi ai.
     *
     * Nên luật là: đang chạy thì chuyến phải có **từ hai hướng dẫn viên trở lên** mới bàn giao
     * được. Không chặn vĩnh viễn, chỉ ép đúng thứ tự: bổ sung người trước, bàn giao sau.
     */
    private function assertDoanKhongBiBoRoi(TourSchedule $schedule): void
    {
        if ($this->lifecycle->effectiveStatus($schedule) !== ScheduleStatus::InProgress) {
            return;
        }

        if ($schedule->guides()->count() >= 2) {
            return;
        }

        throw new BusinessRuleException(
            'Đoàn đang trên đường và chuyến này chỉ có một hướng dẫn viên. Gỡ người đó ra thì đoàn '
            . 'không có ai cho tới khi người mới tới nơi. Hãy phân công thêm một người cho chuyến '
            . 'trước, rồi bàn giao.',
        );
    }

    /**
     * Chuyến còn bàn giao được không.
     *
     * Đã kết thúc hoặc đã hủy thì từ chối: không còn gì để dẫn, và ghi thêm một lần đổi người vào
     * lịch sử của chuyến đã xong chỉ làm nhiễu dữ liệu đối chiếu.
     */
    private function assertChuyenConBanGiaoDuoc(TourSchedule $schedule): void
    {
        $trangThai = $this->lifecycle->effectiveStatus($schedule);

        if ($trangThai === ScheduleStatus::Cancelled || $trangThai === ScheduleStatus::Completed) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đang ở trạng thái "%s" nên không bàn giao được nữa.',
                $trangThai->label(),
            ));
        }
    }

    /**
     * Hướng dẫn viên gửi phiếu xin được thay.
     *
     * Không nhận người thay: tìm ai đang rảnh cần nhìn toàn bộ lịch công ty, đó là việc của điều
     * hành. Hướng dẫn viên nói "tôi cần được thay" chứ không phải "giao cho anh B".
     *
     * Luật "đoàn không bị bỏ rơi" **không áp ở đây**, chỉ áp lúc thực hiện bàn giao. Người đang
     * ốm vẫn phải gửi phiếu được; chặn từ đầu là bịt miệng người đang cần giúp.
     */
    public function request(
        TourSchedule $schedule,
        User $guide,
        string $reason,
        string $groupState,
    ): GuideHandoverRequest {
        $this->assertChuyenConBanGiaoDuoc($schedule);

        if (!$schedule->hasGuide((int) $guide->getKey())) {
            throw new BusinessRuleException('Bạn không phụ trách chuyến này nên không xin bàn giao được.');
        }

        $daCo = GuideHandoverRequest::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->where('requested_by', $guide->getKey())
            ->dangCho()
            ->exists();

        if ($daCo) {
            throw new BusinessRuleException(
                'Bạn đã có một yêu cầu bàn giao đang chờ duyệt cho chuyến này.',
            );
        }

        return GuideHandoverRequest::query()->create([
            'tour_schedule_id' => $schedule->getKey(),
            'requested_by' => $guide->getKey(),
            'status' => HandoverRequestStatus::Pending,
            'reason' => trim($reason),
            'group_state' => trim($groupState),
        ]);
    }

    /**
     * Điều hành xử lý phiếu bằng cách chỉ định người mới.
     *
     * Đi chung đúng một đường với việc điều hành tự bàn giao: gọi `handover()` ở trên. Hai đường
     * ghi cho cùng một việc, mỗi đường một bộ luật, là khuôn của phần lớn lỗi ở dự án này.
     *
     * Lý do và tình trạng đoàn lấy nguyên từ phiếu — đó là chữ của người đang đứng cùng đoàn,
     * không phải của người ngồi văn phòng.
     */
    public function resolveWithHandover(
        GuideHandoverRequest $request,
        int $toGuideId,
        ?string $note,
        User $actor,
    ): GuideHandover {
        return DB::transaction(function () use ($request, $toGuideId, $note, $actor) {
            $khoa = GuideHandoverRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            $this->assertConDangCho($khoa);

            $schedule = TourSchedule::query()->with('tour')->find($khoa->tour_schedule_id);

            if (!$schedule) {
                throw new BusinessRuleException('Không tìm thấy chuyến khởi hành.', 404);
            }

            $bienBan = $this->handover(
                $schedule,
                (int) $khoa->requested_by,
                $toGuideId,
                $khoa->reason,
                $khoa->group_state,
                null,
                $actor,
            );

            $this->dongPhieu($khoa, $note, $actor, $bienBan->getKey());

            return $bienBan;
        });
    }

    /**
     * Đóng phiếu mà không đổi người.
     *
     * Gộp hai việc từng là hai trạng thái riêng: điều hành không đồng ý, hoặc hướng dẫn viên đỡ
     * rồi nên thôi. Khác nhau ở câu ghi chú, không cần thành hai nhánh mà mọi màn hình phải biết
     * phân biệt.
     *
     * Người gửi giữ nguyên quyền phụ trách — không có khoảnh khắc nào đoàn thiếu người chịu
     * trách nhiệm trên hệ thống.
     */
    public function close(GuideHandoverRequest $request, string $note, User $actor): GuideHandoverRequest
    {
        $this->assertConDangCho($request);

        $this->dongPhieu($request, $note, $actor, null);

        return $request->fresh();
    }

    private function dongPhieu(
        GuideHandoverRequest $request,
        ?string $note,
        User $actor,
        ?int $handoverId,
    ): void {
        $request->forceFill([
            'status' => HandoverRequestStatus::Closed,
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note ? trim($note) : null,
            'guide_handover_id' => $handoverId,
        ])->save();

        /*
         * Báo người gửi phiếu.
         *
         * Họ xin được giúp và đang chờ câu trả lời. Không đổi người thì càng phải báo — im lặng
         * để họ tự đoán là kiểu tệ nhất, vì đoàn vẫn đang là của họ mà không ai nói ra.
         *
         * Không gửi khi chính họ là người xử lý: điều hành tự bàn giao thẳng thì phiếu đóng theo,
         * và báo cho chính người vừa bấm là thừa.
         */
        if ((int) $request->requested_by === (int) $actor->getKey()) {
            return;
        }

        $this->notifier->toiNguoiDung(
            $request->requester,
            Alert::PHIEU_DA_XU_LY,
            $handoverId
                ? sprintf('Chuyến #%d đã có người thay', $request->tour_schedule_id)
                : sprintf('Phiếu bàn giao chuyến #%d đã đóng', $request->tour_schedule_id),
            $note
                ? trim($note)
                : 'Điều hành đã xử lý phiếu của bạn.',
            '/guide/handovers',
        );
    }

    private function assertConDangCho(?GuideHandoverRequest $request): void
    {
        if (!$request) {
            throw new BusinessRuleException('Không tìm thấy phiếu bàn giao.', 404);
        }

        if ($request->status !== HandoverRequestStatus::Pending) {
            throw new BusinessRuleException('Phiếu này đã được xử lý rồi.');
        }
    }

    /**
     * Lịch sử bàn giao của một chuyến, cũ trước mới sau.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, GuideHandover>
     */
    public function lichSu(TourSchedule $schedule)
    {
        return GuideHandover::query()
            ->where('tour_schedule_id', $schedule->getKey())
            ->with(['fromGuide:id,name,phone', 'toGuide:id,name,phone', 'creator:id,name'])
            ->orderBy('handed_over_at')
            ->orderBy('id')
            ->get();
    }
}
