<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Mail\DepartureReminderMail;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartureReminderTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Dieu Hanh',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Nhac Lich',
            'slug' => 'tour-nhac-lich-' . Str::random(5),
            'adult_price' => 2_000_000,
            'child_price' => 1_400_000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'pickup_location' => '19 Nguyen Trai, Thanh Xuan',
            'status' => 'active',
        ]);
    }

    private function taoDon(int $ngayNua, string $trangThai = 'confirmed', array $ghiDeChuyen = []): Booking
    {
        $schedule = TourSchedule::create(array_merge([
            'tour_id' => $this->tour->id,
            'start_date' => now()->addDays($ngayNua),
            'end_date' => now()->addDays($ngayNua + 1),
            'max_people' => 20,
            'min_people' => 1,
            'booked_people' => 2,
            'status' => ScheduleStatus::Confirmed->value,
        ], $ghiDeChuyen));

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Di Tour',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 4_000_000,
            'status' => $trangThai,
            'paid_at' => $trangThai === 'confirmed' ? now() : null,
        ]);
    }

    public function test_gui_thu_nhac_cho_don_sap_khoi_hanh(): void
    {
        Mail::fake();
        $don = $this->taoDon(2);

        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        Mail::assertQueued(
            DepartureReminderMail::class,
            fn (DepartureReminderMail $thu) => $thu->hasTo($don->customer_email),
        );
        $this->assertNotNull($don->fresh()->departure_reminder_sent_at);
    }

    public function test_chuyen_con_xa_thi_chua_nhac(): void
    {
        Mail::fake();
        $this->taoDon(30);

        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_moi_don_chi_nhan_dung_mot_thu(): void
    {
        Mail::fake();
        $this->taoDon(2);

        // Lệnh chạy mỗi ngày và quét cả khoảng ngày, nên không có mốc đã gửi thì cùng một khách
        // nhận thư giống hệt mỗi ngày cho tới lúc lên xe.
        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();
        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        Mail::assertQueuedCount(1);
    }

    public function test_don_chua_tra_tien_khong_duoc_nhac(): void
    {
        Mail::fake();
        // Chỗ của họ có thể đã bị nhả từ lâu; nhắc họ ra bến là mời tới một chuyến không có tên mình.
        $this->taoDon(2, 'pending');

        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_chuyen_da_huy_thi_khong_nhac(): void
    {
        Mail::fake();
        $this->taoDon(2, 'confirmed', ['status' => ScheduleStatus::Cancelled->value]);

        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_chuyen_da_khoi_hanh_thi_khong_nhac(): void
    {
        Mail::fake();
        $this->taoDon(-1, 'confirmed', ['status' => ScheduleStatus::InProgress->value]);

        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_thu_nhac_mang_ten_va_so_dien_thoai_huong_dan_vien(): void
    {
        Mail::fake();
        $don = $this->taoDon(2);

        $guide = User::create([
            'name' => 'Huong Dan Vien A',
            'email' => 'hdv-' . Str::random(5) . '@example.com',
            'password' => Hash::make('password123'),
            'phone' => '0901234567',
            'role' => 'guide',
            'status' => 'active',
        ]);
        $don->schedule->guides()->attach($guide->id);

        $this->artisan('bookings:send-departure-reminders')->assertSuccessful();

        // Hướng dẫn viên chỉ được phân công gần ngày đi, nên thư đặt tour không thể mang thông tin
        // này — đây là thư đầu tiên nói được ai dẫn đoàn.
        Mail::assertQueued(DepartureReminderMail::class, function (DepartureReminderMail $thu) use ($guide) {
            return $thu->booking->schedule->guides->contains('id', $guide->id);
        });
    }
}
