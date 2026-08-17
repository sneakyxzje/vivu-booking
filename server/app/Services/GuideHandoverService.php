<?php

namespace App\Services;

use App\Enums\HandoverRequestStatus;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\GuideHandover;
use App\Models\GuideHandoverRequest;
use App\Models\TourSchedule;
use App\Models\User;
use App\Support\GioVietNam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Đổi hướng dẫn viên giữa chừng chuyến, kèm biên bản bàn giao.
 *
 * Đổi người vốn đã làm được: chỉ cần sửa danh sách phân công. Thứ thiếu là **vết**. Người mới
 * nhận đoàn mà không biết đoàn đang ở đâu, ai đã điểm danh tới chặng nào, khách nào cần để ý; và
 * khi có khiếu nại về một chặng thì không tra được lúc ấy ai phụ trách.
 *
 * Nên bàn giao ở đây không phải một lần sửa danh sách, mà là một thao tác riêng bắt buộc kèm hai
 * thứ: **lý do** và **tình trạng đoàn**. Bỏ trống thì không bàn giao được.
 *
 * Ba điều service này giữ:
 *
 *   1. Người cũ **mất quyền ghi ngay**, vì bị gỡ khỏi danh sách phân công. Không có trạng thái
 *      lửng lơ nào mà cả hai cùng ghi được.
 *   2. Người mới **không được trùng lịch** - cùng luật đang áp cho phân công thường, và cũng vì
 *      cùng lý do vật lý: một người không đứng ở hai đoàn cùng lúc.
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
        return DB::transaction(function () use ($schedule, $fromGuideId, $toGuideId, $reason, $note, $luc, $actor) {
            $khoa = TourSchedule::query()
                ->whereKey($schedule->getKey())
                ->with('tour')
                ->lockForUpdate()
                ->first();

            if (!$khoa) {
                throw new BusinessRuleException('Không tìm thấy chuyến khởi hành.', 404);
            }

            $this->assertChuyenConBanGiaoDuoc($khoa);
            $this->assertDoanKhongBiBoRoi($khoa);

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

            // Cùng luật với phân công thường: một người không đứng ở hai đoàn cùng lúc.
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
    }

    /**
     * Đoàn đang trên đường thì không được để trống người phụ trách.
     *
     * Chuyến chưa khởi hành thì đổi ai cũng được: người mới còn thời gian tới điểm tập kết, và
     * đoàn chưa có gì để bỏ rơi.
     *
     * Chuyến **đang chạy** thì khác hẳn. Gỡ người dẫn duy nhất ra khỏi một đoàn đang giữa đường
     * nghĩa là đoàn không có ai trong suốt quãng thời gian người mới di chuyển tới - có thể vài
     * giờ, và đó là lúc khách cần người nhất. Trên giấy tờ thì "đã bàn giao", ngoài thực địa thì
     * ba mươi khách đứng ở bến tàu không biết hỏi ai.
     *
     * Nên luật là: đang chạy thì chuyến phải có **từ hai hướng dẫn viên trở lên** mới bàn giao
     * được, để sau khi một người rời đi vẫn còn người đang có mặt bên đoàn.
     *
     * Luật này **không chặn vĩnh viễn** mà chỉ ép đúng thứ tự: bổ sung người trước, bàn giao sau.
     * Và nó chỉ áp lúc thực hiện, không áp lúc hướng dẫn viên gửi yêu cầu - người đang ốm vẫn
     * phải xin được, chặn từ đầu là bịt miệng người đang cần giúp.
     */
    private function assertDoanKhongBiBoRoi(TourSchedule $schedule): void
    {
        if ($this->lifecycle->effectiveStatus($schedule) !== ScheduleStatus::InProgress) {
            return;
        }

        $soNguoiDang = $schedule->guides()->count();

        if ($soNguoiDang < 2) {
            throw new BusinessRuleException(
                'Đoàn đang trên đường và chuyến này chỉ có một hướng dẫn viên. Gỡ người đó ra thì '
                . 'đoàn không có ai cho tới khi người mới tới nơi. Hãy phân công thêm một người '
                . 'cho chuyến trước, rồi mới bàn giao.',
            );
        }
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
     * Hướng dẫn viên xin được bàn giao đoàn.
     *
     * Không nhận người thay: tìm ai đang rảnh cần nhìn toàn bộ lịch công ty, đó là việc của điều
     * hành. Hướng dẫn viên nói "tôi cần được thay" chứ không phải "giao cho anh B".
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
     * Điều hành duyệt: chọn người thay rồi thực hiện bàn giao.
     *
     * **Duyệt không tự thực hiện bàn giao.** Nó gọi đúng handover() ở trên, tức đi chung một
     * đường với việc điều hành tự bàn giao. Hai đường ghi cho cùng một việc, mỗi đường một bộ
     * luật, là khuôn của phần lớn lỗi ở dự án này - nên ở đây cố ý chỉ có một.
     *
     * Lý do và tình trạng đoàn lấy nguyên từ yêu cầu: đó là chữ của người đang đứng cùng đoàn,
     * không phải của người ngồi văn phòng.
     */
    public function approveRequest(
        GuideHandoverRequest $request,
        int $toGuideId,
        ?string $reviewNote,
        User $actor,
    ): GuideHandover {
        return DB::transaction(function () use ($request, $toGuideId, $reviewNote, $actor) {
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

            $khoa->forceFill([
                'status' => HandoverRequestStatus::Approved,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_note' => $reviewNote ? trim($reviewNote) : null,
                'guide_handover_id' => $bienBan->getKey(),
            ])->save();

            return $bienBan;
        });
    }

    /**
     * Từ chối yêu cầu.
     *
     * Người xin vẫn giữ nguyên quyền phụ trách - đó là điểm an toàn của việc phải chờ duyệt:
     * không có khoảnh khắc nào đoàn không có ai chịu trách nhiệm.
     */
    public function rejectRequest(
        GuideHandoverRequest $request,
        string $reviewNote,
        User $actor,
    ): GuideHandoverRequest {
        $this->assertConDangCho($request);

        $request->forceFill([
            'status' => HandoverRequestStatus::Rejected,
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => now(),
            'review_note' => trim($reviewNote),
        ])->save();

        return $request->fresh();
    }

    /** Hướng dẫn viên rút lại, ví dụ đỡ sốt rồi vẫn dẫn tiếp được. */
    public function withdrawRequest(GuideHandoverRequest $request, User $guide): GuideHandoverRequest
    {
        $this->assertConDangCho($request);

        if ((int) $request->requested_by !== (int) $guide->getKey()) {
            throw new BusinessRuleException('Chỉ người gửi mới rút lại được yêu cầu này.');
        }

        $request->forceFill(['status' => HandoverRequestStatus::Withdrawn])->save();

        return $request->fresh();
    }

    private function assertConDangCho(?GuideHandoverRequest $request): void
    {
        if (!$request) {
            throw new BusinessRuleException('Không tìm thấy yêu cầu bàn giao.', 404);
        }

        if ($request->status !== HandoverRequestStatus::Pending) {
            throw new BusinessRuleException(sprintf(
                'Yêu cầu này đang ở trạng thái "%s" nên không xử lý lại được.',
                $request->status->label(),
            ));
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
