<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\GuideAssignmentDecline;
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
 * Hướng dẫn viên xác nhận hoặc từ chối chuyến được phân công.
 *
 * Hai điều bộ test này giữ:
 *
 *   1. **Chưa xác nhận vẫn là đã được phân công.** Điều hành đang trông vào họ; nếu chưa xác nhận
 *      mà coi như chưa có ai thì chuyến trông như thiếu người trong khi thực tế đã xếp rồi.
 *   2. **Từ chối chỉ được khi chuyến chưa khởi hành.** Đoàn đã lên đường mà người dẫn rút lui thì
 *      đó là bàn giao - phải có người nhận trước khi người cũ rời.
 */
class GuideAssignmentResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $guide;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = $this->taoNguoi('guide');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);

        $this->chuyen = $this->taoChuyen(now()->addDays(10), ScheduleStatus::Confirmed);
        $this->chuyen->guides()->sync([$this->guide->id]);
    }

    private function taoNguoi(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function taoChuyen($start, ScheduleStatus $status): TourSchedule
    {
        $start = \Illuminate\Support\Carbon::parse($start);

        return TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => $status->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);
    }

    // --- Xác nhận -------------------------------------------------------------------------

    /** Chưa xác nhận vẫn nằm trong danh sách phân công: điều hành đang trông vào họ. */
    public function test_chua_xac_nhan_van_la_da_duoc_phan_cong(): void
    {
        $this->assertTrue($this->chuyen->hasGuide($this->guide->id));

        Sanctum::actingAs($this->guide);

        $ds = $this->getJson('/api/guide/assignments')->assertOk()->json('data');

        $this->assertCount(1, $ds);
        $this->assertNull($ds[0]['accepted_at']);
        $this->assertTrue($ds[0]['can_decline']);
    }

    public function test_xac_nhan_thi_ghi_moc_thoi_gian(): void
    {
        Sanctum::actingAs($this->guide);

        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/accept')->assertOk();

        $this->assertNotNull(
            $this->chuyen->fresh()->guides->firstWhere('id', $this->guide->id)->pivot->accepted_at,
        );
    }

    public function test_khong_xac_nhan_ho_chuyen_minh_khong_duoc_phan_cong(): void
    {
        $nguoiLa = $this->taoNguoi('guide');

        Sanctum::actingAs($nguoiLa);

        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/accept')->assertStatus(422);
    }

    // --- Từ chối --------------------------------------------------------------------------

    public function test_tu_choi_thi_go_khoi_danh_sach_va_giu_lai_ly_do(): void
    {
        Sanctum::actingAs($this->guide);

        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/decline', [
            'reason' => 'Tuan do toi da co lich gia dinh, khong di duoc.',
        ])->assertOk();

        $this->assertFalse(
            $this->chuyen->fresh()->hasGuide($this->guide->id),
            'Để tên người đã nói không đi trong danh sách khiến điều hành tưởng đã có người.',
        );

        $tuChoi = GuideAssignmentDecline::query()->latest('id')->first();

        $this->assertNotNull($tuChoi, 'Gỡ khỏi danh sách rồi thì lý do phải nằm ở bảng riêng.');
        $this->assertSame($this->guide->id, $tuChoi->guide_id);
        $this->assertStringContainsString('lich gia dinh', $tuChoi->reason);
    }

    public function test_tu_choi_khong_co_ly_do_thi_bi_tu_choi(): void
    {
        Sanctum::actingAs($this->guide);

        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/decline', [])
            ->assertStatus(422);

        $this->assertTrue($this->chuyen->fresh()->hasGuide($this->guide->id));
    }

    /**
     * Đoàn đã lên đường thì rút lui là bàn giao, không phải từ chối.
     *
     * Từ chối gỡ luôn người ra; làm thế giữa chuyến là bỏ đoàn lại không ai phụ trách. Luồng đúng
     * là gửi yêu cầu bàn giao để điều hành cử người thay trước.
     */
    public function test_chuyen_dang_chay_thi_khong_tu_choi_duoc(): void
    {
        $dangChay = $this->taoChuyen(now()->subDay(), ScheduleStatus::InProgress);
        $dangChay->guides()->sync([$this->guide->id]);

        Sanctum::actingAs($this->guide);

        $response = $this->putJson('/api/guide/assignments/' . $dangChay->id . '/decline', [
            'reason' => 'Toi bi om khong dan tiep duoc nua.',
        ])->assertStatus(422);

        // Câu từ chối phải chỉ đường sang luồng đúng, không chỉ nói không.
        $this->assertStringContainsString('bàn giao', $response->json('message'));

        $this->assertTrue($dangChay->fresh()->hasGuide($this->guide->id));
        $this->assertSame(0, GuideAssignmentDecline::query()->count());
    }

    // --- Sửa danh sách không được xóa dấu đã xác nhận ---------------------------------------

    /**
     * Điều hành thêm người khác vào chuyến thì người đã xác nhận vẫn giữ nguyên trạng thái.
     *
     * sync() ghi lại toàn bộ hàng, nên nếu không chép accepted_at sang thì mỗi lần sửa danh sách
     * là mọi người trong chuyến bị coi như chưa xác nhận lại từ đầu — và điều hành đi hỏi lại
     * những người đã trả lời rồi.
     */
    public function test_them_nguoi_khac_khong_xoa_dau_da_xac_nhan_cua_nguoi_cu(): void
    {
        Sanctum::actingAs($this->guide);
        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/accept')->assertOk();

        $nguoiMoi = $this->taoNguoi('guide');

        app(ScheduleGuideService::class)->sync($this->chuyen, [$this->guide->id, $nguoiMoi->id]);

        $daTai = $this->chuyen->fresh();

        $this->assertNotNull(
            $daTai->guides->firstWhere('id', $this->guide->id)->pivot->accepted_at,
            'Người đã xác nhận không phải xác nhận lại chỉ vì có người khác được thêm vào.',
        );

        $this->assertNull($daTai->guides->firstWhere('id', $nguoiMoi->id)->pivot->accepted_at);
    }

    // --- Điều hành nhìn thấy gì -------------------------------------------------------------

    /**
     * Từ chối xong thì điều hành phải đọc được lý do.
     *
     * Từ chối gỡ người khỏi chuyến, nên nhìn bảng chỉ thấy chuyến thiếu người - không có màn này
     * thì không phân biệt được "chưa xếp ai" với "đã xếp và người ta nói không".
     */
    public function test_dieu_hanh_doc_duoc_ai_tu_choi_va_vi_sao(): void
    {
        Sanctum::actingAs($this->guide);

        $this->putJson('/api/guide/assignments/' . $this->chuyen->id . '/decline', [
            'reason' => 'Tuan do toi da co lich gia dinh, khong di duoc.',
        ])->assertOk();

        Sanctum::actingAs($this->taoNguoi('admin'));

        $ds = $this->getJson('/api/admin/tour-schedules/' . $this->chuyen->id . '/guide-declines')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $ds);
        $this->assertSame($this->guide->id, $ds[0]['guide_id']);
        $this->assertSame($this->guide->name, $ds[0]['guide_name']);
        $this->assertStringContainsString('lich gia dinh', $ds[0]['reason']);
    }
}
