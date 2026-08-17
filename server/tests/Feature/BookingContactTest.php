<?php

namespace Tests\Feature;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sửa thông tin liên hệ của người đặt.
 *
 * Điều bộ test này giữ, và cũng là chỗ dễ bị làm hỏng nhất về sau: **thông tin liên hệ KHÔNG bị
 * hạn chốt danh sách khóa.**
 *
 * Danh sách hành khách gửi cho nhà cung cấp nên sau hạn chốt khách hết quyền sửa. Thông tin liên
 * hệ thì không đi đâu cả - nó là số hướng dẫn viên gọi khách, càng sát ngày càng cần đúng. Áp
 * cùng một mốc cho cả hai là khóa ngược.
 */
class BookingContactTest extends TestCase
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
        ]);

        $this->chuyen = $this->taoChuyen(now()->addDays(20));
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

    private function taoDon(?TourSchedule $schedule = null, string $status = 'confirmed'): Booking
    {
        $schedule ??= $this->chuyen;

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_id' => $this->khach->id,
            'customer_name' => 'Nguyen Van Sai',
            'customer_email' => 'sai@example.com',
            'customer_phone' => '0900000000',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => $status,
            'paid_at' => $status === 'confirmed' ? now()->subDay() : null,
            'confirmed_at' => $status === 'confirmed' ? now()->subDay() : null,
            'expires_at' => $status === 'pending' ? now()->addDay() : null,
        ]);
    }

    // --- Khách tự sửa ---------------------------------------------------------------------

    public function test_khach_tu_sua_duoc_thong_tin_nhap_nham(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->khach);

        $this->putJson('/api/my-bookings/' . $don->id . '/contact', [
            'customer_name' => 'Nguyen Van Dung',
            'customer_email' => 'dung@example.com',
            'customer_phone' => '0912345678',
        ])->assertOk();

        $don->refresh();

        $this->assertSame('Nguyen Van Dung', $don->customer_name);
        $this->assertSame('dung@example.com', $don->customer_email);
        $this->assertSame('0912345678', $don->customer_phone);
    }

    public function test_khong_sua_duoc_don_cua_nguoi_khac(): void
    {
        $don = $this->taoDon();
        $nguoiLa = $this->taoNguoi('customer');

        Sanctum::actingAs($nguoiLa);

        $this->putJson('/api/my-bookings/' . $don->id . '/contact', [
            'customer_name' => 'Ke Trom',
            'customer_email' => 'ketrom@example.com',
        ])->assertStatus(404);

        $this->assertSame('Nguyen Van Sai', $don->fresh()->customer_name);
    }

    // --- Điểm khác biệt cốt lõi: KHÔNG bị hạn chốt khóa -----------------------------------

    /**
     * Qua hạn chốt danh sách vẫn sửa được, và đây là chỗ khác hẳn danh sách hành khách.
     *
     * Sát ngày khởi hành mới là lúc số điện thoại sai gây hậu quả thật: hướng dẫn viên không gọi
     * được khách vào sáng đón đoàn.
     */
    public function test_qua_han_chot_van_sua_duoc_thong_tin_lien_he(): void
    {
        $sat = $this->taoChuyen(now()->addDay(), ['booking_deadline' => now()->subDay()]);
        $don = $this->taoDon($sat);

        Sanctum::actingAs($this->khach);

        $this->putJson('/api/my-bookings/' . $don->id . '/contact', [
            'customer_name' => 'Nguyen Van Dung',
            'customer_email' => 'dung@example.com',
            'customer_phone' => '0912345678',
        ])->assertOk();

        $this->assertSame('0912345678', $don->fresh()->customer_phone);
    }

    /** Đoàn đang đi vẫn sửa được: đó chính là lúc cần gọi được khách nhất. */
    public function test_doan_dang_di_van_sua_duoc_thong_tin_lien_he(): void
    {
        $dangChay = $this->taoChuyen(now()->subDay(), [
            'status' => ScheduleStatus::InProgress->value,
            'end_date' => now()->addDay(),
            'booking_deadline' => now()->subDays(4),
        ]);

        $don = $this->taoDon($dangChay);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/contact', [
            'customer_name' => 'Nguyen Van Dung',
            'customer_email' => 'dung@example.com',
            'customer_phone' => '0912345678',
        ])->assertOk();

        $this->assertSame('0912345678', $don->fresh()->customer_phone);
    }

    // --- Luật chặn duy nhất ---------------------------------------------------------------

    public function test_don_da_ket_thuc_vong_doi_thi_khong_sua_nua(): void
    {
        $don = $this->taoDon();
        $don->forceFill(['status' => 'cancelled'])->save();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/contact', [
            'customer_name' => 'Nguyen Van Dung',
            'customer_email' => 'dung@example.com',
        ])->assertStatus(422);

        $this->assertSame('Nguyen Van Sai', $don->fresh()->customer_name);
    }

    public function test_thu_dien_tu_sai_dinh_dang_bi_tu_choi(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->khach);

        $this->putJson('/api/my-bookings/' . $don->id . '/contact', [
            'customer_name' => 'Nguyen Van Dung',
            'customer_email' => 'khong-phai-email',
        ])->assertStatus(422);
    }

    // --- Nhật ký --------------------------------------------------------------------------

    public function test_chi_ghi_nhat_ky_nhung_truong_that_su_doi(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/contact', [
            // Giữ nguyên tên và thư điện tử, chỉ sửa số điện thoại.
            'customer_name' => 'Nguyen Van Sai',
            'customer_email' => 'sai@example.com',
            'customer_phone' => '0912345678',
        ])->assertOk();

        $log = BookingAuditLog::query()
            ->where('booking_id', $don->id)
            ->where('action', BookingAuditAction::ContactUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(['customer_phone'], array_keys($log->new_values));
        $this->assertSame('0900000000', $log->old_values['customer_phone']);
        $this->assertSame('0912345678', $log->new_values['customer_phone']);
        $this->assertSame($this->dieuHanh->id, $log->actor_id);
    }

    public function test_luu_lai_y_nguyen_thi_khong_sinh_nhat_ky_rong(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson('/api/admin/bookings/' . $don->id . '/contact', [
            'customer_name' => 'Nguyen Van Sai',
            'customer_email' => 'sai@example.com',
            'customer_phone' => '0900000000',
        ])->assertOk();

        $this->assertSame(
            0,
            BookingAuditLog::query()
                ->where('booking_id', $don->id)
                ->where('action', BookingAuditAction::ContactUpdated->value)
                ->count(),
        );
    }
}
