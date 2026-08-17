<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Exceptions\BusinessRuleException;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\ScheduleGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Một chuyến khởi hành có nhiều hướng dẫn viên.
 *
 * Đoàn đông thì một người không kham nổi: điểm danh ở nhiều điểm dừng cùng lúc, khách tách nhóm
 * khi tham quan, có khi thêm cả xe thứ hai.
 *
 * Điều bộ test này cố ý giữ: hệ thống KHÔNG tự suy ra cần bao nhiêu người cho bao nhiêu khách.
 * Tỷ lệ ấy khác nhau theo loại tour và theo cách từng công ty vận hành, nên nó là quyết định của
 * điều hành. Luật duy nhất còn lại là luật vật lý - một người không đứng ở hai đoàn cùng lúc.
 */
class ScheduleGuideTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

        $this->chuyen = $this->taoChuyen(now()->addDays(20));
    }

    private function taoNguoi(string $role, string $status = 'active'): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function taoChuyen($start, array $ghiDe = []): TourSchedule
    {
        $start = \Illuminate\Support\Carbon::parse($start);

        return TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 45,
            'min_people' => 10,
            'booked_people' => 0,
        ], $ghiDe));
    }

    private function service(): ScheduleGuideService
    {
        return app(ScheduleGuideService::class);
    }

    // --- Nhiều người cho một chuyến -----------------------------------------------------

    public function test_gan_duoc_nhieu_huong_dan_vien_cho_mot_chuyen(): void
    {
        $ba = collect(range(1, 3))->map(fn () => $this->taoNguoi('guide'));

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => $ba->pluck('id')->all(),
        ])->assertOk();

        $daGan = $this->chuyen->fresh()->guides;

        $this->assertCount(3, $daGan);
        $this->assertEqualsCanonicalizing(
            $ba->pluck('id')->all(),
            $daGan->pluck('id')->all(),
        );
    }

    /**
     * Không có ngưỡng khách trên mỗi hướng dẫn viên.
     *
     * Một người dẫn đoàn 45 khách là quyết định của điều hành, có thể liều nhưng là việc của họ.
     * Hệ thống không chặn và cũng không cảnh báo - đặt một con số cứng ở đây là áp giá trị do
     * lập trình viên nghĩ ra lên mọi loại tour.
     */
    public function test_khong_ap_nguong_so_khach_tren_moi_huong_dan_vien(): void
    {
        $this->chuyen->update(['booked_people' => 45]);

        $motNguoi = $this->taoNguoi('guide');

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$motNguoi->id],
        ])->assertOk();

        $this->assertCount(1, $this->chuyen->fresh()->guides);
    }

    public function test_gui_mang_rong_thi_bo_het_phan_cong(): void
    {
        $this->chuyen->guides()->sync([$this->taoNguoi('guide')->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [],
        ])->assertOk();

        $this->assertCount(0, $this->chuyen->fresh()->guides);
    }

    // --- Luật còn lại: không đứng ở hai đoàn cùng lúc ------------------------------------

    public function test_nguoi_dang_ban_chuyen_khac_thi_khong_gan_duoc(): void
    {
        $ban = $this->taoNguoi('guide');

        // Chuyến khác chồng ngày với chuyến đang xét (tour 3 ngày).
        $this->taoChuyen(now()->addDays(21))->guides()->sync([$ban->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$ban->id],
        ])->assertStatus(422);

        $this->assertCount(0, $this->chuyen->fresh()->guides);
    }

    /**
     * Được ăn cả ngã về không.
     *
     * Gán một nửa rồi báo lỗi sẽ để lại trạng thái không ai chủ ý tạo ra, và người bấm tưởng cả
     * lần phân công đã bị từ chối.
     */
    public function test_mot_nguoi_vuong_lich_thi_ca_lan_phan_cong_bi_tu_choi(): void
    {
        $ranh = $this->taoNguoi('guide');
        $ban = $this->taoNguoi('guide');

        $this->taoChuyen(now()->addDays(21))->guides()->sync([$ban->id]);

        $this->expectException(BusinessRuleException::class);

        try {
            $this->service()->sync($this->chuyen, [$ranh->id, $ban->id]);
        } finally {
            $this->assertCount(
                0,
                $this->chuyen->fresh()->guides,
                'Người rảnh cũng không được gán khi lần phân công bị từ chối.',
            );
        }
    }

    public function test_chuyen_khong_chong_ngay_thi_cung_mot_nguoi_van_gan_duoc(): void
    {
        $nguoi = $this->taoNguoi('guide');

        // Tour 3 ngày, cách nhau 10 ngày nên không chạm nhau.
        $this->taoChuyen(now()->addDays(40))->guides()->sync([$nguoi->id]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$nguoi->id],
        ])->assertOk();

        $this->assertCount(1, $this->chuyen->fresh()->guides);
    }

    public function test_tai_khoan_ngung_hoat_dong_thi_khong_gan_duoc(): void
    {
        $nghi = $this->taoNguoi('guide', 'inactive');

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$nghi->id],
        ])->assertStatus(422);
    }

    public function test_tai_khoan_khong_phai_huong_dan_vien_thi_khong_gan_duoc(): void
    {
        $khach = $this->taoNguoi('customer');

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/assign-guide', [
            'guide_ids' => [$khach->id],
        ])->assertStatus(422);
    }

    // --- Quyền của từng người trong chuyến ----------------------------------------------

    /** Ai trong danh sách phân công cũng thao tác được, không phân biệt người đầu người sau. */
    public function test_moi_huong_dan_vien_trong_chuyen_deu_co_quyen_nhu_nhau(): void
    {
        $mot = $this->taoNguoi('guide');
        $hai = $this->taoNguoi('guide');
        $ngoai = $this->taoNguoi('guide');

        $this->chuyen->guides()->sync([$mot->id, $hai->id]);

        $daTai = $this->chuyen->fresh();

        $this->assertTrue($daTai->hasGuide($mot->id));
        $this->assertTrue($daTai->hasGuide($hai->id), 'Người thứ hai có quyền y hệt người thứ nhất.');
        $this->assertFalse($daTai->hasGuide($ngoai->id));
    }
}
