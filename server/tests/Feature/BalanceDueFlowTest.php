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


    private function daNhacDayDu(Booking $don): Booking
    {
        $anHan = (int) config('booking.balance_final_notice_days', 2);

        $don->forceFill([
            'balance_reminder_sent_at' => now()->subDays($anHan + 7),
            'balance_final_notice_at' => now()->subDays($anHan + 1),
        ])->save();

        return $don;
    }


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


    public function test_nhac_lan_dau_khi_toi_cua_so(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id && !$thu->laCanhBaoCuoi,
        );

        $this->assertNotNull($don->fresh()->balance_reminder_sent_at);
        $this->assertNull($don->fresh()->balance_final_notice_at, 'Chưa tới lúc cảnh báo cuối.');
    }


    public function test_con_xa_han_thi_chua_nhac(): void
    {
        $don = $this->donDaCoc($this->chuyen(40));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
        $this->assertNull($don->fresh()->balance_reminder_sent_at);
    }


    public function test_sat_han_thi_gui_canh_bao_cuoi(): void
    {
        $don = $this->donDaCoc($this->chuyen(4));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id && $thu->laCanhBaoCuoi,
        );

        $this->assertNotNull($don->fresh()->balance_final_notice_at);
    }


    public function test_chay_lai_khong_gui_trung(): void
    {
        $this->donDaCoc($this->chuyen(9));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();
        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueuedCount(1);
    }


    public function test_don_da_tra_du_khong_bi_nhac(): void
    {
        $don = $this->donDaCoc($this->chuyen(9), daThu: self::TONG);
        $don->forceFill(['paid_at' => now()])->save();

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
    }

    // --- Hủy khi quá hạn --------------------------------------------------------------------


    public function test_qua_han_thi_huy_don_va_khach_mat_coc(): void
    {
        $chuyen = $this->chuyen(2); // hạn trả nốt là 10 ngày trước đi, nên đã qua
        $don = $this->daNhacDayDu($this->donDaCoc($chuyen));

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $daSua = $don->fresh();

        $this->assertSame('cancelled', $daSua->status);
        $this->assertSame('unpaid_balance', $daSua->cancel_type);
        $this->assertEquals(0.0, (float) $daSua->refund_amount, 'Mất đúng tiền cọc, không hoàn đồng nào.');

        Mail::assertQueued(BookingCancelledMail::class);
    }


    public function test_qua_han_thi_cho_ve_kho(): void
    {
        $chuyen = $this->chuyen(2);
        $don = $this->daNhacDayDu($this->donDaCoc($chuyen));

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertTrue((bool) $don->fresh()->seats_released);
        $this->assertSame(0, (int) $chuyen->fresh()->booked_people);
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }


    public function test_dieu_hanh_duoc_bao_chuyen_vua_trong_cho(): void
    {
        $dieuHanh = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->daNhacDayDu($this->donDaCoc($this->chuyen(2)));

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $dieuHanh->id,
            'type' => Alert::class,
        ]);
    }


    public function test_vua_tra_not_thi_khong_bi_huy(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

        // Khách trả nốt trước khi lệnh chạy.
        $this->so()->record($don, 'balance', self::TONG - self::COC, 'gateway', 'GD-CUU-DON');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }


    public function test_don_chua_tra_dong_nao_khong_bi_huy_tu_dong(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9), daThu: 0));

        $this->assertSame(0, $don->payments()->count(), 'Đơn này chưa có bút toán nào.');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }


    public function test_con_trong_han_thi_khong_huy(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(15)));

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }


    public function test_don_doan_khong_bi_huy_tu_dong(): void
    {
        $chuyen = $this->chuyen(2);
        $don = $this->daNhacDayDu($this->donDaCoc($chuyen));

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


    public function test_dry_run_khong_huy_gi(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(2)));

        $this->artisan('bookings:cancel-unpaid-balances --dry-run')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    // --- Không hủy ai chưa từng được cảnh báo ------------------------------------------------


    public function test_qua_han_van_huy_khi_chua_nhan_canh_bao(): void
    {
        $don = $this->donDaCoc($this->chuyen(2));
        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();
        $this->assertSame('cancelled', $don->fresh()->status);
        Mail::assertQueued(BookingCancelledMail::class);
    }


    public function test_canh_bao_khong_gia_han_thanh_toan(): void
    {
        $don = $this->donDaCoc($this->chuyen(2));
        $don->forceFill(['balance_final_notice_at' => now()])->save();
        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();
        $this->assertSame('cancelled', $don->fresh()->status);
    }


    public function test_qua_han_khong_gui_thu_hua_an_han(): void
    {
        $don = $this->donDaCoc($this->chuyen(2));
        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();
        Mail::assertNotQueued(BalanceReminderMail::class);
        $this->assertNull($don->fresh()->balance_final_notice_at);
    }


    public function test_qua_han_va_da_tung_nhac_thi_thoi_nhac(): void
    {
        $this->daNhacDayDu($this->donDaCoc($this->chuyen(2)));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
    }

    // --- Đổi ngày làm quy trình tự động không kịp chạy ---------------------------------------


    public function test_con_no_ma_chuyen_qua_sat_thi_bao_dieu_hanh(): void
    {
        $anHan = (int) config('booking.balance_final_notice_days', 2);
        $hanChot = (int) config('booking.booking_deadline_days', 3);

        // Mốc phải vượt là HẠN CHỐT, tức ngày đi trừ $hanChot — không phải ngày đi.
        $sat = $this->donDaCoc($this->chuyen($hanChot + $anHan));
        $conKip = $this->donDaCoc($this->chuyen($hanChot + $anHan + 5));
        $daTraDu = $this->donDaCoc($this->chuyen($hanChot + $anHan), daThu: self::TONG);

        $so = $this->so();

        $this->assertTrue($so->tuDongThuNotKhongKip($sat), 'Sát hạn chốt mà còn nợ thì tự động không kịp.');
        $this->assertFalse($so->tuDongThuNotKhongKip($conKip), 'Còn thời gian thì để tác vụ nền lo.');
        $this->assertFalse($so->tuDongThuNotKhongKip($daTraDu), 'Trả đủ rồi thì không có gì để thu.');
    }


    public function test_kip_truoc_ngay_di_nhung_khong_kip_truoc_han_chot_van_phai_bao(): void
    {
        $don = $this->donDaCoc($this->chuyen(5));

        $this->assertTrue(
            $this->so()->tuDongThuNotKhongKip($don),
            'Hủy sau hạn chốt thì chỗ không bán lại được — phải để người xử lý.',
        );
    }

    // --- Thứ tự hai cái hạn ------------------------------------------------------------------


    public function test_han_tra_not_luon_trung_han_chot(): void
    {
        $chuyen = $this->chuyen(25);
        $don = $this->donDaCoc($chuyen);
        $this->assertTrue($don->balanceDueAt()->equalTo($chuyen->booking_deadline));
        $chuyen->update(['booking_deadline' => now()->addDays(3)]);
        $this->assertTrue($don->fresh()->balanceDueAt()->equalTo($chuyen->fresh()->booking_deadline));
    }

    // --- Lá thư gửi trước khi đổi chuyến thì coi như chưa gửi ---------------------------------


    private function daDoiChuyen(Booking $don, \Illuminate\Support\Carbon $luc): void
    {
        \App\Models\BookingTransfer::create([
            'booking_id' => $don->id,
            'from_schedule_id' => null,
            'to_schedule_id' => $don->tour_schedule_id,
            'initiated_by' => 'company',
            'price_difference' => 0,
            'fee' => 0,
            'reason' => 'Ghép chuyến',
            'approved_at' => $luc,
        ]);
    }


    public function test_thu_gui_truoc_khi_doi_chuyen_thi_khong_cho_huy(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

        // Đơn được chuyển sang chuyến này SAU khi lá thư đã gửi.
        $this->daDoiChuyen($don, now()->subDay());

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
        Mail::assertNotQueued(BookingCancelledMail::class);
    }


    public function test_thu_lac_hau_khong_chan_lan_nhac_moi(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(4)));
        $this->daDoiChuyen($don, now()->subDay());

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id,
        );
    }


    public function test_doi_chuyen_truoc_khi_gui_thu_thi_thu_van_co_hieu_luc(): void
    {
        $don = $this->donDaCoc($this->chuyen(2));

        $this->daDoiChuyen($don, now()->subDays(30));
        $this->daNhacDayDu($don);

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('cancelled', $don->fresh()->status);
    }


    public function test_don_doan_khong_nhan_la_canh_bao_cuoi(): void
    {
        $chuyen = $this->chuyen(4); // đã vào cửa sổ cảnh báo cuối
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

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
        $this->assertNull($don->fresh()->balance_final_notice_at);
    }
}
