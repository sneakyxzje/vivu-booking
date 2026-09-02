<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Công nợ phải thu — chiều ngược lại của hàng đợi hoàn tiền.
 *
 * Hệ thống vốn chỉ trả lời được nửa câu "ai còn nợ ai": có màn công ty nợ khách, không có màn khách
 * nợ công ty. Từ khi đơn trả nhiều đợt thì nửa còn thiếu ấy là câu kế toán hỏi mỗi ngày.
 */
class ReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create(['status' => 'active', 'adult_price' => 2_000_000]);

        $start = now()->addDays(10);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 40,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        Sanctum::actingAs($this->dieuHanh);
    }

    private function taoDon(string $status, ?float $daThu, ?TourSchedule $chuyen = null): Booking
    {
        $chuyen ??= $this->chuyen;

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'expires_at' => $status === 'pending' ? now()->addMinutes(10) : null,
        ]);

        if ($daThu !== null && $daThu > 0) {
            BookingPayment::create([
                'booking_id' => $don->id,
                'kind' => 'deposit',
                'amount' => $daThu,
                'paid_at' => now(),
            ]);

            if ($daThu >= 4_000_000) {
                $don->forceFill(['paid_at' => now()])->save();
            }
        }

        return $don;
    }

    public function test_don_moi_cop_coc_nam_trong_danh_sach_phai_thu(): void
    {
        $don = $this->taoDon('confirmed', 1_200_000);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([$don->id], array_column($res->json('data.data'), 'id'));
        $this->assertEquals(1_200_000.0, $res->json('data.data.0.net_paid'));
        $this->assertEquals(2_800_000.0, $res->json('data.data.0.balance_due'));
        $this->assertEquals(2_800_000.0, $res->json('data.outstanding_total'));
    }

    /** Thu đủ rồi thì rời khỏi danh sách — không cần ai đi đòi nữa. */
    public function test_don_da_thu_du_khong_con_trong_danh_sach(): void
    {
        $this->taoDon('confirmed', 4_000_000);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([], $res->json('data.data'));
        $this->assertEquals(0.0, $res->json('data.outstanding_total'));
    }

    /**
     * Đơn đang giữ chỗ không phải công nợ.
     *
     * Nó chưa trả đồng nào theo định nghĩa, và tự hủy sau mười phút. Gọi đó là công nợ thì danh
     * sách đầy những dòng sẽ tự biến mất, và con số tổng nói dối.
     */
    public function test_don_dang_giu_cho_khong_tinh_la_cong_no(): void
    {
        $this->taoDon('pending', null);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([], $res->json('data.data'));
    }

    /** Đơn đã hủy cũng không: nó thuộc chiều bên kia, màn hoàn tiền. */
    public function test_don_da_huy_khong_tinh_la_cong_no(): void
    {
        $this->taoDon('cancelled', 1_200_000);

        $this->getJson('/api/admin/receivables')
            ->assertOk()
            ->assertJsonPath('data.data', []);
    }

    /** Hạn chốt danh sách chính là hạn thu tiền, và quá hạn thì phải nhìn thấy được. */
    public function test_danh_dau_don_qua_han_thu(): void
    {
        $chuyenGap = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Closed->value,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'booking_deadline' => now()->subHours(2),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        $this->taoDon('confirmed', 1_000_000, $chuyenGap);

        $dong = $this->getJson('/api/admin/receivables')->assertOk()->json('data.data.0');

        $this->assertTrue($dong['overdue']);
        $this->assertNotNull($dong['due_by']);
    }

    /** Lọc theo số ngày tới ngày đi, để đòi đơn gấp trước. */
    public function test_loc_theo_so_ngay_sap_khoi_hanh(): void
    {
        $chuyenXa = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(60),
            'end_date' => now()->addDays(61),
            'booking_deadline' => now()->addDays(57),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        $donGan = $this->taoDon('confirmed', 1_000_000);
        $this->taoDon('confirmed', 1_000_000, $chuyenXa);

        $res = $this->getJson('/api/admin/receivables?within_days=30')->assertOk();

        $this->assertSame([$donGan->id], array_column($res->json('data.data'), 'id'));
    }

    /** Danh sách hoá đơn mang theo hai con số tiền, để không phải mở từng đơn. */
    public function test_danh_sach_hoa_don_kem_da_thu_va_con_thieu(): void
    {
        $this->taoDon('confirmed', 1_200_000);

        $dong = $this->getJson('/api/admin/bookings')->assertOk()->json('data.data.0');

        $this->assertEquals(1_200_000.0, $dong['net_paid']);
        $this->assertEquals(2_800_000.0, $dong['balance_due']);
    }

    /** Sổ giao dịch lọc riêng được từng loại bút toán, không chỉ vào/ra. */
    public function test_so_giao_dich_loc_theo_loai_but_toan(): void
    {
        $don = $this->taoDon('confirmed', 1_200_000);

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'balance',
            'amount' => 800_000,
            'paid_at' => now(),
        ]);

        $chiCoc = $this->getJson('/api/admin/transactions?kind=deposit')->assertOk();

        $this->assertSame(1, $chiCoc->json('data.totals.count'));
        $this->assertEquals(1_200_000.0, $chiCoc->json('data.totals.in'));

        $tatCa = $this->getJson('/api/admin/transactions')->assertOk();

        $this->assertSame(2, $tatCa->json('data.totals.count'));
        $this->assertEquals(2_000_000.0, $tatCa->json('data.totals.in'));
    }
}
