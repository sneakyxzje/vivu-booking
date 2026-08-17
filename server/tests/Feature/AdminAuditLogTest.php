<?php

namespace Tests\Feature;

use App\Enums\BookingAuditAction;
use App\Enums\ScheduleAuditAction;
use App\Enums\ScheduleStatus;
use App\Enums\TourType;
use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\ScheduleAuditLog;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nhật ký hệ thống: một dòng thời gian cho mọi can thiệp vào đơn và vào chuyến.
 *
 * Nhật ký đơn trước đây chỉ xem được khi mở hộp chi tiết của đúng đơn đó, tức phải biết trước
 * cần tìm đơn nào. Nhật ký chuyến thì chưa có chỗ đọc nào. Bộ test này giữ màn hình gộp.
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $quanTri;
    private Tour $tour;
    private TourSchedule $chuyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quanTri = User::create([
            'name' => 'Quan Tri Test',
            'email' => 'quan-tri-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create([
            'status' => 'active',
            'type' => TourType::Shared->value,
            'number_of_days' => 2,
            'adult_price' => 2_000_000,
        ]);

        $start = now()->addDays(20);

        $this->chuyen = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'booking_deadline' => $start->copy()->subDays(3),
            'max_people' => 20,
            'min_people' => 5,
            'booked_people' => 0,
        ]);
    }

    private function taoDon(string $status = 'confirmed', int $khach = 2): Booking
    {
        $this->chuyen->increment('booked_people', $khach);
        $this->chuyen->refresh();

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->chuyen->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $this->chuyen->start_date,
            'guests' => $khach,
            'adult_count' => $khach,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $khach * 2_000_000,
            'status' => $status,
            'paid_at' => $status === 'confirmed' ? now()->subDay() : null,
            'confirmed_at' => $status === 'confirmed' ? now()->subDay() : null,
            'expires_at' => $status === 'pending' ? now()->addDay() : null,
        ]);
    }

    private function ghiNhatKyDon(Booking $don, BookingAuditAction $thaoTac, $luc): void
    {
        $log = BookingAuditLog::query()->create([
            'booking_id' => $don->id,
            'actor_id' => $this->quanTri->id,
            'actor_role' => 'admin',
            'action' => $thaoTac,
            'reason' => 'Ghi tay trong bài kiểm thử.',
        ]);

        $log->forceFill(['created_at' => $luc])->save();
    }

    private function ghiNhatKyChuyen($luc): void
    {
        $log = ScheduleAuditLog::query()->create([
            'tour_schedule_id' => $this->chuyen->id,
            'actor_id' => $this->quanTri->id,
            'actor_role' => 'admin',
            'action' => ScheduleAuditAction::DeadlineChanged,
            'old_values' => ['booking_deadline' => now()->addDays(17)->toIso8601String()],
            'new_values' => ['booking_deadline' => now()->addDays(19)->toIso8601String()],
            'reason' => 'Khach san cho them phong.',
        ]);

        $log->forceFill(['created_at' => $luc])->save();
    }

    // --- Gộp hai nguồn ------------------------------------------------------------------

    public function test_gop_nhat_ky_don_va_nhat_ky_chuyen_theo_thu_tu_thoi_gian(): void
    {
        $don = $this->taoDon();

        $this->ghiNhatKyDon($don, BookingAuditAction::Cancelled, now()->subHours(3));
        $this->ghiNhatKyChuyen(now()->subHours(2));
        $this->ghiNhatKyDon($don, BookingAuditAction::SeatsReleased, now()->subHour());

        Sanctum::actingAs($this->quanTri);

        $rows = $this->getJson('/api/admin/audit-logs')->assertOk()->json('data.data');

        $this->assertCount(3, $rows);

        // Mới nhất lên đầu, và hai nguồn thật sự nằm chung một dòng thời gian.
        $this->assertSame('seats_released', $rows[0]['action']);
        $this->assertSame('schedule', $rows[1]['source']);
        $this->assertSame('deadline_changed', $rows[1]['action']);
        $this->assertSame('cancelled', $rows[2]['action']);

        $this->assertSame('BK-' . $don->id, $rows[0]['subject_label']);
        $this->assertSame('Chuyến #' . $this->chuyen->id, $rows[1]['subject_label']);
    }

    public function test_loc_theo_nguon(): void
    {
        $don = $this->taoDon();
        $this->ghiNhatKyDon($don, BookingAuditAction::Cancelled, now()->subHours(2));
        $this->ghiNhatKyChuyen(now()->subHour());

        Sanctum::actingAs($this->quanTri);

        $chiChuyen = $this->getJson('/api/admin/audit-logs?scope=schedule')->assertOk()->json('data.data');
        $this->assertCount(1, $chiChuyen);
        $this->assertSame('schedule', $chiChuyen[0]['source']);

        $chiDon = $this->getJson('/api/admin/audit-logs?scope=booking')->assertOk()->json('data.data');
        $this->assertCount(1, $chiDon);
        $this->assertSame('booking', $chiDon[0]['source']);
    }

    /**
     * touchesMoney() đã nằm sẵn trong enum từ lâu và vẫn được trả về trong dữ liệu, nhưng chưa
     * có màn hình nào dùng để lọc. Đây là câu hỏi thật của điều hành: hôm qua ai đụng vào tiền.
     */
    public function test_loc_chi_nhung_lan_cham_tien(): void
    {
        $don = $this->taoDon();

        $this->ghiNhatKyDon($don, BookingAuditAction::Cancelled, now()->subHours(4));
        $this->ghiNhatKyDon($don, BookingAuditAction::PassengersUpdated, now()->subHours(3));
        $this->ghiNhatKyDon($don, BookingAuditAction::Transferred, now()->subHours(2));
        $this->ghiNhatKyChuyen(now()->subHour());

        Sanctum::actingAs($this->quanTri);

        $rows = $this->getJson('/api/admin/audit-logs?money_only=1')->assertOk()->json('data.data');

        $this->assertCount(2, $rows);
        $this->assertSame(['transferred', 'cancelled'], array_column($rows, 'action'));

        foreach ($rows as $dong) {
            $this->assertTrue($dong['touches_money']);
        }
    }

    public function test_loc_theo_chuyen_lay_ca_don_thuoc_chuyen_do(): void
    {
        $don = $this->taoDon();
        $this->ghiNhatKyDon($don, BookingAuditAction::Cancelled, now()->subHours(2));
        $this->ghiNhatKyChuyen(now()->subHour());

        Sanctum::actingAs($this->quanTri);

        $rows = $this->getJson('/api/admin/audit-logs?schedule_id=' . $this->chuyen->id)
            ->assertOk()->json('data.data');

        // Lần hủy đơn và lần dời hạn chốt của chính chuyến đó là hai mảnh của một câu chuyện.
        $this->assertCount(2, $rows);
        $this->assertSame(['schedule', 'booking'], array_column($rows, 'source'));
    }

    public function test_phan_trang_dem_dung_ca_hai_bang(): void
    {
        $don = $this->taoDon();

        for ($i = 1; $i <= 5; $i++) {
            $this->ghiNhatKyDon($don, BookingAuditAction::Confirmed, now()->subHours(10 + $i));
        }

        $this->ghiNhatKyChuyen(now()->subHour());

        Sanctum::actingAs($this->quanTri);

        $response = $this->getJson('/api/admin/audit-logs?per_page=2')->assertOk();

        $this->assertSame(6, $response->json('data.meta.total'));
        $this->assertSame(3, $response->json('data.meta.last_page'));
        $this->assertCount(2, $response->json('data.data'));

        $trangHai = $this->getJson('/api/admin/audit-logs?per_page=2&page=2')->assertOk()->json('data.data');
        $this->assertCount(2, $trangHai);
    }

    // --- Lỗi đã vá kèm ------------------------------------------------------------------

    /**
     * Đường khách xin hủy ghi refund_amount từ đầu; đường quản trị hủy thẳng thì không, mà đó lại
     * chính là đường chạm tiền không qua bước duyệt nào.
     *
     * Bậc hoàn tính theo số giờ còn lại tới khởi hành, nên đọc muộn một ngày là ra con số khác.
     * Nhật ký phải giữ đúng con số tại thời điểm bấm hủy.
     */
    public function test_quan_tri_huy_don_thi_nhat_ky_giu_lai_so_tien_hoan(): void
    {
        $don = $this->taoDon();

        Sanctum::actingAs($this->quanTri);

        $this->putJson('/api/admin/bookings/' . $don->id . '/cancel', [
            'cancel_reason' => 'Khach bao khong di duoc nua.',
        ])->assertOk();

        $log = BookingAuditLog::query()
            ->where('booking_id', $don->id)
            ->where('action', BookingAuditAction::Cancelled->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('refund_amount', $log->new_values);
        $this->assertArrayHasKey('refund_percent', $log->new_values);
        $this->assertArrayHasKey('seats_released', $log->new_values);
    }
}
