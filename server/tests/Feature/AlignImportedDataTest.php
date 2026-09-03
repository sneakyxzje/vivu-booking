<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Services\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nắn dữ liệu nhập từ bản mã cũ.
 *
 * Bài này canh đúng một điều: sau khi nắn, sổ giao dịch phải là nguồn duy nhất — và những chỗ lệnh
 * cố ý KHÔNG đụng tới thì vẫn phải nguyên vẹn.
 */
class AlignImportedDataTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tour = Tour::factory()->create(['status' => 'active', 'adult_price' => 2_000_000]);

        $start = now()->addDays(30);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);
    }

    private function don(array $ghiDe = []): Booking
    {
        return Booking::create(array_merge([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khach Cu',
            'customer_email' => 'cu-' . Str::random(5) . '@example.com',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'confirmed_at' => now()->subDays(20),
        ], $ghiDe));
    }

    /** Đơn có mốc thanh toán mà sổ trống thì được dựng lại đúng một bút toán. */
    public function test_dung_lai_but_toan_cho_don_da_dong_moc(): void
    {
        $don = $this->don(['paid_at' => now()->subDays(20)]);

        $this->artisan('data:align-imported')->assertSuccessful();

        $this->assertSame(1, $don->payments()->count());
        $this->assertEquals(4_000_000, app(BookingPaymentService::class)->netPaid($don->fresh()));
    }

    /**
     * Và sau khi nắn thì hai hàm tiền thôi nói ngược nhau.
     *
     * Đây là lý do cả lệnh tồn tại: trước khi nắn, `paidForTour()` đọc đường lùi `paid_at` nên bảo
     * đã trả đủ, còn `balanceDue()` chỉ cộng sổ nên bảo chưa trả gì.
     */
    public function test_sau_khi_nan_thi_hai_ham_tien_khop_nhau(): void
    {
        $don = $this->don(['paid_at' => now()->subDays(20)]);
        $so = app(BookingPaymentService::class);

        $this->assertEquals(4_000_000, $so->paidForTour($don), 'Đường lùi nói đã trả đủ...');
        $this->assertEquals(4_000_000, $so->balanceDue($don), '...còn sổ nói chưa trả gì.');

        $this->artisan('data:align-imported')->assertSuccessful();

        $moi = $don->fresh();

        $this->assertEquals(4_000_000, $so->paidForTour($moi));
        $this->assertEquals(0.0, $so->balanceDue($moi), 'Sau khi nắn thì hết nợ, và hai hàm khớp.');
    }

    /** Chạy lại không nhân đôi bút toán — lệnh này người ta chạy nhiều lần. */
    public function test_chay_lai_khong_nhan_doi(): void
    {
        $don = $this->don(['paid_at' => now()->subDays(20)]);

        $this->artisan('data:align-imported')->assertSuccessful();
        $this->artisan('data:align-imported')->assertSuccessful();

        $this->assertSame(1, $don->payments()->count());
    }

    /** Đơn đã có bút toán thì không bị đụng, kể cả khi mới trả một phần. */
    public function test_khong_dung_don_da_co_so(): void
    {
        $don = $this->don();

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'deposit',
            'amount' => 2_000_000,
            'paid_at' => now()->subDays(20),
        ]);

        $this->artisan('data:align-imported')->assertSuccessful();

        $this->assertSame(1, $don->payments()->count());
        $this->assertEquals(2_000_000, app(BookingPaymentService::class)->netPaid($don->fresh()));
    }

    /**
     * Đơn xác nhận mà KHÔNG có mốc thanh toán thì cố ý không đoán.
     *
     * Trạng thái ấy có hai nghĩa và không phân biệt được từ bên ngoài. Đoán chiều nào cũng là bịa
     * ra một khoản tiền — hoặc bịa cho công ty, hoặc bịa cho khách.
     */
    public function test_khong_doan_don_khong_co_moc_thanh_toan(): void
    {
        $don = $this->don();

        $this->artisan('data:align-imported')->assertSuccessful();

        $this->assertSame(0, $don->payments()->count());
    }

    /** Và không bao giờ điền hộ bằng chứng khách đã đồng ý điều khoản. */
    public function test_khong_dien_ho_dong_y_dieu_khoan(): void
    {
        $don = $this->don(['paid_at' => now()->subDays(20)]);

        $this->artisan('data:align-imported')->assertSuccessful();

        $this->assertNull($don->fresh()->terms_accepted_at);
    }

    /** `--dry-run` chỉ liệt kê. */
    public function test_dry_run_khong_ghi_gi(): void
    {
        $don = $this->don(['paid_at' => now()->subDays(20)]);

        $this->artisan('data:align-imported --dry-run')->assertSuccessful();

        $this->assertSame(0, $don->payments()->count());
    }
}
