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
