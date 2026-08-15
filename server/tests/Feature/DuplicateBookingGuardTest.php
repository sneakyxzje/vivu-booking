<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * X01 - Khách bấm đặt hai lần liên tiếp.
 *
 * Tình huống A04 ở docs/nghiep-vu/08-danh-muc-edge-case.md.
 *
 * Mạng chậm, khách bấm rồi không thấy phản hồi nên bấm lại. Không chặn thì thành hai đơn giống
 * hệt nhau, trừ hai lần số chỗ, và nếu đã qua hạn chốt thì đơn thừa thành ghế chết do lỗi của
 * chính hệ thống.
 */
class DuplicateBookingGuardTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(array $ghiDe = []): array
    {
        return array_merge([
            'tour_id' => $this->schedule->tour_id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Nguyen Van An',
            'customer_email' => 'khach@example.com',
            'customer_phone' => '0901234567',
            'adult_count' => 2,
        ], $ghiDe);
    }

    public function test_bam_dat_hai_lan_chi_tao_mot_don(): void
    {
        $lanMot = $this->postJson('/api/bookings', $this->payload())->assertStatus(201);
        $lanHai = $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $this->assertSame(1, Booking::query()->count());

        $this->assertSame(
            $lanMot->json('data.booking.id'),
            $lanHai->json('data.booking.id'),
            'Lần bấm thứ hai phải nhận lại chính đơn đã tạo.',
        );
    }

    /** Hệ quả tệ nhất của đơn trùng là chiếm gấp đôi số chỗ. */
    public function test_bam_hai_lan_khong_tru_hai_lan_so_cho(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $this->assertSame(2, (int) $this->schedule->fresh()->booked_people);
    }

    public function test_khach_khac_dat_cung_luc_thi_van_tao_don_rieng(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);
        $this->postJson('/api/bookings', $this->payload([
            'customer_email' => 'khachkhac@example.com',
        ]))->assertStatus(201);

        $this->assertSame(2, Booking::query()->count());
        $this->assertSame(4, (int) $this->schedule->fresh()->booked_people);
    }

    /** Cùng người nhưng đặt số khách khác thì là đơn thật, không phải bấm nhầm. */
    public function test_cung_khach_nhung_khac_so_luong_thi_van_la_don_moi(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);
        $this->postJson('/api/bookings', $this->payload(['adult_count' => 3]))->assertStatus(201);

        $this->assertSame(2, Booking::query()->count());
    }

    public function test_dat_lai_cung_chuyen_o_thoi_diem_khac_thi_van_duoc(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        // Đẩy đơn cũ ra ngoài cửa sổ 60 giây.
        Booking::query()->update(['created_at' => now()->subMinutes(5)]);

        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $this->assertSame(2, Booking::query()->count());
    }

    /** Mốc so sánh: một lần đặt thật thì gửi đúng một thư. */
    public function test_mot_lan_dat_gui_dung_mot_thu(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\BookingCreatedMail::class, 1);
    }

    /**
     * Đơn trùng phải nhận thông báo khác, để khách hiểu là đơn cũ chứ không phải đơn mới.
     *
     * Không kiểm số thư gửi ở đây: thư đi qua app()->terminating(), mà các hàm gọi lúc kết thúc
     * vòng đời ứng dụng tích lũy qua nhiều yêu cầu trong cùng một bài kiểm, nên đếm thư không
     * phản ánh đúng số lần thực sự đăng ký. Bài phía trên đã khóa mốc một yêu cầu một thư.
     */
    public function test_don_trung_bao_cho_khach_biet_la_don_cu(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $this->postJson('/api/bookings', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $lanHai = $this->postJson('/api/bookings', $this->payload());

        $this->assertStringContainsString(
            'đã được ghi nhận trước đó',
            $lanHai->json('message'),
        );
    }

    /** Mã giảm giá cũng chỉ được trừ một lượt cho một lần đặt. */
    public function test_don_trung_khong_tru_hai_luot_ma_giam_gia(): void
    {
        $ma = \App\Models\DiscountCode::query()->create([
            'code' => 'TEST' . Str::upper(Str::random(4)),
            'name' => 'Ma thu nghiem',
            'type' => 'percent',
            'value' => 10,
            'minimum_order_amount' => 0,
            'usage_limit' => 100,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $payload = $this->payload(['discount_code' => $ma->code]);

        $this->postJson('/api/bookings', $payload)->assertStatus(201);
        $this->postJson('/api/bookings', $payload)->assertStatus(201);

        $this->assertSame(1, (int) $ma->fresh()->used_count);
    }
}
