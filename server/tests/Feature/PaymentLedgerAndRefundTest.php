<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\VNPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentLedgerAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $khach;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        // Chữ ký VNPay tính bằng khóa này; test tự ký để đi qua đúng đường kiểm chữ ký thật.
        config([
            'app.frontend_url' => 'http://localhost:5173',
            'services.vnpay.hash_secret' => 'secret-cho-test',
            'services.vnpay.tmn_code' => 'TEST',
            'services.vnpay.return_url' => 'http://localhost:8000/api/vnpay/return',
        ]);

        $this->admin = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->khach = User::create([
            'name' => 'Khach Le',
            'email' => 'khach-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->tour = Tour::create([
            'admin_id' => $this->admin->id,
            'title' => 'Tour Thu Du',
            'slug' => 'tour-thu-du-' . Str::random(5),
            'adult_price' => 5_000_000,
            'child_price' => 3_500_000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(32),
            'max_people' => 20,
            'min_people' => 1,
            'booked_people' => 0,
            'booking_deadline' => now()->addDays(27),
            'status' => ScheduleStatus::Open->value,
        ]);
    }

    private function datTour(int $nguoiLon = 1): Booking
    {
        $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => $this->khach->name,
            'customer_email' => $this->khach->email,
            'adult_count' => $nguoiLon,
        ])->assertStatus(201);

        return Booking::query()->latest('id')->firstOrFail();
    }

    /** Dựng lượt VNPay quay về, ký đúng như cổng thật ký. */
    private function vnpayQuayVe(Booking $booking, float $soTien, bool $thanhCong = true): array
    {
        $params = [
            'vnp_Amount' => (int) round($soTien * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_ResponseCode' => $thanhCong ? '00' : '24',
            'vnp_TransactionNo' => (string) random_int(10000000, 99999999),
            'vnp_TransactionStatus' => $thanhCong ? '00' : '02',
            'vnp_TxnRef' => app(VNPayService::class)->txnRef($booking),
        ];

        ksort($params);
        $hashData = collect($params)
            ->map(fn ($v, $k) => urlencode($k) . '=' . urlencode($v))
            ->implode('&');

        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'secret-cho-test');

        return $params;
    }

    // --- Thanh toán qua cổng ---------------------------------------------------------------------

    public function test_tra_du_qua_cong_thi_ghi_so_va_dong_moc_da_thanh_toan(): void
    {
        Mail::fake();
        $booking = $this->datTour();

        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $daSua = $booking->fresh();

        $this->assertSame('confirmed', $daSua->status);
        $this->assertNotNull($daSua->paid_at);
        // Đơn lẻ thu đủ một lần, nên khoản này ghi là `balance`; nhãn `deposit` chỉ đơn đoàn dùng.
        $this->assertSame('balance', $daSua->payments()->first()->kind);
        $this->assertSame(5_000_000.0, app(BookingPaymentService::class)->netPaid($daSua));
        $this->assertSame(0.0, app(BookingPaymentService::class)->balanceDue($daSua));
    }

    /**
     * `paid_at` do SỔ đóng, không phải do lượt quay về của cổng.
     *
     * Khác biệt lộ ra ở đúng tình huống này: khách chuyển khoản thiếu, điều hành ghi vào sổ đúng
     * số đã nhận rồi vẫn xác nhận đơn. Nếu `paid_at` đóng theo trạng thái thì đơn ấy mang mốc "đã
     * thanh toán" trong khi còn nợ, và mọi phép tính hoàn tiền về sau đọc sai.
     */
    public function test_thu_thieu_thi_chua_dong_moc_da_thanh_toan(): void
    {
        Mail::fake();
        $booking = $this->datTour();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/' . $booking->id . '/payments', [
                'kind' => 'balance',
                'amount' => 2_000_000,
                'method' => 'bank_transfer',
            ])->assertOk();

        $daSua = $booking->fresh();

        $this->assertNull($daSua->paid_at);
        $this->assertSame(3_000_000.0, app(BookingPaymentService::class)->balanceDue($daSua));
    }

    public function test_ma_giao_dich_khac_nhau_giua_hai_lan_tra(): void
    {
        $booking = $this->datTour();
        $vnpay = app(VNPayService::class);

        $mot = $vnpay->txnRef($booking);

        // VNPay coi vnp_TxnRef là mã của MỘT giao dịch. Dùng lại id đơn cho lần trả thứ hai thì
        // bản thật từ chối với lỗi "giao dịch đã tồn tại".
        $this->travel(1)->second();
        $hai = $vnpay->txnRef($booking);

        $this->assertNotSame($mot, $hai);
        $this->assertSame($booking->id, $vnpay->bookingIdFrom($mot));
        $this->assertSame($booking->id, $vnpay->bookingIdFrom($hai));
    }

    public function test_van_doc_duoc_ma_giao_dich_dang_cu_chi_co_id(): void
    {
        // Giao dịch tạo trước thay đổi này vẫn có thể đang trên đường quay về.
        $this->assertSame(42, app(VNPayService::class)->bookingIdFrom('42'));
    }

    /**
     * Đơn còn thiếu tiền thì trang tra cứu vẫn đưa ra liên kết thanh toán, đúng phần thiếu.
     *
     * Với đơn lẻ thu đủ một lần, tình huống này sinh ra từ việc khách chuyển khoản thiếu và điều
     * hành ghi vào sổ đúng số đã nhận. Chỉ dựng liên kết cho đơn `pending` thì người đó không còn
     * đường nào tự trả nốt.
     */
    public function test_don_con_thieu_tien_van_co_lien_ket_thanh_toan(): void
    {
        Mail::fake();
        $booking = $this->datTour();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/' . $booking->id . '/payments', [
                'kind' => 'balance',
                'amount' => 3_000_000,
                'method' => 'bank_transfer',
            ])->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/bookings/' . $booking->id . '/confirm')->assertOk();

        $this->app['auth']->forgetGuards();
        $response = $this->getJson('/api/bookings/' . $booking->public_token)->assertOk();

        $this->assertNotNull($response->json('data.payment_url'));
        $this->assertSame(2_000_000.0, (float) $response->json('data.balance_due'));
    }

    public function test_don_da_tra_du_thi_khong_con_lien_ket_thanh_toan(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $response = $this->getJson('/api/bookings/' . $booking->public_token)->assertOk();

        $this->assertNull($response->json('data.payment_url'));
        $this->assertSame(0.0, (float) $response->json('data.balance_due'));
    }

    /** Màn "Đơn của tôi" cũng phải có đường trả tiền, không bắt khách quay ra trang tra cứu. */
    public function test_man_don_cua_toi_kem_lien_ket_thanh_toan_cho_don_chua_tra(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $booking->update(['customer_id' => $this->khach->id]);

        $response = $this->actingAs($this->khach, 'sanctum')
            ->getJson('/api/my-bookings')
            ->assertOk();

        $this->assertNotNull($response->json('data.0.payment_url'));
        $this->assertSame(5_000_000.0, (float) $response->json('data.0.balance_due'));
    }

    /**
     * Chi tiết đơn phía điều hành kèm nhật ký cổng thanh toán.
     *
     * Dữ liệu này được nạp từ lâu nhưng giao diện chưa bao giờ hiện. Nó ghi MỌI lượt VNPay trả về,
     * kể cả lượt thất bại, kèm kết quả kiểm chữ ký — thứ trả lời câu "làm sao biết khoản thanh
     * toán này là thật".
     */
    public function test_chi_tiet_don_kem_nhat_ky_cong_thanh_toan(): void
    {
        Mail::fake();
        $booking = $this->datTour();

        // Một lượt hỏng trước, rồi một lượt thành công — nhật ký phải giữ cả hai.
        $this->get('/api/vnpay/return?' . http_build_query(
            $this->vnpayQuayVe($booking, 5_000_000, thanhCong: false),
        ));
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $logs = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/bookings/' . $booking->id)
            ->assertOk()
            ->json('data.payment_logs');

        $this->assertCount(2, $logs);
        $this->assertArrayHasKey('is_valid_signature', $logs[0]);
        $this->assertArrayHasKey('transaction_no', $logs[0]);
        $this->assertArrayHasKey('response_code', $logs[0]);
        // Test tự ký bằng đúng khóa nên chữ ký hợp lệ ở cả hai lượt; cái khác nhau là mã trả về.
        $this->assertTrue((bool) $logs[0]['is_valid_signature']);
        $this->assertSame(['00', '24'], collect($logs)->pluck('response_code')->sort()->values()->all());
    }

    // --- Thu tiền thủ công ---------------------------------------------------------------------

    public function test_dieu_hanh_ghi_nhan_khach_chuyen_khoan_cho_don_le(): void
    {
        Mail::fake();
        $booking = $this->datTour();

        // Sổ giao dịch từng chỉ mở cho đơn đoàn. Đây là đường ghi nhận tiền về ngoài cổng
        // thanh toán: khách chuyển khoản hoặc nộp tiền mặt tại văn phòng.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/' . $booking->id . '/payments', [
                'kind' => 'balance',
                'amount' => 5_000_000,
                'method' => 'bank_transfer',
                'reference' => 'FT2609XXXX',
                'note' => 'Khách chuyển khoản tại quầy',
            ])
            ->assertOk()
            ->assertJsonPath('data.paid_in_full', true)
            ->assertJsonPath('data.balance_due', 0);
    }

    public function test_so_giao_dich_hien_du_cac_dong(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/bookings/' . $booking->id . '/payments')
            ->assertOk();

        $this->assertSame(5_000_000.0, (float) $response->json('data.net_paid'));
        $this->assertSame(0.0, (float) $response->json('data.balance_due'));
        $this->assertCount(1, $response->json('data.entries'));
        $this->assertSame('gateway', $response->json('data.entries.0.method'));
    }

    // --- Sổ hoàn tiền --------------------------------------------------------------------------

    public function test_quan_tri_huy_don_thi_ghi_nghia_vu_hoan_len_chinh_don(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/bookings/' . $booking->id . '/cancel', [
                'cancel_reason' => 'Khách báo bận đột xuất, điều hành hủy hộ.',
            ])
            ->assertOk();

        // Trước đây con số này chỉ nằm trong nhật ký, tức không truy vấn được và đơn không bao
        // giờ xuất hiện ở danh sách chờ hoàn.
        $this->assertNotNull($booking->fresh()->refund_amount);
        $this->assertGreaterThan(0, (float) $booking->fresh()->refund_amount);
    }

    public function test_don_con_no_hoan_nam_trong_danh_sach_cho_hoan(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/bookings/' . $booking->id . '/cancel', [
                'cancel_reason' => 'Khách báo bận đột xuất, điều hành hủy hộ.',
            ])->assertOk();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/refunds')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($booking->id));
        $this->assertGreaterThan(0, (float) $response->json('data.outstanding_total'));
    }

    public function test_ghi_khoan_hoan_thi_don_roi_khoi_danh_sach_cho_hoan(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/bookings/' . $booking->id . '/cancel', [
                'cancel_reason' => 'Khách báo bận đột xuất, điều hành hủy hộ.',
            ])->assertOk();

        $conNo = (float) $booking->fresh()->refund_amount;

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/' . $booking->id . '/payments', [
                'kind' => 'refund',
                'amount' => $conNo,
                'method' => 'bank_transfer',
                'reference' => 'FT-HOAN-01',
            ])->assertOk();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/refunds')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertFalse($ids->contains($booking->id));
        $this->assertSame(0.0, (float) $response->json('data.outstanding_total'));
    }

    public function test_khong_hoan_qua_so_da_thu(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));

        // Đã thu 5 triệu, hoàn 6 triệu là chi ra nhiều hơn số từng nhận vào.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/bookings/' . $booking->id . '/payments', [
                'kind' => 'refund',
                'amount' => 6_000_000,
            ])
            ->assertStatus(422);

        $this->assertSame(0, BookingPayment::query()->where('kind', 'refund')->count());
    }

    public function test_khach_gui_yeu_cau_huy_phai_khai_tai_khoan_nhan_hoan(): void
    {
        Mail::fake();
        $booking = $this->datTour();
        $booking->update(['customer_id' => $this->khach->id]);
        $this->get('/api/vnpay/return?' . http_build_query($this->vnpayQuayVe($booking, 5_000_000)));
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->khach, 'sanctum')
            ->postJson('/api/my-bookings/' . $booking->id . '/cancel-request', [
                'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
            ])
            ->assertStatus(422);

        $this->actingAs($this->khach, 'sanctum')
            ->postJson('/api/my-bookings/' . $booking->id . '/cancel-request', [
                'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
                'refund_bank_account' => '0123456789',
                'refund_bank_name' => 'Vietcombank',
                'refund_account_holder' => 'NGUYEN VAN A',
            ])
            ->assertStatus(201);

        $this->assertSame('0123456789', $booking->fresh()->refund_bank_account);
    }
}
