<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Khóa sửa chuyến đã vào vận hành, qua biểu mẫu tour.
 *
 * Thông báo của khóa nói "không thể sửa thông tin chuyến khởi hành ... khi trạng thái là ...",
 * nhưng phạm vi thi hành từng hẹp hơn hẳn lời nói: nó chỉ soi `min_people` và `booking_deadline`,
 * trong khi đường ghi vẫn cập nhật cả `start_date` lẫn `end_date`. Nghĩa là dời được ngày khởi
 * hành của một chuyến đang chạy.
 *
 * Nay hai mốc điều khiển vòng đời nằm trong khóa. Hai mốc mô tả — giờ tới nơi và giờ rời điểm
 * đến — cố ý đứng ngoài, vì đính chính giờ dự kiến giữa chuyến là việc nên làm.
 */
class ScheduleOperationalLockTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->tour = Tour::factory()->create([
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'status' => 'active',
            'start_location' => 'Ha Noi',
            'end_location' => 'Sa Pa',
        ]);

        // Đoàn đang trên đường: khởi hành hôm kia, về ngày mai.
        $this->chuyen = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'start_date' => Carbon::now()->subDays(2)->setTime(6, 0),
            'end_date' => Carbon::now()->addDay()->setTime(18, 0),
            'status' => ScheduleStatus::InProgress->value,
            'min_people' => 4,
            'max_people' => 30,
        ]);
    }

    /** @param array<string, mixed> $ghiDe */
    private function luuTour(array $ghiDe = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api/admin/tours/' . $this->tour->id, [
            'title' => $this->tour->title,
            'description' => $this->tour->description,
            'adult_price' => $this->tour->adult_price,
            'child_price' => $this->tour->child_price,
            'infant_price' => $this->tour->infant_price,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => $this->tour->start_location,
            'end_location' => $this->tour->end_location,
            'status' => 'active',
            'schedules' => [array_merge([
                'id' => $this->chuyen->id,
                // Đúng định dạng ô ngày giờ của biểu mẫu gửi lên: "Y-m-d\TH:i", không có giây.
                'start_date' => $this->chuyen->start_date->format('Y-m-d\TH:i'),
                'end_date' => $this->chuyen->end_date->format('Y-m-d\TH:i'),
                'max_people' => 30,
                'min_people' => 4,
                'status' => 'in_progress',
            ], $ghiDe)],
        ]);
    }

    /**
     * Lưu lại y nguyên thì không báo gì.
     *
     * Bài quan trọng nhất của nhóm này. Biểu mẫu gửi "2026-09-09T06:00" còn cột lưu
     * "2026-09-09 06:00:00" — cùng một thời điểm, khác cách viết. So bằng chuỗi thì mỗi lần bấm
     * Lưu tour đều báo "chuyến đang chạy không sửa được", kể cả khi người ta chỉ đổi tiêu đề.
     */
    public function test_luu_lai_y_nguyen_thi_khong_bi_chan(): void
    {
        $this->luuTour()->assertOk();
    }

    /**
     * Ngày khởi hành mới được chọn để **vẫn trước** mốc kết thúc.
     *
     * Nếu dời sang tương lai thì phép kiểm thứ tự bốn mốc sẽ chặn trước, và bài này sẽ xanh mà
     * không chứng minh được gì về khóa vận hành — đúng cái bẫy đã suýt lọt khi viết nó.
     */
    public function test_khong_doi_duoc_ngay_khoi_hanh_cua_chuyen_dang_chay(): void
    {
        $this->luuTour([
            'start_date' => Carbon::now()->subDays(3)->setTime(6, 0)->format('Y-m-d\TH:i'),
        ])->assertStatus(422)->assertJsonValidationErrors('schedules');

        $this->assertSame(
            Carbon::now()->subDays(2)->format('Y-m-d'),
            $this->chuyen->fresh()->start_date->format('Y-m-d'),
        );
    }

    public function test_khong_doi_duoc_moc_ket_thuc_cua_chuyen_dang_chay(): void
    {
        $this->luuTour([
            'end_date' => Carbon::now()->addDays(5)->setTime(18, 0)->format('Y-m-d\TH:i'),
        ])->assertStatus(422)->assertJsonValidationErrors('schedules');
    }

    /**
     * Giờ tới nơi vẫn sửa được giữa chuyến, và đó là chủ ý.
     *
     * Xe kẹt đường thì điều hành phải đính chính được con số khách đang nhìn trên trang tour.
     * Không dòng nào ra quyết định dựa vào mốc này, nên khóa nó chỉ gây khó mà không bảo vệ gì.
     */
    public function test_van_dinh_chinh_duoc_gio_toi_noi_giua_chuyen(): void
    {
        $this->luuTour([
            'arrival_at' => Carbon::now()->subDay()->setTime(9, 30)->format('Y-m-d\TH:i'),
        ])->assertOk();

        $this->assertSame('09:30', $this->chuyen->fresh()->arrival_at->format('H:i'));
    }

    /** Chuyến còn đang mở bán thì dời ngày thoải mái — khóa chỉ áp cho chuyến đã vào vận hành. */
    public function test_chuyen_dang_mo_ban_van_doi_duoc_ngay(): void
    {
        $this->chuyen->forceFill(['status' => ScheduleStatus::Open->value])->save();

        $ngayMoi = Carbon::now()->addDays(20)->setTime(6, 0);

        $this->luuTour([
            'start_date' => $ngayMoi->format('Y-m-d\TH:i'),
            'end_date' => $ngayMoi->copy()->addDays(2)->setTime(18, 0)->format('Y-m-d\TH:i'),
            'status' => 'open',
        ])->assertOk();

        $this->assertSame(
            $ngayMoi->format('Y-m-d H:i'),
            $this->chuyen->fresh()->start_date->format('Y-m-d H:i'),
        );
    }
}
