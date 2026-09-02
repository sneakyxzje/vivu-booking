<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
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
 * Tài khoản nhận tiền hoàn cho các khoản hoàn do CÔNG TY khởi xướng.
 *
 * Số tài khoản trước đây chỉ được hỏi ở form khách tự xin hủy. Hai đường còn lại - điều hành hủy
 * đơn, và công ty hủy cả chuyến - đều lập ra nghĩa vụ phải trả mà không có ô nào chứa nơi để trả.
 * Kế toán mở màn hình "Chờ hoàn tiền" ra, thấy số tiền và tên khách, rồi phải gọi điện xin số tài
 * khoản và ghi vào sổ tay riêng.
 */
class RefundAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyen;

    private const TAI_KHOAN = [
        'refund_bank_account' => '0123456789',
        'refund_bank_name' => 'Vietcombank',
        'refund_account_holder' => 'NGUYEN VAN A',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
        ]);

        $start = now()->addDays(10);

        $this->chuyen = TourSchedule::create([
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

    private function taoDonDaTra(): Booking
    {
        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khach Vang Lai',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $this->chuyen->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'paid_at' => now()->subDay(),
            'confirmed_at' => now()->subDay(),
        ]);

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'balance',
            'amount' => 4_000_000,
            'paid_at' => now()->subDay(),
        ]);

        return $don;
    }

    /** Công ty hủy chuyến rồi khách tự nhập tài khoản bằng mã tra cứu, không cần đăng nhập. */
    public function test_khach_vang_lai_nhap_duoc_tai_khoan_sau_khi_bi_huy_chuyen(): void
    {
        Mail::fake();
        $don = $this->taoDonDaTra();

        Sanctum::actingAs($this->dieuHanh);
        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Bao vao nen chuyen khong the khoi hanh.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        // Khách không đăng nhập, chỉ có mã tra cứu trong thư báo hủy.
        app('auth')->forgetGuards();

        $this->putJson('/api/bookings/' . $don->public_token . '/refund-account', self::TAI_KHOAN)
            ->assertOk();

        $daSua = $don->fresh();

        $this->assertSame('0123456789', $daSua->refund_bank_account);
        $this->assertSame('Vietcombank', $daSua->refund_bank_name);
        $this->assertSame('NGUYEN VAN A', $daSua->refund_account_holder);
    }

    /** Kế toán đọc được ngay số tài khoản ở hàng đợi hoàn tiền. */
    public function test_tai_khoan_hien_o_hang_doi_hoan_tien(): void
    {
        Mail::fake();
        $don = $this->taoDonDaTra();

        Sanctum::actingAs($this->dieuHanh);
        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Bao vao nen chuyen khong the khoi hanh.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $this->putJson('/api/admin/bookings/' . $don->id . '/refund-account', self::TAI_KHOAN)
            ->assertOk();

        $hangDoi = $this->getJson('/api/admin/refunds')->assertOk()->json('data.data');

        $this->assertSame('0123456789', $hangDoi[0]['refund_bank']['account_number']);
        $this->assertSame('NGUYEN VAN A', $hangDoi[0]['refund_bank']['account_holder']);
    }

    /**
     * Đơn không nợ khách đồng nào thì không nhận số tài khoản.
     *
     * Số tài khoản là dữ liệu nhạy cảm: thu thập khi không có gì để hoàn là giữ một thứ không dùng
     * tới, và để ngỏ thì điểm cuối công khai này thành chỗ ghi bừa vào đơn người khác.
     */
    public function test_don_khong_no_tien_thi_khong_nhan_tai_khoan(): void
    {
        $don = $this->taoDonDaTra();

        $this->putJson('/api/bookings/' . $don->public_token . '/refund-account', self::TAI_KHOAN)
            ->assertStatus(422);

        $this->assertNull($don->fresh()->refund_bank_account);
    }

    /** Trả xong rồi thì cũng thôi nhận: nghĩa vụ đã đóng. */
    public function test_da_hoan_xong_thi_khong_con_nhan_tai_khoan(): void
    {
        Mail::fake();
        $don = $this->taoDonDaTra();

        Sanctum::actingAs($this->dieuHanh);
        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Bao vao nen chuyen khong the khoi hanh.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $this->postJson('/api/admin/bookings/' . $don->id . '/payments', [
            'kind' => 'refund',
            'amount' => 4_000_000,
            'method' => 'bank_transfer',
        ])->assertOk();

        app('auth')->forgetGuards();

        $this->putJson('/api/bookings/' . $don->public_token . '/refund-account', self::TAI_KHOAN)
            ->assertStatus(422);
    }
}
