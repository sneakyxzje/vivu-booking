<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\CancellationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mỗi tour một chính sách hủy riêng.
 *
 * ## Chỗ từng đứt dây
 *
 * Cột `tours.cancellation_policy_id` có từ đầu, và lúc tạo đơn đã đọc nó:
 * `$tour->cancellation_policy_id ?? CancellationPolicy::default()?->id`. Nhưng
 * `AdminTourController` **chưa bao giờ nhận trường đó** từ biểu mẫu, nên mọi tour đều rơi về
 * chính sách mặc định và các chính sách khác nằm chết trong bảng - tạo được nhưng không gán được
 * cho ai.
 *
 * ## Vì sao cần chính sách riêng, không phải một cái dùng chung
 *
 * Tour bay vé máy bay không thể cùng điều khoản hoàn với tour đi xe: vé bay mất trắng từ lúc
 * xuất, còn xe thì hủy trước ba ngày vẫn thương lượng được. Ép chung một bảng phí là hoặc quá
 * chặt với tour xe, hoặc lỗ với tour bay.
 */
class TourCancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, array{min: int, max: int|null, percent: int}>  $bac
     */
    private function taoChinhSach(string $ten, array $bac, bool $macDinh = false): CancellationPolicy
    {
        $policy = CancellationPolicy::create([
            'name' => $ten,
            'description' => null,
            'is_default' => $macDinh,
        ]);

        foreach ($bac as $item) {
            CancellationPolicyRule::create([
                'cancellation_policy_id' => $policy->id,
                'min_hours_before' => $item['min'],
                'max_hours_before' => $item['max'],
                'refund_percent' => $item['percent'],
            ]);
        }

        return $policy->load('rules');
    }

    private function taoTour(?int $policyId = null): Tour
    {
        return Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'adult_price' => 2_000_000,
            'cancellation_policy_id' => $policyId,
        ]);
    }

    private function taoChuyen(Tour $tour): TourSchedule
    {
        $start = Carbon::parse(now()->addDays(20));

        return TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ]);
    }

    // --- Dây đã nối ---------------------------------------------------------------------------

    /**
     * Biểu mẫu tour gửi `cancellation_policy_id` lên thì máy chủ phải nhận và lưu.
     *
     * Đây chính là chỗ từng đứt: validate không khai trường này nên nó bị bỏ im lặng, không báo
     * lỗi gì cả — kiểu hỏng khó thấy nhất.
     */
    public function test_luu_duoc_chinh_sach_rieng_cho_tour(): void
    {
        $khatKhe = $this->taoChinhSach('Tour bay - không hoàn', [
            ['min' => 0, 'max' => null, 'percent' => 0],
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/tours', [
            'title' => 'Tour Phú Quốc bay',
            'adult_price' => 5_000_000,
            'child_price' => 3_000_000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'TP. Hồ Chí Minh',
            'cancellation_policy_id' => $khatKhe->id,
        ])->assertSuccessful();

        $tour = Tour::query()->where('title', 'Tour Phú Quốc bay')->first();

        $this->assertSame($khatKhe->id, $tour->cancellation_policy_id);
    }

    /** Sửa tour đổi được sang chính sách khác. */
    public function test_sua_tour_doi_duoc_chinh_sach(): void
    {
        $cu = $this->taoChinhSach('Chính sách cũ', [['min' => 0, 'max' => null, 'percent' => 50]]);
        $moi = $this->taoChinhSach('Chính sách mới', [['min' => 0, 'max' => null, 'percent' => 20]]);
        $tour = $this->taoTour($cu->id);

        Sanctum::actingAs($this->admin);

        $this->putJson('/api/admin/tours/' . $tour->id, [
            'title' => $tour->title,
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Hà Nội',
            'cancellation_policy_id' => $moi->id,
        ])->assertOk();

        $this->assertSame($moi->id, $tour->fresh()->cancellation_policy_id);
    }

    /** Biểu mẫu cấp danh sách chính sách để chọn, mặc định lên đầu. */
    public function test_bieu_mau_tao_tour_co_danh_sach_chinh_sach(): void
    {
        $this->taoChinhSach('Thường', [['min' => 0, 'max' => null, 'percent' => 30]]);
        $this->taoChinhSach('Mặc định hệ thống', [['min' => 0, 'max' => null, 'percent' => 50]], true);

        Sanctum::actingAs($this->admin);

        $ds = $this->getJson('/api/admin/tours/create')->assertOk()->json('data.cancellation_policies');

        $this->assertCount(2, $ds);
        $this->assertTrue($ds[0]['is_default'], 'Chính sách mặc định phải đứng đầu danh sách chọn.');
    }

    // --- Chính sách riêng thật sự chi phối tiền hoàn -------------------------------------------

    /**
     * Hai tour, hai chính sách, cùng thời điểm hủy — ra hai mức hoàn khác nhau.
     *
     * Đây là bài chứng minh việc gán chính sách có tác dụng thật, không chỉ lưu một con số vào
     * cột rồi không ai đọc.
     */
    public function test_hai_tour_hai_chinh_sach_cho_hai_muc_hoan_khac_nhau(): void
    {
        // Tour bay: mất trắng từ lúc xuất vé.
        $bay = $this->taoChinhSach('Tour bay', [['min' => 0, 'max' => null, 'percent' => 0]]);
        // Tour xe: còn thương lượng được.
        $xe = $this->taoChinhSach('Tour xe', [['min' => 0, 'max' => null, 'percent' => 70]]);

        $donBay = $this->taoDon($this->taoTour($bay->id));
        $donXe = $this->taoDon($this->taoTour($xe->id));

        $service = app(CancellationPolicyService::class);

        $this->assertSame(0, $service->quote($donBay)['refund_percent']);
        $this->assertSame(70, $service->quote($donXe)['refund_percent']);
    }

    /** Tour không chọn riêng thì rơi về chính sách mặc định. */
    public function test_tour_khong_chon_rieng_thi_dung_mac_dinh(): void
    {
        $this->taoChinhSach('Mặc định hệ thống', [['min' => 0, 'max' => null, 'percent' => 40]], true);

        $don = $this->taoDon($this->taoTour(null));

        $this->assertSame(40, app(CancellationPolicyService::class)->quote($don)['refund_percent']);
    }

    /**
     * Đơn chép chính sách lúc đặt, nên đổi chính sách của tour về sau **không hồi tố**.
     *
     * Đây là luật đã có từ trước, nhưng giờ mới thật sự kiểm được: trước đây không gán chính sách
     * riêng cho tour được nên không có cách nào đổi để mà thử.
     */
    public function test_doi_chinh_sach_cua_tour_khong_hoi_to_don_cu(): void
    {
        $rongRai = $this->taoChinhSach('Rộng rãi', [['min' => 0, 'max' => null, 'percent' => 90]]);
        $khatKhe = $this->taoChinhSach('Khắt khe', [['min' => 0, 'max' => null, 'percent' => 0]]);

        $tour = $this->taoTour($rongRai->id);
        $don = $this->taoDon($tour);

        $this->assertSame(90, app(CancellationPolicyService::class)->quote($don)['refund_percent']);

        // Điều hành siết chính sách của tour lại.
        $tour->update(['cancellation_policy_id' => $khatKhe->id]);

        $this->assertSame(
            90,
            app(CancellationPolicyService::class)->quote($don->fresh())['refund_percent'],
            'Đơn đã bán phải giữ điều khoản khách đồng ý lúc đặt.',
        );
    }

    private function taoDon(Tour $tour): Booking
    {
        $chuyen = $this->taoChuyen($tour);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khách Thử',
            'customer_email' => Str::random(6) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'paid_at' => now(),
            // Chép chính sách lúc đặt, đúng như BookingController làm.
            'cancellation_policy_id' => $tour->cancellation_policy_id
                ?? CancellationPolicy::default()?->id,
        ]);
    }
}
