<?php

namespace App\Services;

use App\Models\TourSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * "Ai phù hợp dẫn chuyến này" - khác với "ai đang rảnh".
 *
 * Rảnh mới là điều kiện cần. Một người rảnh nhưng chưa từng đi tuyến đó, hoặc quen dẫn đoàn nghỉ
 * dưỡng mà bị xếp tour leo núi, vẫn là lựa chọn kém hơn người khác. Trước đây hệ thống chỉ trả
 * lời được vế đầu.
 *
 * ## Ranh giới quan trọng: cái gì chặn, cái gì chỉ nói
 *
 * Lớp này **không chặn gì cả**. Việc chặn nằm trọn ở `ScheduleGuideService::lyDoChan()` - cùng
 * một hàm mà đường ghi dùng để ném lỗi và màn hình này dùng để hiện lý do. Lỗi lặp đi lặp lại
 * trong dự án luôn cùng một khuôn: luật có ở đường ghi mà thiếu ở đường đọc, nên người dùng bấm
 * xong mới biết không được. Ở đây một hàm phục vụ cả hai phía nên không lệch được.
 *
 * Mọi thứ lớp này tính - chuyên môn, tuyến quen, ngôn ngữ, tải công việc, sức dẫn - **chỉ xếp thứ tự
 * và nói ra lý do**. Điều hành biết những thứ hệ thống không biết: người này vừa nhận lời đi tuyến
 * đó tuần trước, người kia đang muốn học tuyến mới. Chấm điểm rồi tự chặn theo điểm là thay họ
 * quyết định bằng một con số do lập trình viên nghĩ ra.
 *
 * Điểm số vì thế luôn đi kèm **lý do đọc được**. Một danh sách xếp hạng mà không nói vì sao thì
 * người dùng hoặc tin mù, hoặc bỏ qua - cả hai đều tệ hơn là không xếp hạng.
 *
 * ## Hồ sơ trống thì sao
 *
 * Người chưa khai hồ sơ không mất điểm và không bị đánh dấu gì cả - họ chỉ không được cộng điểm
 * nào, nên nằm giữa danh sách. Phạt người chưa khai là phạt nhầm đối tượng: lỗi ở chỗ chưa ai
 * nhập dữ liệu, không phải ở người hướng dẫn.
 */
class GuideSuitabilityService
{
    /** Số ngày trước và sau chuyến dùng để ước lượng tải công việc. */
    private const CUA_SO_TAI = 30;

    public function __construct(
        private readonly ScheduleGuideService $guides,
    ) {
    }

    /**
     * Chấm cả đội ngũ cho một chuyến, xếp người hợp nhất lên đầu.
     *
     * Trả về đủ cả người bị chặn: giấu đi thì điều hành đi tìm mãi một cái tên đáng lẽ phải thấy,
     * và không hiểu vì sao nó biến mất. Hiện ra kèm lý do thì họ biết phải sửa gì.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function danhGia(TourSchedule $schedule): Collection
    {
        [$batDau, $ketThuc] = $this->guides->periodOf($schedule);

        $loaiHinhTour = $schedule->tour?->categories->pluck('id')->all() ?? [];
        $diemDen = trim((string) ($schedule->tour?->end_location ?? ''));
        $daPhanCong = $schedule->guides->pluck('id')->all();

        return User::query()
            ->where('role', 'guide')
            ->where('status', 'active')
            ->with(['guideProfile', 'guideCategories:id,name', 'assignedSchedules.tour:id,number_of_days'])
            // Người chưa khai hồ sơ vẫn nằm trong danh sách, chỉ là không được cộng điểm nào.
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'status'])
            ->map(fn (User $guide) => $this->chamMotNguoi(
                $guide,
                $schedule,
                $batDau,
                $ketThuc,
                $loaiHinhTour,
                $diemDen,
                in_array($guide->getKey(), $daPhanCong, true),
            ))
            // Người đã ở trong chuyến giữ nguyên đầu danh sách, rồi tới điểm cao, rồi mới tới tên.
            ->sortBy([
                fn (array $a, array $b) => ($b['assigned'] <=> $a['assigned']),
                fn (array $a, array $b) => ($b['score'] <=> $a['score']),
                fn (array $a, array $b) => strcmp($a['name'], $b['name']),
            ])
            ->values();
    }

    /**
     * @param  array<int, int>  $loaiHinhTour
     * @return array<string, mixed>
     */
    private function chamMotNguoi(
        User $guide,
        TourSchedule $schedule,
        Carbon $batDau,
        Carbon $ketThuc,
        array $loaiHinhTour,
        string $diemDen,
        bool $daPhanCong,
    ): array {
        $hoSo = $guide->guideProfile;

        $diem = 0;
        $hopO = [];
        $canBiet = [];
        $chanVi = null;

        /*
         * Điều kiện chặn - hỏi thẳng đường ghi, không tự dựng lại.
         *
         * Bỏ qua chính chuyến này khi xét trùng lịch, nếu không người đang dẫn nó lại bị báo là
         * vướng lịch với chính mình.
         */
        $chanVi = $this->guides->lyDoChan($guide, $batDau, $ketThuc, $schedule->getKey());

        // --- Điểm cộng: chuyên môn và tuyến quen ---------------------------------------------

        $chuyenMonKhop = $guide->guideCategories
            ->whereIn('id', $loaiHinhTour)
            ->pluck('name')
            ->all();

        foreach ($chuyenMonKhop as $ten) {
            $diem += 3;
            $hopO[] = 'Chuyên ' . $ten;
        }

        if ($diemDen !== '' && $hoSo && $this->quenTuyen($hoSo->regions ?? [], $diemDen)) {
            $diem += 3;
            $hopO[] = 'Quen tuyến ' . $diemDen;
        }

        // --- Điểm trừ: đang gánh bao nhiêu chuyến quanh ngày đó -------------------------------

        $tai = $this->demChuyenGan($guide, $batDau);
        $diem -= $tai;

        if ($tai >= 3) {
            $canBiet[] = sprintf('Đang có %d chuyến khác trong khoảng một tháng quanh ngày này.', $tai);
        }

        // --- Cảnh báo sức dẫn -----------------------------------------------------------------

        /*
         * So với số khách đã đặt, không so với sức chứa của chuyến.
         *
         * Sức chứa là con số giả định: chuyến 40 chỗ mới bán 5 vé mà đã kêu quá sức dẫn thì cảnh
         * báo kêu suốt và người ta thôi đọc. Còn số đã đặt là chuyện có thật.
         *
         * Cũng không tự chia sức dẫn cho số người đang phân công: đoàn 60 khách hai người dẫn có
         * đủ hay không là việc điều hành biết rõ hơn hệ thống.
         */
        if ($hoSo?->max_group_size && $schedule->booked_people > $hoSo->max_group_size) {
            $canBiet[] = sprintf(
                'Sức dẫn khai báo %d khách, đoàn hiện có %d.',
                $hoSo->max_group_size,
                $schedule->booked_people,
            );
        }

        return [
            'id' => $guide->getKey(),
            'name' => $guide->name,
            'phone' => $guide->phone,
            'assigned' => $daPhanCong,
            'blocked_reason' => $chanVi,
            'score' => $diem,
            'matches' => $hopO,
            'warnings' => $canBiet,
            'workload' => $tai,
            // Chỉ để xem: tour chưa có ô khai ngôn ngữ đoàn cần nên không có gì để so khớp.
            'languages' => $hoSo?->languages ?? [],
        ];
    }

    /**
     * Tuyến quen có khớp điểm đến không.
     *
     * So không phân biệt hoa thường và cho khớp một phần, vì người ta khai "Hạ Long" còn tour ghi
     * "Vịnh Hạ Long". Cố ý dễ dãi: đây là gợi ý xếp thứ tự, khớp nhầm thì thừa một dòng chữ chứ
     * không chặn ai.
     *
     * @param  array<int, string>  $tuyenQuen
     */
    private function quenTuyen(array $tuyenQuen, string $diemDen): bool
    {
        $dich = mb_strtolower($diemDen);

        foreach ($tuyenQuen as $tuyen) {
            $nguon = mb_strtolower(trim((string) $tuyen));

            if ($nguon !== '' && (str_contains($dich, $nguon) || str_contains($nguon, $dich))) {
                return true;
            }
        }

        return false;
    }

    /** Số chuyến khác người này đang dẫn trong khoảng một tháng quanh ngày khởi hành. */
    private function demChuyenGan(User $guide, Carbon $batDau): int
    {
        $tu = $batDau->copy()->subDays(self::CUA_SO_TAI);
        $den = $batDau->copy()->addDays(self::CUA_SO_TAI);

        return $guide->assignedSchedules
            ->filter(function (TourSchedule $khac) use ($tu, $den) {
                $ngay = Carbon::parse($khac->start_date)->startOfDay();

                return $ngay->betweenIncluded($tu, $den);
            })
            ->count();
    }
}
