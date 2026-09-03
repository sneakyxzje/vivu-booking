<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bộ lọc khoảng ngày của bảng điều khiển.
 *
 * Trang này trộn hai loại con số: **hiện trạng** (bao nhiêu tour đang bán, tỉ lệ lấp đầy) và
 * **trong kỳ** (bao nhiêu đơn, thu về bao nhiêu). Bộ lọc chỉ được chạm vào loại thứ hai — lọc
 * "số tour đang hoạt động" theo một khoảng ngày là câu hỏi không có nghĩa, và trả về con số nhỏ
 * hơn sẽ khiến người xem tưởng tour vừa biến mất.
 */
class AdminDashboardRangeTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 3]);
        $this->chuyen = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'max_people' => 40,
        ]);
    }

    /** Một đơn đã xác nhận, đặt và trả tiền vào đúng ngày chỉ định. */
    private function taoDon(string $ngay, float $soTien): Booking
    {
        $luc = Carbon::parse($ngay)->setTime(10, 0);

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khach ' . $ngay,
            'customer_email' => 'khach' . Str::random(5) . '@example.com',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 2, 'seats' => 2, 'adult_count' => 2,
            'total_amount' => $soTien,
            'status' => 'confirmed',
            'confirmed_at' => $luc,
        ]);

        // `created_at` do Eloquent tự đặt là "bây giờ", phải ghi đè mới xếp được vào quá khứ.
        $don->forceFill(['created_at' => $luc, 'updated_at' => $luc])->save();

        $don->payments()->create([
            'kind' => 'balance',
            'amount' => $soTien,
            'paid_at' => $luc,
        ]);

        return $don;
    }

    /** @return array<string, mixed> */
    private function mo(array $thamSo = []): array
    {
        return $this->getJson('/api/admin/dashboard?' . http_build_query($thamSo))
            ->assertOk()
            ->json('data');
    }

    public function test_khong_truyen_khoang_thi_tinh_toan_thoi_gian(): void
    {
        $this->taoDon(now()->subMonths(6)->format('Y-m-d'), 3_000_000);
        $this->taoDon(now()->format('Y-m-d'), 2_000_000);

        $data = $this->mo();

        $this->assertFalse($data['range']['filtered']);
        $this->assertSame(2, $data['booking_summary']['total_bookings']);
        $this->assertSame(5_000_000.0, (float) $data['booking_summary']['total_revenue']);

        // Biểu đồ giữ nguyên nếp cũ: mười hai tháng của năm nay.
        $this->assertCount(12, $data['monthly_performance']);
        $this->assertSame('T1', $data['monthly_performance'][0]['name']);
    }

    public function test_loc_theo_khoang_chi_dem_don_trong_khoang(): void
    {
        $this->taoDon(now()->subMonths(6)->format('Y-m-d'), 3_000_000);
        $trongKy = $this->taoDon(now()->subDays(3)->format('Y-m-d'), 2_000_000);

        $data = $this->mo([
            'from' => now()->subDays(7)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $this->assertTrue($data['range']['filtered']);
        $this->assertSame(1, $data['booking_summary']['total_bookings']);
        $this->assertSame(2_000_000.0, (float) $data['booking_summary']['total_revenue']);
        $this->assertSame(2_000_000.0, (float) $data['booking_summary']['contracted_value']);

        // Danh sách đơn gần đây cũng chỉ còn đơn trong khoảng.
        $this->assertCount(1, $data['recent_bookings']);
        $this->assertSame($trongKy->id, $data['recent_bookings'][0]['id']);
    }

    /**
     * Ngày cuối phải được tính TRỌN ngày.
     *
     * Ô chọn ngày gửi lên "2026-09-30", tức 00:00 hôm đó. Dùng thẳng làm mốc trên thì mọi giao dịch
     * trong chính ngày 30 rơi ra ngoài, và người dùng thấy một ngày bị mất mà không hiểu vì sao.
     */
    public function test_ngay_cuoi_tinh_tron_ngay(): void
    {
        $homNay = now()->format('Y-m-d');
        $this->taoDon($homNay, 2_000_000);

        $data = $this->mo(['from' => $homNay, 'to' => $homNay]);

        $this->assertSame(1, $data['booking_summary']['total_bookings']);
        $this->assertSame(2_000_000.0, (float) $data['booking_summary']['total_revenue']);
    }

    /** Khoảng ngắn thì biểu đồ vẽ theo NGÀY, và mỗi ngày trong khoảng là một cột. */
    public function test_khoang_ngan_thi_bieu_do_gom_theo_ngay(): void
    {
        $data = $this->mo([
            'from' => now()->subDays(6)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $this->assertSame('day', $data['range']['granularity']);
        $this->assertCount(7, $data['monthly_performance']);
    }

    /** Khoảng dài thì gom theo THÁNG, vì cột theo ngày sẽ mảnh tới mức không so được. */
    public function test_khoang_dai_thi_bieu_do_gom_theo_thang(): void
    {
        $data = $this->mo([
            'from' => now()->subMonths(5)->startOfMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $this->assertSame('month', $data['range']['granularity']);
        $this->assertLessThanOrEqual(7, count($data['monthly_performance']));
    }

    /**
     * Doanh thu trên biểu đồ rơi đúng vào ngày TIỀN VỀ.
     *
     * Đây là chỗ dễ sai nhất: cộng theo ngày đơn được tạo thì con số không đối chiếu được với sao
     * kê ngân hàng, mà đối chiếu sao kê là việc duy nhất người ta dùng nó.
     */
    public function test_doanh_thu_roi_dung_cot_ngay_tien_ve(): void
    {
        $ngay = now()->subDays(2);
        $this->taoDon($ngay->format('Y-m-d'), 4_000_000);

        $data = $this->mo([
            'from' => now()->subDays(4)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $cot = collect($data['monthly_performance'])->firstWhere('name', $ngay->format('d/m'));

        $this->assertNotNull($cot, 'Không tìm thấy cột của ngày tiền về.');
        $this->assertSame(4.0, (float) $cot['revenue']);
        $this->assertSame(1, (int) $cot['bookings']);
    }

    /** Con số hiện trạng KHÔNG đi theo bộ lọc. */
    public function test_so_lieu_hien_trang_khong_bi_loc(): void
    {
        $khongLoc = $this->mo();
        $coLoc = $this->mo([
            'from' => now()->subDays(3)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]);

        $this->assertSame(
            $khongLoc['summary']['active_tours'],
            $coLoc['summary']['active_tours'],
        );
        $this->assertSame(
            $khongLoc['summary']['upcoming_schedules'],
            $coLoc['summary']['upcoming_schedules'],
        );
        $this->assertSame(
            $khongLoc['booking_summary']['occupancy_rate'],
            $coLoc['booking_summary']['occupancy_rate'],
        );
    }

    public function test_ngay_ket_thuc_truoc_ngay_bat_dau_thi_bi_tu_choi(): void
    {
        $this->getJson('/api/admin/dashboard?' . http_build_query([
            'from' => now()->format('Y-m-d'),
            'to' => now()->subDays(5)->format('Y-m-d'),
        ]))->assertStatus(422)->assertJsonValidationErrors('to');
    }
}
