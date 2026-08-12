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
 * X07a - Mở lại đơn đã hủy nhầm trong vòng 24 giờ (edge case C06).
 *
 * Ba tình huống dễ sai nhất được kiểm riêng: đơn còn giữ chỗ thì không cần chỗ trống mới,
 * hai người bấm cùng lúc không được cộng chỗ hai lượt, và đơn thiếu cancelled_at không được
 * mở lại vô thời hạn.
 */
class ReopenBookingTest extends TestCase
{
    use RefreshDatabase;

    private function dangNhapAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array{0: TourSchedule, 1: Booking}
     */
    private function taoDonDaHuy(
        int $maxPeople = 10,
        int $bookedPeople = 4,
        int $guests = 4,
        bool $daTraCho = true,
        ?string $cancelledAt = null,
        bool $daThanhToan = false,
    ): array {
        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(11),
            'booking_deadline' => now()->addDays(7),
            'max_people' => $maxPeople,
            'booked_people' => $bookedPeople,
        ]);

        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $guests,
            'adult_count' => $guests,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $guests * 1_000_000,
            'status' => 'cancelled',
            'cancel_reason' => 'Huy nham',
            'cancelled_at' => $cancelledAt ?? now()->subHour(),
            'seats_released' => $daTraCho,
            'vnpay_transaction_no' => $daThanhToan ? 'VNP' . Str::random(8) : null,
        ]);

        return [$schedule, $booking];
    }

    public function test_mo_lai_don_da_tra_cho_thi_cong_lai_so_cho(): void
    {
        // Chỗ đã trả về kho nên booked_people đang là 0.
        [$schedule, $booking] = $this->taoDonDaHuy(bookedPeople: 0, daTraCho: true);
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Quan tri bam nham nut huy',
        ])->assertOk();

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame(4, (int) $schedule->fresh()->booked_people);
    }

    /**
     * Đơn hủy sau hạn chốt giữ nguyên chỗ, tức booked_people vẫn đang tính chỗ đó.
     * Mở lại không tốn chỗ mới nên không được đòi chỗ trống, và cũng không được cộng thêm.
     */
    public function test_don_con_giu_cho_thi_khong_can_cho_trong_va_khong_cong_them(): void
    {
        // Chuyến đầy: 10 trên 10, trong đó 4 chỗ là của đơn đã hủy nhưng chưa trả.
        [$schedule, $booking] = $this->taoDonDaHuy(
            maxPeople: 10,
            bookedPeople: 10,
            guests: 4,
            daTraCho: false,
        );
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Khach doi y muon di lai',
        ])->assertOk();

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame(
            10,
            (int) $schedule->fresh()->booked_people,
            'Chỗ vốn chưa trả về kho nên không được cộng thêm lần nữa.',
        );
    }

    public function test_khong_du_cho_trong_thi_tu_choi(): void
    {
        // Chỗ đã trả về kho, và chuyến đã được người khác đặt kín.
        [, $booking] = $this->taoDonDaHuy(
            maxPeople: 10,
            bookedPeople: 10,
            guests: 4,
            daTraCho: true,
        );
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Thu mo lai',
        ])->assertStatus(400);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    /**
     * Hai quản trị viên bấm mở lại cùng một đơn. Lần thứ hai phải bị từ chối, và quan trọng
     * hơn là số chỗ không được cộng hai lượt.
     */
    public function test_bam_mo_lai_lan_hai_khong_cong_cho_hai_luot(): void
    {
        [$schedule, $booking] = $this->taoDonDaHuy(bookedPeople: 0, daTraCho: true);
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Mo lai lan mot',
        ])->assertOk();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Mo lai lan hai',
        ])->assertStatus(400);

        $this->assertSame(
            4,
            (int) $schedule->fresh()->booked_people,
            'Số chỗ chỉ được cộng đúng một lần.',
        );
    }

    public function test_qua_hai_muoi_tu_gio_thi_khong_mo_lai_duoc(): void
    {
        [, $booking] = $this->taoDonDaHuy(cancelledAt: now()->subHours(30)->toDateTimeString());
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Thu mo lai don cu',
        ])->assertStatus(400);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    /**
     * Đơn hủy từ trước khi có cột cancelled_at không được mở lại vô thời hạn.
     * Lấy updated_at làm mốc thay thế.
     */
    public function test_don_thieu_cancelled_at_van_bi_gioi_han_thoi_gian(): void
    {
        [, $booking] = $this->taoDonDaHuy();

        $booking->forceFill([
            'cancelled_at' => null,
            'updated_at' => now()->subDays(5),
        ])->saveQuietly();

        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Don cu khong co moc huy',
        ])->assertStatus(400);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_chuyen_da_khoi_hanh_thi_khong_mo_lai_duoc(): void
    {
        [$schedule, $booking] = $this->taoDonDaHuy();
        $schedule->forceFill(['start_date' => now()->subDay()])->save();

        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Chuyen da di roi',
        ])->assertStatus(400);
    }

    public function test_don_da_thanh_toan_thi_mo_lai_ve_confirmed(): void
    {
        [, $booking] = $this->taoDonDaHuy(bookedPeople: 0, daThanhToan: true);
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Khach da tra tien roi',
        ])->assertOk();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_don_chua_huy_thi_khong_mo_lai_duoc(): void
    {
        [, $booking] = $this->taoDonDaHuy();
        $booking->forceFill(['status' => 'confirmed'])->save();

        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Don nay dau co huy',
        ])->assertStatus(400);
    }

    /**
     * Mở lại làm chuyến đầy chỗ thì phải tự đóng bán, nếu không web vẫn nhận đặt tiếp.
     */
    public function test_mo_lai_lam_day_chuyen_thi_tu_dong_ban(): void
    {
        [$schedule, $booking] = $this->taoDonDaHuy(
            maxPeople: 10,
            bookedPeople: 6,
            guests: 4,
            daTraCho: true,
        );
        $this->dangNhapAdmin();

        $this->putJson("/api/admin/bookings/{$booking->id}/reopen", [
            'reopen_reason' => 'Khach quay lai di',
        ])->assertOk();

        $schedule->refresh();

        $this->assertSame(10, (int) $schedule->booked_people);
        $this->assertSame(ScheduleStatus::Closed, $schedule->status);
    }
}
