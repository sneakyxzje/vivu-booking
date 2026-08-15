<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * X12 - Dọn đơn giữ chỗ còn treo của chuyến đã kết thúc.
 *
 * Tình huống J06 ở docs/nghiep-vu/08-danh-muc-edge-case.md.
 *
 * Nhóm này lọt lưới của bookings:release-expired, thường vì expires_at rỗng. Chuyến đi xong rồi
 * mà đơn vẫn ghi "chờ thanh toán" và vẫn chiếm chỗ trong booked_people.
 */
class ExpireStaleHoldsTest extends TestCase
{
    use RefreshDatabase;

    private function taoChuyen(array $ghiDe = []): TourSchedule
    {
        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        return TourSchedule::create(array_merge([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Completed->value,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(3),
            'booking_deadline' => now()->subDays(8),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 2,
        ], $ghiDe));
    }

    private function taoDon(TourSchedule $schedule, string $status = 'pending', $expiresAt = null): Booking
    {
        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Treo',
            'customer_email' => 'treo-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => $status,
            // Cố ý để trống: đây chính là lý do đơn lọt qua tác vụ nhả chỗ quá hạn.
            'expires_at' => $expiresAt,
            'paid_at' => $status === 'confirmed' ? now()->subDays(6) : null,
            'confirmed_at' => $status === 'confirmed' ? now()->subDays(6) : null,
        ]);
    }

    public function test_don_treo_cua_chuyen_da_ket_thuc_bi_don(): void
    {
        $schedule = $this->taoChuyen();
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $don->refresh();

        $this->assertSame('cancelled', $don->status);
        $this->assertSame('stale_hold', $don->cancel_type);
        $this->assertNotNull($don->cancelled_at);
    }

    /** Đơn chưa thanh toán thì chỗ luôn trả về kho, kể cả đã qua hạn chốt danh sách. */
    public function test_don_xong_thi_cho_ve_kho(): void
    {
        $schedule = $this->taoChuyen();
        $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertSame(0, (int) $schedule->fresh()->booked_people);
    }

    /**
     * Bài quan trọng nhất. Chuyến chưa kết thúc thì đơn vẫn còn cơ hội được thanh toán, dọn lúc
     * này là hủy đơn của khách đang trên đường ra ngân hàng.
     */
    public function test_chuyen_chua_ket_thuc_thi_khong_dung_vao_don(): void
    {
        $schedule = $this->taoChuyen([
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(7),
            'booking_deadline' => now()->addDays(2),
        ]);
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertSame('pending', $don->fresh()->status);
        $this->assertSame(2, (int) $schedule->fresh()->booked_people);
    }

    /** Chuyến đang chạy cũng chưa dọn: khách có thể trả tiền tại điểm tập trung. */
    public function test_chuyen_dang_chay_thi_chua_don(): void
    {
        $schedule = $this->taoChuyen([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(3),
            'end_date' => now()->addDay(),
        ]);
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertSame('pending', $don->fresh()->status);
    }

    public function test_don_da_thanh_toan_khong_bi_dung_toi(): void
    {
        $schedule = $this->taoChuyen();
        $don = $this->taoDon($schedule, 'confirmed');

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertSame('confirmed', $don->fresh()->status);
    }

    /**
     * Trạng thái lưu trong cơ sở dữ liệu có thể chậm hơn đồng hồ, và đó chính là hoàn cảnh sinh
     * ra nhóm đơn treo này: tác vụ nền dừng thì cả trạng thái chuyến lẫn đơn đều đứng yên.
     */
    public function test_chuyen_qua_gio_ket_thuc_nhung_cot_trang_thai_chua_kip_doi_van_don_duoc(): void
    {
        $schedule = $this->taoChuyen(['status' => ScheduleStatus::InProgress->value]);
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertSame('cancelled', $don->fresh()->status);
    }

    public function test_chuyen_da_huy_thi_khong_xet(): void
    {
        $schedule = $this->taoChuyen(['status' => ScheduleStatus::Cancelled->value]);
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertSame('pending', $don->fresh()->status);
    }

    public function test_don_bi_don_thi_co_ghi_nhat_ky(): void
    {
        $schedule = $this->taoChuyen();
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $log = BookingAuditLog::query()->where('booking_id', $don->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->actor_id, 'Tác vụ nền không có người thao tác.');
        $this->assertSame('pending', $log->old_values['status']);
        $this->assertSame('cancelled', $log->new_values['status']);
    }

    public function test_chay_lan_hai_khong_dung_lai_don_da_don(): void
    {
        $schedule = $this->taoChuyen();
        $don = $this->taoDon($schedule);

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();
        $huyLuc = $don->fresh()->cancelled_at;

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();

        $this->assertEquals($huyLuc, $don->fresh()->cancelled_at);
        $this->assertSame(0, (int) $schedule->fresh()->booked_people);
    }

    /** Dọn xong thì số chỗ phải khớp với thực tế, không để lệnh đối chiếu báo đỏ. */
    public function test_sau_khi_don_thi_so_cho_van_nhat_quan(): void
    {
        $schedule = $this->taoChuyen(['booked_people' => 4]);
        $this->taoDon($schedule);
        $this->taoDon($schedule, 'confirmed');

        $this->artisan('bookings:expire-stale-holds')->assertSuccessful();
        $this->artisan('bookings:check-seat-consistency')->assertSuccessful();
    }
}
