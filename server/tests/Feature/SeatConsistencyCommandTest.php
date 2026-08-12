<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C05 - Lệnh đối chiếu số chỗ.
 *
 * Từ khi có quy tắc giữ chỗ sau hạn chốt, booked_people không còn bằng tổng đơn chưa hủy:
 * nó gồm cả ghế chết. Lệnh này kiểm tra đúng công thức đó.
 */
class SeatConsistencyCommandTest extends TestCase
{
    use RefreshDatabase;

    private function taoChuyen(int $daGhi): TourSchedule
    {
        $tour = Tour::factory()->create(['status' => 'active']);

        return TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
            'max_people' => 30,
            'booked_people' => $daGhi,
        ]);
    }

    private function taoDon(TourSchedule $schedule, int $khach, string $trangThai, bool $daTraCho = true): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => $khach,
            'adult_count' => $khach,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $khach * 1_000_000,
            'status' => $trangThai,
            'seats_released' => $daTraCho,
        ]);
    }

    public function test_khong_bao_gi_khi_so_cho_khop(): void
    {
        $schedule = $this->taoChuyen(daGhi: 5);
        $this->taoDon($schedule, khach: 3, trangThai: 'confirmed');
        $this->taoDon($schedule, khach: 2, trangThai: 'pending');

        $this->artisan('bookings:check-seat-consistency')
            ->expectsOutputToContain('Số chỗ của mọi chuyến đều khớp.')
            ->assertSuccessful();
    }

    /**
     * Ghế chết vẫn phải tính vào số chỗ đã bán, vì suất đó đã cam kết với nhà cung cấp.
     */
    public function test_ghe_chet_van_tinh_la_cho_dang_bi_chiem(): void
    {
        $schedule = $this->taoChuyen(daGhi: 6);
        $this->taoDon($schedule, khach: 2, trangThai: 'confirmed');
        // Đơn đã hủy nhưng chỗ chưa trả về kho.
        $this->taoDon($schedule, khach: 4, trangThai: 'cancelled', daTraCho: false);

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_don_da_huy_va_da_tra_cho_thi_khong_tinh_nua(): void
    {
        $schedule = $this->taoChuyen(daGhi: 2);
        $this->taoDon($schedule, khach: 2, trangThai: 'confirmed');
        $this->taoDon($schedule, khach: 4, trangThai: 'cancelled', daTraCho: true);

        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }

    public function test_bao_loi_khi_so_cho_bi_lech(): void
    {
        $schedule = $this->taoChuyen(daGhi: 10);
        $this->taoDon($schedule, khach: 3, trangThai: 'confirmed');

        $this->artisan('bookings:check-seat-consistency')
            ->expectsOutputToContain("Chuyến #{$schedule->id} lệch")
            ->assertFailed();

        // Chỉ báo cáo, không tự sửa: lệch số chỗ là dấu hiệu có lỗi ở đâu đó.
        $this->assertSame(10, (int) $schedule->fresh()->booked_people);
    }

    public function test_tuy_chon_fix_nan_lai_so_lieu(): void
    {
        $schedule = $this->taoChuyen(daGhi: 10);
        $this->taoDon($schedule, khach: 3, trangThai: 'confirmed');

        $this->artisan('bookings:check-seat-consistency', ['--fix' => true])->assertSuccessful();

        $this->assertSame(3, (int) $schedule->fresh()->booked_people);
    }
}
