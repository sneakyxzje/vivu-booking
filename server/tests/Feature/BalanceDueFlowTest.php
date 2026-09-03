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

    /**
     * Đơn đã đi qua đủ hai lá thư nhắc, tức đủ điều kiện để lệnh hủy đụng tới.
     *
     * Lệnh hủy chỉ xử lý đơn đã nhận cảnh báo cuối và đã qua khoảng ân hạn kể từ lá đó, nên phần lớn
     * bài dưới đây phải dựng cả lịch sử nhắc — đúng thứ mà một đơn thật đi tới bước bị hủy sẽ có.
     */
    private function daNhacDayDu(Booking $don): Booking
    {
        $anHan = (int) config('booking.balance_final_notice_days', 2);

        $don->forceFill([
            'balance_reminder_sent_at' => now()->subDays($anHan + 7),
            'balance_final_notice_at' => now()->subDays($anHan + 1),
        ])->save();

        return $don;
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
        $don = $this->daNhacDayDu($this->donDaCoc($chuyen));

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
        $don = $this->daNhacDayDu($this->donDaCoc($chuyen));

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

        $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

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
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

        // Khách trả nốt trước khi lệnh chạy.
        $this->so()->record($don, 'balance', self::TONG - self::COC, 'gateway', 'GD-CUU-DON');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /**
     * Đơn chưa từng trả đồng nào KHÔNG bị lệnh này hủy.
     *
     * Luật nói về người đã đặt cọc rồi không trả nốt. Một đơn `confirmed` mà sổ ghi 0 đồng không
     * thuộc nhóm ấy: nhiều khả năng khách đã trả tiền thật nhưng ai đó xác nhận tay mà quên ghi sổ
     * — đúng loại lỗi hệ thống vừa sửa ở đường xác nhận, nên dữ liệu cũ còn đầy những đơn như thế.
     *
     * Hủy chúng là hủy đơn của người đã trả tiền, và ghi nghĩa vụ hoàn bằng 0 vì sổ tưởng chưa thu
     * gì. Để điều hành xử lý tay; chúng vẫn hiện ở màn công nợ phải thu.
     */
    public function test_don_chua_tra_dong_nao_khong_bi_huy_tu_dong(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9), daThu: 0));

        $this->assertSame(0, $don->payments()->count(), 'Đơn này chưa có bút toán nào.');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /** Còn trong hạn thì không đụng tới — dựng sẵn cả dấu vết nhắc để chỉ còn ngày tháng là biến. */
    public function test_con_trong_han_thi_khong_huy(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(15)));

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

    /** `--dry-run` chỉ liệt kê, không đụng vào đơn nào. */
    public function test_dry_run_khong_huy_gi(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

        $this->artisan('bookings:cancel-unpaid-balances --dry-run')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    // --- Không hủy ai chưa từng được cảnh báo ------------------------------------------------

    /**
     * Quá hạn nhưng CHƯA nhận cảnh báo cuối thì không bị hủy.
     *
     * Đây là cảnh của đơn vừa được chuyển sang chuyến gần hơn: hạn trả nốt tính theo chuyến đích nên
     * nó đã nằm ở quá khứ ngay lúc đơn hạ cánh xuống đó. Lệnh nhắc bỏ qua vì "quá hạn rồi nhắc gì
     * nữa", còn lệnh hủy thì quét trúng — người bị công ty dời ngày mất luôn chuyến, không một lời
     * báo trước. Điều kiện này là thứ duy nhất chặn được chuỗi ấy.
     */
    public function test_chua_nhan_canh_bao_cuoi_thi_khong_bi_huy(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));

        $this->assertNull($don->balance_final_notice_at, 'Đơn này chưa nhận lá nào.');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
        Mail::assertNotQueued(BookingCancelledMail::class);
    }

    /** Vừa nhận cảnh báo cuối hôm nay thì chưa hủy — phải cho hết khoảng ân hạn đã. */
    public function test_vua_nhan_canh_bao_cuoi_thi_chua_bi_huy(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));
        $don->forceFill(['balance_final_notice_at' => now()])->save();

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /**
     * Đơn quá hạn mà chưa từng được nhắc thì nhận một lá thư muộn, thay vì bị bỏ rơi.
     *
     * Không có lá này thì luật ở trên biến đơn ấy thành đơn không ai đụng tới mãi mãi: lệnh nhắc bỏ
     * qua vì quá hạn, lệnh hủy bỏ qua vì chưa được nhắc. Hai điều kiện phải đi cùng nhau.
     */
    public function test_qua_han_ma_chua_tung_nhac_thi_van_nhan_thu(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id,
        );
        $this->assertNotNull($don->fresh()->balance_final_notice_at);
    }

    /** Đã bỏ qua hai lá thư rồi thì không nhắc nữa — lúc ấy thư đúng là thư báo hủy. */
    public function test_qua_han_va_da_tung_nhac_thi_thoi_nhac(): void
    {
        $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertNotQueued(BalanceReminderMail::class);
    }

    // --- Đổi ngày làm quy trình tự động không kịp chạy ---------------------------------------

    /**
     * Đơn còn nợ mà chuyến khởi hành quá sát thì tự động không kịp, phải gọi người.
     *
     * Dây chuyền cần `ân hạn + 1` ngày: một ngày để lệnh nhắc chạy, rồi mấy ngày ân hạn mới tới lượt
     * hủy. Chuyến đi sớm hơn ngần ấy thì cả dây vô nghĩa — và đây đúng là cảnh mà ghép chuyến tạo ra.
     */
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

    /**
     * Khoảng mà bản cũ bỏ lọt: hủy kịp trước ngày ĐI nhưng không kịp trước HẠN CHỐT.
     *
     * Chuyến đi sau 5 ngày, hạn chốt sau 2 ngày. Dây chuyền cần 3 ngày nên lượt hủy sớm nhất rơi
     * vào ngày thứ 3 — đã qua hạn chốt. Lúc ấy chỗ không về kho nữa mà thành ghế chết: công ty trả
     * tiền cho một chỗ không có khách, và vẫn hủy đơn của khách ngay trước ngày đi.
     *
     * Đo tới ngày khởi hành thì khoảng này im lặng, vì 3 ngày vẫn nhỏ hơn 5.
     */
    public function test_kip_truoc_ngay_di_nhung_khong_kip_truoc_han_chot_van_phai_bao(): void
    {
        $don = $this->donDaCoc($this->chuyen(5));

        $this->assertTrue(
            $this->so()->tuDongThuNotKhongKip($don),
            'Hủy sau hạn chốt thì chỗ không bán lại được — phải để người xử lý.',
        );
    }

    // --- Thứ tự hai cái hạn ------------------------------------------------------------------

    /**
     * Hạn chốt danh sách không được đặt sớm hơn hạn trả nốt.
     *
     * Khoảng giữa hai mốc chính là cửa sổ bán lại. Đảo thứ tự thì chỗ của người bỏ cọc không bán
     * lại được nữa, và mọi lượt hủy tự động đều để lại một ghế chết.
     */
    public function test_han_chot_khong_duoc_som_hon_han_tra_not(): void
    {
        $khoiHanh = now()->addDays(25);
        $hanTraNot = (int) config('booking.balance_due_days', 10);

        $this->assertNotNull(
            \App\Services\ScheduleDeadlineService::lyDoDaoNguocHaiHan(
                $khoiHanh,
                $khoiHanh->copy()->subDays($hanTraNot + 10),
            ),
            'Hạn chốt 20 ngày trước khi đi thì sớm hơn hạn trả nốt — phải bị chặn.',
        );

        $this->assertNull(
            \App\Services\ScheduleDeadlineService::lyDoDaoNguocHaiHan(
                $khoiHanh,
                $khoiHanh->copy()->subDays(3),
            ),
            'Hạn chốt mặc định nằm sau hạn trả nốt — hợp lệ.',
        );

        $this->assertNull(
            \App\Services\ScheduleDeadlineService::lyDoDaoNguocHaiHan(
                $khoiHanh,
                $khoiHanh->copy()->subDays($hanTraNot),
            ),
            'Trùng đúng hạn trả nốt vẫn chấp nhận: cửa sổ bán lại bằng 0 chứ không âm.',
        );
    }

    // --- Lá thư gửi trước khi đổi chuyến thì coi như chưa gửi ---------------------------------

    /** Dựng một lần đổi chuyến đã duyệt vào thời điểm chỉ định. */
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

    /**
     * Lá cảnh báo cuối gửi TRƯỚC lần đổi chuyến thì không cho phép hủy.
     *
     * Nó nói về hạn của một ngày khởi hành đã không còn tồn tại. Không loại nó ra thì một cái mốc
     * hai tháng tuổi vẫn thỏa điều kiện ân hạn ngay lập tức, và đơn bị hủy vì một cái hạn chưa lá
     * thư nào nói tới.
     */
    public function test_thu_gui_truoc_khi_doi_chuyen_thi_khong_cho_huy(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(9)));

        // Đơn được chuyển sang chuyến này SAU khi lá thư đã gửi.
        $this->daDoiChuyen($don, now()->subDay());

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
        Mail::assertNotQueued(BookingCancelledMail::class);
    }

    /** Và lá lạc hậu ấy cũng không chặn lệnh nhắc gửi lá mới cho chuyến mới. */
    public function test_thu_lac_hau_khong_chan_lan_nhac_moi(): void
    {
        $don = $this->daNhacDayDu($this->donDaCoc($this->chuyen(11)));
        $this->daDoiChuyen($don, now()->subDay());

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        Mail::assertQueued(
            BalanceReminderMail::class,
            fn (BalanceReminderMail $thu) => $thu->booking->id === $don->id,
        );
    }

    /** Đổi chuyến TRƯỚC khi gửi thư thì lá thư vẫn còn hiệu lực — không nới ân hạn oan. */
    public function test_doi_chuyen_truoc_khi_gui_thu_thi_thu_van_co_hieu_luc(): void
    {
        $don = $this->donDaCoc($this->chuyen(9));

        $this->daDoiChuyen($don, now()->subDays(30));
        $this->daNhacDayDu($don);

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $this->assertSame('cancelled', $don->fresh()->status);
    }

    /**
     * Đơn đoàn không nhận lá cảnh báo cuối.
     *
     * Lá ấy nói thẳng "quá hạn thì hủy đơn và mất cọc", mà lệnh hủy lại cố ý chừa đơn đoàn ra. Gửi
     * nó cho khách đoàn là dọa một điều công ty không bao giờ làm, và người phải đính chính là điều
     * hành. Lá nhắc nhẹ thì vẫn gửi được: nó chỉ nêu số còn thiếu.
     */
    public function test_don_doan_khong_nhan_la_canh_bao_cuoi(): void
    {
        $chuyen = $this->chuyen(11); // đã vào cửa sổ cảnh báo cuối
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
