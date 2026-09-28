<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\VNPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;


class DepositFlowProbeTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $huongDanVien;
    private Tour $tour;
    private TourSchedule $chuyen;


    private const TONG = 4_000_000;
    private const COC = 2_000_000;
    private const CON_LAI = 2_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            // Ghim tỷ lệ cọc để các hằng số ở trên luôn đúng, kể cả khi mặc định của dự án đổi.
            'booking.deposit_percent' => 50,
            'services.vnpay.hash_secret' => 'secret-cho-test',
            'services.vnpay.tmn_code' => 'TEST',
            'services.vnpay.return_url' => 'http://localhost:8000/api/vnpay/return',
            'app.frontend_url' => 'http://localhost:5173',
        ]);

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->huongDanVien = $this->taoNguoi('guide');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
        ]);

        $start = now()->addDays(20);

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

        $this->chuyen->guides()->sync([$this->huongDanVien->id]);
    }

    private function taoNguoi(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role . '-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }


    private function datTour(): Booking
    {
        $email = 'deposit-test@example.com';
        \Illuminate\Support\Facades\Cache::put('booking_verified_' . $email, true, 600);
        $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khach Dat Coc',
            'customer_email' => $email,
            'customer_phone' => '0901234567',
            'adult_count' => 2,
            'accept_terms' => true,
        ])->assertStatus(201);

        return Booking::query()->latest('id')->firstOrFail();
    }


    private function vnpayBaoVe(Booking $don, float $soTien): array
    {
        $p = [
            'vnp_Amount' => (int) round($soTien * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionNo' => (string) random_int(10000000, 99999999),
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => app(VNPayService::class)->txnRef($don),
        ];

        ksort($p);
        $hash = collect($p)->map(fn ($v, $k) => urlencode($k) . '=' . urlencode($v))->implode('&');
        $p['vnp_SecureHash'] = hash_hmac('sha512', $hash, 'secret-cho-test');

        return $p;
    }

    private function so(): BookingPaymentService
    {
        return app(BookingPaymentService::class);
    }

    // --- Chặng 1: thu cọc lúc đặt ------------------------------------------------------------


    public function test_chang1_thu_coc_thi_don_van_duoc_xac_nhan(): void
    {
        Mail::fake();
        $don = $this->datTour();

        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        $daSua = $don->fresh();

        $this->assertSame('confirmed', $daSua->status, 'Trả cọc phải đủ để giữ chỗ chắc chắn.');
        $this->assertNull($daSua->expires_at, 'Đã cọc thì không còn là giữ chỗ tạm.');
        $this->assertNull($daSua->paid_at, 'Mới cọc thì chưa được đóng mốc đã-thu-đủ.');
        $this->assertEquals(self::COC, $this->so()->netPaid($daSua));
        $this->assertEquals(self::CON_LAI, $this->so()->balanceDue($daSua));
    }


    public function test_chang1_cho_van_bi_tru_khi_moi_coc(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        $this->assertSame(2, (int) $this->chuyen->fresh()->booked_people);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }


    public function test_chang1_trang_tra_cuu_chi_doi_tien_coc(): void
    {
        Mail::fake();
        $don = $this->datTour();

        $res = $this->getJson('/api/bookings/' . $don->public_token)->assertOk();

        $this->assertEquals(
            self::COC,
            $res->json('data.payment_amount'),
            'Đơn chưa trả gì thì lần trả sắp tới là tiền cọc.',
        );
        $this->assertEquals(
            self::TONG,
            $res->json('data.balance_due'),
            'Số còn thiếu của cả đơn vẫn là toàn bộ giá tour — hai con số khác nhau.',
        );
    }


    public function test_chang1_da_coc_roi_thi_doi_phan_con_lai(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        $res = $this->getJson('/api/bookings/' . $don->public_token)->assertOk();

        $this->assertEquals(self::CON_LAI, $res->json('data.payment_amount'));
        $this->assertEquals(self::CON_LAI, $res->json('data.balance_due'));
    }


    public function test_dat_sat_ngay_van_coc_mot_nua(): void
    {
        Mail::fake();

        $satNgay = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(8),
            'booking_deadline' => now()->addDays(4),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        $email = 'deposit-test@example.com';
        \Illuminate\Support\Facades\Cache::put('booking_verified_' . $email, true, 600);
        $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $satNgay->id,
            'customer_name' => 'Khach Dat Gap',
            'customer_email' => $email,
            'customer_phone' => '0901234567',
            'adult_count' => 2,
            'accept_terms' => true,
        ])->assertStatus(201)
            ->assertJsonPath('data.deposit_amount', self::COC)
            ->assertJsonPath('data.balance_amount', self::CON_LAI);

        $don = Booking::query()->latest('id')->firstOrFail();

        $this->assertEquals(
            self::COC,
            $this->so()->nextPaymentAmount($don),
            'Mọi đơn trước hạn chốt đều cọc 50%.',
        );
    }

    // --- Chặng 2: khách tự trả nốt trước ngày đi ---------------------------------------------


    public function test_chang2_khach_tu_tra_not_online_duoc(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        $res = $this->getJson('/api/bookings/' . $don->public_token)->assertOk();

        $this->assertEquals(self::CON_LAI, $res->json('data.balance_due'));
        $this->assertNotNull(
            $res->json('data.payment_url'),
            'Đơn đã cọc mà không có đường trả nốt thì khách kẹt.',
        );

        // Khách bấm vào đó và trả nốt.
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don->fresh(), self::CON_LAI)));

        $daSua = $don->fresh();

        $this->assertNotNull($daSua->paid_at, 'Thu đủ thì mốc phải đóng.');
        $this->assertEquals(0.0, $this->so()->balanceDue($daSua));
        $this->assertSame(2, $daSua->payments()->count(), 'Hai lần trả, hai dòng sổ.');
    }

    // --- Chặng 3: thu nốt tại điểm tập trung -------------------------------------------------


    public function test_chang3_dieu_hanh_ghi_nhan_khoan_tra_not(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/bookings/{$don->id}/payments", [
            'kind' => 'balance',
            'amount' => self::CON_LAI,
            'method' => 'cash',
        ])->assertOk();

        $daSua = $don->fresh();

        $this->assertEquals(0.0, $this->so()->balanceDue($daSua));
        $this->assertNotNull($daSua->paid_at, 'Thu đủ thì mốc đã-thanh-toán phải đóng.');

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $don->id,
            'method' => 'cash',
            'recorded_by' => $this->dieuHanh->id,
        ]);
    }


    public function test_chang3_khong_ghi_qua_phan_con_thieu(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson("/api/admin/bookings/{$don->id}/payments", [
            'kind' => 'balance',
            'amount' => self::TONG,
            'method' => 'cash',
        ])->assertStatus(422);

        $this->assertEquals(self::CON_LAI, $this->so()->balanceDue($don->fresh()));
    }

    // --- Chặng 4: những thứ ăn theo số tiền đã thu -------------------------------------------


    public function test_chang4_huy_khi_moi_coc_thi_hoan_dung_so_da_dua(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        Sanctum::actingAs($this->dieuHanh);

        $duBao = $this->getJson("/api/admin/bookings/{$don->id}/cancel-preview")->assertOk();

        // Còn 20 ngày nên bậc hoàn cao nhất: phí 10% giá đơn, hoàn phần còn lại của số đã thu.
        $this->assertEquals(self::COC, $duBao->json('data.paid_amount'));
        $this->assertEquals(
            self::COC - $duBao->json('data.cancellation_fee'),
            $duBao->json('data.refund_amount'),
        );
    }


    public function test_chang4_don_moi_coc_hien_o_cong_no_phai_thu(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        Sanctum::actingAs($this->dieuHanh);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([$don->id], array_column($res->json('data.data'), 'id'));
        $this->assertEquals(self::CON_LAI, $res->json('data.data.0.balance_due'));
    }


    public function test_chang4_doanh_thu_chi_dem_tien_da_ve(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        Sanctum::actingAs($this->dieuHanh);

        $tong = $this->getJson('/api/admin/bookings')->assertOk()->json('data.summary');

        $this->assertEquals(self::COC, $tong['revenue']);
        $this->assertEquals(self::TONG, $tong['contracted_value']);
    }


    public function test_chot_danh_sach_loai_khach_chua_tra_du(): void
    {
        Mail::fake();

        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        // Hạn chốt đã trôi qua: lệnh nền chỉ chốt tại đúng mốc ấy, không chốt sớm theo cửa sổ xét.
        $this->chuyen->forceFill(['booking_deadline' => now()->subHour()])->save();

        $this->artisan('schedules:confirm-ready')->assertSuccessful();

        $this->assertSame(
            ScheduleStatus::Open,
            $this->chuyen->fresh()->status,
            'Không chốt chuyến rỗng sau khi loại đơn chưa trả đủ.',
        );
        $this->assertSame('cancelled', $don->fresh()->status);
    }


    public function test_chang4_no_khong_bien_mat_sau_khi_chuyen_ket_thuc(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        // Đẩy chuyến qua toàn bộ vòng đời cho tới khi kết thúc.
        $this->chuyen->forceFill([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDay(),
            'booking_deadline' => now()->subDays(6),
        ])->save();

        $this->artisan('schedules:advance-status')->assertSuccessful();
        $this->artisan('bookings:finalize-completed')->assertSuccessful();

        $this->assertSame('completed', $don->fresh()->status);

        Sanctum::actingAs($this->dieuHanh);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame(
            [$don->id],
            array_column($res->json('data.data'), 'id'),
            'Chuyến đi xong không làm khoản nợ biến mất.',
        );
    }
}
