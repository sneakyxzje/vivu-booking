<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\ScheduleGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Khoảng thời gian một chuyến chiếm chỗ của hướng dẫn viên.
 *
 * Luật nằm gọn trong hai hàm: `periodOf()` trả về khoảng bận của một chuyến, `overlaps()` so hai
 * khoảng. Khoảng bận chạy từ **đầu ngày khởi hành** tới **đúng mốc kết thúc**.
 *
 * Mốc đầu lấy đầu ngày chứ không lấy giờ đi: đã nhận chuyến khởi hành 22h thì cả hôm đó bận, chứ
 * không phải rảnh tới tận tối. Mốc cuối giữ giờ thật để một chuyến về chiều không chặn mất chuyến
 * khởi hành sáng hôm sau.
 */
class GuideOverlapWindowTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private User $guide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tour = Tour::factory()->create(['number_of_days' => 3, 'status' => 'active']);
        $this->guide = User::factory()->create(['role' => 'guide', 'status' => 'active']);
    }

    private function taoChuyen(string $batDau, string $ketThuc, ?ScheduleStatus $trangThai = null): TourSchedule
    {
        $chuyen = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'start_date' => Carbon::parse($batDau),
            'end_date' => Carbon::parse($ketThuc),
            'status' => ($trangThai ?? ScheduleStatus::Confirmed)->value,
        ]);

        $chuyen->guides()->sync([$this->guide->id]);

        return $chuyen;
    }

    private function vuong(TourSchedule $chuyenMoi): bool
    {
        $service = app(ScheduleGuideService::class);
        [$batDau, $ketThuc] = $service->periodOf($chuyenMoi);

        return $service->conflictFor($this->guide->id, $batDau, $ketThuc, $chuyenMoi->getKey()) !== null;
    }

    /**
     * Đoàn về sáng nay, chuyến kế tiếp khởi hành trưa nay: BỊ CHẶN.
     *
     * Đây là tình huống dễ lọt nhất vì hai chuyến không đè lên nhau theo đồng hồ — chuyến cũ kết
     * thúc 05:00, chuyến mới chạy 12:00, cách nhau bảy tiếng. Nhưng người vừa xuống xe lúc năm giờ
     * sáng không dẫn tiếp một đoàn trong cùng ngày ấy, nên khoảng bận tính trọn ngày về.
     */
    public function test_ve_sang_nay_thi_khong_nhan_duoc_chuyen_khoi_hanh_trua_nay(): void
    {
        $homNay = Carbon::now()->addDays(10)->format('Y-m-d');
        $truoc = Carbon::parse($homNay)->subDays(2)->format('Y-m-d');

        $this->taoChuyen($truoc . ' 22:00', $homNay . ' 05:00');

        $chuyenMoi = TourSchedule::factory()->make([
            'tour_id' => $this->tour->id,
            'start_date' => Carbon::parse($homNay . ' 12:00'),
            'end_date' => Carbon::parse($homNay)->addDays(2)->setTime(18, 0),
        ]);
        $chuyenMoi->setRelation('tour', $this->tour);

        $this->assertTrue($this->vuong($chuyenMoi), 'Chuyến khởi hành cùng ngày đoàn về phải bị chặn.');
    }

    /** Về hôm nay thì sáng mai nhận chuyến mới được: đã qua một đêm. */
    public function test_ve_hom_nay_thi_nhan_duoc_chuyen_khoi_hanh_ngay_mai(): void
    {
        $homNay = Carbon::now()->addDays(10)->format('Y-m-d');
        $truoc = Carbon::parse($homNay)->subDays(2)->format('Y-m-d');

        $this->taoChuyen($truoc . ' 22:00', $homNay . ' 05:00');

        $chuyenMoi = TourSchedule::factory()->make([
            'tour_id' => $this->tour->id,
            'start_date' => Carbon::parse($homNay)->addDay()->setTime(6, 0),
            'end_date' => Carbon::parse($homNay)->addDays(3)->setTime(18, 0),
        ]);
        $chuyenMoi->setRelation('tour', $this->tour);

        $this->assertFalse($this->vuong($chuyenMoi));
    }

    /** Chuyến khởi hành tối muộn vẫn chiếm trọn ngày hôm đó. */
    public function test_chuyen_khoi_hanh_22h_van_chiem_ca_ngay_hom_do(): void
    {
        $ngay = Carbon::now()->addDays(10)->format('Y-m-d');

        $this->taoChuyen($ngay . ' 22:00', Carbon::parse($ngay)->addDays(2)->format('Y-m-d') . ' 05:00');

        // Một chuyến trong ngày, chạy buổi sáng rồi về chiều — trước cả giờ chuyến kia lăn bánh.
        $chuyenMoi = TourSchedule::factory()->make([
            'tour_id' => $this->tour->id,
            'start_date' => Carbon::parse($ngay . ' 07:00'),
            'end_date' => Carbon::parse($ngay . ' 17:00'),
        ]);
        $chuyenMoi->setRelation('tour', $this->tour);

        $this->assertTrue($this->vuong($chuyenMoi));
    }

    /**
     * Chuyến ĐÃ HỦY không được chiếm chỗ của hướng dẫn viên nữa.
     *
     * Hủy chuyến là chuyến ấy không chạy: người đã phân công phải quay lại kho nhân sự ngay, vì
     * điều hành hủy xong thường xếp lại họ vào chuyến khác cùng ngày. Bản ghi phân công vẫn nằm
     * đó để đọc lại lịch sử, nên nếu phép kiểm không phân biệt trạng thái thì nó chặn nhầm.
     */
    public function test_chuyen_da_huy_khong_con_chan_phan_cong(): void
    {
        $ngay = Carbon::now()->addDays(10)->format('Y-m-d');

        $this->taoChuyen(
            $ngay . ' 06:00',
            Carbon::parse($ngay)->addDays(2)->format('Y-m-d') . ' 18:00',
            ScheduleStatus::Cancelled,
        );

        $chuyenMoi = TourSchedule::factory()->make([
            'tour_id' => $this->tour->id,
            'start_date' => Carbon::parse($ngay . ' 06:00'),
            'end_date' => Carbon::parse($ngay)->addDays(2)->setTime(18, 0),
        ]);
        $chuyenMoi->setRelation('tour', $this->tour);

        $this->assertFalse(
            $this->vuong($chuyenMoi),
            'Chuyến đã hủy vẫn đang chiếm chỗ của hướng dẫn viên.',
        );
    }
}
