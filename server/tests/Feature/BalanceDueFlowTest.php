<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Mail\BalanceReminderMail;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nhắc trả nốt, rồi hủy khi quá hạn.
 *
 * Đây là đoạn duy nhất trong hệ thống mà một tác vụ nền lấy tiền của khách, nên nó phải đúng ở cả
 * hai đầu: không được hủy nhầm người đã trả, và không được hủy ai chưa từng nhận lời nhắc.
 */
class BalanceDueFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private User $khach;

    private const TONG = 4_000_000;
    private const COC = 2_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->khach = User::create([
            'name' => 'Khach Coc',
            'email' => 'khach-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create(['status' => 'active', 'adult_price' => 2_000_000]);
    }

    /** Chuyến khởi hành sau $ngayNua ngày. */
    private function chuyen(int $ngayNua): TourSchedule
    {
        $start = now()->addDays($ngayNua);

        return TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 2,
        ]);
    }

    /** Đơn đã cọc, còn nợ phần đuôi. */
    private function donDaCoc(TourSchedule $chuyen, float $daThu = self::COC): Booking
    {
        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'customer_id' => $this->khach->id,
            'customer_name' => $this->khach->name,
            'customer_email' => $this->khach->email,
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => self::TONG,
            'status' => 'confirmed',
            'confirmed_at' => now()->subDays(5),
        ]);

        if ($daThu > 0) {
            BookingPayment::create([
                'booking_id' => $don->id,
                'kind' => 'deposit',
                'amount' => $daThu,
                'paid_at' => now()->subDays(5),
            ]);
        }

        return $don;
    }

    private function so(): BookingPaymentService
    {
        return app(BookingPaymentService::class);
    }

    // --- Nhắc trước hạn ---------------------------------------------------------------------

    /** Hạn trả nốt là 10 ngày trước khởi hành, nhắc lần đầu 7 ngày trước hạn — tức 17 ngày trước đi. */
    public function test_nhac_lan_dau_khi_toi_cua_so(): void
    {
        $don = $this->donDaCoc($this->chuyen(16));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id && !$thu->laCanhBaoCuoi,
        );

        $this->assertNotNull($don->fresh()->balance_reminder_sent_at);
        $this->assertNull($don->fresh()->balance_final_notice_at, 'Chưa tới lúc cảnh báo cuối.');
    }

    /** Còn xa thì chưa nhắc: nhắc quá sớm thì tới hạn khách đã quên. */
    public function test_con_xa_han_thi_chua_nhac(): void
    {
        $don = $this->donDaCoc($this->chuyen(40));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
        $this->assertNull($don->fresh()->balance_reminder_sent_at);
    }

    /** Sát hạn thì gửi cảnh báo cuối, và lá này phải nói thẳng hậu quả. */
    public function test_sat_han_thi_gui_canh_bao_cuoi(): void
    {
        $don = $this->donDaCoc($this->chuyen(11));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id && $thu->laCanhBaoCuoi,
        );

        $this->assertNotNull($don->fresh()->balance_final_notice_at);
    }

    /** Chạy lại không gửi trùng — lệnh chạy mỗi ngày, khách không nhận một lá mỗi sáng. */
    public function test_chay_lai_khong_gui_trung(): void
    {
        $this->donDaCoc($this->chuyen(16));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();
        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueuedCount(1);
    }

    /** Đơn đã trả đủ thì không bị nhắc nợ. */
    public function test_don_da_tra_du_khong_bi_nhac(): void
    {
        $don = $this->donDaCoc($this->chuyen(16), daThu: self::TONG);
        $don->forceFill(['paid_at' => now()])->save();

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
    }

    // --- Hủy khi quá hạn --------------------------------------------------------------------

    /**
     * Bài quan trọng nhất. Quá hạn thì đơn bị hủy, khách mất đúng phần đã cọc, và chỗ về kho.
     *
     * Mất cọc ở đây KHÔNG đến từ một điều khoản riêng: bậc phí hủy tại mốc này là 50% giá tour, mà
     * khách đã đưa đúng 50% — hoàn ra bằng không. Đó là lý do hạn trả nốt và tỷ lệ cọc phải nhìn nhau.
     */
    public function test_qua_han_thi_huy_don_va_khach_mat_coc(): void
    {
        $chuyen = $this->chuyen(9); // hạn trả nốt là 10 ngày trước đi, nên đã qua
        $don = $this->donDaCoc($chuyen);

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $daSua = $don->fresh();

        $this->assertSame('cancelled', $daSua->status);
        $this->assertSame('unpaid_balance', $daSua->cancel_type);
        $this->assertEquals(0.0, (float) $daSua->refund_amount, 'Mất đúng tiền cọc, không hoàn đồng nào.');

        Mail::assertQueued(BookingCancelledMail::class);
    }

    /** Chỗ phải về kho: hạn trả nốt nằm trước hạn chốt nên còn cửa sổ bán lại. */
    public function test_qua_han_thi_cho_ve_kho(): void
    {
        $chuyen = $this->chuyen(9);
        $don = $this->donDaCoc($chuyen);

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertTrue((bool) $don->fresh()->seats_released);
        $this->assertSame(0, (int) $chuyen->fresh()->booked_people);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    /** Điều hành được báo để còn quyết bán tiếp hay chịu đi thiếu. */
    public function test_dieu_hanh_duoc_bao_chuyen_vua_trong_cho(): void
    {
        $dieuHanh = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->donDaCoc($this->chuyen(9));

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $dieuHanh->id,
            'type' => Alert::class,
        ]);
    }

    /**
     * Khách vừa trả nốt đúng lúc lệnh chạy thì KHÔNG bị hủy.
     *
     * Lệnh đọc lại đơn dưới khóa dòng trước khi hủy, nên khoản tiền về giữa chừng vẫn cứu được đơn.
     */
    public function test_vua_tra_not_thi_khong_bi_huy(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));

        // Khách trả nốt trước khi lệnh chạy.
        $this->so()->record($don, 'balance', self::TONG - self::COC, 'gateway', 'GD-CUU-DON');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /** Còn trong hạn thì không đụng tới. */
    public function test_con_trong_han_thi_khong_huy(): void
    {
        $don = $this->donDaCoc($this->chuyen(15));

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /**
     * Đơn đoàn không bị hủy tự động.
     *
     * Hủy một đoàn bốn mươi người vì kế toán bên họ chuyển chậm một ngày là thiệt hại không cân xứng
     * với thứ luật này bảo vệ. Tiền đoàn vốn do điều hành ghi tay nên luôn có người theo.
     */
    public function test_don_doan_khong_bi_huy_tu_dong(): void
    {
        $chuyen = $this->chuyen(9);
        $don = $this->donDaCoc($chuyen);

        $yeuCau = \App\Models\GroupBookingRequest::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'contact_name' => 'Cong ty ABC',
            'contact_email' => 'abc@example.com',
            'contact_phone' => '0901234567',
            'estimated_guests' => 2,
            'status' => \App\Enums\GroupRequestStatus::Confirmed,
        ]);

        $don->forceFill(['group_booking_request_id' => $yeuCau->id])->save();

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /** `--dry-run` chỉ liệt kê, không đụng vào đơn nào. */
    public function test_dry_run_khong_huy_gi(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));

        $this->artisan('bookings:cancel-unpaid-balances --dry-run')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }
}
