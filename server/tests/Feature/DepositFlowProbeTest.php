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

/**
 * THĂM DÒ: hệ thống chịu được mô hình "đặt cọc trước, trả nốt sau" tới đâu.
 *
 * Đây không phải bộ test của một tính năng đã có, mà là phép đo trước khi đổi mô hình bán hàng: đơn
 * lẻ hiện thu đủ một lần qua cổng, và câu hỏi là nếu chuyển sang thu cọc lúc đặt rồi thu nốt lúc
 * lên xe (hoặc ở một mốc nào đó) thì những gì còn chạy và những gì gãy.
 *
 * Mỗi bài dưới đây mô phỏng một chặng của luồng ấy bằng chính các điểm cuối thật, không gọi tắt vào
 * service — vì thứ cần biết là *đường đi của người dùng* có thông hay không.
 */
class DepositFlowProbeTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $huongDanVien;
    private Tour $tour;
    private TourSchedule $chuyen;

    /** Cọc 30% của đơn 4 triệu. */
    private const COC = 1_200_000;
    private const CON_LAI = 2_800_000;
    private const TONG = 4_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        config([
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

    /** Đặt một đơn 4 triệu, dừng ở trạng thái chờ thanh toán. */
    private function datTour(): Booking
    {
        $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khach Dat Coc',
            'customer_email' => 'coc-' . Str::random(5) . '@example.com',
            'customer_phone' => '0901234567',
            'adult_count' => 2,
            'accept_terms' => true,
        ])->assertStatus(201);

        return Booking::query()->latest('id')->firstOrFail();
    }

    /** Lượt VNPay báo về cho MỘT lần trả tiền, ký đúng như cổng thật ký. */
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

    /**
     * Cổng thanh toán báo về ĐÚNG SỐ CỌC thì đơn vẫn được xác nhận và giữ chỗ.
     *
     * Đây là chặng nền của cả mô hình. Nếu luồng quay về đòi phải đủ giá đơn mới cho qua thì không
     * còn gì để bàn tiếp.
     */
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

    /** Chỗ vẫn bị trừ như đơn trả đủ: cọc là cam kết, không phải giữ chỗ tạm. */
    public function test_chang1_cho_van_bi_tru_khi_moi_coc(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        $this->assertSame(2, (int) $this->chuyen->fresh()->booked_people);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    // --- Chặng 2: khách tự trả nốt trước ngày đi ---------------------------------------------

    /** Trang tra cứu phải đưa ra liên kết trả nốt, đúng phần còn thiếu. */
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

    /**
     * ĐÂY LÀ CHỖ GÃY: hướng dẫn viên không thu nốt được của đơn đã cọc.
     *
     * Nút xác nhận của hướng dẫn viên chỉ nhận đơn đang `pending`. Đơn đã cọc mang trạng thái
     * `confirmed`, nên toàn bộ đường thu tiền tại bến đóng lại với họ — đúng cái đường mà mô hình
     * "trả nốt lúc lên xe" dựa vào.
     */
    public function test_chang3_huong_dan_vien_khong_thu_not_duoc(): void
    {
        Mail::fake();
        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        Sanctum::actingAs($this->huongDanVien);

        $this->putJson("/api/guide/bookings/{$don->id}/confirm", [
            'amount' => self::CON_LAI,
            'method' => 'cash',
        ])->assertStatus(400);

        $this->assertEquals(
            self::CON_LAI,
            $this->so()->balanceDue($don->fresh()),
            'Tiền mặt khách đưa tại bến không vào được sổ.',
        );
    }

    /** Điều hành thì thu nốt được — nhưng phải ngồi ở văn phòng, không ở bến. */
    public function test_chang3_dieu_hanh_van_thu_not_duoc(): void
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

        $this->assertEquals(0.0, $this->so()->balanceDue($don->fresh()));
        $this->assertNotNull($don->fresh()->paid_at);
    }

    // --- Chặng 4: những thứ ăn theo số tiền đã thu -------------------------------------------

    /** Hủy khi mới cọc: hoàn trên số đã đưa, không phải trên giá đơn. */
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

    /** Đơn mới cọc phải nằm trong công nợ phải thu, đúng phần còn thiếu. */
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

    /** Doanh thu chỉ đếm tiền đã về, không đếm phần khách còn nợ. */
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

    /** Chuyến vẫn chốt được dù khách mới cọc: đủ khách tính theo đơn, không theo tiền. */
    public function test_chang4_chuyen_van_chot_duoc_khi_khach_moi_coc(): void
    {
        Mail::fake();

        $don = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayBaoVe($don, self::COC)));

        // Hạn chốt đã trôi qua: lệnh nền chỉ chốt tại đúng mốc ấy, không chốt sớm theo cửa sổ xét.
        $this->chuyen->forceFill(['booking_deadline' => now()->subHour()])->save();

        $this->artisan('schedules:confirm-ready')->assertSuccessful();

        $this->assertSame(
            ScheduleStatus::Confirmed,
            $this->chuyen->fresh()->status,
            'Đoàn đã cọc đủ người thì chuyến phải chốt được.',
        );
    }

    /**
     * Chuyến đi xong mà khách chưa trả nốt: đơn vẫn chốt thành hoàn thành, và khoản nợ KHÔNG mất.
     *
     * Đây là hệ quả trực tiếp của mô hình thu nốt tại bến — nếu hướng dẫn viên quên thu, hoặc khách
     * khất, thì sau chuyến vẫn phải còn dấu vết để đi đòi.
     */
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
