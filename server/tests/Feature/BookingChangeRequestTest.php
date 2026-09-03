<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
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
 * F07 - Khách xin hủy, điều hành duyệt.
 *
 * Câu số 10 của hội đồng: ai được hủy, ai xác nhận. Bảng phân quyền ở
 * docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.1.
 *
 * Ranh giới cần khóa chặt nhất là: đơn đã thu tiền thì khách KHÔNG tự hủy được, chỉ gửi yêu
 * cầu; và duyệt phải kiểm tra lại luật chặn tại thời điểm duyệt chứ không tin vào lúc gửi.
 */
class BookingChangeRequestTest extends TestCase
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

    private function taoDon(bool $daThanhToan = true): Booking
    {
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
            'status' => $daThanhToan ? 'confirmed' : 'pending',
            'paid_at' => $daThanhToan ? now()->subDay() : null,
            'confirmed_at' => $daThanhToan ? now()->subDay() : null,
            'expires_at' => $daThanhToan ? null : now()->addDay(),
        ]);
    }

    // --- Phía khách -------------------------------------------------------------------

    public function test_khach_xem_duoc_muc_hoan_truoc_khi_gui_yeu_cau(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->getJson("/api/my-bookings/{$don->id}/cancel-preview")
            ->assertOk()
            // Còn 20 ngày nên rơi vào bậc cao nhất.
            ->assertJsonPath('data.refund_percent', 75)
            ->assertJsonPath('data.seats_will_be_released', true);
    }

    /**
     * Bài quan trọng nhất của nhóm F. Đơn đã thu tiền thì gửi yêu cầu chỉ tạo bản ghi chờ duyệt,
     * đơn phải giữ nguyên trạng thái và số chỗ không được đụng tới.
     */
    public function test_gui_yeu_cau_khong_lam_doi_trang_thai_don(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
            'refund_bank_account' => '0123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => 'NGUYEN VAN A',
        ])->assertStatus(201);

        $this->assertSame('confirmed', $don->fresh()->status);
        $this->assertSame(2, (int) $this->schedule->fresh()->booked_people);

        $yeuCau = BookingChangeRequest::query()->first();

        $this->assertSame(ChangeRequestStatus::Pending, $yeuCau->status);
        $this->assertSame(75, (int) $yeuCau->estimated_refund_percent);
    }

    /** Đơn chưa thanh toán không đi đường này, khách tự hủy được ngay. */
    public function test_don_chua_thanh_toan_thi_khong_can_gui_yeu_cau(): void
    {
        $don = $this->taoDon(daThanhToan: false);
        Sanctum::actingAs($this->khach);

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Doi lich nen khong di duoc nua.',
        ])->assertStatus(422);

        $this->assertSame(0, BookingChangeRequest::query()->count());
    }

    public function test_chuyen_dang_chay_thi_khong_gui_duoc_yeu_cau(): void
    {
        $don = $this->taoDon();
        $this->schedule->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(3),
            'end_date' => now()->addDay(),
        ]);
        Sanctum::actingAs($this->khach);

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Doan da di roi nhung toi muon huy.',
        ])->assertStatus(422);
    }

    public function test_khong_gui_duoc_hai_yeu_cau_cung_luc(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $payload = [
            'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
            'refund_bank_account' => '0123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => 'NGUYEN VAN A',
        ];

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", $payload)->assertStatus(201);
        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", $payload)->assertStatus(422);

        $this->assertSame(1, BookingChangeRequest::query()->count());
    }

    public function test_khach_khong_gui_duoc_yeu_cau_cho_don_cua_nguoi_khac(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->taoUser('customer'));

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Toi muon huy don nay du no khong phai cua toi.',
        ])->assertStatus(404);
    }

    public function test_khach_rut_lai_yeu_cau_thi_gui_lai_duoc(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->khach);

        $payload = [
            'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
            'refund_bank_account' => '0123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => 'NGUYEN VAN A',
        ];

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", $payload)->assertStatus(201);

        $yeuCau = BookingChangeRequest::query()->first();

        $this->putJson("/api/my-change-requests/{$yeuCau->id}/withdraw")->assertOk();

        $this->assertSame(ChangeRequestStatus::CancelledByCustomer, $yeuCau->fresh()->status);

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", $payload)->assertStatus(201);
    }

    // --- Phía điều hành ---------------------------------------------------------------

    public function test_duyet_thi_don_bi_huy_va_cho_ve_kho(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve", [
            'review_note' => 'Khach bao truoc som, dong y hoan theo chinh sach.',
        ])->assertOk();

        $don->refresh();

        $this->assertSame('cancelled', $don->status);
        $this->assertSame('by_customer', $don->cancel_type);
        $this->assertEquals(7_500_000, (float) $don->refund_amount);
        $this->assertTrue((bool) $don->seats_released);
        $this->assertSame(0, (int) $this->schedule->fresh()->booked_people);

        $this->assertSame(ChangeRequestStatus::Approved, $yeuCau->fresh()->status);
        $this->assertSame($this->dieuHanh->id, (int) $yeuCau->fresh()->reviewed_by);
    }

    /**
     * Yêu cầu nằm chờ vài ngày thì chuyến hoàn toàn có thể đã khởi hành. Duyệt lúc đó là hủy
     * chỗ của người đang ngồi trên xe, nên phải kiểm tra lại luật chặn ở thời điểm duyệt.
     */
    public function test_khong_duyet_duoc_khi_chuyen_da_khoi_hanh_trong_luc_cho(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        $this->schedule->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(2),
            'end_date' => now()->addDay(),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertStatus(422);

        $this->assertSame('confirmed', $don->fresh()->status);
        $this->assertSame(ChangeRequestStatus::Pending, $yeuCau->fresh()->status);
    }

    /**
     * Mức hoàn chốt theo thời điểm khách gửi, không tính lại lúc duyệt: khách không kiểm soát
     * được việc điều hành xử lý nhanh hay chậm.
     */
    public function test_muc_hoan_giu_theo_luc_gui_du_da_qua_moc_phi(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        $this->assertSame(75, (int) $yeuCau->estimated_refund_percent);

        // Yêu cầu nằm chờ tới sát ngày đi, lúc này tính lại chỉ còn bậc thấp nhất.
        $this->schedule->update([
            'start_date' => now()->addHours(30),
            'end_date' => now()->addDays(3),
        ]);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertOk();

        $this->assertEquals(
            7_500_000,
            (float) $don->fresh()->refund_amount,
            'Khách vẫn nhận mức đã chốt lúc gửi, không phải mức tính lại lúc duyệt.',
        );
    }

    /**
     * Màn duyệt chỉ được đưa ra một con số. Trả về thêm mức tính lại tại thời điểm xem chỉ làm
     * người duyệt phân vân giữa hai số, trong khi chỉ một số được chi và không có đường nào đổi.
     */
    public function test_chi_tiet_yeu_cau_khong_tra_ve_muc_hoan_tinh_lai(): void
    {
        $yeuCau = $this->guiYeuCau($this->taoDon());

        Sanctum::actingAs($this->dieuHanh);

        $this->getJson("/api/admin/change-requests/{$yeuCau->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.current_quote');
    }

    public function test_tu_choi_thi_don_giu_nguyen_va_bat_buoc_co_ly_do(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/reject", [
            'review_note' => 'Ngan',
        ])->assertStatus(422);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/reject", [
            'review_note' => 'Khach da doi lich mot lan, lan nay ap dung dieu khoan khong hoan.',
        ])->assertOk();

        $this->assertSame('confirmed', $don->fresh()->status);
        $this->assertSame(ChangeRequestStatus::Rejected, $yeuCau->fresh()->status);
        $this->assertSame(2, (int) $this->schedule->fresh()->booked_people);
    }

    public function test_duyet_lan_hai_bi_tu_choi(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertOk();
        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertStatus(422);
    }

    public function test_khach_khong_vao_duoc_man_duyet(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        Sanctum::actingAs($this->khach);

        $this->getJson('/api/admin/change-requests')->assertStatus(403);
        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertStatus(403);
    }

    /**
     * Quan hệ tới người gửi và người duyệt phải nằm ở khóa riêng, không đè lên cột id.
     *
     * Nếu đặt tên quan hệ trùng tên cột khóa ngoại thì Eloquent ghi object người dùng vào đúng
     * chỗ đáng lẽ là số. Không có lỗi nào được ném ra, chỉ có phía client đọc sai kiểu, và
     * TypeScript không bắt được vì kiểu là do mình tự khai.
     */
    public function test_nguoi_gui_va_nguoi_duyet_khong_de_len_cot_id(): void
    {
        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertOk();

        $this->getJson("/api/admin/change-requests/{$yeuCau->id}")
            ->assertOk()
            ->assertJsonPath('data.request.requested_by', $this->khach->id)
            ->assertJsonPath('data.request.reviewed_by', $this->dieuHanh->id)
            ->assertJsonPath('data.request.requester.name', $this->khach->name)
            ->assertJsonPath('data.request.reviewer.name', $this->dieuHanh->name);
    }

    public function test_danh_sach_cho_duyet_dem_dung(): void
    {
        $this->guiYeuCau($this->taoDon());
        $this->guiYeuCau($this->taoDon());

        Sanctum::actingAs($this->dieuHanh);

        $this->getJson('/api/admin/change-requests')
            ->assertOk()
            ->assertJsonPath('data.pending_count', 2);
    }

    /**
     * Hủy sau hạn chốt danh sách vẫn sinh ghế chết, kể cả khi đi qua đường duyệt yêu cầu. Luật
     * trả chỗ của nhóm C không có ngoại lệ nào cho luồng này.
     */
    public function test_duyet_sau_han_chot_thi_van_sinh_ghe_chet(): void
    {
        $this->schedule->update([
            'start_date' => now()->addHours(30),
            'end_date' => now()->addDays(3),
            'booking_deadline' => now()->subHours(2),
        ]);

        $don = $this->taoDon();
        $yeuCau = $this->guiYeuCau($don);

        Sanctum::actingAs($this->dieuHanh);

        $this->putJson("/api/admin/change-requests/{$yeuCau->id}/approve")->assertOk();

        $this->assertFalse((bool) $don->fresh()->seats_released);
        $this->assertSame(
            2,
            (int) $this->schedule->fresh()->booked_people,
            'Chỗ phải giữ lại vì đã qua hạn chốt danh sách.',
        );
    }

    private function guiYeuCau(Booking $don): BookingChangeRequest
    {
        Sanctum::actingAs($this->khach);

        $this->postJson("/api/my-bookings/{$don->id}/cancel-request", [
            'reason' => 'Gia dinh co viec dot xuat khong di duoc.',
            'refund_bank_account' => '0123456789',
            'refund_bank_name' => 'Vietcombank',
            'refund_account_holder' => 'NGUYEN VAN A',
        ])->assertStatus(201);

        return BookingChangeRequest::query()
            ->where('booking_id', $don->id)
            ->latest('id')
            ->first();
    }
}
