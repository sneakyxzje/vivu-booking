<?php

namespace Tests\Feature;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingChangeRequest;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * E05 - Nhật ký thay đổi đơn hàng.
 *
 * Mỗi thao tác chạm trạng thái, tiền hoặc số chỗ phải sinh đúng một bản ghi, và bản ghi phải
 * đủ để trả lời câu hỏi thật: ai làm, lúc nào, vì sao, và trước đó đơn đang ở đâu.
 *
 * Nhật ký ghi thiếu tệ hơn không có nhật ký, vì nó tạo cảm giác đã có dấu vết.
 */
class BookingAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $khach;
    private User $dieuHanh;
    private TourSchedule $schedule;

    private function taoUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'email' => $role . '-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->khach = $this->taoUser('customer');
        $this->dieuHanh = $this->taoUser('admin');

        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22),
            'booking_deadline' => now()->addDays(17),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 2,
        ]);
    }

    private function taoDon(string $status = 'confirmed'): Booking
    {
        $daThanhToan = $status === 'confirmed';

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->schedule->tour_id,
            'customer_id' => $this->khach->id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => $this->khach->name,
            'customer_email' => $this->khach->email,
            'departure_date' => $this->schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 10_000_000,
            'status' => $status,
            'paid_at' => $daThanhToan ? now()->subDay() : null,
            'confirmed_at' => $daThanhToan ? now()->subDay() : null,
            'expires_at' => $daThanhToan ? null : now()->addDay(),
        ]);
    }

    private function nhatKy(Booking $booking)
    {
        return BookingAuditLog::query()
            ->where('booking_id', $booking->id)
            ->orderBy('id')
            ->get();
    }

    // --- Từng thao tác sinh đúng một bản ghi -------------------------------------------

    public function test_quan_tri_huy_don_thi_ghi_lai_ai_huy_va_vi_sao(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach goi dien xin huy vi ban viec.',
        ])->assertOk();

        $logs = $this->nhatKy($don);

        $this->assertCount(1, $logs);
        $this->assertSame(BookingAuditAction::Cancelled, $logs[0]->action);
        $this->assertSame($this->dieuHanh->id, (int) $logs[0]->actor_id);
        $this->assertSame('admin', $logs[0]->actor_role);
        $this->assertSame('confirmed', $logs[0]->old_values['status']);
        $this->assertSame('cancelled', $logs[0]->new_values['status']);
        $this->assertStringContainsString('ban viec', $logs[0]->reason);
    }

    /**
     * Chỗ có về kho hay thành ghế chết là hệ quả quan trọng nhất của lần hủy, và người đọc nhật
     * ký sau này không tính lại được vì hạn chốt đã trôi qua từ lâu.
     */
    public function test_nhat_ky_ghi_lai_cho_co_ve_kho_hay_khong(): void
    {
        $don = $this->taoDon();
        $this->schedule->update(['booking_deadline' => now()->subHour()]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach huy sat ngay di.',
        ])->assertOk();

        $this->assertFalse($this->nhatKy($don)[0]->new_values['seats_released']);
    }

    public function test_khach_tu_huy_don_chua_thanh_toan_cung_duoc_ghi(): void
    {
        $don = $this->taoDon('pending');
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Doi lich nen khong di duoc.',
        ])->assertOk();

        $logs = $this->nhatKy($don);

        $this->assertCount(1, $logs);
        $this->assertSame($this->khach->id, (int) $logs[0]->actor_id);
        $this->assertSame('customer', $logs[0]->actor_role);
    }

    /** Luồng yêu cầu hủy sinh hai bản ghi: lúc khách gửi và lúc điều hành duyệt. */
    public function test_luong_yeu_cau_huy_ghi_ca_hai_buoc(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->khach);
        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
        ])->assertStatus(201);

        $yeuCau = BookingChangeRequest::query()->first();

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve", [
            'review_note' => 'Khach bao truoc som, dong y hoan theo chinh sach.',
        ])->assertOk();

        $logs = $this->nhatKy($don);

        $this->assertCount(2, $logs);

        $this->assertSame(BookingAuditAction::CancelRequested, $logs[0]->action);
        $this->assertSame($this->khach->id, (int) $logs[0]->actor_id);

        $this->assertSame(BookingAuditAction::CancelRequestApproved, $logs[1]->action);
        $this->assertSame($this->dieuHanh->id, (int) $logs[1]->actor_id);
        $this->assertEquals(9_000_000, (float) $logs[1]->new_values['refund_amount']);
    }

    public function test_tu_choi_yeu_cau_cung_duoc_ghi_kem_ly_do(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->khach);
        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
        ])->assertStatus(201);

        $yeuCau = BookingChangeRequest::query()->first();

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/reject", [
            'review_note' => 'Da qua han huy theo dieu khoan khach dong y luc dat.',
        ])->assertOk();

        $logs = $this->nhatKy($don);

        $this->assertSame(BookingAuditAction::CancelRequestRejected, $logs->last()->action);
        $this->assertStringContainsString('dieu khoan', $logs->last()->reason);
    }

    public function test_xac_nhan_don_duoc_ghi(): void
    {
        $don = $this->taoDon('pending');
        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/bookings/{$don->id}/confirm")->assertOk();

        $logs = $this->nhatKy($don);

        $this->assertSame(BookingAuditAction::Confirmed, $logs[0]->action);
        $this->assertSame('pending', $logs[0]->old_values['status']);
    }

    /** Đổi tên hành khách sát ngày đi là chuyện nhạy cảm, phải giữ được tên cũ. */
    public function test_doi_ten_hanh_khach_thi_giu_lai_ten_cu(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult'],
                ['name' => 'Tran Thi Binh', 'type' => 'adult'],
            ],
        ])->assertOk();

        $this->putJson("/api/my-bookings/{$don->id}/passengers", [
            'passengers' => [
                ['name' => 'Nguyen Van An', 'type' => 'adult'],
                ['name' => 'Le Thi Cuc', 'type' => 'adult'],
            ],
        ])->assertOk();

        $log = $this->nhatKy($don)->last();

        $this->assertSame(BookingAuditAction::PassengersUpdated, $log->action);
        $this->assertContains('Tran Thi Binh', $log->old_values['passengers']);
        $this->assertContains('Le Thi Cuc', $log->new_values['passengers']);
    }

    /** Sửa thông tin khác mà không đổi tên thì không cần một dòng nhật ký. */
    public function test_sua_ma_khong_doi_ten_thi_khong_sinh_ban_ghi_thua(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $danhSach = [
            'passengers' => [['name' => 'Nguyen Van An', 'type' => 'adult', 'phone' => '0901234567']],
        ];

        $this->putJson("/api/my-bookings/{$don->id}/passengers", $danhSach)->assertOk();

        $soTruoc = $this->nhatKy($don)->count();

        $danhSach['passengers'][0]['phone'] = '0909999999';
        $this->putJson("/api/my-bookings/{$don->id}/passengers", $danhSach)->assertOk();

        $this->assertSame($soTruoc, $this->nhatKy($don)->count());
    }

    /**
     * Tác vụ nền không có người bấm, nhưng vẫn phải để lại dấu vết: chốt đơn thành khách không
     * có mặt là kết luận đóng đường hoàn tiền.
     */
    public function test_chot_don_sau_chuyen_duoc_ghi_du_khong_co_nguoi_thao_tac(): void
    {
        $don = $this->taoDon();
        $this->schedule->update([
            'status' => ScheduleStatus::Completed->value,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(3),
        ]);

        $this->artisan('bookings:finalize-completed')->assertSuccessful();

        $log = $this->nhatKy($don)->last();

        $this->assertSame(BookingAuditAction::Finalized, $log->action);
        $this->assertNull($log->actor_id, 'Tác vụ nền không có người thao tác.');
        $this->assertNull($log->ip_address, 'Chạy từ dòng lệnh thì không có địa chỉ mạng.');
        $this->assertSame('completed', $log->new_values['status']);
    }

    // --- API lịch sử --------------------------------------------------------------------

    public function test_api_lich_su_tra_ve_theo_thu_tu_thoi_gian(): void
    {
        $don = $this->taoDon('pending');

        Sanctum::actingAs($this->dieuHanh);
        $this->putJson("/api/admin/bookings/{$don->id}/confirm")->assertOk();
        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach goi dien xin huy.',
        ])->assertOk();

        $response = $this->getJson("/api/admin/bookings/{$don->id}/history")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'confirmed')
            ->assertJsonPath('data.1.action', 'cancelled')
            ->assertJsonPath('data.1.action_label', 'Hủy đơn')
            ->assertJsonPath('data.1.touches_money', true);

        $this->assertSame($this->dieuHanh->name, $response->json('data.1.actor_name'));
    }

    public function test_khach_khong_xem_duoc_lich_su_don(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->getJson("/api/admin/bookings/{$don->id}/history")->assertStatus(403);
    }

    /**
     * Nhật ký hỏng không được kéo theo nghiệp vụ hỏng. Đây là việc phụ; hủy đơn thất bại vì
     * bảng nhật ký lỗi là bắt khách chịu hậu quả của một sự cố không liên quan tới họ.
     */
    public function test_nhat_ky_hong_thi_van_huy_don_duoc(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->dieuHanh);

        \Illuminate\Support\Facades\Schema::drop('booking_audit_logs');

        $this->putJson("/api/admin/bookings/{$don->id}/cancel", [
            'cancel_reason' => 'Khach goi dien xin huy vi ban viec.',
        ])->assertOk();

        $this->assertSame('cancelled', $don->fresh()->status);
    }
}
