<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mốc kết thúc chuyến do điều hành đặt.
 *
 * Trước đây `end_date` được suy trọn gói từ `start_date + (số ngày - 1)`, nên phần giờ của nó là
 * bản sao của giờ đi. Con số ấy không mang thông tin nào: nó nói xe về đúng giờ nó chạy.
 *
 * Số ngày của tour không đủ để suy ra mốc về. Cùng một tour ba ngày, chuyến này về chiều ngày thứ
 * ba, chuyến kia đi xe đêm và trả khách lúc 5 giờ sáng — đó là thỏa thuận với từng nhà xe. Nay
 * điều hành đặt cả ngày lẫn giờ, và luật duy nhất còn lại là kết thúc phải sau khởi hành.
 */
class ScheduleReturnTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($this->admin);
    }

    /** @param array<string, mixed> $chuyen */
    private function payloadTour(array $chuyen): array
    {
        return [
            'title' => 'Tour Ha Long 3N2D',
            'description' => 'Tour dung de thu moc ket thuc chuyen.',
            'adult_price' => 3_000_000,
            'child_price' => 2_000_000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Ha Noi',
            'end_location' => 'Ha Long',
            'status' => 'active',
            'schedules' => [$chuyen],
        ];
    }

    /** @param array<string, mixed> $chuyen */
    private function taoTour(array $chuyen): Tour
    {
        $this->postJson('/api/admin/tours', $this->payloadTour($chuyen))->assertSuccessful();

        return Tour::query()->latest('id')->firstOrFail();
    }

    private function ngayDi(): string
    {
        return Carbon::now()->addDays(30)->format('Y-m-d');
    }

    public function test_dieu_hanh_dat_ca_ngay_lan_gio_ket_thuc(): void
    {
        $ngayDi = $this->ngayDi();

        $tour = $this->taoTour([
            'start_date' => $ngayDi . ' 06:00',
            'end_date' => Carbon::parse($ngayDi)->addDays(2)->format('Y-m-d') . ' 19:30',
            'max_people' => 30,
            'min_people' => 4,
        ]);

        $chuyen = $tour->schedules()->firstOrFail();

        $this->assertSame(
            Carbon::parse($ngayDi)->addDays(2)->format('Y-m-d'),
            $chuyen->end_date->format('Y-m-d'),
        );
        $this->assertSame('19:30', $chuyen->end_date->format('H:i'));
        $this->assertSame('06:00', $chuyen->start_date->format('H:i'));
    }

    /**
     * Mốc về KHÔNG bị ép khớp với số ngày của tour.
     *
     * Đây là điểm khác hẳn bản cũ, và là cả lý do thay đổi. Xe đêm trả khách sáng sớm hôm sau thì
     * chuyến kéo dài sang ngày thứ tư của một tour ba ngày, và hệ thống phải ghi đúng thứ điều
     * hành thỏa thuận với nhà xe chứ không nắn lại theo con số trong mô tả tour.
     */
    public function test_moc_ve_khong_bi_ep_theo_so_ngay_cua_tour(): void
    {
        $ngayDi = $this->ngayDi();

        $tour = $this->taoTour([
            'start_date' => $ngayDi . ' 22:00',
            'end_date' => Carbon::parse($ngayDi)->addDays(3)->format('Y-m-d') . ' 05:00',
            'max_people' => 30,
            'min_people' => 4,
        ]);

        $chuyen = $tour->schedules()->firstOrFail();

        $this->assertSame(
            Carbon::parse($ngayDi)->addDays(3)->format('Y-m-d'),
            $chuyen->end_date->format('Y-m-d'),
        );
        $this->assertSame('05:00', $chuyen->end_date->format('H:i'));
    }

    /**
     * Không gửi mốc kết thúc thì giữ nguyên nếp suy cũ.
     *
     * Quan trọng vì nó là điều kiện để thay đổi này không viết lại dữ liệu đã có: một đường gọi cũ
     * không biết tới trường mới vẫn phải tạo được chuyến hợp lệ.
     */
    public function test_khong_gui_moc_ket_thuc_thi_suy_tu_so_ngay(): void
    {
        $ngayDi = $this->ngayDi();

        $tour = $this->taoTour([
            'start_date' => $ngayDi . ' 06:00',
            'max_people' => 30,
            'min_people' => 4,
        ]);

        $chuyen = $tour->schedules()->firstOrFail();

        $this->assertSame(
            Carbon::parse($ngayDi)->addDays(2)->format('Y-m-d'),
            $chuyen->end_date->format('Y-m-d'),
        );
        $this->assertSame('06:00', $chuyen->end_date->format('H:i'));
    }

    public function test_sua_tour_doi_duoc_moc_ket_thuc(): void
    {
        $ngayDi = $this->ngayDi();

        $tour = $this->taoTour([
            'start_date' => $ngayDi . ' 06:00',
            'end_date' => Carbon::parse($ngayDi)->addDays(2)->format('Y-m-d') . ' 19:30',
            'max_people' => 30,
            'min_people' => 4,
        ]);

        $chuyen = $tour->schedules()->firstOrFail();

        $this->putJson('/api/admin/tours/' . $tour->id, [
            'title' => $tour->title,
            'description' => $tour->description,
            'adult_price' => $tour->adult_price,
            'child_price' => $tour->child_price,
            'infant_price' => $tour->infant_price,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => $tour->start_location,
            'end_location' => $tour->end_location,
            'status' => 'active',
            'schedules' => [[
                'id' => $chuyen->id,
                'start_date' => $ngayDi . ' 06:00',
                'end_date' => Carbon::parse($ngayDi)->addDays(2)->format('Y-m-d') . ' 21:00',
                'max_people' => 30,
                'min_people' => 4,
                'status' => 'open',
            ]],
        ])->assertOk();

        $this->assertSame('21:00', $chuyen->fresh()->end_date->format('H:i'));
    }

    public function test_ket_thuc_truoc_khoi_hanh_thi_bi_tu_choi(): void
    {
        $ngayDi = $this->ngayDi();

        $this->postJson('/api/admin/tours', $this->payloadTour([
            'start_date' => $ngayDi . ' 06:00',
            'end_date' => Carbon::parse($ngayDi)->subDay()->format('Y-m-d') . ' 19:30',
            'max_people' => 30,
            'min_people' => 4,
        ]))->assertStatus(422)->assertJsonValidationErrors('schedules');
    }

    /** Kết thúc trùng khít giờ khởi hành cũng là gõ nhầm: một chuyến dài 0 phút. */
    public function test_ket_thuc_trung_khoi_hanh_thi_bi_tu_choi(): void
    {
        $ngayDi = $this->ngayDi();

        $this->postJson('/api/admin/tours', $this->payloadTour([
            'start_date' => $ngayDi . ' 06:00',
            'end_date' => $ngayDi . ' 06:00',
            'max_people' => 30,
            'min_people' => 4,
        ]))->assertStatus(422)->assertJsonValidationErrors('schedules');
    }

    /**
     * Chuyến về sáng hôm sau vẫn giữ chỗ của hướng dẫn viên tới đúng lúc đó.
     *
     * Đây là hệ quả quan trọng nhất của việc cho điều hành đặt mốc kết thúc, và là chỗ dễ lọt
     * nhất: phép kiểm trùng lịch từng tính khoảng bận theo số ngày của tour, nên một chuyến xe
     * đêm kết thúc ngày thứ tư vẫn để hướng dẫn viên "rảnh" từ sáng hôm ấy.
     */
    public function test_chuyen_ve_sang_hom_sau_van_chan_phan_cong_trung(): void
    {
        $guide = User::factory()->create(['role' => 'guide', 'status' => 'active']);
        $ngayDi = $this->ngayDi();

        // Chuyến xe đêm: khởi hành 22h, trả khách 5h sáng ngày thứ tư.
        $tour = $this->taoTour([
            'start_date' => $ngayDi . ' 22:00',
            'end_date' => Carbon::parse($ngayDi)->addDays(3)->format('Y-m-d') . ' 05:00',
            'max_people' => 30,
            'min_people' => 4,
            'guide_ids' => [$guide->id],
        ]);

        $chuyenDem = $tour->schedules()->firstOrFail();
        $this->assertTrue($chuyenDem->guides()->where('users.id', $guide->id)->exists());

        // Chuyến khác khởi hành sáng ngày thứ tư — lúc người này còn đang trên xe về.
        $this->postJson('/api/admin/tours', $this->payloadTour([
            'start_date' => Carbon::parse($ngayDi)->addDays(3)->format('Y-m-d') . ' 08:00',
            'max_people' => 30,
            'min_people' => 4,
            'guide_ids' => [$guide->id],
        ]))->assertStatus(422);
    }

    /** Chuyến cũ trong cơ sở dữ liệu không bị đụng tới cho tới khi có người thật sự sửa nó. */
    public function test_chuyen_cu_giu_nguyen_moc_ket_thuc(): void
    {
        $chuyen = TourSchedule::factory()->create([
            'start_date' => Carbon::now()->addDays(30)->setTime(7, 0),
            'end_date' => Carbon::now()->addDays(32)->setTime(7, 0),
        ]);

        $this->assertSame('07:00', $chuyen->fresh()->end_date->format('H:i'));
    }
}
