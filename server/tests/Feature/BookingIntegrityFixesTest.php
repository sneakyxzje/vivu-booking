<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Mail\BookingCancelledMail;
use App\Mail\CancelRequestRejectedMail;
use App\Mail\GroupBookingUpdateMail;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
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
 * Khóa lại những luật vừa được vá sau đợt soi toàn bộ nghiệp vụ.
 *
 * Mỗi bài ở đây tương ứng với một lỗ hổng đã tái hiện được trên hệ thống thật. Chúng nằm chung một
 * tệp vì cùng một tính chất: **luật đã được nói ra ở đâu đó — trong tài liệu, trong chú thích, hoặc
 * trong một hàm không ai gọi — nhưng đường ghi thì không áp**.
 */
class BookingIntegrityFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $guide;
    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->guide = $this->taoNguoi('guide');

        $chinhSach = CancellationPolicy::create([
            'name' => 'Chính sách chuẩn',
            'effective_from' => now()->subDay(),
        ]);

        foreach ([[15, null, 90], [8, 15, 70], [4, 8, 50], [2, 4, 30], [0, 2, 0]] as [$min, $max, $pc]) {
            CancellationPolicyRule::create([
                'cancellation_policy_id' => $chinhSach->id,
                'min_days_before' => $min,
                'max_days_before' => $max,
                'refund_percent' => $pc,
            ]);
        }

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
        ]);
    }

    private function taoNguoi(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . Str::random(4),
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function taoChuyen($start, array $ghiDe = []): TourSchedule
    {
        $start = \Illuminate\Support\Carbon::parse($start);

        return TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 45,
            'min_people' => 2,
            'booked_people' => 0,
        ], $ghiDe));
    }

    private function taoDon(TourSchedule $chuyen, array $ghiDe = []): Booking
    {
        return Booking::create(array_merge([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Le',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $chuyen->start_date,
            'guests' => 1,
            'seats' => 1,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 2_000_000,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ], $ghiDe));
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Tiền
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Sổ giao dịch không nhận khoản thu vượt số đơn còn thiếu.
     *
     * Phép chặn này vốn chỉ có ở nút "xác nhận đơn"; màn sổ giao dịch gọi thẳng lớp dịch vụ nên đi
     * vòng qua được. Gõ nhầm một số 0 là 18 triệu thừa biến mất khỏi mọi báo cáo: `balance_due` về
     * 0 nên đơn rời khỏi danh sách phải thu, còn `refund_amount` vẫn rỗng nên nó không vào danh
     * sách phải trả.
     */
    public function test_khong_ghi_duoc_khoan_thu_vuot_gia_don(): void
    {
        $don = $this->taoDon($this->taoChuyen(now()->addDays(20)));

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/' . $don->id . '/payments', [
            'kind' => 'balance',
            'amount' => 20_000_000,
            'method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(0, $don->fresh()->payments()->count());
    }

    /** Thu đúng phần còn thiếu thì vẫn ghi được bình thường. */
    public function test_van_ghi_duoc_dung_so_con_thieu(): void
    {
        $don = $this->taoDon($this->taoChuyen(now()->addDays(20)));

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/bookings/' . $don->id . '/payments', [
            'kind' => 'balance',
            'amount' => 2_000_000,
            'method' => 'cash',
        ])->assertOk()->assertJsonPath('data.balance_due', 0);
    }

    /**
     * Đơn của chuyến đã đi xong mà còn nợ thì vẫn nằm trong danh sách phải thu.
     *
     * Trước đây bộ lọc chỉ nhận confirmed/paid/deposit_paid, nên đúng lúc chuyến kết thúc và đơn
     * chuyển sang `completed`, khoản nợ biến mất khỏi màn hình và tổng phải thu tụt xuống. Chính
     * màn hình ấy lại xếp đơn sắp khởi hành lên đầu vì "sau khi đoàn đi rồi thì đòi khó hơn nhiều".
     */
    public function test_don_da_di_xong_ma_con_no_van_o_danh_sach_phai_thu(): void
    {
        $chuyen = $this->taoChuyen(now()->addDays(20));
        $don = $this->taoDon($chuyen, ['total_amount' => 5_000_000]);
        $don->payments()->create(['kind' => 'balance', 'amount' => 1_000_000, 'paid_at' => now()]);

        $don->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

        Sanctum::actingAs($this->dieuHanh);

        $ketQua = $this->getJson('/api/admin/receivables')->assertOk()->json('data');

        $this->assertContains($don->id, collect($ketQua['data'])->pluck('id')->all());
        $this->assertSame(4_000_000.0, (float) $ketQua['outstanding_total']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Danh sách hành khách
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Không khai được nhiều hành khách hơn số ghế đã mua.
     *
     * Danh sách này chính là thứ gửi khách sạn và nhà xe. Trước luật này, đơn mua một ghế khai được
     * sáu người và bản xuất danh sách đoàn in ra đủ sáu cái tên.
     */
    public function test_khong_khai_duoc_nhieu_hanh_khach_hon_so_ghe_da_mua(): void
    {
        $don = $this->taoDon($this->taoChuyen(now()->addDays(20)));

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/passengers', [
            'passengers' => collect(range(1, 6))
                ->map(fn (int $i) => ['name' => 'Hanh khach ' . $i, 'type' => 'adult'])
                ->all(),
        ])->assertStatus(422);

        $this->assertSame(0, $don->fresh()->passengers()->count());
    }

    /** Khai đúng số thì lưu được, và khai dở dang vẫn được — người ta hỏi giấy tờ từng người. */
    public function test_khai_dung_so_hoac_khai_do_dang_thi_van_luu_duoc(): void
    {
        $chuyen = $this->taoChuyen(now()->addDays(20));
        $don = $this->taoDon($chuyen, [
            'guests' => 3, 'seats' => 3, 'adult_count' => 2, 'child_count' => 1,
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/passengers', [
            'passengers' => [['name' => 'Nguoi lon mot', 'type' => 'adult']],
        ])->assertOk();

        $this->putJson('/api/admin/bookings/' . $don->id . '/passengers', [
            'passengers' => [
                ['name' => 'Nguoi lon mot', 'type' => 'adult'],
                ['name' => 'Nguoi lon hai', 'type' => 'adult'],
                ['name' => 'Tre em', 'type' => 'child'],
            ],
        ])->assertOk();

        $this->assertSame(3, $don->fresh()->passengers()->count());
    }

    /** Loại khách quyết định giá vé, nên số khai theo từng loại cũng không vượt được. */
    public function test_khong_khai_duoc_nhieu_tre_em_hon_so_ve_tre_em_da_mua(): void
    {
        $chuyen = $this->taoChuyen(now()->addDays(20));
        $don = $this->taoDon($chuyen, [
            'guests' => 2, 'seats' => 2, 'adult_count' => 2, 'child_count' => 0,
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/passengers', [
            'passengers' => [
                ['name' => 'Nguoi lon', 'type' => 'adult'],
                ['name' => 'Tre em khong mua ve', 'type' => 'child'],
            ],
        ])->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Điều khoản
    // ─────────────────────────────────────────────────────────────────────────────────────

    /** Không tích ô đồng ý điều khoản thì không đặt được, và mốc xác nhận được ghi lại. */
    public function test_phai_dong_y_dieu_khoan_moi_dat_duoc_tour(): void
    {
        $chuyen = $this->taoChuyen(now()->addDays(20));

        $payload = [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'customer_name' => 'Khach Moi',
            'customer_email' => 'moi@example.com',
            'adult_count' => 1,
        ];

        $this->postJson('/api/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('accept_terms');

        $this->assertSame(0, Booking::query()->count());

        $this->postJson('/api/bookings', $payload + ['accept_terms' => true])->assertStatus(201);

        $this->assertNotNull(Booking::query()->firstOrFail()->terms_accepted_at);
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Thư báo cho khách
    // ─────────────────────────────────────────────────────────────────────────────────────

    /** Duyệt yêu cầu hủy thì phải báo cho người đã gửi yêu cầu. */
    public function test_duyet_yeu_cau_huy_thi_gui_thu_cho_khach(): void
    {
        Mail::fake();

        $khach = $this->taoNguoi('customer');
        $chuyen = $this->taoChuyen(now()->addDays(20));
        $don = $this->taoDon($chuyen, [
            'customer_id' => $khach->id,
            'customer_email' => $khach->email,
            'paid_at' => now(),
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
        ]);
        $don->payments()->create(['kind' => 'balance', 'amount' => 2_000_000, 'paid_at' => now()]);
        $chuyen->update(['booked_people' => 1]);

        Sanctum::actingAs($khach);
        $yeuCau = $this->postJson('/api/my-bookings/' . $don->id . '/cancel-request', [
            'reason' => 'Toi bi om khong di duoc nua',
            'refund_bank_account' => '123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => 'NGUYEN VAN A',
        ])->assertStatus(201)->json('data.id');

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson('/api/admin/change-requests/' . $yeuCau . '/approve', [
            'review_note' => 'Dong y huy theo yeu cau',
        ])->assertOk();

        Mail::assertQueued(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $thu) => $thu->hasTo($don->customer_email),
        );
    }

    /**
     * Từ chối yêu cầu hủy càng phải báo hơn cả duyệt.
     *
     * Đơn không đổi gì cả, nên im lặng nghĩa là khách tưởng yêu cầu vẫn treo rồi vắng mặt ngày
     * khởi hành — lúc đó mất trắng theo đúng chính sách.
     */
    public function test_tu_choi_yeu_cau_huy_thi_gui_thu_kem_ly_do(): void
    {
        Mail::fake();

        $khach = $this->taoNguoi('customer');
        $chuyen = $this->taoChuyen(now()->addDays(20));
        $don = $this->taoDon($chuyen, [
            'customer_id' => $khach->id,
            'customer_email' => $khach->email,
            'paid_at' => now(),
            'cancellation_policy_id' => CancellationPolicy::dangApDung()?->id,
        ]);
        $don->payments()->create(['kind' => 'balance', 'amount' => 2_000_000, 'paid_at' => now()]);
        $chuyen->update(['booked_people' => 1]);

        Sanctum::actingAs($khach);
        $yeuCau = $this->postJson('/api/my-bookings/' . $don->id . '/cancel-request', [
            'reason' => 'Toi doi y khong muon di nua',
            'refund_bank_account' => '123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => 'NGUYEN VAN A',
        ])->assertStatus(201)->json('data.id');

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson('/api/admin/change-requests/' . $yeuCau . '/reject', [
            'review_note' => 'Da qua han chot danh sach, suat da cam ket voi nha cung cap',
        ])->assertOk();

        Mail::assertQueued(
            CancelRequestRejectedMail::class,
            fn (CancelRequestRejectedMail $thu) => $thu->hasTo($don->customer_email),
        );

        // Và đơn phải còn nguyên hiệu lực — đó là điều lá thư kia phải nói ra.
        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /** Gửi yêu cầu đoàn thì mã tra cứu phải về hòm thư, không chỉ nằm trong phản hồi API. */
    public function test_gui_yeu_cau_doan_thi_nhan_duoc_thu_kem_ma_tra_cuu(): void
    {
        Mail::fake();

        $chuyen = $this->taoChuyen(now()->addDays(30));

        $this->postJson('/api/group-bookings', [
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $chuyen->id,
            'contact_name' => 'Nguyen Van Dai Dien',
            'contact_email' => 'hanhchinh@congty.example.com',
            'contact_phone' => '0901234567',
            'estimated_guests' => 40,
        ])->assertStatus(201);

        Mail::assertQueued(
            GroupBookingUpdateMail::class,
            fn (GroupBookingUpdateMail $thu) => $thu->hasTo('hanhchinh@congty.example.com'),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Đánh giá
    // ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Lập tài khoản trùng địa chỉ thư của một đơn vãng lai thì KHÔNG thừa hưởng quyền đánh giá.
     *
     * Nghe thì đây là một nới rộng hợp lý — người đi thật, lập tài khoản đúng thư đã đặt. Nhưng
     * đăng ký ở hệ thống này không xác minh quyền sở hữu địa chỉ thư: `register` cấp thẻ đăng nhập
     * ngay. Nên "cùng địa chỉ thư" mới chỉ là một lời khai tự nhận, và bất kỳ ai biết một địa chỉ
     * đã từng đặt tour đều mượn được lịch sử chuyến đi của người khác.
     *
     * Đánh giá đứng công khai cạnh tên tour và kéo điểm trung bình, nên bằng chứng "người này đã
     * đi" phải chắc chắn. Bài này khóa lại quyết định ấy — xem chú thích ở `ReviewController` để
     * biết hai cách mở đúng nếu về sau muốn nhận đánh giá của khách vãng lai.
     */
    public function test_lap_tai_khoan_trung_email_khong_muon_duoc_quyen_danh_gia(): void
    {
        $chuyen = $this->taoChuyen(now()->subDays(10), ['status' => ScheduleStatus::Completed->value]);

        $this->taoDon($chuyen, [
            'customer_id' => null,
            'guest_id' => (string) Str::uuid(),
            'customer_email' => 'vanglai@example.com',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $taiKhoanMoi = User::create([
            'name' => 'Nguoi La',
            'email' => 'vanglai@example.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($taiKhoanMoi);

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Chuyen di rat tuyet voi, huong dan vien nhiet tinh.',
        ])->assertStatus(403);
    }

    /** Người chưa từng đi tour này thì vẫn không đánh giá được. */
    public function test_nguoi_chua_di_van_khong_danh_gia_duoc(): void
    {
        Sanctum::actingAs($this->taoNguoi('customer'));

        $this->postJson('/api/reviews', [
            'tour_id' => $this->tour->id,
            'rating' => 5,
            'comment' => 'Toi chua di nhung van muon cham nam sao.',
        ])->assertStatus(403);
    }
}
