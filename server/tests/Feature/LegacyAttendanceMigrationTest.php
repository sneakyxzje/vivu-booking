<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * H04 - Chuyển dữ liệu điểm danh cũ sang mô hình theo hành khách và điểm dừng.
 *
 * Bài này gọi thẳng vào tệp migration thật chứ không viết lại logic tương đương, để thứ được
 * kiểm chứng đúng là thứ sẽ chạy trên máy thật.
 */
class LegacyAttendanceMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_08_12_150000_migrate_legacy_booking_checkins.php';

    private User $guide;
    private Tour $tour;
    private TourSchedule $schedule;
    private TourItinerary $itinerary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guide = User::create([
            'name' => 'Guide Test',
            'email' => 'guide-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'guide',
            'status' => 'active',
        ]);

        $this->tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 2]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'guide_id' => $this->guide->id,
            'status' => ScheduleStatus::Completed->value,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(9),
            'max_people' => 20,
            'booked_people' => 4,
        ]);

        $this->itinerary = $this->tour->itineraries()->create([
            'day_number' => 1,
            'title' => 'Ngày 1',
            'content' => 'Khởi hành.',
        ]);
    }

    /** Chạy đúng tệp migration đang nằm trong repo. */
    private function chayMigration(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->up();
    }

    private function taoDon(int $soHanhKhach, string $trangThai = 'confirmed'): Booking
    {
        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $this->schedule->start_date,
            'guests' => $soHanhKhach,
            'adult_count' => $soHanhKhach,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $soHanhKhach * 1_000_000,
            'status' => $trangThai,
        ]);

        for ($i = 1; $i <= $soHanhKhach; $i++) {
            BookingPassenger::create([
                'booking_id' => $booking->id,
                'name' => "Hanh khach {$i}",
                'type' => 'adult',
            ]);
        }

        return $booking;
    }

    private function taoDiemDanhCu(Booking $booking, bool $coMat): void
    {
        DB::table('booking_checkins')->insert([
            'booking_id' => $booking->id,
            'tour_itinerary_id' => $this->itinerary->id,
            'guide_id' => $this->guide->id,
            'present' => $coMat,
            'checked_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
    }

    /**
     * Điểm cốt lõi của việc chuyển đổi: một bản ghi cũ theo đơn phải tách thành nhiều bản ghi
     * mới theo từng hành khách.
     */
    public function test_mot_ban_ghi_cu_tach_thanh_nhieu_ban_ghi_theo_tung_hanh_khach(): void
    {
        $booking = $this->taoDon(soHanhKhach: 4);
        $this->taoDiemDanhCu($booking, coMat: true);

        $this->chayMigration();

        $this->assertSame(4, PassengerCheckin::query()->count());

        foreach ($booking->passengers as $passenger) {
            $this->assertDatabaseHas('passenger_checkins', [
                'booking_passenger_id' => $passenger->id,
                'tour_schedule_id' => $this->schedule->id,
                'status' => 'present',
            ]);
        }
    }

    public function test_sinh_diem_dung_mac_dinh_cho_chang_chua_co(): void
    {
        $booking = $this->taoDon(soHanhKhach: 1);
        $this->taoDiemDanhCu($booking, coMat: true);

        $this->assertSame(0, ItineraryCheckpoint::query()->count());

        $this->chayMigration();

        $this->assertSame(1, ItineraryCheckpoint::query()->count());
        $this->assertSame('Điểm danh trong ngày', ItineraryCheckpoint::query()->first()->name);
    }

    /**
     * Chặng đã được quản trị khai báo điểm dừng thì dùng lại, không sinh thêm điểm dừng trùng.
     */
    public function test_dung_lai_diem_dung_da_co_thay_vi_sinh_trung(): void
    {
        $diemDungCoSan = $this->itinerary->checkpoints()->create([
            'name' => 'Điểm đón Mỹ Đình',
            'sequence' => 1,
        ]);

        $booking = $this->taoDon(soHanhKhach: 2);
        $this->taoDiemDanhCu($booking, coMat: true);

        $this->chayMigration();

        $this->assertSame(1, ItineraryCheckpoint::query()->count());
        $this->assertSame(
            2,
            PassengerCheckin::query()->where('itinerary_checkpoint_id', $diemDungCoSan->id)->count(),
        );
    }

    public function test_vang_mat_duoc_chuyen_kem_ghi_chu_giai_thich_nguon_goc(): void
    {
        $booking = $this->taoDon(soHanhKhach: 1);
        $this->taoDiemDanhCu($booking, coMat: false);

        $this->chayMigration();

        $checkin = PassengerCheckin::query()->first();

        $this->assertSame('absent', $checkin->status->value);
        $this->assertStringContainsString('dữ liệu điểm danh cũ', $checkin->note);
    }

    /**
     * Đơn tạo trước khi có bảng hành khách thì không có ai để gắn bản ghi vào.
     * Bỏ qua chứ không dựng hành khách giả, và dữ liệu gốc vẫn còn nguyên ở bảng cũ.
     */
    public function test_don_khong_co_hanh_khach_thi_bo_qua_va_giu_nguyen_ban_goc(): void
    {
        $booking = $this->taoDon(soHanhKhach: 0);
        $this->taoDiemDanhCu($booking, coMat: true);

        $this->chayMigration();

        $this->assertSame(0, PassengerCheckin::query()->count());
        $this->assertSame(1, DB::table('booking_checkins')->count());
    }

    /** Chạy lại nhiều lần không được nhân đôi dữ liệu. */
    public function test_chay_lai_khong_sinh_ban_ghi_trung(): void
    {
        $booking = $this->taoDon(soHanhKhach: 3);
        $this->taoDiemDanhCu($booking, coMat: true);

        $this->chayMigration();
        $this->chayMigration();
        $this->chayMigration();

        $this->assertSame(3, PassengerCheckin::query()->count());
        $this->assertSame(1, ItineraryCheckpoint::query()->count());
    }

    /** Bản ghi gốc phải còn nguyên để đối chiếu khi có khiếu nại. */
    public function test_khong_xoa_du_lieu_cu(): void
    {
        $booking = $this->taoDon(soHanhKhach: 2);
        $this->taoDiemDanhCu($booking, coMat: true);

        $this->chayMigration();

        $this->assertSame(1, DB::table('booking_checkins')->count());
    }

    public function test_khong_co_du_lieu_cu_thi_khong_lam_gi(): void
    {
        $this->chayMigration();

        $this->assertSame(0, PassengerCheckin::query()->count());
        $this->assertSame(0, ItineraryCheckpoint::query()->count());
    }
}
