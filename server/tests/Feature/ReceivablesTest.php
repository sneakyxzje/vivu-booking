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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Công nợ phải thu — chiều ngược lại của hàng đợi hoàn tiền.
 *
 * Hệ thống vốn chỉ trả lời được nửa câu "ai còn nợ ai": có màn công ty nợ khách, không có màn khách
 * nợ công ty. Từ khi đơn trả nhiều đợt thì nửa còn thiếu ấy là câu kế toán hỏi mỗi ngày.
 */
class ReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private Tour $tour;
    private TourSchedule $chuyen;

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

        $this->tour = Tour::factory()->create(['status' => 'active', 'adult_price' => 2_000_000]);

        $start = now()->addDays(10);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 40,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        Sanctum::actingAs($this->dieuHanh);
    }

    private function taoDon(string $status, ?float $daThu, ?TourSchedule $chuyen = null): Booking
    {
        $chuyen ??= $this->chuyen;

        $don = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $chuyen->tour_id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 2,
            'seats' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'expires_at' => $status === 'pending' ? now()->addMinutes(10) : null,
        ]);

        if ($daThu !== null && $daThu > 0) {
            BookingPayment::create([
                'booking_id' => $don->id,
                'kind' => 'deposit',
                'amount' => $daThu,
                'paid_at' => now(),
            ]);

            if ($daThu >= 4_000_000) {
                $don->forceFill(['paid_at' => now()])->save();
            }
        }

        return $don;
    }

    public function test_don_moi_cop_coc_nam_trong_danh_sach_phai_thu(): void
    {
        $don = $this->taoDon('confirmed', 1_200_000);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([$don->id], array_column($res->json('data.data'), 'id'));
        $this->assertEquals(1_200_000.0, $res->json('data.data.0.net_paid'));
        $this->assertEquals(2_800_000.0, $res->json('data.data.0.balance_due'));
        $this->assertEquals(2_800_000.0, $res->json('data.outstanding_total'));
    }

    /** Thu đủ rồi thì rời khỏi danh sách — không cần ai đi đòi nữa. */
    public function test_don_da_thu_du_khong_con_trong_danh_sach(): void
    {
        $this->taoDon('confirmed', 4_000_000);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([], $res->json('data.data'));
        $this->assertEquals(0.0, $res->json('data.outstanding_total'));
    }

    /**
     * Sổ đủ tiền nhưng mốc `paid_at` còn trống thì vẫn KHÔNG phải công nợ.
     *
     * Đây là lỗi của bản đầu tiên, và nó lộ ra ngay trên dữ liệu mẫu. Bộ lọc khi ấy chỉ đọc
     * `paid_at`, với lý lẽ rằng `BookingPaymentService::record()` đóng mốc ấy lúc thu đủ. Lý lẽ đó
     * chỉ đúng với tiền đi qua service — seeder, dữ liệu nhập từ hệ thống cũ, hay một lần sửa tay
     * trong cơ sở dữ liệu đều ghi thẳng vào bảng bút toán và không đụng tới mốc.
     *
     * Hậu quả là đơn đã thu đủ vẫn nằm trong danh sách đòi nợ, cột "còn thiếu" ghi 0 đồng — một
     * dòng tự mâu thuẫn với chính nó.
     */
    public function test_so_du_tien_nhung_thieu_moc_thanh_toan_van_khong_phai_cong_no(): void
    {
        $don = $this->taoDon('confirmed', null);

        // Ghi thẳng vào bảng, đúng cách seeder làm: đủ tiền nhưng không qua service.
        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'balance',
            'amount' => 4_000_000,
            'paid_at' => now(),
        ]);

        $this->assertNull($don->fresh()->paid_at, 'Ghi thẳng thì mốc thanh toán vẫn trống.');

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([], $res->json('data.data'));
        $this->assertEquals(0.0, $res->json('data.outstanding_total'));
    }

    /**
     * Ghi mọi khoản bằng nhãn "Tiền cọc" cũng ra đúng con số.
     *
     * `BookingPayment::THU` gồm cả `deposit` lẫn `balance`, và mọi phép cộng tiền trong hệ thống lọc
     * theo cả tập ấy chứ không theo từng nhãn. Hai nhãn chỉ khác nhau ở chữ hiển thị trên sổ và ở bộ
     * lọc loại bút toán — không ở bất kỳ phép tính nào.
     *
     * Bài này chốt điều đó lại: một đơn thu đủ bằng ba lần cọc phải rời danh sách y như đơn thu đủ
     * một lần, kể cả khi mốc `paid_at` chưa đóng vì các bút toán được ghi thẳng vào bảng.
     */
    public function test_thu_du_bang_nhieu_lan_coc_van_khong_phai_cong_no(): void
    {
        $don = $this->taoDon('confirmed', null);

        foreach ([1_500_000, 1_500_000, 1_000_000] as $lan) {
            BookingPayment::create([
                'booking_id' => $don->id,
                'kind' => 'deposit',
                'amount' => $lan,
                'paid_at' => now(),
            ]);
        }

        $this->assertNull($don->fresh()->paid_at, 'Ghi thẳng thì mốc thanh toán vẫn trống.');

        $this->getJson('/api/admin/receivables')
            ->assertOk()
            ->assertJsonPath('data.data', []);
    }

    /** Và thu thiếu bằng nhãn cọc thì vẫn ra đúng phần còn thiếu. */
    public function test_thu_thieu_bang_nhan_coc_van_ra_dung_so_con_thieu(): void
    {
        $don = $this->taoDon('confirmed', null);

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'deposit',
            'amount' => 1_200_000,
            'paid_at' => now(),
        ]);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([$don->id], array_column($res->json('data.data'), 'id'));
        $this->assertEquals(1_200_000.0, $res->json('data.data.0.net_paid'));
        $this->assertEquals(2_800_000.0, $res->json('data.data.0.balance_due'));
    }

    /**
     * Đơn cũ không có bút toán nào nhưng đã đóng mốc thanh toán cũng không phải công nợ.
     *
     * Nhóm này tạo trước khi sổ mở cho đơn lẻ, nên `paid_at` là bằng chứng duy nhất còn lại rằng
     * tiền đã về. Bỏ vế ấy khỏi bộ lọc là đi đòi lại tiền của mọi đơn cũ.
     */
    public function test_don_cu_khong_dung_so_nhung_da_dong_moc_thi_khong_phai_cong_no(): void
    {
        $don = $this->taoDon('confirmed', null);
        $don->forceFill(['paid_at' => now()])->save();

        $this->getJson('/api/admin/receivables')
            ->assertOk()
            ->assertJsonPath('data.data', []);
    }

    /**
     * Đơn đã trả đủ rồi bị chuyển sang chuyến ĐẮT hơn thì phần chênh là công nợ.
     *
     * Đây là lỗ mà bản trước còn sót. Bộ lọc khi ấy có vế `paid_at IS NULL`, mà mốc ấy đã đóng từ
     * lúc khách trả đủ và **không bao giờ lùi** — kể cả khi giá đơn tăng lên sau đó. Chuyển chuyến
     * sang tour đắt hơn, hay chỉ cần cộng phí đổi lịch từ lần chuyển thứ hai, là khách nợ thêm mà
     * màn công nợ không hề biết.
     */
    public function test_don_da_tra_du_roi_tang_gia_thi_phan_chenh_la_cong_no(): void
    {
        $don = $this->taoDon('confirmed', 4_000_000);

        $this->assertNotNull($don->fresh()->paid_at, 'Thu đủ thì mốc thanh toán đã đóng.');

        // Chuyển sang chuyến đắt hơn: giá đơn tăng, tiền đã thu giữ nguyên.
        $don->forceFill(['total_amount' => 6_000_000])->save();

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([$don->id], array_column($res->json('data.data'), 'id'));
        $this->assertEquals(2_000_000.0, $res->json('data.data.0.balance_due'));
    }

    /**
     * Đơn thu đủ rồi được hoàn bớt thuộc chiều bên kia, không phải phải thu.
     *
     * Nó còn thiếu so với giá đơn, nhưng phần thiếu là tiền công ty vừa trả lại khách.
     */
    public function test_don_da_hoan_bot_khong_nam_trong_phai_thu(): void
    {
        $don = $this->taoDon('confirmed', 4_000_000);

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => BookingPayment::HOAN,
            'amount' => 1_000_000,
            'paid_at' => now(),
        ]);

        $this->getJson('/api/admin/receivables')
            ->assertOk()
            ->assertJsonPath('data.data', []);
    }

    /**
     * Đơn đang giữ chỗ không phải công nợ.
     *
     * Nó chưa trả đồng nào theo định nghĩa, và tự hủy sau mười phút. Gọi đó là công nợ thì danh
     * sách đầy những dòng sẽ tự biến mất, và con số tổng nói dối.
     */
    public function test_don_dang_giu_cho_khong_tinh_la_cong_no(): void
    {
        $this->taoDon('pending', null);

        $res = $this->getJson('/api/admin/receivables')->assertOk();

        $this->assertSame([], $res->json('data.data'));
    }

    /** Đơn đã hủy cũng không: nó thuộc chiều bên kia, màn hoàn tiền. */
    public function test_don_da_huy_khong_tinh_la_cong_no(): void
    {
        $this->taoDon('cancelled', 1_200_000);

        $this->getJson('/api/admin/receivables')
            ->assertOk()
            ->assertJsonPath('data.data', []);
    }

    /** Hạn chốt danh sách chính là hạn thu tiền, và quá hạn thì phải nhìn thấy được. */
    public function test_danh_dau_don_qua_han_thu(): void
    {
        $chuyenGap = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Closed->value,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'booking_deadline' => now()->subHours(2),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        $this->taoDon('confirmed', 1_000_000, $chuyenGap);

        $dong = $this->getJson('/api/admin/receivables')->assertOk()->json('data.data.0');

        $this->assertTrue($dong['overdue']);
        $this->assertNotNull($dong['due_by']);
    }

    /**
     * Chạy trên chính bộ dữ liệu mẫu, vì đó là chỗ lỗi cũ lộ ra.
     *
     * Các seeder ghi bút toán thẳng vào bảng chứ không qua `BookingPaymentService`, nên chúng dựng
     * đúng loại đơn mà bộ lọc cũ đọc sai: sổ đủ tiền, mốc `paid_at` trống. Bài này giữ cho mọi dòng
     * trong danh sách đều thực sự còn thiếu tiền — không dòng nào ghi "còn thiếu 0 đồng".
     */
    public function test_khong_co_don_da_thu_du_nao_lot_vao_du_lieu_mau(): void
    {
        $this->seed();

        $dong = $this->getJson('/api/admin/receivables?per_page=100')->assertOk()->json('data.data');

        // Bộ dữ liệu mẫu có đơn đoàn mới đóng cọc, nên danh sách phải có ít nhất một dòng — nếu
        // rỗng thì vòng lặp dưới đây không kiểm gì cả và bài này xanh một cách vô nghĩa.
        $this->assertNotEmpty($dong, 'Dữ liệu mẫu phải có ít nhất một đơn còn nợ để bài này có nghĩa.');

        foreach ($dong as $row) {
            $this->assertGreaterThan(
                0,
                $row['balance_due'],
                sprintf('Đơn BK-%d đã thu đủ mà vẫn nằm trong danh sách phải thu.', $row['id']),
            );

            $this->assertLessThan(
                $row['total_amount'],
                $row['net_paid'],
                sprintf('Đơn BK-%d có số đã thu bằng giá trị đơn.', $row['id']),
            );
        }
    }

    /** Lọc theo số ngày tới ngày đi, để đòi đơn gấp trước. */
    public function test_loc_theo_so_ngay_sap_khoi_hanh(): void
    {
        $chuyenXa = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(60),
            'end_date' => now()->addDays(61),
            'booking_deadline' => now()->addDays(57),
            'max_people' => 20,
            'min_people' => 2,
            'booked_people' => 0,
        ]);

        $donGan = $this->taoDon('confirmed', 1_000_000);
        $this->taoDon('confirmed', 1_000_000, $chuyenXa);

        $res = $this->getJson('/api/admin/receivables?within_days=30')->assertOk();

        $this->assertSame([$donGan->id], array_column($res->json('data.data'), 'id'));
    }

    /** Danh sách hoá đơn mang theo hai con số tiền, để không phải mở từng đơn. */
    public function test_danh_sach_hoa_don_kem_da_thu_va_con_thieu(): void
    {
        $this->taoDon('confirmed', 1_200_000);

        $dong = $this->getJson('/api/admin/bookings')->assertOk()->json('data.data.0');

        $this->assertEquals(1_200_000.0, $dong['net_paid']);
        $this->assertEquals(2_800_000.0, $dong['balance_due']);
    }

    /** Sổ giao dịch lọc riêng được từng loại bút toán, không chỉ vào/ra. */
    public function test_so_giao_dich_loc_theo_loai_but_toan(): void
    {
        $don = $this->taoDon('confirmed', 1_200_000);

        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'balance',
            'amount' => 800_000,
            'paid_at' => now(),
        ]);

        $chiCoc = $this->getJson('/api/admin/transactions?kind=deposit')->assertOk();

        $this->assertSame(1, $chiCoc->json('data.totals.count'));
        $this->assertEquals(1_200_000.0, $chiCoc->json('data.totals.in'));

        $tatCa = $this->getJson('/api/admin/transactions')->assertOk();

        $this->assertSame(2, $tatCa->json('data.totals.count'));
        $this->assertEquals(2_000_000.0, $tatCa->json('data.totals.in'));
    }
}
