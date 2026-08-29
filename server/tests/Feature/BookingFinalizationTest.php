<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\BookingFinalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * D03 - Chốt đơn khi chuyến đã kết thúc.
 *
 * Phần dễ sai nhất là chiều kết luận: 'no_show' đóng đường hoàn tiền của khách, nên nó chỉ được
 * phép xuất hiện khi có bằng chứng đầy đủ. Phần lớn bài dưới đây kiểm đúng chuyện đó.
 */
class BookingFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $guide;
    private Tour $tour;
    private TourSchedule $schedule;
    private ItineraryCheckpoint $diemDauTien;
    private ItineraryCheckpoint $diemThuHai;

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

    /** Chuyến hai ngày đã kết thúc từ hôm qua, hành trình có hai điểm dừng. */
    private function dungChuyenDaKetThuc(): void
    {
        $admin = $this->taoUser('admin');
        $this->guide = $this->taoUser('guide');

        $this->tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Chot Don',
            'slug' => 'tour-chot-don-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $ngayMot = TourItinerary::create([
            'tour_id' => $this->tour->id,
            'day_number' => 1,
            'title' => 'Ha Noi - Ha Long',
            'content' => 'Khoi hanh.',
        ]);

        $ngayHai = TourItinerary::create([
            'tour_id' => $this->tour->id,
            'day_number' => 2,
            'title' => 'Ha Long - Ha Noi',
            'content' => 'Ve.',
        ]);

        // Tạo điểm của ngày 2 trước để chắc chắn thứ tự "điểm đầu tiên" không ăn may theo id.
        $this->diemThuHai = $ngayHai->checkpoints()->create([
            'name' => 'Diem tra khach',
            'sequence' => 1,
        ]);

        $this->diemDauTien = $ngayMot->checkpoints()->create([
            'name' => 'Diem don My Dinh',
            'sequence' => 1,
        ]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDay(),
            'max_people' => 10,
            'booked_people' => 2,
            'status' => ScheduleStatus::Completed->value,
        ]);

        $this->schedule->guides()->sync([$this->guide->id]);
    }

    /**
     * @param  array<int, string>  $tenHanhKhach
     */
    private function taoDon(array $tenHanhKhach, string $status = 'confirmed'): Booking
    {
        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $this->tour->id,
            'tour_schedule_id' => $this->schedule->id,
            'customer_name' => 'Khach ' . Str::random(4),
            'customer_email' => Str::random(6) . '@example.com',
            'departure_date' => $this->schedule->start_date,
            'guests' => count($tenHanhKhach),
            'adult_count' => count($tenHanhKhach),
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 1000000 * count($tenHanhKhach),
            'status' => $status,
        ]);

        foreach ($tenHanhKhach as $ten) {
            BookingPassenger::create([
                'booking_id' => $booking->id,
                'name' => $ten,
                'type' => 'adult',
            ]);
        }

        return $booking;
    }

    private function diemDanh(
        BookingPassenger $passenger,
        PassengerCheckinStatus $status,
        ?ItineraryCheckpoint $checkpoint = null,
    ): void {
        PassengerCheckin::create([
            'booking_passenger_id' => $passenger->id,
            'tour_schedule_id' => $this->schedule->id,
            'itinerary_checkpoint_id' => ($checkpoint ?? $this->diemDauTien)->id,
            'status' => $status,
            'note' => $status === PassengerCheckinStatus::Present ? null : 'Ghi chu du dai de hop le.',
            'checked_by' => $this->guide->id,
            'checked_at' => now()->subDays(2),
            'is_late_entry' => false,
        ]);
    }

    private function chot(): array
    {
        return app(BookingFinalizationService::class)->finalizeSchedule($this->schedule);
    }

    public function test_khach_co_mat_thi_don_chuyen_sang_da_hoan_thanh(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A']);
        $this->diemDanh($don->passengers()->first(), PassengerCheckinStatus::Present);

        $ketQua = $this->chot();

        $this->assertSame(1, $ketQua['completed']);
        $this->assertSame(0, $ketQua['no_show']);
        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
        $this->assertNotNull($don->fresh()->completed_at);
    }

    public function test_toan_bo_hanh_khach_vang_o_diem_don_thi_don_thanh_khong_co_mat(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A', 'Tran Thi B']);

        foreach ($don->passengers as $hanhKhach) {
            $this->diemDanh($hanhKhach, PassengerCheckinStatus::Absent);
        }

        $ketQua = $this->chot();

        $this->assertSame(1, $ketQua['no_show']);
        $this->assertSame(BookingStatus::NoShow->value, $don->fresh()->status);
    }

    /**
     * Một người lên xe là cả đơn đã tham gia chuyến. Không có khái niệm không có mặt một phần
     * ở tầng đơn; chuyện từng người vắng nằm ở dữ liệu điểm danh.
     */
    public function test_chi_mot_nguoi_vang_thi_don_van_la_da_hoan_thanh(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A', 'Tran Thi B']);

        $hanhKhach = $don->passengers;
        $this->diemDanh($hanhKhach[0], PassengerCheckinStatus::Absent);
        $this->diemDanh($hanhKhach[1], PassengerCheckinStatus::Present);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    /**
     * Bài quan trọng nhất. Hướng dẫn viên chỉ điểm danh một trong hai người rồi bỏ dở; người
     * được ghi thì vắng. Thiếu bằng chứng cho người còn lại nên không được kết luận cả đơn
     * không có mặt - đó là kết luận cắt mất quyền hoàn tiền của khách.
     */
    public function test_diem_danh_thieu_nguoi_thi_khong_ket_luan_khong_co_mat(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A', 'Tran Thi B']);

        $this->diemDanh($don->passengers[0], PassengerCheckinStatus::Absent);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    public function test_khong_diem_danh_gi_ca_thi_van_la_da_hoan_thanh(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A']);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    /** Vắng có phép là đã thống nhất trước với hướng dẫn viên, khác với không tới. */
    public function test_vang_co_phep_khong_tinh_la_khong_co_mat(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A']);
        $this->diemDanh($don->passengers()->first(), PassengerCheckinStatus::Excused);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    /**
     * Vắng ở điểm cuối chỉ nghĩa là khách không tham gia hoạt động đó, không phải không lên đoàn.
     */
    public function test_vang_o_diem_cuoi_khong_bien_don_thanh_khong_co_mat(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A']);
        $hanhKhach = $don->passengers()->first();

        $this->diemDanh($hanhKhach, PassengerCheckinStatus::Present);
        $this->diemDanh($hanhKhach, PassengerCheckinStatus::Absent, $this->diemThuHai);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    public function test_tour_chua_co_diem_dung_nao_thi_van_chot_duoc(): void
    {
        $this->dungChuyenDaKetThuc();
        ItineraryCheckpoint::query()->delete();
        $don = $this->taoDon(['Nguyen Van A']);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    public function test_chuyen_chua_ket_thuc_thi_khong_dung_vao_don(): void
    {
        $this->dungChuyenDaKetThuc();
        $this->schedule->update([
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subHours(2),
            'end_date' => now()->addDay(),
        ]);
        $don = $this->taoDon(['Nguyen Van A']);

        $ketQua = $this->chot();

        $this->assertSame(0, $ketQua['completed']);
        $this->assertSame('confirmed', $don->fresh()->status);
        $this->assertNull($don->fresh()->completed_at);
    }

    /**
     * Trạng thái lưu trong cơ sở dữ liệu có thể chậm hơn đồng hồ khi tác vụ nền bị dừng. Đơn
     * không được nằm treo chỉ vì cột status của chuyến chưa kịp cập nhật.
     */
    public function test_chuyen_qua_gio_ket_thuc_nhung_cot_trang_thai_chua_kip_doi_van_chot_duoc(): void
    {
        $this->dungChuyenDaKetThuc();
        $this->schedule->update(['status' => ScheduleStatus::InProgress->value]);
        $don = $this->taoDon(['Nguyen Van A']);

        $this->chot();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
    }

    public function test_don_da_huy_va_don_chua_thanh_toan_khong_bi_dung_toi(): void
    {
        $this->dungChuyenDaKetThuc();
        $daHuy = $this->taoDon(['Nguyen Van A'], 'cancelled');
        $choThanhToan = $this->taoDon(['Tran Thi B'], 'pending');

        $this->chot();

        $this->assertSame('cancelled', $daHuy->fresh()->status);
        $this->assertSame('pending', $choThanhToan->fresh()->status);
    }

    public function test_chay_lenh_lan_hai_khong_doi_gi_them(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A']);

        $this->artisan('bookings:finalize-completed')->assertSuccessful();

        $chotLanDau = $don->fresh()->completed_at;
        $this->assertNotNull($chotLanDau);
        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);

        $this->artisan('bookings:finalize-completed')->assertSuccessful();

        $this->assertSame(BookingStatus::Completed->value, $don->fresh()->status);
        $this->assertEquals($chotLanDau, $don->fresh()->completed_at);
    }

    /**
     * Cột bookings.status vốn là enum ba giá trị. Bài này đi qua đường ghi thật để chứng minh
     * migration đã nới cột, chứ không phải chỉ enum trong mã biết thêm hai trạng thái.
     */
    public function test_cot_trang_thai_luu_duoc_hai_gia_tri_moi(): void
    {
        $this->dungChuyenDaKetThuc();
        $don = $this->taoDon(['Nguyen Van A']);
        $this->diemDanh($don->passengers()->first(), PassengerCheckinStatus::Absent);

        $this->chot();

        $this->assertDatabaseHas('bookings', [
            'id' => $don->id,
            'status' => BookingStatus::NoShow->value,
        ]);
    }
}
