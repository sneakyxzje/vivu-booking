<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * X03 - Mã giảm giá kiểm lại tại thời điểm tạo đơn.
 *
 * Tình huống A11 ở docs/nghiep-vu/08-danh-muc-edge-case.md.
 *
 * Khách nhập mã ở bước xem giá rồi điền thông tin vài phút mới bấm đặt. Trong khoảng đó mã có
 * thể hết lượt vì người khác vừa dùng nốt. Chặn đơn ở bước cuối vì lý do không phải lỗi của
 * khách là cách chắc chắn nhất để mất một đơn hàng.
 */
class DiscountCodeAtCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private TourSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $tour = Tour::factory()->create([
            'status' => 'active',
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
        ]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22),
            'booking_deadline' => now()->addDays(17),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);
    }

    private function taoMa(array $ghiDe = []): DiscountCode
    {
        return DiscountCode::query()->create(array_merge([
            'code' => 'GIAM' . Str::upper(Str::random(4)),
            'name' => 'Ma thu nghiem',
            'type' => 'percent',
            'value' => 10,
            'minimum_order_amount' => 0,
            'usage_limit' => 10,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ], $ghiDe));
    }

    /** @return array<string, mixed> */
    private function payload(?string $ma = null): array
    {
        return array_filter([
            'tour_id' => $this->schedule->tour_id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Nguyen Van An',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'customer_phone' => '0901234567',
            'adult_count' => 2,
            'discount_code' => $ma,
        ]);
    }

    public function test_ma_con_hieu_luc_thi_tru_dung_so_tien(): void
    {
        $ma = $this->taoMa();

        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);

        $don = Booking::query()->first();

        $this->assertEquals(3_600_000, (float) $don->total_amount);
        $this->assertEquals(400_000, (float) $don->discount_amount);
        $this->assertSame(1, (int) $ma->fresh()->used_count);
    }

    /**
     * Một khách không dùng được mã quá số lần cho phép.
     *
     * `usage_limit` đếm tổng lượt của cả mã, không đếm theo người — nên một mã "giảm cho khách mới"
     * phát 100 lượt có thể bị đúng một người dùng cả 100 lần. Nhận diện theo địa chỉ thư đã đặt,
     * vì khách vãng lai không có tài khoản để đếm.
     */
    public function test_mot_khach_khong_dung_ma_qua_so_lan_cho_phep(): void
    {
        $ma = $this->taoMa(['per_customer_limit' => 1]);
        $email = 'khach-quen@example.com';

        $lanDau = $this->payload($ma->code);
        $lanDau['customer_email'] = $email;

        $this->postJson('/api/bookings', $lanDau)->assertStatus(201);
        $this->assertEquals(3_600_000, (float) Booking::query()->latest('id')->first()->total_amount);

        // Cùng người, cùng mã, đơn thứ hai: vẫn đặt được nhưng theo giá gốc.
        $lanHai = $this->payload($ma->code);
        $lanHai['customer_email'] = $email;

        $phanHoi = $this->postJson('/api/bookings', $lanHai)->assertStatus(201);

        $donHai = Booking::query()->latest('id')->first();

        $this->assertEquals(4_000_000, (float) $donHai->total_amount);
        $this->assertEquals(0, (float) $donHai->discount_amount);
        $this->assertStringContainsString('đủ số lần', $phanHoi->json('data.discount_notice'));
    }

    /** Người khác vẫn dùng được mã ấy bình thường. */
    public function test_gioi_han_theo_nguoi_khong_chan_khach_khac(): void
    {
        $ma = $this->taoMa(['per_customer_limit' => 1]);

        $mot = $this->payload($ma->code);
        $mot['customer_email'] = 'nguoi-mot@example.com';
        $this->postJson('/api/bookings', $mot)->assertStatus(201);

        $hai = $this->payload($ma->code);
        $hai['customer_email'] = 'nguoi-hai@example.com';
        $this->postJson('/api/bookings', $hai)->assertStatus(201);

        $this->assertEquals(3_600_000, (float) Booking::query()->latest('id')->first()->total_amount);
    }

    /**
     * Bài quan trọng nhất của X03. Mã hết lượt trong lúc khách điền thông tin thì vẫn tạo đơn,
     * theo giá gốc, và nói rõ lý do.
     */
    public function test_ma_het_luot_thi_tao_don_gia_goc_chu_khong_tu_choi(): void
    {
        $ma = $this->taoMa(['usage_limit' => 1, 'used_count' => 1]);

        $response = $this->postJson('/api/bookings', $this->payload($ma->code))
            ->assertStatus(201);

        $don = Booking::query()->first();

        $this->assertEquals(4_000_000, (float) $don->total_amount, 'Phải là giá gốc.');
        $this->assertNull($don->discount_code_id);
        $this->assertStringContainsString('không còn áp dụng được', $response->json('message'));
        $this->assertNotNull($response->json('data.discount_notice'));
    }

    public function test_ma_het_han_cung_tao_don_gia_goc(): void
    {
        $ma = $this->taoMa(['expires_at' => now()->subHour()]);

        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);

        $this->assertEquals(4_000_000, (float) Booking::query()->first()->total_amount);
    }

    public function test_ma_da_tat_cung_tao_don_gia_goc(): void
    {
        $ma = $this->taoMa(['is_active' => false]);

        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);

        $this->assertEquals(4_000_000, (float) Booking::query()->first()->total_amount);
    }

    /** Đơn chưa đạt giá trị tối thiểu cũng là mã không áp dụng được, không phải lỗi gõ sai. */
    public function test_don_chua_du_gia_tri_toi_thieu_thi_tao_don_gia_goc(): void
    {
        $ma = $this->taoMa(['minimum_order_amount' => 10_000_000]);

        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);

        $this->assertEquals(4_000_000, (float) Booking::query()->first()->total_amount);
    }

    /**
     * Mã không tồn tại thì khác hẳn: đó là khách gõ sai, phải nói để họ sửa chứ không lặng lẽ
     * tính giá gốc rồi để khách phát hiện lúc thanh toán.
     */
    public function test_ma_khong_ton_tai_thi_tu_choi_de_khach_sua(): void
    {
        $this->postJson('/api/bookings', $this->payload('KHONGCOMANAY'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount_code');

        $this->assertSame(0, Booking::query()->count());
    }

    /**
     * Lượt cuối cùng chỉ một người lấy được.
     *
     * Kiểm tra nằm trong giao dịch đã khóa dòng mã giảm giá, nên hai yêu cầu phải xếp hàng.
     * Bài này chạy tuần tự nên chỉ chứng minh được phần đếm lượt; phần chống tranh chấp thật
     * do lockForUpdate lo, và một bài kiểm đơn luồng không dựng lại được tình huống đó.
     */
    public function test_luot_cuoi_cung_chi_mot_don_dung_duoc(): void
    {
        $ma = $this->taoMa(['usage_limit' => 1]);

        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);
        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);

        $this->assertSame(1, (int) $ma->fresh()->used_count);

        $donCoGiam = Booking::query()->whereNotNull('discount_code_id')->count();
        $donGiaGoc = Booking::query()->whereNull('discount_code_id')->count();

        $this->assertSame(1, $donCoGiam);
        $this->assertSame(1, $donGiaGoc);
    }

    /** Hủy đơn thì lượt mã được hoàn lại để khách khác dùng. */
    public function test_huy_don_thi_hoan_lai_luot_ma(): void
    {
        $ma = $this->taoMa();

        $this->postJson('/api/bookings', $this->payload($ma->code))->assertStatus(201);
        $this->assertSame(1, (int) $ma->fresh()->used_count);

        $don = Booking::query()->first();
        $don->update(['expires_at' => now()->subHour()]);

        $this->artisan('bookings:release-expired')->assertSuccessful();

        $this->assertSame(0, (int) $ma->fresh()->used_count);
    }
}
