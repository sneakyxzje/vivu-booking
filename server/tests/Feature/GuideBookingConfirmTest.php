<?php

namespace Tests\Feature;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleStatus;
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
 * Hướng dẫn viên xác nhận đơn tại điểm tập trung.
 *
 * Tình huống: khách hẹn trả tiền mặt lúc lên xe, hướng dẫn viên thu rồi vào xác nhận.
 *
 * Đây là thao tác khẳng định khách đã trả tiền, tức một quyết định về tiền. Đường này từng ghi
 * thẳng vào model, không khóa dòng và không để lại dấu vết nào trên nhật ký - cùng một mẫu lỗi
 * với Guide\AttendanceController trước đây: controller của hướng dẫn viên tự làm thay vì đi qua
 * đường mà quản trị đi.
 */
class GuideBookingConfirmTest extends TestCase
{
    use RefreshDatabase;

    private User $guide;
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

        $this->guide = $this->taoUser('guide');

        $tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addHours(6),
            'end_date' => now()->addDays(2),
            'booking_deadline' => now()->subDays(2),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 2,
        ]);

        $this->schedule->guides()->sync([$this->guide->id]);
    }

    private function taoDon(string $status = 'pending', ?TourSchedule $schedule = null): Booking
    {
        $schedule ??= $this->schedule;

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Tra Tien Mat',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => $status,
            'expires_at' => $status === 'pending' ? now()->addDay() : null,
        ]);
    }

    public function test_guide_xac_nhan_duoc_don_cua_chuyen_minh_phu_trach(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->guide);

        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertOk();

        $don->refresh();

        $this->assertSame('confirmed', $don->status);
        $this->assertNotNull($don->confirmed_at);
        $this->assertNull($don->expires_at, 'Xác nhận rồi thì chỗ không còn là giữ tạm.');
    }

    /**
     * Bài quan trọng nhất. Hướng dẫn viên xác nhận nghĩa là khẳng định khách đã trả tiền, nên
     * phải để lại dấu vết y như khi quản trị làm.
     */
    public function test_xac_nhan_cua_guide_duoc_ghi_nhat_ky_kem_dung_vai_tro(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->guide);

        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertOk();

        $log = BookingAuditLog::query()->where('booking_id', $don->id)->latest('id')->first();

        $this->assertNotNull($log, 'Xác nhận mà không ghi nhật ký thì thao tác biến mất khỏi dòng thời gian.');
        $this->assertSame(BookingAuditAction::Confirmed, $log->action);
        $this->assertSame($this->guide->id, (int) $log->actor_id);
        $this->assertSame('guide', $log->actor_role);
        $this->assertSame('pending', $log->old_values['status']);
        $this->assertSame('confirmed', $log->new_values['status']);
        $this->assertStringContainsString('điểm tập trung', $log->reason);
    }

    public function test_guide_khac_khong_xac_nhan_duoc(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->taoUser('guide'));

        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertStatus(404);

        $this->assertSame('pending', $don->fresh()->status);
    }

    public function test_don_da_xac_nhan_thi_bam_lan_hai_bi_tu_choi(): void
    {
        $don = $this->taoDon('confirmed');
        Sanctum::actingAs($this->guide);

        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertStatus(400);
    }

    /** Bấm hai lần liên tiếp chỉ sinh đúng một bản ghi nhật ký. */
    public function test_bam_hai_lan_khong_sinh_hai_ban_ghi_nhat_ky(): void
    {
        $don = $this->taoDon();
        Sanctum::actingAs($this->guide);

        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertOk();
        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertStatus(400);

        $this->assertSame(
            1,
            BookingAuditLog::query()->where('booking_id', $don->id)->count(),
        );
    }

    public function test_don_da_huy_thi_khong_xac_nhan_duoc(): void
    {
        $don = $this->taoDon('cancelled');
        Sanctum::actingAs($this->guide);

        $this->putJson("/api/guide/bookings/{$don->id}/confirm")->assertStatus(400);
        $this->assertSame('cancelled', $don->fresh()->status);
    }

    public function test_guide_chi_thay_don_cua_chuyen_minh_phu_trach(): void
    {
        $donCuaMinh = $this->taoDon();

        $chuyenKhac = TourSchedule::create([
            'tour_id' => $this->schedule->tour_id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
            'max_people' => 10,
            'min_people' => 2,
            'booked_people' => 2,
        ]);

        $chuyenKhac->guides()->sync([$this->taoUser('guide')->id]);
        $donNguoiKhac = $this->taoDon('pending', $chuyenKhac);

        Sanctum::actingAs($this->guide);

        $response = $this->getJson('/api/guide/bookings')->assertOk();
        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($donCuaMinh->id, $ids);
        $this->assertNotContains($donNguoiKhac->id, $ids);
    }
}
