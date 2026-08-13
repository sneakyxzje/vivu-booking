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

        // Đẩy ngày khởi hành ra xa để rơi vào bậc hoàn cao nhất.
        $schedule->update([
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(22),
        ]);

        Sanctum::actingAs($this->taoAdmin());

        $this->getJson("/api/admin/bookings/{$booking->id}/cancel-preview")
            ->assertOk()
            ->assertJsonPath('data.refund_percent', 90)
            ->assertJsonPath('data.cancellation_fee', 400000)
            ->assertJsonPath('data.refund_amount', 3600000);
    }

    /**
     * Mở lại chỗ trả số chỗ về kho, nhưng không được kéo chuyến về "đang mở bán".
     *
     * Ghế chết chỉ sinh ra sau hạn chốt danh sách, nên nhánh mở lại luôn chạy trên chuyến đã quá
     * hạn. Ghi trạng thái đang mở bán ở đó là nói dối màn hình quản trị: khách vẫn không đặt
     * được vì hạn chốt đã qua, và tác vụ đóng bán chạy phút sau lại đóng về, thành ra trạng thái
     * nhấp nháy mà không ai hiểu vì sao.
     */
    public function test_mo_lai_cho_khong_keo_chuyen_qua_han_ve_dang_mo_ban(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        $schedule->update(['status' => ScheduleStatus::Closed->value]);

        $admin = $this->taoAdmin();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Khach huy sat ngay di',
        ])->assertOk();

        $this->putJson("/api/admin/bookings/{$booking->id}/release-seats")->assertOk();

        $schedule->refresh();

        $this->assertSame(0, (int) $schedule->booked_people, 'Chỗ vẫn phải về kho.');
        $this->assertSame(
            ScheduleStatus::Closed->value,
            $schedule->getRawOriginal('status'),
            'Chuyến đã qua hạn chốt thì giữ nguyên đã đóng bán.',
        );
    }

    /** Còn trong hạn chốt thì mở lại chỗ kéo theo mở bán lại, vì lúc đó bán tiếp được thật. */
    public function test_mo_lai_cho_khi_con_trong_han_thi_chuyen_mo_ban_lai(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());

        $admin = $this->taoAdmin();
        Sanctum::actingAs($admin);

        // Dựng thẳng một đơn đã hủy mà chỗ chưa trả, tình huống chỉ sinh ra được bằng tay khi
        // hạn chốt còn phía trước.
        $booking->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now()->subHour(),
            'seats_released' => false,
        ])->save();

        $schedule->update(['status' => ScheduleStatus::Closed->value]);

        $this->putJson("/api/admin/bookings/{$booking->id}/release-seats")->assertOk();

        $this->assertSame(
            ScheduleStatus::Open->value,
            $schedule->fresh()->getRawOriginal('status'),
        );
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

    public function test_ghe_chet_hien_tren_danh_sach_cho_dieu_hanh(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Huy sat ngay di',
        ])->assertOk();

        $this->getJson('/api/admin/bookings/held-seats')
            ->assertOk()
            ->assertJsonPath('data.total_held_seats', 4)
            ->assertJsonPath('data.bookings.data.0.id', $booking->id);
    }

    public function test_dieu_hanh_mo_lai_cho_thi_ban_tiep_duoc(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        $admin = $this->taoAdmin();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Huy sat ngay di',
        ])->assertOk();

        $this->assertSame(4, (int) $schedule->fresh()->booked_people);

        $this->putJson("/api/admin/bookings/{$booking->id}/release-seats")->assertOk();

        $booking->refresh();

        $this->assertSame(0, (int) $schedule->fresh()->booked_people);
        $this->assertTrue((bool) $booking->seats_released);
        $this->assertNotNull($booking->seats_released_at);
        $this->assertSame($admin->id, $booking->seats_released_by);
    }

    /**
     * Hai người cùng bấm mở lại thì người sau phải bị từ chối, không được trừ số chỗ lần hai.
     */
    public function test_mo_lai_cho_lan_hai_bi_tu_choi(): void
    {
        [$schedule, $booking] = $this->taoChuyenVaDon(hanChot: now()->subHour()->toDateTimeString());
        Sanctum::actingAs($this->taoAdmin());

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Huy sat ngay di',
        ])->assertOk();

        $this->putJson("/api/admin/bookings/{$booking->id}/release-seats")->assertOk();
        $this->putJson("/api/admin/bookings/{$booking->id}/release-seats")->assertStatus(400);

        $this->assertSame(0, (int) $schedule->fresh()->booked_people);
    }

    public function test_ghi_lai_ai_huy_va_huy_kieu_gi(): void
    {
        [, $booking] = $this->taoChuyenVaDon(hanChot: now()->addDay()->toDateTimeString());
        $admin = $this->taoAdmin();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/bookings/{$booking->id}/cancel", [
            'cancel_reason' => 'Doi lich trinh',
        ])->assertOk();

        $booking->refresh();

        $this->assertSame('by_company', $booking->cancel_type);
        $this->assertSame($admin->id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);
    }
}
