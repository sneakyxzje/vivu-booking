<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\TourDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Xóa tour — K06, và là câu trả lời cho góp ý số 15 của hội đồng.
 *
 * Điều bộ test này giữ, và nó nghiêm trọng hơn vẻ ngoài: **`bookings.tour_id` khai
 * `onDelete('cascade')`**. Nếu luật chặn ở tầng dịch vụ hỏng, một cú bấm nhầm sẽ xóa im lặng
 * toàn bộ đơn hàng của tour cùng hành khách, sổ giao dịch và nhật ký. Không cảnh báo, không hoàn
 * tác được.
 *
 * Vì thế bài quan trọng nhất ở đây không phải "có chặn không" mà là **đếm lại số đơn sau khi bị
 * chặn** — chỉ như vậy mới chứng minh được cascade chưa hề chạy.
 */
class TourDeletionTest extends TestCase
{
    use RefreshDatabase;

    private TourDeletionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TourDeletionService::class);
    }

    private function taoTour(): Tour
    {
        return Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
        ]);
    }

    private function taoChuyen(Tour $tour, ScheduleStatus $status = ScheduleStatus::Open): TourSchedule
    {
        $start = Carbon::parse(now()->addDays(20));

        return TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => $status->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);
    }

    private function taoDon(Tour $tour, TourSchedule $chuyen): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khách Thử',
            'customer_email' => 'khach@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
        ]);
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

    // --- Xóa được: tour chưa từng dùng tới -------------------------------------------------

    /** Tour tạo nhầm, chưa bán chuyến nào — đây đúng là tình huống cần nút xóa. */
    public function test_tour_chua_tung_dung_toi_thi_xoa_duoc(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);

        $this->assertTrue($this->service->preview($tour)['can_delete']);

        $this->service->delete($tour);

        $this->assertNull(Tour::query()->find($tour->id));
        $this->assertNull(TourSchedule::query()->find($chuyen->id), 'Chuyến trống thì xóa theo.');
    }

    // --- Bị chặn: và quan trọng là dữ liệu phải còn nguyên ------------------------------------

    /**
     * Có đơn thì không xóa, và **đơn phải còn nguyên sau khi bị chặn**.
     *
     * Đây là bài giữ đúng cái hàng rào trước cascade của cơ sở dữ liệu. Chỉ khẳng định "có ném
     * lỗi" thì chưa đủ: lỗi ném sau khi cascade đã chạy vẫn là mất sạch dữ liệu.
     */
    public function test_co_don_thi_khong_xoa_va_don_van_con_nguyen(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);
        $don = $this->taoDon($tour, $chuyen);

        try {
            $this->service->delete($tour);
            $this->fail('Tour đã có đơn thì phải bị chặn.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('1 đơn đặt tour', $e->getMessage());
            // Câu từ chối phải chỉ sang lối đi đúng, không phải ngõ cụt.
            $this->assertStringContainsString('ngừng bán', $e->getMessage());
        }

        $this->assertNotNull(Tour::query()->find($tour->id));
        $this->assertNotNull(Booking::query()->find($don->id), 'Cascade không được phép chạy.');
        $this->assertSame(1, Booking::query()->where('tour_id', $tour->id)->count());
    }

    /** Đơn đã hủy vẫn là chứng từ tài chính, vẫn chặn. */
    public function test_don_da_huy_van_chan_viec_xoa(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);
        $don = $this->taoDon($tour, $chuyen);
        $don->update(['status' => 'cancelled']);

        $this->assertFalse($this->service->preview($tour)['can_delete']);
    }

    /**
     * Chuyến đã rời khỏi quầy thì chặn, kể cả khi không có đơn nào.
     *
     * Chuyến đã chốt hoặc đã chạy mang theo phân công hướng dẫn viên và sự cố dọc đường — đó là
     * lịch sử vận hành, không phải bản nháp.
     */
    public function test_chuyen_da_chot_thi_chan_du_khong_co_don(): void
    {
        $tour = $this->taoTour();
        $this->taoChuyen($tour, ScheduleStatus::Confirmed);

        $canTro = $this->service->blockers($tour);

        $this->assertNotEmpty($canTro);
        $this->assertSame('schedules', $canTro[0]['key']);
    }

    /** Chuyến mới mở bán hoặc đóng bán thì vẫn là bản nháp, không chặn. */
    public function test_chuyen_moi_mo_ban_khong_chan(): void
    {
        $tour = $this->taoTour();
        $this->taoChuyen($tour, ScheduleStatus::Open);
        $this->taoChuyen($tour, ScheduleStatus::Closed);

        $this->assertSame([], $this->service->blockers($tour));
    }

    public function test_danh_gia_cua_khach_chan_viec_xoa(): void
    {
        $tour = $this->taoTour();

        Review::create([
            'tour_id' => $tour->id,
            'user_id' => $this->taoAdmin()->id,
            'rating' => 5,
            'comment' => 'Chuyến đi rất đáng tiền.',
        ]);

        $canTro = collect($this->service->blockers($tour));

        $this->assertTrue($canTro->contains(fn (array $c) => $c['key'] === 'reviews'));
    }

    // --- Ngừng bán: lối đi an toàn ------------------------------------------------------------

    /** Ngừng bán không đụng tới chuyến đã chốt — khách đã mua thì chuyến vẫn phải chạy. */
    public function test_ngung_ban_giu_nguyen_chuyen_da_chot(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour, ScheduleStatus::Confirmed);

        $this->service->retire($tour);

        $this->assertSame('inactive', $tour->fresh()->status);
        $this->assertSame(
            ScheduleStatus::Confirmed->value,
            $chuyen->fresh()->getRawOriginal('status'),
            'Ngừng bán chỉ nghĩa là không nhận khách mới.',
        );
    }

    public function test_ngung_ban_hai_lan_bi_tu_choi(): void
    {
        $tour = $this->taoTour();
        $this->service->retire($tour);

        $this->expectException(BusinessRuleException::class);

        $this->service->retire($tour->fresh());
    }

    // --- Qua API ------------------------------------------------------------------------------

    public function test_dieu_hanh_xem_truoc_va_xoa_qua_api(): void
    {
        $tour = $this->taoTour();
        Sanctum::actingAs($this->taoAdmin());

        $this->getJson('/api/admin/tours/' . $tour->id . '/delete-preview')
            ->assertOk()
            ->assertJsonPath('data.can_delete', true);

        $this->deleteJson('/api/admin/tours/' . $tour->id)->assertOk();

        $this->assertNull(Tour::query()->find($tour->id));
    }

    public function test_api_tu_choi_xoa_tour_da_co_don_va_neu_ro_so_luong(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);
        $this->taoDon($tour, $chuyen);

        Sanctum::actingAs($this->taoAdmin());

        $xemTruoc = $this->getJson('/api/admin/tours/' . $tour->id . '/delete-preview')
            ->assertOk()
            ->json('data');

        $this->assertFalse($xemTruoc['can_delete']);
        $this->assertSame(1, $xemTruoc['blockers'][0]['count']);

        $this->deleteJson('/api/admin/tours/' . $tour->id)->assertStatus(422);

        $this->assertNotNull(Tour::query()->find($tour->id));
        $this->assertSame(1, Booking::query()->where('tour_id', $tour->id)->count());
    }

    public function test_api_ngung_ban(): void
    {
        $tour = $this->taoTour();
        Sanctum::actingAs($this->taoAdmin());

        $this->putJson('/api/admin/tours/' . $tour->id . '/retire')
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }
}
