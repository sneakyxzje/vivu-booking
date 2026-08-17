<?php

namespace App\Services;

use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\GuideHandover;
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
