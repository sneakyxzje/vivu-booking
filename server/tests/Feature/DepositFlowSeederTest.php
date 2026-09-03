<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\BookingPaymentService;
use Database\Seeders\DepositFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Seeder dựng dữ liệu thử tay cho luồng đặt cọc.
 *
 * Bài này giữ cho bảng hướng dẫn seeder in ra nói đúng về chính dữ liệu nó vừa tạo. Một seeder thử
 * tay dựng sai mốc còn tệ hơn không có: người thử chạy lệnh, thấy kết quả khác bảng, rồi mất buổi
 * đi tìm lỗi trong mã nghiệp vụ vốn đang đúng.
 */
class DepositFlowSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Seeder cần một admin để gắn tour, và một chính sách hủy đang hiệu lực.
        $this->seed(\Database\Seeders\CancellationPolicySeeder::class);

        \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin-seed@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function donCuaSeeder()
    {
        return Booking::query()->where('note', 'like', '[coc]%')->orderBy('id')->get();
    }

    public function test_dung_du_muoi_mot_tinh_huong(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->assertCount(11, $this->donCuaSeeder());
    }

    /** Hai chuyến để trống, để tự đặt tour và so cọc với trả đủ. */
    public function test_co_hai_chuyen_trong_de_tu_dat(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $chuyenTrong = \App\Models\TourSchedule::query()
            ->whereDoesntHave('bookings')
            ->get();

        $this->assertCount(2, $chuyenTrong);

        $hanTraNot = (int) config('booking.balance_due_days', 10);

        // Một chuyến còn đủ xa để được cọc, một chuyến sát ngày phải trả đủ.
        $this->assertTrue(
            $chuyenTrong->contains(fn ($c) => now()->diffInDays($c->start_date, false) > $hanTraNot),
            'Phải có một chuyến còn xa hơn hạn trả nốt.',
        );
        $this->assertTrue(
            $chuyenTrong->contains(fn ($c) => now()->diffInDays($c->start_date, false) < $hanTraNot),
            'Phải có một chuyến sát ngày, đặt vào đó là phải trả đủ.',
        );
    }

    /** Đơn mới cọc phải còn nợ đúng một nửa, và KHÔNG được đóng mốc đã-thanh-toán. */
    public function test_don_moi_coc_con_no_dung_mot_nua(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $don = $this->donCuaSeeder()->first(fn (Booking $b) => str_contains($b->note, 'còn xa hạn'));

        $this->assertNotNull($don);
        $this->assertNull($don->paid_at, 'Mới cọc thì chưa đóng mốc đã-thanh-toán.');
        $this->assertEquals(
            5_000_000,
            app(BookingPaymentService::class)->balanceDue($don),
            'Đơn 10 triệu cọc 50% thì còn thiếu 5 triệu.',
        );
    }

    /**
     * Bài quan trọng nhất: chạy hai lệnh nền lên dữ liệu seeder phải ra đúng thứ bảng hướng dẫn hứa.
     *
     * Đây là chỗ seeder dễ sai nhất — mốc lệch một ngày là cả bảng nói dối, mà lệch một ngày thì
     * nhìn bằng mắt không thấy.
     */
    public function test_hai_lenh_nen_xu_ly_dung_nhung_don_bang_huong_dan_hua(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        $don = $this->donCuaSeeder();
        $tim = fn (string $manh) => $don->first(fn (Booking $b) => str_contains($b->note, $manh));

        // --- Ranh giới 1: cửa sổ nhắc -------------------------------------------------------

        $this->assertNull(
            $tim('còn xa hạn')->fresh()->balance_reminder_sent_at,
            'Đơn còn xa hạn chưa được nhắc.',
        );
        $this->assertNotNull(
            $tim('nhắc lần đầu')->fresh()->balance_reminder_sent_at,
            'Đơn tới lượt phải được nhắc.',
        );
        $this->assertNotNull(
            $tim('cảnh báo cuối')->fresh()->balance_final_notice_at,
            'Đơn sát hạn phải nhận cảnh báo cuối.',
        );
        $this->assertNull(
            $tim('CHUYẾN ĐÃ HỦY')->fresh()->balance_reminder_sent_at,
            'Chuyến đã hủy thì không đòi tiền khách nữa.',
        );

        // --- Ranh giới 2 và 3: điều kiện bị hủy ---------------------------------------------

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $quaHan = $tim('đã quá hạn trả nốt')->fresh();

        $this->assertSame('cancelled', $quaHan->status, 'Đơn đã cọc mà quá hạn phải bị hủy.');
        $this->assertEquals(0.0, (float) $quaHan->refund_amount, 'Mất đúng tiền cọc.');
        $this->assertTrue((bool) $quaHan->seats_released, 'Chỗ phải về kho.');

        $this->assertSame(
            'confirmed',
            $tim('đã trả đủ')->fresh()->status,
            'Đơn đã trả đủ không bị đụng.',
        );
        $this->assertSame(
            'confirmed',
            $tim('sổ ghi 0 đồng')->fresh()->status,
            'Đơn chưa có bút toán nào để điều hành xử lý tay, không hủy tự động.',
        );
        $this->assertSame(
            'confirmed',
            $tim('ĐOÀN')->fresh()->status,
            'Đơn đoàn không bị hủy tự động.',
        );
        $this->assertSame(
            'confirmed',
            $tim('CHƯA nhận thư nào')->fresh()->status,
            'Chưa từng được cảnh báo thì không bị hủy, dù đã quá hạn.',
        );

        // --- Ranh giới 4: đặt sát ngày ------------------------------------------------------

        $this->assertSame(
            'confirmed',
            $tim('Đặt sát ngày')->fresh()->status,
            'Đơn đặt sát ngày đã trả đủ thì không có gì để hủy.',
        );
        $this->assertSame(
            'pending',
            $tim('Chờ thanh toán')->fresh()->status,
            'Đơn chưa cọc vẫn chờ trong hạn giữ chỗ, không thuộc luồng này.',
        );
    }

    /**
     * Đúng một đơn bị hủy — không hơn.
     *
     * Con số này là thứ bảng hướng dẫn hứa với người thử, và cũng là phép kiểm mạnh nhất của cả bộ:
     * chín đơn còn lại mỗi đơn chặn lệnh vì một lý do khác nhau, nên chỉ cần một điều kiện lọc sai
     * là con số này lệch ngay.
     */
    public function test_dung_mot_don_bi_huy(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $daHuy = $this->donCuaSeeder()->filter(fn (Booking $b) => $b->fresh()->status === 'cancelled');

        $this->assertCount(1, $daHuy);
    }

    /**
     * Đúng ba lá thư bay đi — nhắc nhẹ, cảnh báo cuối, và lá muộn cho đơn vừa bị chuyển chuyến.
     *
     * Đếm thư THỰC GỬI chứ không đếm cột đánh dấu: seeder cố ý dựng sẵn dấu vết nhắc cho đơn sắp bị
     * hủy, nên đếm cột thì lẫn giữa "lệnh vừa gửi" và "vốn đã có sẵn".
     */
    public function test_dung_ba_la_thu_nhac_duoc_gui(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();

        $this->assertCount(3, Mail::queued(\App\Mail\BalanceReminderMail::class));
    }

    /**
     * Đơn quá hạn mà chưa từng nhận thư thì được cứu: nhận thư, và KHÔNG bị hủy trong cùng ngày.
     *
     * Đây là bài canh chính cái lỗ hổng vừa vá. Trước đó đơn như thế — điển hình là đơn vừa được
     * chuyển sang chuyến gần hơn — bị hủy ngay lần chạy đầu tiên mà không một lời báo trước.
     */
    public function test_don_qua_han_chua_tung_nhac_thi_duoc_nhac_chu_khong_bi_huy(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $don = $this->donCuaSeeder()->first(fn (Booking $b) => str_contains($b->note, 'CHƯA nhận thư nào'));

        $this->assertNotNull($don);

        $this->artisan('bookings:send-balance-reminders')->assertSuccessful();
        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $daSua = $don->fresh();

        $this->assertNotNull($daSua->balance_final_notice_at, 'Phải nhận được lá thư muộn.');
        $this->assertSame('confirmed', $daSua->status, 'Nhận thư xong không được hủy ngay trong ngày.');
    }

    /** Chạy lại seeder không nhân đôi dữ liệu — người thử seed nhiều lần trong một buổi. */
    public function test_chay_lai_khong_nhan_doi_du_lieu(): void
    {
        $this->seed(DepositFlowSeeder::class);
        $this->seed(DepositFlowSeeder::class);

        $this->assertCount(11, $this->donCuaSeeder());
    }

    /** Số chỗ của chuyến phải khớp ngay sau khi seed, nếu không mọi phép thử sau đều lệch. */
    public function test_so_cho_khop_sau_khi_seed(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }
}
