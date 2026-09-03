<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * C06 - Luồng đầy đủ của quy tắc trả chỗ, từ lúc hủy đơn tới lúc điều hành mở lại chỗ.
 *
 * Câu hỏi số 8 của hội đồng. Quy tắc và lý do ở
 * docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 3.
 */
class HeldSeatsTest extends TestCase
{
    use RefreshDatabase;

    private function taoAdmin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * @return array{0: TourSchedule, 1: Booking}
     */
    private function taoChuyenVaDon(string $hanChot, bool $daVaoDanhSach = true): array
    {
        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(3),
            'booking_deadline' => $hanChot,
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 4,
        ]);

        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 4,
            'adult_count' => 4,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => 'confirmed',
            'paid_at' => $daVaoDanhSach ? now()->subDays(5) : null,
            'confirmed_at' => $daVaoDanhSach ? now()->subDays(5) : null,
        ]);

        return [$schedule, $booking];
    }

    /**
     * Người bấm nút phải biết trước hậu quả, không phải phát hiện ra sau.
     *
     * Trước khi có dự báo này, hộp thoại hủy khẳng định "hủy đơn sẽ trả lại chỗ cho lịch khởi
     * hành" trong mọi trường hợp - sai kể từ khi có quy tắc giữ chỗ sau hạn chốt, và sai theo
     * hướng nguy hiểm: điều hành hủy xong tưởng chỗ đã về kho nên không đi xin thêm suất.
     */
    public function test_du_bao_bao_truoc_rang_huy_som_thi_cho_ve_kho(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.seats_will_be_released', true);
    }

    public function test_du_bao_canh_bao_ghe_chet_truoc_khi_huy(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.seats_will_be_released', false);
    }

    /**
     * Dự báo phải khớp với việc thật sự xảy ra khi bấm hủy. Hai con số lệch nhau thì dự báo còn
     * tệ hơn không có, vì nó khiến người ta tin vào một kết quả không đúng.
     */
    public function test_du_bao_khop_voi_ket_qua_huy_that(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $duBao = $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")->json('data');

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Khach huy sat ngay di',
        ])->assertOk();

        $this->assertSame(
            $duBao['seats_will_be_released'],
            (bool) $booking->fresh()->seats_released,
            'Dự báo trả chỗ phải khớp với kết quả thật.',
        );

        $this->assertSame(4, (int) $schedule->fresh()->booked_people);
    }

    /** Chuyến đang chạy thì dự báo phải nói không hủy được, kèm lý do để hiện ngay trên màn hình. */
    public function test_du_bao_noi_ro_khi_khong_huy_duoc(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subDay()->toDateTimeString());

        $schedule->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(3),
            'end_date' => now()->addDay(),
        ]);

        Sanctum::actingAs($this->taoAdmin());

        $response = $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false);

        $this->assertNotEmpty($response->json('data.blocked_reason'));
    }

    /** Mức hoàn trong dự báo phải là con số thật của bảng phí, không phải số làm tròn cho đẹp. */
    public function test_du_bao_tra_ve_dung_muc_hoan_theo_so_gio_con_lai(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());

        // Đẩy ngày khởi hành ra xa để rơi vào bậc 15 đến 20 ngày.
        $schedule->update([
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22),
        ]);

        Sanctum::actingAs($this->taoAdmin());

        $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")
            ->assertOk()
            ->assertJsonPath('data.refund_percent', 75)
            ->assertJsonPath('data.cancellation_fee', 1000000)
            ->assertJsonPath('data.refund_amount', 3000000);
    }

    public function test_huy_truoc_han_chot_thi_cho_ve_kho_ngay(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Khach yeu cau huy som',
        ])->assertOk();

        $this->assertSame(0, (int) $schedule->fresh()->booked_people);
        $this->assertTrue((bool) $booking->fresh()->seats_released);
    }

    /**
     * Bài quan trọng nhất của nhóm C. Chỗ trống về mặt vật lý nhưng phòng và suất ăn đã chốt
     * theo danh sách, nên không được trả về kho để bán lại.
     */
    public function test_huy_sau_han_chot_thi_giu_cho_lai_khong_ban_tiep(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Khach huy sat ngay di',
        ])->assertOk();

        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertSame(
            4,
            (int) $schedule->fresh()->booked_people,
            'Số chỗ đã bán phải giữ nguyên, vì suất đã cam kết với nhà cung cấp.',
        );
        $this->assertFalse((bool) $booking->seats_released);
        $this->assertNull($booking->seats_released_at);
    }

    /**
     * Giữ chỗ chưa thanh toán không nằm trong danh sách gửi nhà cung cấp, nên luôn trả chỗ
     * kể cả khi đã qua hạn chốt. Nếu không, một người vào giữ chỗ rồi bỏ đi cũng làm mất
     * vĩnh viễn một chỗ bán được.
     */
    public function test_giu_cho_chua_thanh_toan_van_tra_cho_du_qua_han(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(
            hanChot: now()->subHour()->toDateTimeString(),
            daVaoDanhSach: false,
        );

        $booking->update(['status' => 'pending', 'expires_at' => now()->subMinute()]);

        $this->artisan('bookings:release-expired')->assertSuccessful();

        $this->assertSame(0, (int) $schedule->fresh()->booked_people);
        $this->assertTrue((bool) $booking->fresh()->seats_released);
    }

    /**
     * Ghế chết ở lại tới khi chuyến kết thúc, không có đường nào trả nó về kho.
     *
     * Từng có màn hình cho điều hành mở lại; đã bỏ. Phí hủy đã bù phần chi phí đã cam kết với nhà
     * cung cấp, nên việc còn lại thuần túy là **đừng bán ra thứ không giao được** - và luật này
     * lo trọn. Bài test giữ đúng điều đó: đơn hủy sau hạn chốt thì số chỗ đứng yên.
     */
    public function test_ghe_chet_khong_tu_quay_ve_kho(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Huy sat ngay di',
        ])->assertOk();

        $this->assertSame(4, (int) $schedule->fresh()->booked_people);
        $this->assertFalse((bool) $booking->fresh()->seats_released);

        // Chạy hết các tác vụ nền: không tác vụ nào được âm thầm trả chỗ ấy về kho.
        $this->artisan('bookings:release-expired')->assertSuccessful();
        $this->artisan('schedules:close-expired')->assertSuccessful();

        $this->assertSame(4, (int) $schedule->fresh()->booked_people);
    }

    /**
     * Không nói ai hủy thì mặc định là KHÁCH đổi ý — và khi đó bảng phí được áp.
     *
     * Trước đây màn này ghi cứng `by_company` cho mọi lần hủy, trong khi vẫn trừ phí theo bảng.
     * Bản ghi tự mâu thuẫn với chính số tiền của nó, và mẫu thư báo hủy đọc đúng cột ấy rồi nói
     * với khách rằng họ được hoàn đủ 100%.
     */
    public function test_mac_dinh_la_khach_huy_va_co_ap_phi_huy(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());
        $admin = $this->taoAdmin();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Khach goi dien xin huy vi ban viec dot xuat',
        ])->assertOk();

        $booking->refresh();

        $this->assertSame('by_customer', $booking->cancel_type);
        $this->assertSame($admin->id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);

        $this->assertLessThan(
            4_000_000,
            (float) $booking->refund_amount,
            'Khách đổi ý thì phải áp bảng phí hủy, không hoàn đủ.',
        );
    }

    /**
     * Chọn "công ty hủy" thì hoàn ĐỦ số đã thu, không áp bảng phí.
     *
     * Bảng phí dành cho người đổi ý; ở đây bên bán là bên không thực hiện. Cùng nguyên tắc mà luồng
     * hủy cả chuyến đã áp từ trước — chỉ là màn hủy từng đơn chưa có đường nào diễn đạt nó.
     */
    public function test_cong_ty_huy_thi_hoan_du_khong_ap_phi(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());
        $admin = $this->taoAdmin();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Xe hong khong the thay the, cong ty khong phuc vu duoc don nay',
            'cancel_type' => 'by_company',
        ])->assertOk();

        $booking->refresh();

        $this->assertSame('by_company', $booking->cancel_type);
        $this->assertSame(4_000_000.0, (float) $booking->refund_amount);
    }

    /** Dự báo phải tính theo đúng loại hủy sắp chọn, nếu không số trên màn hình khác số thực chi. */
    public function test_du_bao_doi_theo_loai_huy(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $khachHuy = $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")
            ->assertOk()
            ->json('data');

        $congTyHuy = $this->getJson(
            "/api/admin/bookings/{$booking->id}/cancel-preview?cancel_type=by_company",
        )->assertOk()->json('data');

        $this->assertLessThan(100, $khachHuy['refund_percent']);
        $this->assertSame(100, $congTyHuy['refund_percent']);
        $this->assertTrue($congTyHuy['company_initiated']);
        $this->assertGreaterThan($khachHuy['refund_amount'], $congTyHuy['refund_amount']);
    }
}
