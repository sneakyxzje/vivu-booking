<?php

namespace Tests\Feature;

use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\PassengerCheckinHistory;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Điểm danh qua API của hướng dẫn viên, mô hình theo từng hành khách tại từng điểm dừng.
 *
 * Bộ này đi qua HTTP nên kiểm chứng được rằng đường API thật cũng chịu đủ chín quy tắc,
 * chứ không chỉ tầng dịch vụ. AttendanceRulesTest kiểm tra quy tắc ở tầng dưới; nếu controller
 * ghi thẳng vào model thì bộ đó vẫn xanh trong khi đường thật đã thủng.
 */
class GuideAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $guide;
    private TourSchedule $schedule;
    private TourItinerary $itinerary;
    private ItineraryCheckpoint $checkpoint;
    private Booking $booking;
    private BookingPassenger $passenger;

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

    /** Chuyến đang chạy, vì quy tắc 2 chỉ cho điểm danh khi đoàn đã lên đường. */
    private function dungChuyenDi(): void
    {
        // Đóng băng đồng hồ vào giữa trưa trước khi dựng dữ liệu.
        //
        // Quy tắc 4 so theo NGÀY chứ không theo giờ. Chuyến khởi hành "hai tiếng trước" mà chạy
        // vào lúc 00:30 thì mốc khởi hành rơi sang hôm qua, kéo theo điểm dừng của ngày thứ hai
        // rơi vào hôm nay, và bài kiểm tick trước sẽ xanh sai. Ứng dụng chạy giờ UTC nên khe
        // hỏng là 07:00-09:00 giờ Việt Nam, đúng lúc hay ngồi vào máy nhất.
        $this->travelTo(now()->startOfDay()->addHours(12));

        $admin = $this->taoUser('admin');
        $this->guide = $this->taoUser('guide');

        $tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Diem Danh',
            'slug' => 'tour-diem-danh-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $this->itinerary = TourItinerary::create([
            'tour_id' => $tour->id,
            'day_number' => 1,
            'title' => 'Ha Noi - Ha Long',
            'start_point' => 'Ha Noi',
            'end_point' => 'Ha Long',
            'content' => 'Khoi hanh va tham quan vinh',
        ]);

        $this->checkpoint = $this->itinerary->checkpoints()->create([
            'name' => 'Diem don My Dinh',
            'sequence' => 1,
        ]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'start_date' => now()->subHours(2),
            'end_date' => now()->addDay(),
            'max_people' => 10,
            'booked_people' => 2,
            'status' => ScheduleStatus::InProgress->value,
        ]);

        $this->schedule->guides()->sync([$this->guide->id]);

        $this->booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Khach Diem Danh',
            'customer_email' => 'diemdanh@example.com',
            'departure_date' => $this->schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 2000000,
            'status' => 'confirmed',
        ]);

        $this->passenger = BookingPassenger::create([
            'booking_id' => $this->booking->id,
            'name' => 'Nguyen Van A',
            'type' => 'adult',
        ]);
    }

    private function guiDiemDanh(array $entry)
    {
        return $this->putJson(
            "/api/guide/schedules/{$this->schedule->id}/checkpoints/{$this->checkpoint->id}/attendance",
            ['checkins' => [$entry]],
        );
    }

    public function test_guide_xem_duoc_du_lieu_diem_danh_cua_lich_duoc_phan_cong(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.tour.title', 'Tour Diem Danh')
            ->assertJsonPath('data.bookings.0.customer_name', 'Khach Diem Danh')
            ->assertJsonPath('data.checkpoints.0.name', 'Diem don My Dinh');
    }

    public function test_guide_khac_khong_xem_duoc_lich_khong_duoc_phan_cong(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->taoUser('guide'));

        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")
            ->assertStatus(404);
    }

    public function test_guide_luu_duoc_diem_danh_tai_mot_diem_dung(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Present->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);

        $checkin = PassengerCheckin::query()->first();

        $this->assertNotNull($checkin);
        $this->assertSame(PassengerCheckinStatus::Present, $checkin->status);
        $this->assertSame($this->schedule->id, (int) $checkin->tour_schedule_id);
        $this->assertSame($this->guide->id, (int) $checkin->checked_by);
    }

    public function test_hanh_khach_cua_don_chua_xac_nhan_thi_bi_bo_qua(): void
    {
        $this->dungChuyenDi();
        $this->booking->update(['status' => 'pending']);
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Present->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.saved', 0);

        $this->assertSame(0, PassengerCheckin::query()->count());
    }

    // --- Bốn quy tắc từng bị mất khi controller tự ghi thẳng vào model ---

    /**
     * Quy tắc 2. Trước khi controller gọi qua AttendanceService, hướng dẫn viên điểm danh
     * được cho cả chuyến chưa khởi hành.
     */
    public function test_chuyen_chua_khoi_hanh_thi_api_tu_choi(): void
    {
        $this->dungChuyenDi();
        $this->schedule->update([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(6),
        ]);
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Present->value,
        ])->assertStatus(422);

        $this->assertSame(0, PassengerCheckin::query()->count());
    }

    /**
     * Quy tắc 4. Điểm dừng của ngày mai thì hôm nay chưa được tick.
     */
    public function test_khong_tick_truoc_cho_diem_dung_ngay_chua_toi(): void
    {
        $this->dungChuyenDi();

        $ngayHai = TourItinerary::create([
            'tour_id' => $this->itinerary->tour_id,
            'day_number' => 2,
            'title' => 'Ha Long - Ha Noi',
            'content' => 'Tra phong va ve.',
        ]);
        $diemDungNgayHai = $ngayHai->checkpoints()->create([
            'name' => 'Diem tra khach',
            'sequence' => 1,
        ]);

        Sanctum::actingAs($this->guide);

        $this->putJson(
            "/api/guide/schedules/{$this->schedule->id}/checkpoints/{$diemDungNgayHai->id}/attendance",
            ['checkins' => [[
                'booking_passenger_id' => $this->passenger->id,
                'status' => PassengerCheckinStatus::Present->value,
            ]]],
        )->assertStatus(422);

        $this->assertSame(0, PassengerCheckin::query()->count());
    }

    /**
     * Quy tắc 7. Ghi chú phải đủ dài mới có ý nghĩa khi đọc lại; controller cũ chỉ chặn rỗng.
     */
    public function test_ghi_chu_qua_ngan_khi_danh_vang_thi_bi_tu_choi(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Absent->value,
            'note' => 'vang',
        ])->assertStatus(422);

        $this->assertSame(0, PassengerCheckin::query()->count());
    }

    public function test_danh_vang_kem_ghi_chu_day_du_thi_luu_duoc(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Absent->value,
            'note' => 'Khach bao truoc khong tham gia, tu di rieng.',
        ])->assertOk();

        $this->assertSame(
            PassengerCheckinStatus::Absent,
            PassengerCheckin::query()->first()->status,
        );
    }

    /**
     * Quy tắc 5. Ghi bù sau hơn một ngày vẫn cho ghi nhưng phải đánh dấu, để truy vết được.
     */
    public function test_ghi_bu_muon_thi_duoc_danh_dau(): void
    {
        $this->dungChuyenDi();
        $this->schedule->update([
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDay(),
        ]);
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Present->value,
        ])->assertOk();

        $this->assertTrue(PassengerCheckin::query()->first()->is_late_entry);
    }

    /**
     * Quy tắc 9. Sửa điểm danh phải để lại dấu vết, vì đây là dữ liệu đối chiếu khi khiếu nại.
     */
    public function test_sua_diem_danh_qua_api_thi_luu_lich_su(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Present->value,
        ])->assertOk();

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Absent->value,
            'note' => 'Kiem tra lai thi khach khong len xe.',
        ])->assertOk();

        $this->assertSame(1, PassengerCheckin::query()->count());

        $lichSu = PassengerCheckinHistory::query()->first();

        $this->assertNotNull($lichSu);
        $this->assertSame('present', $lichSu->old_status);
        $this->assertSame('absent', $lichSu->new_status);
    }

    /**
     * Từ D03, đơn của chuyến đã đi xong chuyển sang 'completed'. Danh sách đoàn phải giữ nguyên
     * người: đây là dữ liệu để đối chiếu khi khách khiếu nại sau chuyến, mà khiếu nại thì luôn
     * tới sau khi chuyến đã kết thúc.
     */
    public function test_danh_sach_doan_khong_bien_mat_khi_don_da_chot_sau_chuyen(): void
    {
        $this->dungChuyenDi();
        $this->booking->update(['status' => 'completed']);
        Sanctum::actingAs($this->guide);

        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.bookings.0.customer_name', 'Khach Diem Danh')
            ->assertJsonPath('data.bookings.0.passengers.0.name', 'Nguyen Van A');
    }

    public function test_guide_khac_khong_diem_danh_duoc(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->taoUser('guide'));

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => PassengerCheckinStatus::Present->value,
        ])->assertStatus(404);

        $this->assertSame(0, PassengerCheckin::query()->count());
    }
}
