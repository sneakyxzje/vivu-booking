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
 * Xóa tour — K06, và câu trả lời cho góp ý số 15 của hội đồng.
 *
 * Với người dùng đây là **xóa**; bên dưới thực hiện bằng **xóa mềm**, vì đơn hàng, đánh giá và
 * yêu cầu đoàn đều trỏ tới tour. Điều bộ test này giữ, và đây là phần dễ hỏng nhất khi sửa về sau:
 *
 *   1. **Xóa tour không làm mất chứng từ nào.** Đơn còn nguyên, và vẫn đọc ra được tên tour.
 *      Vế thứ hai quan trọng ngang vế thứ nhất: thiếu `withTrashed` ở quan hệ thì đơn cũ vẫn tồn
 *      tại nhưng hiện ra với tên tour trống - hỏng đúng thứ việc xóa mềm sinh ra để tránh.
 *   2. **Đoàn đang trên đường thì chưa xóa được.** Điều hành còn cần tour để điểm danh và bàn
 *      giao hướng dẫn viên.
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
            'paid_at' => now(),
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

    // --- Chứng từ phải sống sót ----------------------------------------------------------------

    /**
     * Bài quan trọng nhất của cả tệp: xóa tour rồi mà đơn vẫn còn, **và vẫn đọc ra tên tour**.
     *
     * Nếu quan hệ `Booking::tour()` mất `withTrashed`, hàng đơn vẫn còn nhưng tên tour thành
     * rỗng trên mọi màn hình và mọi chứng từ. Bài này đỏ ngay lúc đó.
     */
    public function test_xoa_tour_khong_lam_mat_don_va_don_van_doc_ra_ten_tour(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);
        $don = $this->taoDon($tour, $chuyen);
        $tenTour = $tour->title;

        $this->service->delete($tour);

        // Tour biến mất khỏi danh sách thường...
        $this->assertNull(Tour::query()->find($tour->id));
        // ...nhưng hàng dữ liệu còn nguyên.
        $this->assertNotNull(Tour::withTrashed()->find($tour->id));

        $donSauKhiCat = Booking::query()->find($don->id);

        $this->assertNotNull($donSauKhiCat, 'Đơn hàng không được mất.');
        $this->assertSame(4_000_000.0, (float) $donSauKhiCat->total_amount);
        $this->assertSame(
            $tenTour,
            $donSauKhiCat->tour?->title,
            'Đơn cũ vẫn phải tra ra được tên tour, nếu không thì chứng từ mất thông tin.',
        );
    }

    public function test_danh_gia_va_chuyen_cung_o_lai(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);

        $danhGia = Review::create([
            'tour_id' => $tour->id,
            'user_id' => $this->taoAdmin()->id,
            'rating' => 5,
            'comment' => 'Chuyến đi rất đáng tiền.',
        ]);

        $this->service->delete($tour);

        $this->assertNotNull(Review::query()->find($danhGia->id));
        $this->assertNotNull(TourSchedule::query()->find($chuyen->id));
        $this->assertSame($tour->title, Review::query()->find($danhGia->id)->tour?->title);
    }

    /** Tour có bao nhiêu đơn cũ cũng xóa được — xóa không làm mất gì nên không cần chặn. */
    public function test_don_cu_khong_chan_viec_xoa(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);
        $this->taoDon($tour, $chuyen);

        $this->assertTrue($this->service->preview($tour)['can_delete']);

        $this->service->delete($tour);

        $this->assertNull(Tour::query()->find($tour->id));
    }

    // --- Đoàn đang đi thì chặn -----------------------------------------------------------------

    public function test_chuyen_dang_khoi_hanh_thi_chua_xoa_duoc(): void
    {
        $tour = $this->taoTour();
        $this->taoChuyen($tour, ScheduleStatus::InProgress);

        try {
            $this->service->delete($tour);
            $this->fail('Đoàn đang trên đường thì phải bị chặn.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('đang khởi hành', $e->getMessage());
            // Câu từ chối phải nói khi nào làm được, không chỉ nói không.
            $this->assertStringContainsString('Đợi chuyến kết thúc rồi hãy xóa tour', $e->getMessage());
        }

        $this->assertNotNull(Tour::query()->find($tour->id));
    }

    public function test_chuyen_da_chot_cung_chan(): void
    {
        $tour = $this->taoTour();
        $this->taoChuyen($tour, ScheduleStatus::Confirmed);

        $this->assertFalse($this->service->preview($tour)['can_delete']);
    }

    /** Chuyến đã kết thúc hoặc đã hủy thì không còn ai trông vào, xóa được. */
    public function test_chuyen_da_ket_thuc_khong_chan(): void
    {
        $tour = $this->taoTour();
        $this->taoChuyen($tour, ScheduleStatus::Completed);
        $this->taoChuyen($tour, ScheduleStatus::Cancelled);

        $this->assertSame([], $this->service->blockers($tour));
    }

    // --- Lấy lại ------------------------------------------------------------------------------

    public function test_khoi_phuc_tour_da_xoa(): void
    {
        $tour = $this->taoTour();
        $this->service->delete($tour);

        $this->service->restore($tour->id);

        $this->assertNotNull(Tour::query()->find($tour->id));
    }

    public function test_khoi_phuc_tour_dang_hien_binh_thuong_bi_tu_choi(): void
    {
        $tour = $this->taoTour();

        $this->expectException(BusinessRuleException::class);

        $this->service->restore($tour->id);
    }

    // --- Ngừng bán khác xóa --------------------------------------------------------------------

    public function test_ngung_ban_giu_tour_trong_man_quan_tri(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour, ScheduleStatus::Confirmed);

        $this->service->retire($tour);

        // Khác với xóa: tour vẫn tra được bằng truy vấn thường.
        $this->assertNotNull(Tour::query()->find($tour->id));
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

    public function test_xem_truoc_noi_ro_nhung_gi_o_lai(): void
    {
        $tour = $this->taoTour();
        $chuyen = $this->taoChuyen($tour);
        $this->taoDon($tour, $chuyen);

        Sanctum::actingAs($this->taoAdmin());

        $xemTruoc = $this->getJson('/api/admin/tours/' . $tour->id . '/delete-preview')
            ->assertOk()
            ->json('data');

        $this->assertTrue($xemTruoc['can_delete']);
        $this->assertSame(1, $xemTruoc['preserved']['bookings']);
        $this->assertSame(1, $xemTruoc['preserved']['schedules']);
    }

    public function test_xoa_va_khoi_phuc_qua_api(): void
    {
        $tour = $this->taoTour();
        Sanctum::actingAs($this->taoAdmin());

        $this->deleteJson('/api/admin/tours/' . $tour->id)->assertOk();

        $daCat = $this->getJson('/api/admin/tours/trashed')->assertOk()->json('data');

        $this->assertCount(1, $daCat);
        $this->assertSame($tour->id, $daCat[0]['id']);

        $this->putJson('/api/admin/tours/' . $tour->id . '/restore')->assertOk();

        $this->assertNotNull(Tour::query()->find($tour->id));
        $this->assertCount(0, $this->getJson('/api/admin/tours/trashed')->json('data'));
    }

    /** Tour đã xóa phải biến mất khỏi danh sách của khách. */
    public function test_tour_da_xoa_khong_con_tren_trang_khach(): void
    {
        $tour = $this->taoTour();
        $this->service->delete($tour);

        $ds = $this->getJson('/api/tours')->assertOk()->json('data');

        $this->assertNotContains($tour->id, collect($ds)->pluck('id')->all());
    }
}
