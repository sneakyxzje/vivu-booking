<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\TourSchedule;
use App\Models\User;
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

        return DB::transaction(function () use ($schedule, $ids) {
            // Khóa từng hướng dẫn viên để hai lần phân công song song cho cùng một người không
            // cùng đọc thấy "chưa có lịch nào" rồi cùng ghi.
            if ($ids->isNotEmpty()) {
                User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            }

            $huongDanVien = $this->assertValidGuides($ids->all());

            [$start, $end] = $this->periodOf($schedule);

            foreach ($ids as $guideId) {
                $vuong = $this->conflictFor($guideId, $start, $end, $schedule->getKey());

                if ($vuong) {
                    throw new BusinessRuleException($this->moTaTrungLich(
                        $huongDanVien[$guideId]->name,
                        $vuong,
                    ));
                }
            }

            $schedule->guides()->sync($ids->all());

            return $schedule->fresh(['guides:id,name,email,phone,status']);
        });
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
     * Khoảng ngày chuyến chiếm chỗ của hướng dẫn viên.
     *
     * Lấy theo số ngày của tour chứ không theo end_date, vì chuyến cũ có thể chưa đặt end_date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodOf(TourSchedule $schedule): array
    {
        $start = Carbon::parse($schedule->start_date)->startOfDay();
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
