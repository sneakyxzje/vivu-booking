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

    public function test_qua_nua_dem_khoa_ngay_cu_nhung_van_xem_duoc_du_lieu(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);
        $today = now()->startOfDay();
        $this->travelTo($today->copy()->endOfDay());
        $entry = ['booking_passenger_id' => $this->passenger->id, 'status' => 'present'];
        $this->guiDiemDanh($entry)->assertOk();
        $saved = PassengerCheckin::firstOrFail()->toArray();

        $this->travelTo($today->copy()->addDay());
        $this->guiDiemDanh([...$entry, 'status' => 'absent', 'note' => 'Sửa lại điểm danh ngày trước.'])
            ->assertStatus(422)->assertJsonPath('message', 'Điểm dừng ngày ' . $today->format('d/m/Y') . ' đã qua, chỉ được xem. Nếu cần đính chính, vui lòng báo điều hành.');
        $this->postJson("/api/guide/schedules/{$this->schedule->id}/checkpoints/{$this->checkpoint->id}/checkin-photo")
            ->assertStatus(422)->assertJsonPath('message', 'Điểm dừng ngày ' . $today->format('d/m/Y') . ' đã qua, chỉ được xem. Nếu cần đính chính, vui lòng báo điều hành.');
        $this->assertSame($saved, PassengerCheckin::firstOrFail()->toArray());
        $this->assertDatabaseCount('passenger_checkin_histories', 0);
        $this->assertDatabaseCount('checkpoint_photos', 0);
        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")->assertOk()
            ->assertJsonPath('data.checkins.0.status', 'present')
            ->assertJsonPath('data.checkpoints.0.attendance_date', $today->toDateString())
            ->assertJsonPath('data.schedule.server_now', now()->toIso8601String());
    }

    public function test_ngay_moi_mo_dung_diem_theo_mui_gio_viet_nam(): void
    {
        $this->dungChuyenDi();
        $this->schedule->update(['start_date' => '2026-10-03 23:00:00', 'end_date' => '2026-10-05 18:00:00']);
        $dayTwo = TourItinerary::create([
            'tour_id' => $this->itinerary->tour_id, 'day_number' => 2, 'title' => 'Ngày 2', 'content' => 'Tham quan.',
        ])->checkpoints()->create(['name' => 'Điểm ngày 2', 'sequence' => 1]);
        Sanctum::actingAs($this->guide);
        $url = "/api/guide/schedules/{$this->schedule->id}/checkpoints/{$dayTwo->id}";
        $payload = ['checkins' => [['booking_passenger_id' => $this->passenger->id, 'status' => 'present']]];

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-03T16:59:59Z'));
        $this->putJson($url . '/attendance', $payload)->assertStatus(422)
            ->assertJsonPath('message', 'Điểm dừng này thuộc ngày 04/10/2026, chưa tới nên chưa điểm danh được.');
        $this->postJson($url . '/checkin-photo')->assertStatus(422)
            ->assertJsonPath('message', 'Điểm dừng này thuộc ngày 04/10/2026, chưa tới nên chưa điểm danh được.');

        // UTC vẫn là 03/10 nhưng Việt Nam đã sang 04/10 lúc 00:00.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-03T17:00:00Z'));
        $this->putJson($url . '/attendance', $payload)->assertOk();
        $payload['checkins'][0]['note'] = 'Đã đối chiếu danh sách trong ngày.';
        $this->putJson($url . '/attendance', $payload)->assertOk();
        $this->assertDatabaseCount('passenger_checkin_histories', 1);
        $this->assertFalse(PassengerCheckin::firstOrFail()->is_late_entry);
    }

    public function test_anh_chi_duoc_ghi_trong_ngay_ke_ca_tai_len_keo_dai_qua_nua_dem(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);
        $midnight = now()->startOfDay()->addDay();
        $uploadCrossesMidnight = false;
        $this->mock(\App\Services\CloudinaryService::class, function ($mock) use (&$uploadCrossesMidnight, $midnight) {
            $mock->shouldReceive('uploadImage')->twice()->andReturnUsing(function () use (&$uploadCrossesMidnight, $midnight) {
                if ($uploadCrossesMidnight) $this->travelTo($midnight);
                return 'https://example.test/checkin.png';
            });
        });
        $url = "/api/guide/schedules/{$this->schedule->id}/checkpoints/{$this->checkpoint->id}/checkin-photo";
        $payload = fn () => [
            'photo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('checkin.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWZkAAAAASUVORK5CYII=')),
        ];
        $this->postJson($url, $payload())->assertOk()
            ->assertJsonPath('data.photo.itinerary_checkpoint_id', $this->checkpoint->id)
            ->assertJsonMissingPath('data.location')
            ->assertJsonMissingPath('data.distance_meters')
            ->assertJsonMissingPath('data.warning');
        $this->assertDatabaseHas('checkpoint_photos', [
            'tour_schedule_id' => $this->schedule->id,
            'itinerary_checkpoint_id' => $this->checkpoint->id,
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->assertDatabaseCount('checkpoint_photos', 1);

        $this->travelTo($midnight->copy()->subSecond());
        $uploadCrossesMidnight = true;
        $this->postJson($url, $payload())->assertStatus(422)
            ->assertJsonPath('message', 'Điểm dừng ngày ' . $midnight->copy()->subDay()->format('d/m/Y') . ' đã qua, chỉ được xem. Nếu cần đính chính, vui lòng báo điều hành.');
        $this->assertDatabaseCount('checkpoint_photos', 1);
    }

    public function test_anh_bo_qua_toa_do_gui_tu_client_cu(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);
        $this->mock(\App\Services\CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('uploadImage')->once()->andReturn('https://example.test/checkin.png');
        });
        $url = "/api/guide/schedules/{$this->schedule->id}/checkpoints/{$this->checkpoint->id}/checkin-photo";
        $this->postJson($url, [
            'photo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('checkin.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWZkAAAAASUVORK5CYII=')),
            'latitude' => 21.0285,
            'longitude' => 105.8542,
        ])->assertOk()->assertJsonMissingPath('data.distance_meters');
        $this->assertDatabaseHas('checkpoint_photos', [
            'itinerary_checkpoint_id' => $this->checkpoint->id,
            'latitude' => null,
            'longitude' => null,
        ]);
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
    public function test_khong_duoc_ghi_bu_cho_ngay_da_qua(): void
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
        ])->assertStatus(422);

        $this->assertDatabaseCount('passenger_checkins', 0);
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

    public function test_chuyen_ket_thuc_van_xem_duoc_nhung_khong_sua_hoac_them_anh(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);
        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => 'present',
        ])->assertOk();

        // Scheduler chưa đổi trạng thái: vẫn phải khóa ngay khi thời gian chuyến đã hết.
        $this->schedule->update(['end_date' => now()->subMinute()]);
        $this->booking->update(['status' => 'completed']);
        $this->getJson("/api/guide/schedules/{$this->schedule->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.schedule.status', 'completed')
            ->assertJsonPath('data.schedule.can_record', false)
            ->assertJsonPath('data.checkins.0.status', 'present')
            ->assertJsonPath('data.bookings.0.passengers.0.name', 'Nguyen Van A');

        $this->guiDiemDanh([
            'booking_passenger_id' => $this->passenger->id,
            'status' => 'absent',
            'note' => 'Khong duoc sua sau khi ket thuc.',
        ])->assertStatus(422);
        $this->postJson("/api/guide/schedules/{$this->schedule->id}/checkpoints/{$this->checkpoint->id}/checkin-photo")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ điểm danh được khi đoàn đang đi. Chuyến này đang ở trạng thái "Đã kết thúc".');
        $this->assertSame('present', PassengerCheckin::query()->first()->status->value);
        $this->assertDatabaseCount('checkpoint_photos', 0);
        $this->assertDatabaseCount('passenger_checkin_histories', 0);
    }

    public function test_tour_va_chuyen_moi_nhat_len_dau_van_giu_chuyen_cu(): void
    {
        $this->dungChuyenDi();
        Sanctum::actingAs($this->guide);
        $tour = $this->schedule->tour;
        $tour->update(['created_at' => now()->addHour()]);
        $newTour = Tour::factory()->create();
        $older = TourSchedule::create([
            'tour_id' => $tour->id, 'start_date' => now()->subDays(8),
            'end_date' => now()->subDays(7), 'max_people' => 10,
            'booked_people' => 0, 'status' => 'completed',
        ]);
        $newer = TourSchedule::create([
            'tour_id' => $newTour->id, 'start_date' => now()->addDays(8),
            'end_date' => now()->addDays(9), 'max_people' => 10,
            'booked_people' => 0, 'status' => 'open',
        ]);
        $older->guides()->sync([$this->guide->id]);
        $newer->guides()->sync([$this->guide->id]);
        // Chuyến rất mới nhưng không phân công không được ảnh hưởng thứ tự hay lộ dữ liệu.
        TourSchedule::create([
            'tour_id' => $tour->id, 'start_date' => now()->addDays(30),
            'max_people' => 10, 'booked_people' => 0, 'status' => 'open',
        ]);

        $this->getJson('/api/guide/my-tours')->assertOk()
            ->assertJsonPath('data.0.id', $newTour->id)
            ->assertJsonPath('data.1.id', $tour->id)
            ->assertJsonCount(2, 'data.1.schedules')
            ->assertJsonPath('data.1.schedules.0.id', $this->schedule->id)
            ->assertJsonPath('data.1.schedules.1.id', $older->id)
            ->assertJsonPath('data.1.schedules.1.effective_status', 'completed');
        $this->getJson("/api/guide/schedules/{$older->id}/attendance")->assertOk()
            ->assertJsonPath('data.schedule.can_record', false);
    }
}
