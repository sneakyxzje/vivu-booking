<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Enums\ScheduleStatus;
use App\Models\GuideAssignmentDecline;
use App\Models\TourSchedule;
use App\Models\User;
use App\Notifications\Alert;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phân công hướng dẫn viên cho chuyến khởi hành.
 *
 * Một chuyến nhận nhiều người. Đoàn đông thì một người không kham nổi: điểm danh ở nhiều điểm
 * dừng cùng lúc, khách tách nhóm khi tham quan, có khi thêm cả xe thứ hai đi cùng tuyến.
 *
 * Hệ thống cố ý KHÔNG suy ra cần bao nhiêu người cho bao nhiêu khách. Tỷ lệ ấy khác nhau theo
 * loại tour, theo tuyến, theo cách từng công ty vận hành - đặt một con số cứng ở đây là áp một
 * giá trị do lập trình viên nghĩ ra lên mọi tour. Điều hành tự quyết.
 *
 * Luật duy nhất còn lại là luật vật lý: **một người không đứng ở hai đoàn cùng lúc**.
 *
 * Gom vào một chỗ vì trước đây phép kiểm chồng lịch nằm ở hai nơi - lúc lưu tour và lúc phân
 * công lẻ - với hai đoạn mã gần giống nhau. Sửa một bên quên bên kia là chuyện sớm muộn.
 */
class ScheduleGuideService
{
    public function __construct(
        private readonly ScheduleLifecycleService $lifecycle,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * Đặt lại danh sách hướng dẫn viên của một chuyến.
     *
     * Được ăn cả ngã về không: một người vướng lịch thì cả lần phân công bị từ chối, chứ không
     * gán được ai thì gán. Gán một nửa rồi báo lỗi sẽ để lại trạng thái không ai chủ ý tạo ra.
     *
     * @param  array<int, int|string>  $guideIds
     */
    public function sync(TourSchedule $schedule, array $guideIds): TourSchedule
    {
        $ids = collect($guideIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        // Ai đang có mặt trước khi sửa. So với danh sách sau để biết ai là người MỚI được thêm.
        $truocDo = $schedule->guides()->pluck('users.id');

        $daSua = DB::transaction(function () use ($schedule, $ids) {
            // Khóa từng hướng dẫn viên để hai lần phân công song song cho cùng một người không
            // cùng đọc thấy "chưa có lịch nào" rồi cùng ghi.
            if ($ids->isNotEmpty()) {
                User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            }

            $huongDanVien = $this->assertValidGuides($ids->all());

            [$start, $end] = $this->periodOf($schedule);

            foreach ($ids as $guideId) {
                $chanVi = $this->lyDoChan($huongDanVien[$guideId], $start, $end, $schedule->getKey());

                if ($chanVi) {
                    throw new BusinessRuleException($chanVi);
                }
            }

            /*
             * Giữ lại mốc đã xác nhận của những người vẫn còn trong danh sách.
             *
             * sync() ghi lại toàn bộ hàng, nên nếu không chép accepted_at sang thì mỗi lần điều
             * hành sửa danh sách - kể cả chỉ thêm một người khác - là mọi người đang có trong
             * chuyến bị coi như chưa xác nhận lại từ đầu.
             */
            $daXacNhan = $schedule->guides()
                ->get()
                ->mapWithKeys(fn (User $g) => [$g->getKey() => $g->pivot->accepted_at]);

            $schedule->guides()->sync(
                $ids->mapWithKeys(fn (int $id) => [$id => ['accepted_at' => $daXacNhan[$id] ?? null]])->all(),
            );

            return $schedule->fresh(['guides:id,name,email,phone,status']);
        });

        $this->baoNguoiMoiDuocPhanCong($daSua, $ids->diff($truocDo));

        return $daSua;
    }

    /**
     * Báo cho người vừa được thêm vào chuyến.
     *
     * Chỉ người **mới**, không phải cả danh sách. Điều hành sửa danh sách vì nhiều lý do — thêm
     * người thứ hai, bớt một người — và bắn lại thông báo cho những ai vốn đã ở trong chuyến là
     * cách nhanh nhất để họ ngừng đọc thông báo.
     *
     * Gọi **sau** giao dịch: thư và thông báo đã gửi thì không gọi về được, còn giao dịch thì vẫn
     * có thể quay lại. Cùng lý do đang áp ở `ScheduleCancellationService`.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $nguoiMoi
     */
    private function baoNguoiMoiDuocPhanCong(TourSchedule $schedule, $nguoiMoi): void
    {
        if ($nguoiMoi->isEmpty()) {
            return;
        }

        $schedule->loadMissing('tour:id,title');

        foreach (User::query()->whereIn('id', $nguoiMoi)->get() as $guide) {
            $this->notifier->toiNguoiDung(
                $guide,
                Alert::PHAN_CONG,
                sprintf('Bạn được phân công chuyến #%d', $schedule->getKey()),
                sprintf(
                    '%s · khởi hành %s',
                    $schedule->tour?->title ?? 'Tour',
                    $schedule->start_date?->format('d/m/Y H:i') ?? 'chưa rõ',
                ),
                '/guide/assignments',
            );
        }
    }

    /** Hướng dẫn viên xác nhận sẽ dẫn chuyến này. */
    public function acceptAssignment(TourSchedule $schedule, User $guide): void
    {
        if (!$schedule->hasGuide((int) $guide->getKey())) {
            throw new BusinessRuleException('Bạn không được phân công chuyến này.');
        }

        $schedule->guides()->updateExistingPivot($guide->getKey(), ['accepted_at' => now()]);
    }

    /**
     * Hướng dẫn viên từ chối chuyến được phân công, kèm lý do.
     *
     * Gỡ luôn khỏi danh sách phân công: chuyến chưa khởi hành nên không bỏ rơi ai, và để tên một
     * người đã nói "tôi không đi" nằm trong danh sách chỉ khiến điều hành tưởng đã có người.
     *
     * Chuyến đã lên đường thì không từ chối được. Lúc đó rút lui là **bàn giao** chứ không phải
     * từ chối: phải có người nhận trước khi người cũ rời, nếu không đoàn không còn ai. Luồng ấy
     * đã có ở yêu cầu bàn giao.
     */
    public function declineAssignment(TourSchedule $schedule, User $guide, string $lyDo): void
    {
        if (!$schedule->hasGuide((int) $guide->getKey())) {
            throw new BusinessRuleException('Bạn không được phân công chuyến này.');
        }

        $trangThai = $this->lifecycle->effectiveStatus($schedule);

        if ($trangThai->isRunning() || $trangThai->isFinal()) {
            throw new BusinessRuleException(sprintf(
                'Chuyến đang ở trạng thái "%s" nên không từ chối được nữa. Nếu bạn không dẫn tiếp '
                . 'được, hãy gửi yêu cầu bàn giao để điều hành cử người thay trước khi bạn rời đoàn.',
                $trangThai->label(),
            ));
        }

        DB::transaction(function () use ($schedule, $guide, $lyDo) {
            GuideAssignmentDecline::query()->create([
                'tour_schedule_id' => $schedule->getKey(),
                'guide_id' => $guide->getKey(),
                'reason' => trim($lyDo),
                'declined_at' => now(),
            ]);

            $schedule->guides()->detach($guide->getKey());
        });
    }

    /**
     * Vì sao không phân công được người này cho chuyến, hoặc null nếu được.
     *
     * **Một hàm cho cả hai phía.** `sync()` ném ngoại lệ với đúng câu này, còn màn hình chọn người
     * hiện đúng câu này bên cạnh cái tên. Lỗi lặp lại nhiều lần trong dự án luôn cùng một khuôn:
     * luật có ở đường ghi mà thiếu ở đường đọc, nên người dùng bấm xong mới biết không được. Gộp
     * vào một chỗ thì hai phía không lệch nhau được nữa.
     *
     * **Đúng một luật, và cố ý chỉ một:** trùng lịch - luật vật lý, một người không đứng ở hai
     * đoàn cùng lúc. Đây là thứ hệ thống biết chắc chắn và người dùng không thể muốn khác.
     *
     * Chuyên môn, tuyến quen, sức dẫn, tải công việc đều **không** ở đây. Chúng chỉ xếp thứ tự ở
     * `GuideSuitabilityService` - xem lý do đầy đủ trong lớp đó.
     *
     * Hàm vẫn tồn tại dù chỉ còn một luật, vì giá trị của nó không nằm ở số lượng luật mà ở chỗ
     * **cả đường ghi lẫn đường đọc cùng hỏi một câu**. Thêm luật mới sau này thì thêm vào đây,
     * hai phía tự khớp.
     */
    public function lyDoChan(User $guide, Carbon $start, Carbon $end, ?int $boQuaChuyen = null): ?string
    {
        $vuong = $this->conflictFor($guide->getKey(), $start, $end, $boQuaChuyen);

        return $vuong ? $this->moTaTrungLich($guide->name, $vuong) : null;
    }

    /**
     * Chuyến đang chồng lịch của hướng dẫn viên này, hoặc null nếu rảnh.
     *
     * So sánh theo ngày chứ không theo giờ: đoàn về lúc 22h thì hôm đó người dẫn coi như đã bận
     * cả ngày, không nhận tiếp chuyến khác khởi hành cùng ngày.
     */
    public function conflictFor(
        int $guideId,
        Carbon $start,
        Carbon $end,
        ?int $boQuaChuyen = null,
    ): ?TourSchedule {
        return TourSchedule::query()
            ->with('tour:id,title,number_of_days')
            ->whereHas('guides', fn ($query) => $query->whereKey($guideId))
            ->when($boQuaChuyen, fn ($query) => $query->whereKeyNot($boQuaChuyen))
            ->get()
            ->first(fn (TourSchedule $khac) => self::overlaps($start, $end, ...$this->periodOf($khac)));
    }

    /**
     * @param  array<int, int>  $guideIds
     * @return Collection<int, User>
     */
    public function assertValidGuides(array $guideIds): Collection
    {
        if ($guideIds === []) {
            return collect();
        }

        $nguoi = User::query()->whereIn('id', $guideIds)->get()->keyBy('id');

        foreach ($guideIds as $id) {
            $guide = $nguoi->get($id);

            if (!$guide || $guide->role !== 'guide' || $guide->status !== 'active') {
                throw new BusinessRuleException(
                    'Hướng dẫn viên không hợp lệ hoặc đang ngừng hoạt động.',
                );
            }
        }

        return $nguoi;
    }

    /**
     * Khoảng thời gian chuyến chiếm chỗ của hướng dẫn viên.
     *
     * Đọc `end_date` khi có, chỉ suy từ số ngày của tour khi cột ấy rỗng.
     *
     * Trước đây hàm này **luôn** suy từ số ngày, với lý do "chuyến cũ có thể chưa đặt end_date".
     * Lý do ấy hết đúng từ hai phía: migration `2026_08_11_000002` đã điền `end_date` cho toàn bộ
     * hàng cũ, và nay điều hành đặt thẳng mốc kết thúc cho từng chuyến.
     *
     * Hậu quả của bản cũ là một lỗ thật: chuyến đi xe đêm, khởi hành 22h và trả khách 5h sáng hôm
     * sau, kết thúc vào ngày thứ tư của một tour ba ngày. Tính theo số ngày thì hướng dẫn viên
     * được coi là rảnh từ ngày thứ tư, nên xếp được họ vào một chuyến khác khởi hành sáng hôm ấy —
     * trong lúc họ vẫn đang trên xe về.
     *
     * Bốn đường cùng gọi hàm này: xếp người (`conflictFor`), bàn giao giữa chuyến, màn "ai phù
     * hợp", và danh sách người rảnh ở biểu mẫu tour. Sửa ở đây là sửa cả bốn.
     *
     * Mốc đầu vẫn `startOfDay`: một người đã đi chuyến khởi hành 22h thì cả ngày hôm đó coi như
     * bận, không nhận thêm chuyến sáng cùng ngày. Mốc cuối giữ nguyên giờ thật, để một chuyến về
     * chiều không chặn mất chuyến khởi hành ngày hôm sau.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodOf(TourSchedule $schedule): array
    {
        $start = Carbon::parse($schedule->start_date)->startOfDay();

        if (!empty($schedule->end_date)) {
            return [$start, Carbon::parse($schedule->end_date)];
        }

        $soNgay = max(1, (int) ($schedule->tour?->number_of_days ?? 1));

        return [$start, $start->copy()->addDays($soNgay - 1)];
    }

    public static function overlaps(Carbon $start, Carbon $end, Carbon $khacStart, Carbon $khacEnd): bool
    {
        return $start->lte($khacEnd) && $end->gte($khacStart);
    }

    public function moTaTrungLich(string $tenGuide, TourSchedule $vuong): string
    {
        [$start, $end] = $this->periodOf($vuong);

        return sprintf(
            '%s đã có chuyến "%s" từ %s đến %s.',
            $tenGuide,
            $vuong->tour?->title ?? 'không rõ tên',
            $start->format('d/m/Y'),
            $end->format('d/m/Y'),
        );
    }
}
