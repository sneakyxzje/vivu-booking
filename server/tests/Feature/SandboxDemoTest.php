<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Mail\BalanceReminderMail;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingMailDispatcher;
use App\Services\BookingPaymentService;
use App\Services\SandboxDemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sân thử nghiệm nghiệp vụ.
 *
 * Hai thứ phải đúng, và chúng kéo ngược nhau:
 *
 * 1. Nút tua thời gian phải đưa đơn tới ĐÚNG mốc, nếu không thì nó chứng minh nhầm chuyện khác.
 * 2. Quyền tua ấy phải chết cứng ngoài tour đánh dấu sandbox — trên dữ liệu thật, dời ngày khởi
 *    hành là dời hạn thanh toán của từng khách trên chuyến.
 */
class SandboxDemoTest extends TestCase
{
    use RefreshDatabase;

    private const TONG = 4_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function sandbox(): SandboxDemoService
    {
        return app(SandboxDemoService::class);
    }

    private function tour(bool $laSanThu): Tour
    {
        return Tour::factory()->create([
            'status' => 'active',
            'adult_price' => 2_000_000,
            'is_sandbox' => $laSanThu,
        ]);
    }

    private function chuyen(Tour $tour, int $ngayNua): TourSchedule
    {
        $start = now()->addDays($ngayNua)->setTime(6, 0);

        return TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 2,
        ]);
    }

    private function donDaCoc(TourSchedule $chuyen, float $daThu = 2_000_000): Booking
    {
        $khach = User::create([
            'name' => 'Khach San Thu',
            'email' => 'sandbox-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'customer_id' => $khach->id,
            'customer_name' => $khach->name,
            'customer_email' => $khach->email,
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'total_amount' => self::TONG,
            'status' => 'confirmed',
            'confirmed_at' => now()->subDays(2),
        ]);

        if ($daThu > 0) {
            BookingPayment::create([
                'booking_id' => $don->id,
                'kind' => 'deposit',
                'amount' => $daThu,
                'paid_at' => now()->subDays(2),
            ]);
        }

        return $don;
    }

    // --- Hàng rào ---------------------------------------------------------------------------

    /** Tour thật thì không tua được. Đây là điều kiện quan trọng nhất của cả tính năng. */
    public function test_khong_tua_duoc_tren_tour_that(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: false), 30);

        $this->expectException(BusinessRuleException::class);

        $this->sandbox()->tuaToiMoc($chuyen, 'qua_han_tra_not');
    }

    /** Và cửa API cũng phải chặn, không chỉ tầng dịch vụ. */
    public function test_api_chan_tua_tren_tour_that(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-sandbox@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $chuyen = $this->chuyen($this->tour(laSanThu: false), 30);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/sandbox/schedules/{$chuyen->id}/fast-forward", [
                'milestone' => 'qua_han_tra_not',
            ])
            ->assertStatus(422);
    }

    // --- Tua đúng mốc -----------------------------------------------------------------------

    /** Tua tới "vừa quá hạn trả nốt" thì đơn phải thật sự quá hạn. */
    public function test_tua_toi_qua_han_thi_don_qua_han_that(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 90);
        $don = $this->donDaCoc($chuyen);

        $this->assertFalse(
            now()->gte($don->balanceDueAt()),
            'Trước khi tua thì đơn còn xa hạn.',
        );

        $this->sandbox()->tuaToiMoc($chuyen, 'qua_han_tra_not');

        $this->assertTrue(
            now()->gte($don->fresh()->balanceDueAt()),
            'Sau khi tua thì hạn trả nốt phải nằm ở quá khứ.',
        );
    }

    /** Tua tới mốc nhắc thì chạy lệnh nhắc phải ra thư thật. */
    public function test_tua_toi_moc_nhac_roi_chay_lenh_thi_thu_bay_di(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 120);
        $don = $this->donDaCoc($chuyen);

        $this->sandbox()->tuaToiMoc($chuyen, 'toi_han_nhac');
        $this->sandbox()->chayLenh('bookings:send-balance-reminders');

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id,
        );
    }

    /**
     * Tua tới mốc đủ ân hạn rồi chạy lệnh hủy thì đơn bị hủy thật.
     *
     * Đây là bài chứng minh cả chuỗi: tua → nhắc → tua tiếp → hủy, đúng thứ người xem sẽ bấm.
     */
    public function test_ca_chuoi_tua_nhac_roi_huy(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 120);
        $don = $this->donDaCoc($chuyen);

        $this->sandbox()->tuaToiMoc($chuyen, 'toi_canh_bao_cuoi');
        $this->sandbox()->chayLenh('bookings:send-balance-reminders');

        $this->assertNotNull($don->fresh()->balance_final_notice_at, 'Phải nhận cảnh báo cuối.');

        // Lá thư gửi hôm nay nên ân hạn chưa hết — lùi mốc thư về quá khứ đúng như thời gian trôi.
        $don->fresh()->forceFill([
            'balance_final_notice_at' => now()->subDays(
                (int) config('booking.balance_final_notice_days', 2) + 1,
            ),
        ])->save();

        $this->sandbox()->tuaToiMoc($chuyen->fresh(), 'du_an_han_de_huy');
        $this->sandbox()->chayLenh('bookings:cancel-unpaid-balances');

        $this->assertSame('cancelled', $don->fresh()->status);
        Mail::assertQueued(BookingCancelledMail::class);
    }

    /** Tua kéo theo cả `departure_date` trên đơn, nếu không thì hai chỗ nói khác nhau. */
    public function test_tua_keo_theo_ngay_di_ghi_tren_don(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 90);
        $don = $this->donDaCoc($chuyen);

        $this->sandbox()->tuaToiMoc($chuyen, 'toi_han_chot');

        $moi = $chuyen->fresh();

        $this->assertSame(
            $moi->start_date->format('Y-m-d'),
            \Illuminate\Support\Carbon::parse($don->fresh()->departure_date)->format('Y-m-d'),
        );
    }

    // --- Gửi lại thư ------------------------------------------------------------------------

    /** Thư nhắc trả nốt không gửi cho đơn đã trả đủ. */
    public function test_khong_gui_thu_doi_tien_cho_don_da_tra_du(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 30);
        $don = $this->donDaCoc($chuyen, daThu: self::TONG);

        $this->expectException(BusinessRuleException::class);

        app(BookingMailDispatcher::class)->gui($don, 'balance_reminder');
    }

    /** Và thư báo hủy không gửi cho đơn còn hiệu lực. */
    public function test_khong_gui_thu_bao_huy_cho_don_con_hieu_luc(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 30);
        $don = $this->donDaCoc($chuyen);

        $this->expectException(BusinessRuleException::class);

        app(BookingMailDispatcher::class)->gui($don, 'cancelled');
    }

    /** Đúng hoàn cảnh thì gửi được, và gửi tới đúng địa chỉ của đơn. */
    public function test_gui_duoc_thu_nhac_khi_don_con_no(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 30);
        $don = $this->donDaCoc($chuyen);

        $ketQua = app(BookingMailDispatcher::class)->gui($don, 'balance_reminder');

        $this->assertSame($don->customer_email, $ketQua['gui_toi']);
        Mail::assertQueued(BalanceReminderMail::class);
    }

    // --- Ảnh chụp ---------------------------------------------------------------------------

    /** Bảng so sánh phải nói đúng số tiền, vì đó là thứ người xem đọc để tin. */
    public function test_anh_chup_noi_dung_so_tien(): void
    {
        $chuyen = $this->chuyen($this->tour(laSanThu: true), 30);
        $don = $this->donDaCoc($chuyen);

        $dong = collect($this->sandbox()->anhChup($chuyen))->firstWhere('id', $don->id);

        $this->assertNotNull($dong);
        $this->assertEquals(2_000_000, $dong['da_thu']);
        $this->assertEquals(2_000_000, $dong['con_thieu']);
        $this->assertEquals(
            app(BookingPaymentService::class)->nextPaymentAmount($don),
            $dong['phai_tra_lan_nay'],
        );
    }
}
