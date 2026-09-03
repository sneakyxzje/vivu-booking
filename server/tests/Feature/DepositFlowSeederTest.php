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

    public function test_dung_du_sau_tinh_huong(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->assertCount(6, $this->donCuaSeeder());
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

        $don = $this->donCuaSeeder()->keyBy(fn (Booking $b) => $b->note);

        $conXa = $don->first(fn ($b) => str_contains($b->note, 'còn xa hạn'));
        $nhacDau = $don->first(fn ($b) => str_contains($b->note, 'nhắc lần đầu'));
        $canhBao = $don->first(fn ($b) => str_contains($b->note, 'cảnh báo cuối'));

        $this->assertNull($conXa->fresh()->balance_reminder_sent_at, 'Đơn còn xa hạn chưa được nhắc.');
        $this->assertNotNull($nhacDau->fresh()->balance_reminder_sent_at, 'Đơn tới lượt phải được nhắc.');
        $this->assertNotNull($canhBao->fresh()->balance_final_notice_at, 'Đơn sát hạn phải nhận cảnh báo cuối.');

        $this->artisan('bookings:cancel-unpaid-balances')->assertSuccessful();

        $quaHan = $don->first(fn ($b) => str_contains($b->note, 'Đã quá hạn'));
        $traDu = $don->first(fn ($b) => str_contains($b->note, 'đã trả đủ'));
        $doan = $don->first(fn ($b) => str_contains($b->note, 'ĐOÀN'));

        $this->assertSame('cancelled', $quaHan->fresh()->status, 'Đơn quá hạn phải bị hủy.');
        $this->assertEquals(0.0, (float) $quaHan->fresh()->refund_amount, 'Mất đúng tiền cọc.');
        $this->assertTrue((bool) $quaHan->fresh()->seats_released, 'Chỗ phải về kho.');

        $this->assertSame('confirmed', $traDu->fresh()->status, 'Đơn đã trả đủ không bị đụng.');
        $this->assertSame('confirmed', $doan->fresh()->status, 'Đơn đoàn không bị hủy tự động.');
    }

    /** Chạy lại seeder không nhân đôi dữ liệu — người thử seed nhiều lần trong một buổi. */
    public function test_chay_lai_khong_nhan_doi_du_lieu(): void
    {
        $this->seed(DepositFlowSeeder::class);
        $this->seed(DepositFlowSeeder::class);

        $this->assertCount(6, $this->donCuaSeeder());
    }

    /** Số chỗ của chuyến phải khớp ngay sau khi seed, nếu không mọi phép thử sau đều lệch. */
    public function test_so_cho_khop_sau_khi_seed(): void
    {
        $this->seed(DepositFlowSeeder::class);

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }
}
