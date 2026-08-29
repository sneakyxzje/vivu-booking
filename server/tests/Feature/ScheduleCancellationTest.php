<?php

namespace Tests\Feature;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingPayment;
use App\Models\ScheduleAuditLog;
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
 * K - Hủy cả chuyến khi đã có khách trả tiền.
 *
 * Điều bộ test này giữ, và cũng là chỗ hệ thống từng sai: hủy chuyến KHÔNG được phép chỉ đổi
 * trạng thái rồi bỏ đó. Mỗi đơn đã thanh toán phải có một phương án cụ thể, và khách phải được
 * đền bù đủ vì lỗi không thuộc về họ.
 */
class ScheduleCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $dieuHanh;
    private User $khach;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dieuHanh = $this->taoNguoi('admin');
        $this->khach = $this->taoNguoi('customer');

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
            'child_price' => 1_000_000,
            'infant_price' => 0,
        ]);

        $this->chuyen = $this->taoChuyen(now()->addDays(10));
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
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 4,
            'booked_people' => 0,
        ], $ghiDe));
    }

    private function taoDon(TourSchedule $schedule, string $status = 'confirmed', int $khach = 2): Booking
    {
        $schedule->increment('booked_people', $khach);
        $schedule->refresh();

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_id' => $this->khach->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $khach,
            'adult_count' => $khach,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $khach * 2_000_000,
            'status' => $status,
            'paid_at' => $status === 'confirmed' ? now()->subDay() : null,
            'confirmed_at' => $status === 'confirmed' ? now()->subDay() : null,
            'expires_at' => $status === 'pending' ? now()->addDay() : null,
        ]);
    }

    // --- Bắt buộc có phương án ------------------------------------------------------------

    public function test_con_don_da_thanh_toan_chua_co_phuong_an_thi_khong_huy_duoc(): void
    {
        $mot = $this->taoDon($this->chuyen);
        $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $response = $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Nha xe bao hong xe, khong thu xep duoc xe thay the.',
            // Cố tình chỉ khai phương án cho một đơn.
            'plans' => [
                ['booking_id' => $mot->id, 'action' => 'refund'],
            ],
        ])->assertStatus(422);

        // Báo rõ còn thiếu đơn nào, không chặn mù.
        $this->assertStringContainsString('1 đơn', $response->json('message'));

        $this->assertSame(
            ScheduleStatus::Open,
            $this->chuyen->fresh()->status,
            'Thiếu phương án thì chuyến phải giữ nguyên, không hủy dở dang.',
        );
    }

    // --- Hoàn đủ -------------------------------------------------------------------------

    /**
     * Hãng hủy thì hoàn 100%, không đọc bảng phí hủy.
     *
     * Bảng phí dành cho khách đổi ý. Ở đây hãng là bên không thực hiện, nên dù còn 10 ngày hay
     * còn 10 tiếng thì khách vẫn nhận đủ.
     */
    public function test_huy_chuyen_thi_khach_duoc_hoan_du_khong_theo_bang_phi(): void
    {
        $don = $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Nha xe bao hong xe, khong thu xep duoc xe thay the.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $log = BookingAuditLog::query()
            ->where('booking_id', $don->id)
            ->where('action', BookingAuditAction::Cancelled->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(4_000_000.0, (float) $log->new_values['refund_amount']);
        $this->assertSame(100, $log->new_values['refund_percent']);

        $don->refresh();
        $this->assertSame('cancelled', (string) $don->status);
        $this->assertSame('by_company', $don->cancel_type);
        $this->assertTrue((bool) $don->seats_released);
    }

    /**
     * Chỗ về kho kể cả khi đã qua hạn chốt.
     *
     * Ghế chết sinh ra để giữ đúng số suất đã cam kết của một chuyến VẪN CHẠY. Chuyến này không
     * chạy nữa nên con số ấy không còn nghĩa gì.
     */
    public function test_qua_han_chot_van_tra_cho_ve_kho_vi_chuyen_khong_chay_nua(): void
    {
        $sat = $this->taoChuyen(now()->addDay(), ['booking_deadline' => now()->subDay()]);
        $don = $this->taoDon($sat);

        $this->assertSame(2, (int) $sat->fresh()->booked_people);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $sat->id . '/cancel', [
            'reason' => 'Bao vao dat lien, khong the khoi hanh an toan.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $this->assertSame(0, (int) $sat->fresh()->booked_people);
        $this->assertTrue((bool) $don->fresh()->seats_released);
    }

    // --- Chuyển sang chuyến khác ----------------------------------------------------------

    public function test_chuyen_sang_chuyen_khac_thi_mien_phi_va_giu_nguyen_so_khach(): void
    {
        $don = $this->taoDon($this->chuyen);
        $dich = $this->taoChuyen(now()->addDays(30));

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Chuyen nay khong du khach toi thieu de khoi hanh.',
            'plans' => [[
                'booking_id' => $don->id,
                'action' => 'transfer',
                'to_schedule_id' => $dich->id,
            ]],
        ])->assertOk();

        $don->refresh();

        $this->assertSame($dich->id, $don->tour_schedule_id);
        $this->assertSame('confirmed', (string) $don->status, 'Chuyển chứ không hủy.');
        // Hãng khởi xướng thì không thu phí đổi lịch.
        $this->assertSame(4_000_000.0, (float) $don->total_amount);

        $this->assertSame(2, (int) $dich->fresh()->booked_people);
        $this->assertSame(0, (int) $this->chuyen->fresh()->booked_people);
    }

    /**
     * Chuyến nguồn quá hạn chốt vẫn chuyển đi được, vì cả chuyến bị hủy.
     *
     * Luật chặn chuyển sau hạn chốt tồn tại để giữ giá trị tồn kho của chỗ ở chuyến nguồn. Chuyến
     * nguồn bị hủy hẳn thì chỗ đó không còn giá trị nào để giữ - đúng lý do ghép chuyến cũng
     * được miễn luật này.
     */
    public function test_chuyen_nguon_qua_han_chot_van_chuyen_don_di_duoc(): void
    {
        $sat = $this->taoChuyen(now()->addDay(), ['booking_deadline' => now()->subDay()]);
        $don = $this->taoDon($sat);
        $dich = $this->taoChuyen(now()->addDays(30));

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $sat->id . '/cancel', [
            'reason' => 'Huong dan vien nhap vien, khong tim duoc nguoi thay.',
            'plans' => [[
                'booking_id' => $don->id,
                'action' => 'transfer',
                'to_schedule_id' => $dich->id,
            ]],
        ])->assertOk();

        $this->assertSame($dich->id, $don->fresh()->tour_schedule_id);
    }

    // --- Đơn chưa thanh toán --------------------------------------------------------------

    public function test_don_chua_thanh_toan_tu_huy_khong_can_phuong_an(): void
    {
        $daTra = $this->taoDon($this->chuyen);
        $chuaTra = $this->taoDon($this->chuyen, 'pending', 3);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Chuyen bi huy do thoi tiet xau keo dai.',
            // Chỉ khai phương án cho đơn đã trả tiền.
            'plans' => [['booking_id' => $daTra->id, 'action' => 'refund']],
        ])->assertOk();

        $this->assertSame('cancelled', (string) $chuaTra->fresh()->status);
        $this->assertTrue((bool) $chuaTra->fresh()->seats_released);
        $this->assertSame(0, (int) $this->chuyen->fresh()->booked_people);
    }

    // --- Luật chặn ------------------------------------------------------------------------

    public function test_doan_da_len_duong_thi_khong_huy_chuyen_duoc(): void
    {
        $dangChay = $this->taoChuyen(now()->subDay(), [
            'status' => ScheduleStatus::InProgress->value,
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
        ]);

        $don = $this->taoDon($dangChay);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $dangChay->id . '/cancel', [
            'reason' => 'Khach phan nan ve chat luong dich vu tren duong di.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertStatus(422);

        $this->assertSame(ScheduleStatus::InProgress, $dangChay->fresh()->status);
        $this->assertSame('confirmed', (string) $don->fresh()->status);
    }

    // --- Xem trước và nhật ký -------------------------------------------------------------

    public function test_xem_truoc_tach_ro_don_can_phuong_an_va_don_tu_xu_ly(): void
    {
        $this->taoDon($this->chuyen);
        $this->taoDon($this->chuyen, 'pending', 3);
        $dich = $this->taoChuyen(now()->addDays(30));

        Sanctum::actingAs($this->dieuHanh);

        $impact = $this->getJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel-preview')
            ->assertOk()
            ->json('data.impact');

        $this->assertTrue($impact['can_cancel']);
        $this->assertCount(1, $impact['paid_bookings']);
        $this->assertSame(1, $impact['unpaid_bookings']);
        $this->assertSame(3, $impact['unpaid_guests']);
        $this->assertSame(4_000_000.0, (float) $impact['total_refund_if_all_refunded']);

        $this->assertSame(
            [$dich->id],
            array_column($impact['transfer_options'], 'schedule_id'),
        );
    }

    public function test_huy_chuyen_duoc_ghi_vao_nhat_ky_chuyen(): void
    {
        $don = $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Nha cung cap bao khong con phong cho doan.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $log = ScheduleAuditLog::query()
            ->where('tour_schedule_id', $this->chuyen->id)
            ->where('action', ScheduleAuditAction::Cancelled->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->dieuHanh->id, $log->actor_id);
        $this->assertSame(1, $log->new_values['refunded_bookings']);
        $this->assertSame(4_000_000.0, (float) $log->new_values['refund_total']);
        $this->assertStringContainsString('khong con phong', $log->reason);
    }

    // --- Báo cho khách ---------------------------------------------------------------------

    /**
     * Lỗ hở lâu nhất của nhóm này: hủy chuyến, hoàn đủ tiền, ghi nhật ký đầy đủ - rồi không nói
     * với ai. Khách vẫn tưởng mai đi.
     */
    public function test_huy_chuyen_thi_moi_khach_deu_nhan_duoc_thu(): void
    {
        Mail::fake();

        $daTra = $this->taoDon($this->chuyen);
        $chuaTra = $this->taoDon($this->chuyen, 'pending');

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Khong du khach toi thieu nen huy chuyen.',
            'plans' => [
                ['booking_id' => $daTra->id, 'action' => 'refund'],
            ],
        ])->assertOk();

        Mail::assertQueued(BookingCancelledMail::class, 2);

        Mail::assertQueued(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $thu) => $thu->booking->id === $daTra->id,
        );

        Mail::assertQueued(
            BookingCancelledMail::class,
            fn (BookingCancelledMail $thu) => $thu->booking->id === $chuaTra->id,
        );
    }

    /** Đơn chuyển chuyến KHÔNG bị hủy, nên gửi thư hủy cho họ là nói sai. */
    public function test_don_chuyen_chuyen_nhan_thu_xac_nhan_chu_khong_phai_thu_huy(): void
    {
        Mail::fake();

        $dich = $this->taoChuyen(now()->addDays(20));
        $don = $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Khong du khach toi thieu nen huy chuyen.',
            'plans' => [
                ['booking_id' => $don->id, 'action' => 'transfer', 'to_schedule_id' => $dich->id],
            ],
        ])->assertOk();

        Mail::assertNotQueued(BookingCancelledMail::class);
        Mail::assertQueued(BookingConfirmedMail::class, 1);
    }

    /** Số tiền hoàn phải nằm trên chính đơn, để thư và kế toán đọc được mà không mở bảng khác. */
    public function test_so_tien_hoan_duoc_ghi_len_don(): void
    {
        $don = $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Khong du khach toi thieu nen huy chuyen.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $this->assertSame(4_000_000.0, (float) $don->fresh()->refund_amount);
    }

    // --- Tiền hoàn phải vào sổ giao dịch ---------------------------------------------------

    /**
     * `BookingPayment` mở đầu bằng "số đã thu là TỔNG của sổ chứ không phải một cột bị ghi đè".
     * Hoàn tiền mà không có dòng trong sổ thì câu ấy không còn đúng: đơn đoàn đã cọc bị hủy chuyến
     * vẫn hiện đủ tiền cọc, và số đã thu thực trả về một con số không còn ai giữ.
     */
    public function test_don_co_so_giao_dich_thi_khoan_hoan_vao_so(): void
    {
        $don = $this->taoDon($this->chuyen);

        // Đơn dùng sổ: một dòng cọc như đơn đoàn vẫn ghi.
        BookingPayment::create([
            'booking_id' => $don->id,
            'kind' => 'deposit',
            'amount' => 4_000_000,
            'paid_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Khong du khach toi thieu nen huy chuyen.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $don->id,
            'kind' => BookingPayment::HOAN,
            'amount' => 4_000_000,
        ]);

        $thu = (float) $don->payments()->whereIn('kind', BookingPayment::THU)->sum('amount');
        $hoan = (float) $don->payments()->where('kind', BookingPayment::HOAN)->sum('amount');

        $this->assertSame(0.0, $thu - $hoan, 'Hoàn đủ thì sổ phải về 0, không còn giữ đồng nào.');
    }

    /**
     * Đơn lẻ KHÔNG dùng sổ thì đừng nhét một dòng hoàn vào sổ trống.
     *
     * `paidAmount()` phân nhánh theo "sổ có dòng chưa". Một dòng hoàn đứng lẻ làm nó rơi vào
     * nhánh sổ, cộng các loại thu ra 0, trừ đi khoản hoàn và trả về số ÂM - đúng cái bẫy vừa gặp
     * ở phụ thu sự cố, chỉ đổi chiều.
     */
    public function test_don_le_khong_dung_so_thi_khong_sinh_dong_hoan_le_loi(): void
    {
        $don = $this->taoDon($this->chuyen);

        Sanctum::actingAs($this->dieuHanh);

        $this->postJson('/api/admin/schedules/' . $this->chuyen->id . '/cancel', [
            'reason' => 'Khong du khach toi thieu nen huy chuyen.',
            'plans' => [['booking_id' => $don->id, 'action' => 'refund']],
        ])->assertOk();

        $this->assertSame(
            0,
            $don->payments()->count(),
            'Đơn trả một lần qua cổng thì tiền của nó ghi bằng paid_at, không phải bằng sổ.',
        );
    }
}
