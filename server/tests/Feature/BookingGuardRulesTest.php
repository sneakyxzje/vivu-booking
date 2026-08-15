<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Mail\ResendLookupCodeMail;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * X14 - Phần còn lại của nhóm X: X02 và X06.
 *
 * Hai tình huống nhỏ nhưng ở hai đầu đối lập của trải nghiệm: một cái chặn đơn không hợp lệ,
 * một cái cứu khách đã mất mã tra cứu.
 */
class BookingGuardRulesTest extends TestCase
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

    // --- X02: đơn phải có người lớn đi kèm ---------------------------------------------

    /**
     * Đơn toàn trẻ em không tồn tại trên thực tế: nhà xe và khách sạn đều không nhận đoàn không
     * có người lớn chịu trách nhiệm.
     */
    public function test_don_khong_co_nguoi_lon_bi_tu_choi(): void
    {
        $this->postJson('/api/bookings', $this->payload([
            'adult_count' => 0,
            'child_count' => 2,
        ]))->assertStatus(422)->assertJsonValidationErrors('adult_count');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_don_chi_co_em_be_cung_bi_tu_choi(): void
    {
        $this->postJson('/api/bookings', $this->payload([
            'adult_count' => 0,
            'infant_count' => 1,
        ]))->assertStatus(422)->assertJsonValidationErrors('adult_count');
    }

    public function test_mot_nguoi_lon_di_kem_tre_em_thi_duoc(): void
    {
        $this->postJson('/api/bookings', $this->payload([
            'adult_count' => 1,
            'child_count' => 2,
        ]))->assertStatus(201);

        $this->assertSame(3, (int) Booking::query()->first()->guests);
    }

    // --- X06: gửi lại mã tra cứu --------------------------------------------------------

    public function test_gui_lai_ma_tra_cuu_cho_khach_co_don(): void
    {
        Mail::fake();

        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $this->postJson('/api/bookings/resend-code', [
            'email' => 'khach@example.com',
        ])->assertOk();

        Mail::assertSent(ResendLookupCodeMail::class);
    }

    /**
     * Email lạ vẫn trả về cùng một thông báo và không gửi thư.
     *
     * Trả lời khác nhau cho email có đơn và email không có đơn là biến trang tra cứu thành công
     * cụ dò xem ai từng đặt tour ở đây.
     */
    public function test_email_khong_co_don_van_tra_ve_thong_bao_giong_het(): void
    {
        Mail::fake();

        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $coDon = $this->postJson('/api/bookings/resend-code', ['email' => 'khach@example.com']);
        $khongCoDon = $this->postJson('/api/bookings/resend-code', ['email' => 'nguoila@example.com']);

        $this->assertSame($coDon->json('message'), $khongCoDon->json('message'));

        Mail::assertNotSent(
            ResendLookupCodeMail::class,
            fn (ResendLookupCodeMail $mail) => $mail->hasTo('nguoila@example.com'),
        );
    }

    public function test_don_da_huy_khong_nam_trong_thu_ma_tra_cuu(): void
    {
        Mail::fake();

        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        Booking::query()->update(['status' => 'cancelled']);

        $this->postJson('/api/bookings/resend-code', [
            'email' => 'khach@example.com',
        ])->assertOk();

        Mail::assertNotSent(ResendLookupCodeMail::class);
    }

    public function test_email_sai_dinh_dang_bi_tu_choi(): void
    {
        $this->postJson('/api/bookings/resend-code', ['email' => 'khong-phai-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** Lọc thêm theo số điện thoại khi khách cung cấp, để không lộ đơn của người trùng email. */
    public function test_loc_them_theo_so_dien_thoai_khi_co(): void
    {
        Mail::fake();

        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $this->postJson('/api/bookings/resend-code', [
            'email' => 'khach@example.com',
            'phone' => '0900000000',
        ])->assertOk();

        Mail::assertNotSent(ResendLookupCodeMail::class);
    }

    public function test_ma_tra_cuu_gui_di_la_ma_that_cua_don(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(201);

        $don = Booking::query()->first();

        $this->assertNotEmpty($don->public_token);
        $this->assertTrue(Str::isUuid($don->public_token));

        // Mã tra cứu phải mở được đơn mà không cần đăng nhập.
        $this->getJson("/api/bookings/{$don->public_token}")
            ->assertOk()
            ->assertJsonPath('data.id', $don->id);
    }
}
