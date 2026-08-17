<?php

namespace Tests\Feature;

use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\CheckpointPhoto;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * H13a — API Báo cáo điểm danh (tài liệu 04 §5.5).
 *
 * Kiểm tra hai endpoint:
 *   GET /api/admin/attendance-reports       — báo cáo tổng hợp
 *   GET /api/admin/schedules/{id}/attendance-report  — báo cáo chi tiết 1 chuyến
 */
class AdminAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tour $tour;
    private TourSchedule $schedule;
    private TourItinerary $itinerary;
    private ItineraryCheckpoint $checkpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->taoUser('admin');

        $guide = $this->taoUser('guide');

        $this->tour = Tour::factory()->create([
            'status'         => 'active',
            'number_of_days' => 2,
        ]);

        $this->schedule = TourSchedule::create([
            'tour_id'      => $this->tour->id,
            'status'       => ScheduleStatus::Completed->value,
            'start_date'   => now()->subDays(3),
            'end_date'     => now()->subDay(),
            'max_people'   => 20,
            'booked_people' => 2,
        ]);

        $this->schedule->guides()->sync([$guide->id]);

        $this->itinerary = $this->tour->itineraries()->create([
            'day_number' => 1,
            'title'      => 'Ngày 1',
            'content'    => 'Khởi hành sáng sớm.',
        ]);

        $this->checkpoint = $this->itinerary->checkpoints()->create([
            'name'             => 'Điểm đón sân bay',
            'sequence'         => 1,
            'is_required_photo' => false,
            'expected_at'      => '07:00',
        ]);
    }

    // ─── Test 1: Cấu trúc response đầy đủ ──────────────────────────────────

    public function test_schedule_report_tra_ve_dung_cau_truc(): void
    {
        $passenger = $this->taoHanhKhach($this->schedule);
        $this->taoCheckin($passenger, $this->checkpoint, PassengerCheckinStatus::Present);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/schedules/{$this->schedule->id}/attendance-report");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'schedule' => ['id', 'start_date', 'end_date', 'status', 'tour', 'guides'],
                    'summary'  => [
                        'total_passengers', 'fully_present', 'had_absent',
                        'presence_rate', 'late_entry_count', 'missing_photo_checkpoints',
                    ],
                    'by_checkpoint',
                    'absent_passengers',
                    'late_entries',
                ],
            ]);
    }

    // ─── Test 2: Tỷ lệ có mặt tính đúng ────────────────────────────────────

    public function test_ti_le_co_mat_tinh_dung_theo_passenger_checkins(): void
    {
        $p1 = $this->taoHanhKhach($this->schedule);
        $p2 = $this->taoHanhKhach($this->schedule);

        $this->taoCheckin($p1, $this->checkpoint, PassengerCheckinStatus::Present);
        $this->taoCheckin($p2, $this->checkpoint, PassengerCheckinStatus::Absent, 'Khach bao om, khong tham gia.');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/schedules/{$this->schedule->id}/attendance-report");

        $response->assertOk();
        $byCheckpoint = $response->json('data.by_checkpoint.0');

        $this->assertSame(1, $byCheckpoint['present']);
        $this->assertSame(1, $byCheckpoint['absent']);
        $this->assertEquals(50.0, $byCheckpoint['presence_rate']);
    }

    // ─── Test 3: absent_passengers chứa đúng dữ liệu ───────────────────────

    public function test_absent_passengers_chi_chua_trang_thai_khac_present(): void
    {
        $p1 = $this->taoHanhKhach($this->schedule);
        $p2 = $this->taoHanhKhach($this->schedule);

        $this->taoCheckin($p1, $this->checkpoint, PassengerCheckinStatus::Present);
        $this->taoCheckin($p2, $this->checkpoint, PassengerCheckinStatus::Absent, 'Khach bao truoc khong toi.');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/schedules/{$this->schedule->id}/attendance-report");

        $response->assertOk();
        $absentList = $response->json('data.absent_passengers');

        $this->assertCount(1, $absentList);
        $this->assertSame('absent', $absentList[0]['status']);
        $this->assertNotEmpty($absentList[0]['note']);
    }

    // ─── Test 4: late_entries chứa đúng bản ghi is_late_entry = true ────────

    public function test_late_entries_chua_dung_ban_ghi_co_is_late_entry_true(): void
    {
        $passenger = $this->taoHanhKhach($this->schedule);

        // Tạo bản ghi điểm danh thông thường
        PassengerCheckin::create([
            'booking_passenger_id'     => $passenger->id,
            'tour_schedule_id'         => $this->schedule->id,
            'itinerary_checkpoint_id'  => $this->checkpoint->id,
            'status'                   => PassengerCheckinStatus::Present->value,
            'checked_at'               => now(),
            'is_late_entry'            => false,
            'checked_by'               => $this->admin->id,
        ]);

        // Tạo bản ghi ghi bù muộn
        $passenger2 = $this->taoHanhKhach($this->schedule);
        PassengerCheckin::create([
            'booking_passenger_id'     => $passenger2->id,
            'tour_schedule_id'         => $this->schedule->id,
            'itinerary_checkpoint_id'  => $this->checkpoint->id,
            'status'                   => PassengerCheckinStatus::Present->value,
            'checked_at'               => now(),
            'is_late_entry'            => true,
            'checked_by'               => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/schedules/{$this->schedule->id}/attendance-report");

        $response->assertOk();
        $lateEntries = $response->json('data.late_entries');

        $this->assertCount(1, $lateEntries);
        $this->assertArrayHasKey('delay_hours', $lateEntries[0]);
    }

    // ─── Test 5: missing_photo_checkpoints đếm đúng ─────────────────────────

    public function test_missing_photo_checkpoints_dem_dung(): void
    {
        // Tạo checkpoint bắt buộc ảnh
        $cpWithPhoto = $this->itinerary->checkpoints()->create([
            'name'             => 'Điểm bắt buộc ảnh - có ảnh',
            'sequence'         => 2,
            'is_required_photo' => true,
        ]);

        $cpNoPhoto = $this->itinerary->checkpoints()->create([
            'name'             => 'Điểm bắt buộc ảnh - chưa có ảnh',
            'sequence'         => 3,
            'is_required_photo' => true,
        ]);

        // Upload ảnh cho cpWithPhoto nhưng không upload cho cpNoPhoto
        CheckpointPhoto::create([
            'tour_schedule_id'         => $this->schedule->id,
            'tour_itinerary_id'        => $this->itinerary->id,
            'itinerary_checkpoint_id'  => $cpWithPhoto->id,
            'guide_id'                 => $this->admin->id,
            'image_path'               => 'https://example.com/anh.jpg',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/schedules/{$this->schedule->id}/attendance-report");

        $response->assertOk();
        $this->assertSame(1, $response->json('data.summary.missing_photo_checkpoints'));
    }

    // ─── Test 6: Schedule không tồn tại trả về 404 ──────────────────────────

    public function test_schedule_khong_ton_tai_tra_ve_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/schedules/99999/attendance-report')
            ->assertStatus(404);
    }

    // ─── Test 7: report() dùng PassengerCheckin, trả về by_checkpoint ───────

    public function test_attendance_reports_tra_ve_du_lieu_tu_passenger_checkins(): void
    {
        $passenger = $this->taoHanhKhach($this->schedule);
        $this->taoCheckin($passenger, $this->checkpoint, PassengerCheckinStatus::Present);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/attendance-reports');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'overall_presence_rate',
                        'total_checkins',
                        'total_present',
                        'total_absent',
                        'late_entry_count',
                        'missing_photos_count',
                    ],
                    'schedules' => [
                        'data' => [['id', 'present_count', 'absent_count', 'late_entry_count', 'presence_rate']],
                    ],
                ],
            ]);

        // KPI phải đọc từ PassengerCheckin - có 1 bản ghi present
        $this->assertSame(1, $response->json('data.kpis.total_present'));
        $this->assertSame(1, $response->json('data.kpis.total_checkins'));
    }

    /**
     * Màn báo cáo có tab Nhật ký vắng mặt đọc thẳng absence_logs.
     *
     * Trước đây phản hồi không có khóa này, giao diện đọc undefined.length và cả trang vỡ ngay
     * khi mở. Bộ kiểm cũ chỉ soi kpis và schedules nên không thấy gì. Bài này khóa nốt khóa còn
     * lại của hợp đồng.
     */
    public function test_bao_cao_tra_ve_nhat_ky_vang_mat(): void
    {
        $coMat = $this->taoHanhKhach($this->schedule);
        $vangMat = $this->taoHanhKhach($this->schedule);

        $this->taoCheckin($coMat, $this->checkpoint, PassengerCheckinStatus::Present);
        $this->taoCheckin(
            $vangMat,
            $this->checkpoint,
            PassengerCheckinStatus::Absent,
            'Goi ba lan khong nghe may, xe phai roi diem don.',
        );

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/attendance-reports')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'absence_logs' => [[
                        'id',
                        'booking_id',
                        'passenger_name',
                        'customer_name',
                        'customer_phone',
                        'day_number',
                        'itinerary_title',
                        'checkpoint_name',
                        'status',
                        'status_label',
                        'note',
                        'checked_at',
                        'guide_name',
                    ]],
                ],
            ]);

        $logs = $response->json('data.absence_logs');

        $this->assertCount(1, $logs, 'Chỉ người vắng mới vào nhật ký, người có mặt thì không.');
        $this->assertSame('absent', $logs[0]['status']);
        $this->assertSame($vangMat->name, $logs[0]['passenger_name']);
    }

    /** Không có ai vắng thì trả mảng rỗng, không phải thiếu khóa. */
    public function test_khong_co_ai_vang_thi_nhat_ky_la_mang_rong(): void
    {
        $this->taoCheckin(
            $this->taoHanhKhach($this->schedule),
            $this->checkpoint,
            PassengerCheckinStatus::Present,
        );

        $this->actingAs($this->admin)
            ->getJson('/api/admin/attendance-reports')
            ->assertOk()
            ->assertJsonCount(0, 'data.absence_logs');
    }

    // ─── Test 8: Bộ lọc from_date, to_date, status, search ─────────────────

    public function test_bo_loc_from_date_va_to_date_hoat_dong_dung(): void
    {
        // Chuyến ngoài khoảng lọc
        TourSchedule::create([
            'tour_id'      => $this->tour->id,
            'status'       => ScheduleStatus::Completed->value,
            'start_date'   => now()->subMonths(3),
            'end_date'     => now()->subMonths(3)->addDay(),
            'max_people'   => 10,
            'booked_people' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/attendance-reports?from_date=' . now()->subDays(7)->toDateString()
                . '&to_date=' . now()->toDateString());

        $response->assertOk();
        // Chỉ trả về chuyến nằm trong khoảng lọc (schedule hiện tại)
        $this->assertSame(1, $response->json('data.schedules.total'));
    }

    // ─── Test 9: màn xem lại điểm danh của một chuyến ───────────────────────

    /**
     * Endpoint này chưa từng có bài kiểm nào, nên khi mô hình điểm danh đổi sang từng hành khách
     * tại từng điểm dừng thì nó lặng lẽ trả thiếu khóa mà không bài nào đỏ. Giao diện quản trị
     * đọc `guests` và `photos[].tour_itinerary_id`, cả hai đều không còn trong phản hồi.
     */
    public function test_xem_lai_diem_danh_tra_ve_du_diem_dung_va_danh_sach_doan(): void
    {
        $passenger = $this->taoHanhKhach($this->schedule);
        $this->taoCheckin($passenger, $this->checkpoint, PassengerCheckinStatus::Present);

        CheckpointPhoto::create([
            'tour_schedule_id'        => $this->schedule->id,
            'tour_itinerary_id'       => $this->itinerary->id,
            'itinerary_checkpoint_id' => $this->checkpoint->id,
            'guide_id'                => $this->schedule->guides->first()?->id,
            'image_path'              => 'https://example.com/anh.jpg',
            'captured_at'             => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/tour-schedules/{$this->schedule->id}/attendance");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'schedule',
                    'tour',
                    'itineraries',
                    'checkpoints' => [['id', 'name', 'sequence', 'is_required_photo', 'tour_itinerary']],
                    'bookings' => [['id', 'customer_name', 'passengers' => [['id', 'name', 'type']]]],
                    'total_passengers',
                    'checkins' => [['booking_passenger_id', 'itinerary_checkpoint_id', 'status']],
                    'photos' => [['id', 'tour_itinerary_id', 'itinerary_checkpoint_id', 'image_path']],
                ],
            ])
            ->assertJsonPath('data.checkpoints.0.id', $this->checkpoint->id)
            ->assertJsonPath('data.bookings.0.passengers.0.id', $passenger->id)
            ->assertJsonPath('data.checkins.0.status', 'present');
    }

    /**
     * Điểm dừng chưa ai điểm danh vẫn phải xuất hiện. Đó chính là chỗ điều hành cần nhìn thấy:
     * một điểm bị bỏ quên hoàn toàn trông y hệt một điểm không tồn tại nếu suy danh sách ngược
     * từ các bản ghi đã có.
     */
    public function test_diem_dung_chua_ai_diem_danh_van_nam_trong_phan_hoi(): void
    {
        $diemChuaGhi = $this->itinerary->checkpoints()->create([
            'name'     => 'Diem tham quan buoi chieu',
            'sequence' => 2,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/tour-schedules/{$this->schedule->id}/attendance");

        $response->assertOk();

        $ids = array_column($response->json('data.checkpoints'), 'id');

        $this->assertContains($diemChuaGhi->id, $ids);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function taoUser(string $role): User
    {
        return User::create([
            'name'     => ucfirst($role),
            'email'    => $role . '-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => $role,
            'status'   => 'active',
        ]);
    }

    private function taoHanhKhach(TourSchedule $schedule, string $status = 'confirmed'): BookingPassenger
    {
        $booking = Booking::create([
            'public_token'     => (string) Str::uuid(),
            'tour_id'          => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name'    => 'Khach Test ' . Str::random(4),
            'customer_email'   => 'khach-' . Str::random(5) . '@example.com',
            'departure_date'   => $schedule->start_date,
            'guests'           => 1,
            'adult_count'      => 1,
            'child_count'      => 0,
            'infant_count'     => 0,
            'total_amount'     => 1_000_000,
            'status'           => $status,
        ]);

        return BookingPassenger::create([
            'booking_id' => $booking->id,
            'name'       => 'Nguyen Van Test',
            'type'       => 'adult',
        ]);
    }

    private function taoCheckin(
        BookingPassenger $passenger,
        ItineraryCheckpoint $checkpoint,
        PassengerCheckinStatus $status,
        ?string $note = null,
        bool $lateEntry = false
    ): PassengerCheckin {
        return PassengerCheckin::create([
            'booking_passenger_id'    => $passenger->id,
            'tour_schedule_id'        => $passenger->booking->tour_schedule_id,
            'itinerary_checkpoint_id' => $checkpoint->id,
            'status'                  => $status->value,
            'note'                    => $note,
            'checked_at'              => now(),
            'is_late_entry'           => $lateEntry,
            'checked_by'              => $this->admin->id,
        ]);
    }
}
