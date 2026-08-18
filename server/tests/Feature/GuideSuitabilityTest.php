<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\GuideSuitabilityService;
use App\Services\ScheduleGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Ai phù hợp dẫn chuyến này" - điểm 17 của hội đồng.
 *
 * Bộ test này giữ đúng một ranh giới, và đó là phần dễ trôi nhất khi sửa về sau:
 *
 *   - **Chỉ đúng một thứ chặn**: trùng lịch. Đó là luật vật lý, hệ thống biết chắc và người dùng
 *     không thể muốn khác.
 *   - **Toàn bộ hồ sơ năng lực chỉ xếp thứ tự**. Chuyên môn lệch, tuyến lạ, quá sức dẫn, thậm chí
 *     chưa khai hồ sơ - đều vẫn bấm được. Nếu một ngày nào đó chúng bắt đầu chặn, một trong các
 *     bài dưới đây sẽ đỏ.
 */
class GuideSuitabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private TourSchedule $chuyen;
    private Category $bienDao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bienDao = Category::query()->create([
            'name' => 'Biển đảo',
            'slug' => 'bien-dao-' . Str::random(4),
            'is_active' => true,
        ]);

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
            'end_location' => 'Hạ Long',
        ]);

        $this->tour->categories()->sync([$this->bienDao->id]);

        $this->chuyen = $this->taoChuyen(now()->addDays(20));
    }

    private function taoGuide(?string $ten = null): User
    {
        return User::create([
            'name' => $ten ?? 'Guide ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'guide',
            'status' => 'active',
        ]);
    }

    private function taoChuyen($start): TourSchedule
    {
        $start = Carbon::parse($start);

        return TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 40,
            'min_people' => 4,
            'booked_people' => 0,
        ]);
    }

    private function hoSo(User $guide, array $gia): void
    {
        $guide->guideProfile()->create($gia);
        $guide->unsetRelation('guideProfile');
    }

    // --- Hồ sơ không chặn ai -----------------------------------------------------------------

    /**
     * Chưa khai hồ sơ thì phân công bình thường.
     *
     * Phạt người chưa khai là phạt nhầm đối tượng: lỗi ở chỗ chưa ai nhập dữ liệu, không phải ở
     * người hướng dẫn. Họ chỉ không được cộng điểm nào nên nằm giữa danh sách.
     */
    public function test_chua_khai_ho_so_thi_khong_bi_chan(): void
    {
        $guide = $this->taoGuide();

        app(ScheduleGuideService::class)->sync($this->chuyen, [$guide->id]);

        $this->assertTrue($this->chuyen->fresh()->hasGuide($guide->id));
    }

    /**
     * Hồ sơ lệch hoàn toàn với tour vẫn phân công được.
     *
     * Đây là bài giữ ranh giới của cả tính năng: chuyên môn và tuyến quen **chỉ xếp thứ tự**.
     * Nếu một ngày nào đó chúng bắt đầu chặn, bài này đỏ.
     */
    public function test_ho_so_lech_hoan_toan_van_phan_cong_duoc(): void
    {
        $guide = $this->taoGuide('Người lạ tuyến');
        $this->hoSo($guide, ['regions' => ['Đà Lạt'], 'max_group_size' => 5]);

        app(ScheduleGuideService::class)->sync($this->chuyen, [$guide->id]);

        $this->assertTrue($this->chuyen->fresh()->hasGuide($guide->id));
    }

    // --- Chấm điểm: chỉ xếp thứ tự, không chặn -----------------------------------------------

    /** Người chuyên đúng loại hình và quen tuyến phải xếp trên người không có gì khớp. */
    public function test_chuyen_mon_va_tuyen_quen_day_len_dau_danh_sach(): void
    {
        $hop = $this->taoGuide('Người hợp');
        $this->hoSo($hop, ['regions' => ['Hạ Long']]);
        $hop->guideCategories()->sync([$this->bienDao->id]);

        $la = $this->taoGuide('Người lạ tuyến');
        $this->hoSo($la, ['regions' => ['Đà Lạt']]);

        $ds = app(GuideSuitabilityService::class)->danhGia($this->chuyen);

        $this->assertSame('Người hợp', $ds->first()['name']);

        $dong = $ds->firstWhere('name', 'Người hợp');
        $this->assertContains('Chuyên Biển đảo', $dong['matches']);
        $this->assertContains('Quen tuyến Hạ Long', $dong['matches']);

        // Người không khớp gì vẫn nằm trong danh sách và vẫn chọn được.
        $this->assertNull($ds->firstWhere('name', 'Người lạ tuyến')['blocked_reason']);
    }

    /**
     * Người bị chặn vẫn hiện, kèm lý do.
     *
     * Giấu đi thì điều hành tìm mãi một cái tên đáng lẽ phải có mà không hiểu vì sao mất.
     */
    public function test_nguoi_bi_chan_van_hien_kem_ly_do(): void
    {
        $guide = $this->taoGuide('Người bận');

        $chuyenKhac = $this->taoChuyen(Carbon::parse($this->chuyen->start_date)->addDay());
        $chuyenKhac->guides()->sync([$guide->id]);

        $dong = app(GuideSuitabilityService::class)
            ->danhGia($this->chuyen)
            ->firstWhere('name', 'Người bận');

        $this->assertNotNull($dong, 'Người bị chặn phải còn trong danh sách, không được biến mất.');
        $this->assertNotNull($dong['blocked_reason']);
    }

    /** Người đang dẫn chính chuyến này không bị báo là trùng lịch với chính mình. */
    public function test_nguoi_dang_o_trong_chuyen_khong_tu_chan_chinh_minh(): void
    {
        $guide = $this->taoGuide('Người đang dẫn');
        $this->chuyen->guides()->sync([$guide->id]);

        $dong = app(GuideSuitabilityService::class)
            ->danhGia($this->chuyen->fresh())
            ->firstWhere('name', 'Người đang dẫn');

        $this->assertNull($dong['blocked_reason']);
        $this->assertTrue($dong['assigned']);
    }

    /**
     * Quá sức dẫn thì **cảnh báo**, không chặn.
     *
     * Đoàn bao nhiêu khách cần mấy người dẫn là quyết định của điều hành - họ có thể xếp thêm
     * người thứ hai mà hệ thống không biết trước. Nói ra rồi để họ quyết.
     */
    public function test_qua_suc_dan_chi_canh_bao_chu_khong_chan(): void
    {
        $guide = $this->taoGuide();
        $this->hoSo($guide, ['max_group_size' => 10]);

        $this->chuyen->update(['booked_people' => 25]);

        $dong = app(GuideSuitabilityService::class)
            ->danhGia($this->chuyen->fresh())
            ->firstWhere('id', $guide->id);

        $this->assertNull($dong['blocked_reason'], 'Sức dẫn là cảnh báo, không phải luật chặn.');
        $this->assertNotEmpty($dong['warnings']);

        // Và phân công vẫn phải chạy được.
        app(ScheduleGuideService::class)->sync($this->chuyen, [$guide->id]);
        $this->assertTrue($this->chuyen->fresh()->hasGuide($guide->id));
    }

    /** Người chưa khai hồ sơ vẫn nằm trong danh sách chọn, chỉ là không được cộng điểm nào. */
    public function test_chua_co_ho_so_van_chon_duoc(): void
    {
        $guide = $this->taoGuide('Người chưa khai');

        $dong = app(GuideSuitabilityService::class)
            ->danhGia($this->chuyen)
            ->firstWhere('name', 'Người chưa khai');

        $this->assertNotNull($dong);
        $this->assertNull($dong['blocked_reason']);
        $this->assertSame([], $dong['matches']);
    }

    // --- Hồ sơ ------------------------------------------------------------------------------

    public function test_dieu_hanh_luu_duoc_ho_so_nang_luc(): void
    {
        $guide = $this->taoGuide();

        Sanctum::actingAs($this->taoAdmin());

        $this->putJson('/api/admin/guides/' . $guide->id . '/profile', [
            'languages' => ['Tiếng Việt', 'Tiếng Anh', ''],
            'regions' => ['Hạ Long'],
            'max_group_size' => 30,
            'category_ids' => [$this->bienDao->id],
        ])->assertOk();

        $hoSo = $guide->fresh()->guideProfile;

        $this->assertSame(30, (int) $hoSo->max_group_size);
        // Ô nhập tách bằng dấu phẩy rất dễ để lại phần tử rỗng, phải lọc đi.
        $this->assertSame(['Tiếng Việt', 'Tiếng Anh'], $hoSo->languages);
        $this->assertSame([$this->bienDao->id], $guide->fresh()->guideCategories->pluck('id')->all());
    }

    private function taoAdmin(): User
    {
        return User::create([
            'name' => 'Admin ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
