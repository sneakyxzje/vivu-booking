<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Mail\BookingTransferredMail;
use App\Mail\ReviewModeratedMail;
use App\Models\Booking;
use App\Models\CustomerContactLog;
use App\Models\DiscountCode;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nhóm lỗi nhỏ còn sót sau đợt soi chính.
 *
 * Không cái nào làm mất tiền hay hỏng dữ liệu — đó là lý do chúng bị xếp sau. Nhưng mỗi cái vẫn là
 * một chỗ hệ thống nói một đằng làm một nẻo, hoặc để người dùng chờ một câu trả lời không bao giờ
 * tới.
 */
class SmallDefectFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
        ]);
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

    private function taoChuyen($start, array $ghiDe = []): TourSchedule
    {
        $start = \Illuminate\Support\Carbon::parse($start);

        return TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 45,
            'min_people' => 2,
            'booked_people' => 0,
        ], $ghiDe));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Mã giảm giá
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Xem trước mã giảm giá phải kiểm cả giới hạn theo NGƯỜI.
     *
     * `usage_limit` đếm tổng lượt của cả mã; `per_customer_limit` đếm theo từng khách. Lượt tạo
     * đơn kiểm cả hai, còn màn xem trước chỉ kiểm cái đầu — nên khách đã dùng hết phần của mình
     * vẫn thấy "áp dụng thành công", rồi đơn tạo ra theo giá gốc mà không hiểu vì sao.
     */
    public function test_xem_truoc_ma_giam_gia_kiem_ca_gioi_han_theo_nguoi(): void
    {
        $ma = DiscountCode::create([
            'code' => 'KHACHMOI',
            'name' => 'Giam cho khach moi',
            'type' => 'fixed',
            'value' => 400_000,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);

        $chuyen = $this->taoChuyen(now()->addDays(20));
        $email = 'khach@example.com';

        // Lần đầu: chưa dùng lượt nào nên xem trước phải chấp nhận.
        $this->postJson('/api/discount-codes/validate', [
            'code' => $ma->code,
            'order_amount' => 4_000_000,
            'email' => $email,
        ])->assertOk();

        // Đặt một đơn có dùng mã — hết phần của người này.
        $this->postJson('/api/bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Nguyen Van An',
            'customer_email' => $email,
            'adult_count' => 2,
            'discount_code' => $ma->code,
            'accept_terms' => true,
        ])->assertStatus(201);

        // Lần hai: xem trước phải nói KHÔNG, đúng như lượt tạo đơn sẽ làm.
        $this->postJson('/api/discount-codes/validate', [
            'code' => $ma->code,
            'order_amount' => 4_000_000,
            'email' => $email,
        ])->assertStatus(422);

        // Người khác thì vẫn dùng được bình thường.
        $this->postJson('/api/discount-codes/validate', [
            'code' => $ma->code,
            'order_amount' => 4_000_000,
            'email' => 'nguoikhac@example.com',
        ])->assertOk();
    }

    /**
     * Ô nhập mã giảm giá có hạn mức riêng.
     *
     * Mã giảm giá là chuỗi ngắn dễ đoán. Không giới hạn thì một kịch bản tự động dò được cả kho
     * mã trong vài phút, kể cả mã nội bộ chỉ định phát cho một nhóm khách.
     */
    public function test_o_nhap_ma_giam_gia_bi_gioi_han_so_lan_thu(): void
    {
        config()->set('rate_limit.enabled', true);
        config()->set('rate_limit.discount', '3,1');

        $goi = fn () => $this->postJson('/api/discount-codes/validate', [
            'code' => 'DOANMO' . random_int(1000, 9999),
            'order_amount' => 1_000_000,
        ]);

        $goi();
        $goi();
        $goi();

        $goi()->assertStatus(429);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Chuyển chuyến
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Chuyển đơn sang chuyến khác thì khách nhận được một bản viết.
     *
     * Khách đã đồng ý qua điện thoại — hệ thống bắt buộc có bản ghi ấy mới cho chuyển. Nhưng ngày
     * đi mới là thứ người ta phải xin nghỉ phép và đặt vé tới điểm tập kết, nghe qua điện thoại
     * rồi nhớ nhầm một ngày là chuyện thường. Thư cũng là nơi duy nhất khách đọc được phần chênh
     * lệch tiền.
     */
    public function test_chuyen_chuyen_thi_gui_thu_bao_ngay_moi(): void
    {
        Mail::fake();

        $chuyenCu = $this->taoChuyen(now()->addDays(20));
        $chuyenMoi = $this->taoChuyen(now()->addDays(40));

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyenCu->id,
            'customer_name' => 'Khach Doi Lich',
            'customer_email' => 'doilich@example.com',
            'departure_date' => $chuyenCu->start_date,
            'guests' => 2, 'seats' => 2, 'adult_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'confirmed_at' => now(), 'paid_at' => now(),
        ]);
        $chuyenCu->update(['booked_people' => 2]);

        $canCu = CustomerContactLog::create([
            'booking_id' => $don->id,
            'channel' => 'phone',
            'purpose' => 'transfer',
            'outcome' => 'agreed',
            'note' => 'Khach dong y doi sang ngay khac vi ban cong tac dot xuat.',
            'contacted_by' => $this->dieuHanh->id,
            'contacted_at' => now(),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/' . $don->id . '/transfer', [
            'to_schedule_id' => $chuyenMoi->id,
            'contact_log_id' => $canCu->id,
            'reason_category' => 'customer_request',
            'reason' => 'Khach ban cong tac nen xin doi sang chuyen sau.',
            'initiated_by' => 'customer',
        ])->assertOk();

        Mail::assertQueued(
            BookingTransferredMail::class,
            fn (BookingTransferredMail $thu) => $thu->hasTo('doilich@example.com'),
        );

        $this->assertSame($chuyenMoi->id, (int) $don->fresh()->tour_schedule_id);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Kiểm duyệt đánh giá
    // ─────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0: User, 1: Review} */
    private function dungDanhGiaChoDuyet(): array
    {
        $khach = $this->taoNguoi('customer');

        $review = Review::create([
            'tour_id' => $this->tour->id,
            'user_id' => $khach->id,
            'rating' => 5,
            'comment' => 'Chuyen di rat tuyet voi, huong dan vien nhiet tinh.',
            'status' => ReviewStatus::Pending,
        ]);

        return [$khach, $review];
    }

    public function test_duyet_danh_gia_thi_bao_cho_nguoi_viet(): void
    {
        Mail::fake();
        [$khach, $review] = $this->dungDanhGiaChoDuyet();

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson('/api/admin/reviews/' . $review->id . '/approve')->assertOk();

        Mail::assertQueued(
            ReviewModeratedMail::class,
            fn (ReviewModeratedMail $thu) => $thu->hasTo($khach->email),
        );
    }

    /**
     * Từ chối càng phải báo: chỉ lá thư này mang lý do tới tận nơi.
     *
     * Người viết vẫn đọc được trạng thái khi mở lại trang tour, nhưng điều đó đòi họ chủ động quay
     * lại đúng chỗ. Im lặng khiến họ tưởng bấm gửi không ăn rồi gửi lại lần nữa.
     */
    public function test_tu_choi_danh_gia_thi_bao_kem_ly_do(): void
    {
        Mail::fake();
        [$khach, $review] = $this->dungDanhGiaChoDuyet();

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson('/api/admin/reviews/' . $review->id . '/reject', [
            'reason' => 'Noi dung khong lien quan toi chuyen di nay.',
        ])->assertOk();

        Mail::assertQueued(
            ReviewModeratedMail::class,
            fn (ReviewModeratedMail $thu) => $thu->hasTo($khach->email)
                && $thu->review->moderation_note === 'Noi dung khong lien quan toi chuyen di nay.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Bảng điều khiển
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Bảng điều khiển tính bằng câu lệnh gộp, không nạp bảng đơn hàng về bộ nhớ.
     *
     * Bài này giữ đúng các con số sau khi đổi cách tính: nếu một phép gộp SQL nào đó hiểu sai ý
     * bản cũ, số sẽ lệch ngay tại đây.
     */
    public function test_bang_dieu_khien_tra_dung_so_sau_khi_doi_cach_tinh(): void
    {
        $chuyen = $this->taoChuyen(now()->addDays(20));

        $daTra = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Da Tra',
            'customer_email' => 'datra@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2, 'seats' => 2, 'adult_count' => 2,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        $daTra->payments()->create([
            'kind' => 'balance', 'amount' => 3_000_000, 'paid_at' => now(),
        ]);

        Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Da Huy',
            'customer_email' => 'dahuy@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 1, 'seats' => 1, 'adult_count' => 1,
            'total_amount' => 2_000_000,
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $data = $this->getJson('/api/admin/dashboard')->assertOk()->json('data.booking_summary');

        $this->assertSame(2, $data['total_bookings']);
        $this->assertSame(1, $data['confirmed_bookings']);
        $this->assertSame(1, $data['cancelled_bookings']);
        $this->assertSame(0, $data['pending_bookings']);

        // Doanh thu là tiền ĐÃ VỀ của nhóm đơn tính doanh thu — đơn đã hủy không được cộng vào.
        $this->assertSame(3_000_000.0, (float) $data['total_revenue']);
        $this->assertSame(3_000_000.0, (float) $data['revenue_this_month']);

        // Giá trị đơn đã bán thì tính đủ, kể cả phần khách còn nợ.
        $this->assertSame(4_000_000.0, (float) $data['contracted_value']);
    }
}
