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
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTransactionRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Booking $donA;
    private Booking $donB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $tour = Tour::create([
            'admin_id' => $this->admin->id,
            'title' => 'Tour So Tong',
            'slug' => 'tour-so-tong-' . Str::random(5),
            'adult_price' => 3_000_000,
            'child_price' => 2_000_000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(21),
            'max_people' => 20,
            'min_people' => 1,
            'booked_people' => 2,
            'status' => ScheduleStatus::Open->value,
        ]);

        $this->donA = $this->taoDon($tour, $schedule, 'Khach A');
        $this->donB = $this->taoDon($tour, $schedule, 'Khach B');
    }

    private function taoDon(Tour $tour, TourSchedule $schedule, string $ten): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => $ten,
            'customer_email' => Str::slug($ten) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 1,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 3_000_000,
            'status' => 'confirmed',
        ]);
    }

    private function butToan(Booking $booking, string $kind, float $amount, ?string $method, ?string $ngay = null): BookingPayment
    {
        return BookingPayment::query()->create([
            'booking_id' => $booking->id,
            'kind' => $kind,
            'amount' => $amount,
            'method' => $method,
            'reference' => 'FT' . random_int(100000, 999999),
            'paid_at' => $ngay ? now()->parse($ngay) : now(),
        ]);
    }

    public function test_liet_ke_moi_but_toan_cua_moi_don(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway');
        $this->butToan($this->donB, 'balance', 1_800_000, 'bank_transfer');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions')
            ->assertOk();

        // Sổ vốn chỉ mở được từ bên trong một đơn — màn này là chỗ nhìn thấy cả hai cùng lúc.
        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(4_800_000, $response->json('data.totals.in'));
        $this->assertSame(0, $response->json('data.totals.out'));
        $this->assertSame(4_800_000, $response->json('data.totals.net'));
    }

    public function test_dong_kem_ten_khach_va_ma_don(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway');

        $dong = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->json('data.data.0');

        // "Khoản này của ai" là câu đầu tiên khi đối chiếu sao kê.
        $this->assertSame('Khach A', $dong['customer_name']);
        $this->assertSame($this->donA->id, $dong['booking_id']);
        $this->assertSame('in', $dong['direction']);
        $this->assertSame('Cổng thanh toán', $dong['method_label']);
    }

    public function test_khoan_hoan_dem_vao_chieu_RA(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway');
        $this->butToan($this->donA, 'refund', 1_000_000, 'bank_transfer');

        $totals = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->json('data.totals');

        $this->assertSame(3_000_000, $totals['in']);
        $this->assertSame(1_000_000, $totals['out']);
        $this->assertSame(2_000_000, $totals['net']);
    }

    /**
     * Phụ thu sự cố cũng là tiền vào tài khoản công ty.
     *
     * Khác với phép tính hoàn tiền, nơi phụ thu bị tách riêng để không bị đem hoàn theo bậc phần
     * trăm của chính sách hủy. Sổ tổng trả lời một câu khác: bao nhiêu tiền đã thực sự vào ra.
     */
    public function test_phu_thu_su_co_tinh_la_tien_vao(): void
    {
        $this->butToan($this->donA, 'surcharge', 500_000, 'cash');

        $totals = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->json('data.totals');

        $this->assertSame(500_000, $totals['in']);
    }

    public function test_loc_theo_hinh_thuc(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway');
        $this->butToan($this->donB, 'balance', 1_800_000, 'cash');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions?method=cash')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        // Tổng phải theo đúng bộ lọc, không phải tổng của tất cả.
        $this->assertSame(1_800_000, $response->json('data.totals.in'));
    }

    public function test_loc_theo_khoang_ngay(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway', now()->subDays(10)->toDateTimeString());
        $this->butToan($this->donB, 'balance', 1_800_000, 'cash', now()->toDateTimeString());

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions?from=' . now()->subDays(2)->toDateString())
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(1_800_000, $response->json('data.totals.in'));
    }

    /**
     * Khai cả giờ thì máy chủ lọc tới giờ, không cắt bỏ.
     *
     * `whereDate()` bỏ qua phần giờ, nên nếu dùng nó thì bộ chọn khoảng thời gian cho người dùng
     * chỉnh giờ rồi kết quả không đổi — giao diện hứa một thứ máy chủ không làm.
     */
    public function test_khai_ca_gio_thi_loc_toi_gio(): void
    {
        $hom = now()->toDateString();
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway', $hom . ' 09:00:00');
        $this->butToan($this->donB, 'balance', 1_800_000, 'cash', $hom . ' 16:00:00');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions?from=' . $hom . 'T12:00')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(1_800_000, $response->json('data.totals.in'));
    }

    /** Chỉ khai ngày thì lấy trọn ngày, nếu không mốc cuối rơi vào 0 giờ và mất cả ngày đó. */
    public function test_chi_khai_ngay_thi_lay_tron_ngay(): void
    {
        $hom = now()->toDateString();
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway', $hom . ' 09:00:00');
        $this->butToan($this->donB, 'balance', 1_800_000, 'cash', $hom . ' 23:30:00');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions?from=' . $hom . '&to=' . $hom)
            ->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertSame(4_800_000, $response->json('data.totals.in'));
    }

    public function test_tim_theo_ten_khach(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway');
        $this->butToan($this->donB, 'balance', 1_800_000, 'cash');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions?q=Khach+B')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Khach B', $response->json('data.data.0.customer_name'));
    }

    public function test_ngay_ket_thuc_truoc_ngay_bat_dau_bi_tu_choi(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/transactions?from=' . now()->toDateString() . '&to=' . now()->subDays(5)->toDateString())
            ->assertStatus(422);
    }

    public function test_xuat_csv_theo_dung_bo_loc_dang_xem(): void
    {
        $this->butToan($this->donA, 'balance', 3_000_000, 'gateway');
        $this->butToan($this->donB, 'balance', 1_800_000, 'cash');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/admin/transactions/export?method=cash')
            ->assertOk();

        $noiDung = $response->streamedContent();

        $this->assertStringContainsString('Khach B', $noiDung);
        // Xuất tất trong khi người dùng vừa lọc là bắt họ lọc lại lần nữa trong Excel.
        $this->assertStringNotContainsString('Khach A', $noiDung);
    }

    public function test_khach_khong_vao_duoc_so_tong(): void
    {
        $khach = User::create([
            'name' => 'Khach',
            'email' => 'khach-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->actingAs($khach, 'sanctum')->getJson('/api/admin/transactions')->assertStatus(403);
    }
}
