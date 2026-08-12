<?php

namespace Tests\Feature;

use App\Enums\PassengerCheckinStatus;
use App\Enums\ScheduleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\CheckpointPhoto;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\PassengerCheckinHistory;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * H08 - Chín quy tắc kiểm tra khi điểm danh.
 *
 * Quy tắc và lý do ở docs/nghiep-vu/04-luong-dieu-hanh.md mục 5.3.
 */
class AttendanceRulesTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;
    private User $guide;
    private Tour $tour;
    private TourSchedule $schedule;
    private ItineraryCheckpoint $checkpoint;
    private BookingPassenger $passenger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceService::class);
        $this->guide = $this->taoUser('guide');

        $this->tour = Tour::factory()->create(['status' => 'active', 'number_of_days' => 3]);

        $this->schedule = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'guide_id' => $this->guide->id,
            'status' => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'max_people' => 20,
            'booked_people' => 2,
        ]);

        $itinerary = $this->tour->itineraries()->create([
            'day_number' => 1,
            'title' => 'Ngày 1',
            'content' => 'Khởi hành.',
        ]);

        $this->checkpoint = $itinerary->checkpoints()->create([
            'name' => 'Điểm đón Mỹ Đình',
            'sequence' => 1,
        ]);

        $this->passenger = $this->taoHanhKhach($this->schedule);
    }

    private function taoUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role . '-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function taoHanhKhach(TourSchedule $schedule, string $trangThaiDon = 'confirmed'): BookingPassenger
    {
        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $schedule->tour_id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach-' . Str::random(5) . '@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 1,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 1_000_000,
            'status' => $trangThaiDon,
        ]);

        return BookingPassenger::create([
            'booking_id' => $booking->id,
            'name' => 'Nguyen Van A',
            'type' => 'adult',
        ]);
    }

    // --- Quy tắc 1: chỉ hướng dẫn viên phụ trách ---

    public function test_huong_dan_vien_khong_phu_trach_thi_bi_tu_choi(): void
    {
        $nguoiKhac = $this->taoUser('guide');

        $this->expectException(BusinessRuleException::class);
        $this->service->assertCanRecord($nguoiKhac, $this->schedule, $this->checkpoint);
    }

    public function test_huong_dan_vien_phu_trach_thi_ghi_duoc(): void
    {
        $this->service->assertCanRecord($this->guide, $this->schedule, $this->checkpoint);
        $this->addToAssertionCount(1);
    }

    // --- Quy tắc 2: chuyến phải đang chạy ---

    public function test_chuyen_chua_khoi_hanh_thi_khong_diem_danh_duoc(): void
    {
        $this->schedule->update([
            'status' => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(7),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->service->assertCanRecord($this->guide, $this->schedule->fresh(), $this->checkpoint);
    }

    public function test_chuyen_da_ket_thuc_thi_khong_diem_danh_duoc(): void
    {
        $this->schedule->update([
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(8),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->service->assertCanRecord($this->guide, $this->schedule->fresh(), $this->checkpoint);
    }

    /**
     * Trạng thái lưu trong cơ sở dữ liệu có thể chậm hơn thực tế nếu tác vụ nền chưa chạy.
     * Đoàn đã lên đường thì phải điểm danh được, bất kể cột status ghi gì.
     */
    public function test_van_diem_danh_duoc_khi_tac_vu_nen_chua_kip_doi_trang_thai(): void
    {
        $this->schedule->update(['status' => ScheduleStatus::Confirmed->value]);

        $this->service->assertCanRecord($this->guide, $this->schedule->fresh(), $this->checkpoint);
        $this->addToAssertionCount(1);
    }

    // --- Quy tắc 3: điểm dừng phải thuộc tour của chuyến ---

    public function test_diem_dung_cua_tour_khac_bi_tu_choi(): void
    {
        $tourKhac = Tour::factory()->create(['status' => 'active']);
        $itineraryKhac = $tourKhac->itineraries()->create([
            'day_number' => 1,
            'title' => 'Ngày 1',
            'content' => 'Tour khác.',
        ]);
        $diemDungLa = $itineraryKhac->checkpoints()->create(['name' => 'Điểm lạ', 'sequence' => 1]);

        $this->expectException(BusinessRuleException::class);
        $this->service->assertCanRecord($this->guide, $this->schedule, $diemDungLa);
    }

    // --- Quy tắc 4: không tick trước cho ngày chưa tới ---

    public function test_khong_tick_truoc_cho_ngay_chua_toi(): void
    {
        $itineraryNgay3 = $this->tour->itineraries()->create([
            'day_number' => 3,
            'title' => 'Ngày 3',
            'content' => 'Về Hà Nội.',
        ]);
        $diemDungNgay3 = $itineraryNgay3->checkpoints()->create(['name' => 'Điểm trả khách', 'sequence' => 1]);

        $this->expectException(BusinessRuleException::class);
        $this->service->assertCanRecord($this->guide, $this->schedule, $diemDungNgay3);
    }

    // --- Quy tắc 5: ghi bù muộn thì đánh dấu ---

    public function test_ghi_bu_qua_hai_muoi_tu_gio_thi_danh_dau_la_ghi_muon(): void
    {
        $this->schedule->update([
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDay(),
        ]);

        $checkin = $this->service->record(
            $this->guide,
            $this->schedule->fresh(),
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Present,
        );

        $this->assertTrue($checkin->is_late_entry);
    }

    public function test_ghi_dung_ngay_thi_khong_bi_danh_dau(): void
    {
        $checkin = $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Present,
        );

        $this->assertFalse($checkin->is_late_entry);
    }

    // --- Quy tắc 6: hành khách phải thuộc chuyến, đơn còn hiệu lực ---

    public function test_hanh_khach_cua_chuyen_khac_bi_tu_choi(): void
    {
        $chuyenKhac = TourSchedule::create([
            'tour_id' => $this->tour->id,
            'guide_id' => $this->guide->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(20),
            'max_people' => 10,
            'booked_people' => 1,
        ]);

        $khachLa = $this->taoHanhKhach($chuyenKhac);

        $this->expectException(BusinessRuleException::class);
        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $khachLa,
            PassengerCheckinStatus::Present,
        );
    }

    public function test_hanh_khach_cua_don_da_huy_bi_tu_choi(): void
    {
        $khachDaHuy = $this->taoHanhKhach($this->schedule, trangThaiDon: 'cancelled');

        $this->expectException(BusinessRuleException::class);
        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $khachDaHuy,
            PassengerCheckinStatus::Present,
        );
    }

    // --- Quy tắc 7: trạng thái khác có mặt phải kèm ghi chú ---

    public function test_danh_vang_ma_khong_ghi_chu_thi_bi_tu_choi(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Absent,
        );
    }

    public function test_ghi_chu_qua_ngan_cung_bi_tu_choi(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Absent,
            'chua toi',
        );
    }

    public function test_danh_vang_kem_ghi_chu_day_du_thi_ghi_duoc(): void
    {
        $checkin = $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Absent,
            'Khach bao truoc khong tham gia hoat dong nay, tu di rieng.',
        );

        $this->assertSame(PassengerCheckinStatus::Absent, $checkin->status);
    }

    public function test_co_mat_thi_khong_can_ghi_chu(): void
    {
        $checkin = $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Present,
        );

        $this->assertSame(PassengerCheckinStatus::Present, $checkin->status);
    }

    // --- Quy tắc 8: điểm dừng bắt buộc ảnh ---

    public function test_diem_dung_bat_buoc_anh_ma_chua_co_anh_thi_khong_chot_duoc(): void
    {
        $this->checkpoint->update(['is_required_photo' => true]);

        $this->expectException(BusinessRuleException::class);
        $this->service->assertCheckpointCompletable($this->schedule, $this->checkpoint->fresh());
    }

    public function test_co_anh_roi_thi_chot_duoc(): void
    {
        $this->checkpoint->update(['is_required_photo' => true]);

        CheckpointPhoto::create([
            'tour_schedule_id' => $this->schedule->id,
            'tour_itinerary_id' => $this->checkpoint->tour_itinerary_id,
            'itinerary_checkpoint_id' => $this->checkpoint->id,
            'guide_id' => $this->guide->id,
            'image_path' => 'https://example.com/anh.jpg',
        ]);

        $this->service->assertCheckpointCompletable($this->schedule, $this->checkpoint->fresh());
        $this->addToAssertionCount(1);
    }

    public function test_diem_dung_khong_bat_buoc_anh_thi_chot_duoc_ngay(): void
    {
        $this->service->assertCheckpointCompletable($this->schedule, $this->checkpoint);
        $this->addToAssertionCount(1);
    }

    // --- Quy tắc 9: sửa thì lưu lịch sử ---

    public function test_sua_diem_danh_thi_luu_lich_su_khong_ghi_de_lang_le(): void
    {
        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Present,
        );

        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Absent,
            'Kiem tra lai thi khach khong len xe, da lien he khong duoc.',
        );

        $this->assertSame(1, PassengerCheckin::query()->count(), 'Không được tạo bản ghi thứ hai.');

        $lichSu = PassengerCheckinHistory::query()->first();

        $this->assertNotNull($lichSu, 'Phải lưu lại trạng thái cũ trước khi ghi đè.');
        $this->assertSame('present', $lichSu->old_status);
        $this->assertSame('absent', $lichSu->new_status);
        $this->assertSame($this->guide->id, $lichSu->changed_by);
    }

    public function test_ghi_lai_y_nguyen_thi_khong_sinh_lich_su_thua(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->service->record(
                $this->guide,
                $this->schedule,
                $this->checkpoint,
                $this->passenger,
                PassengerCheckinStatus::Present,
            );
        }

        $this->assertSame(0, PassengerCheckinHistory::query()->count());
    }

    // --- Danh sách chưa điểm danh ---

    public function test_liet_ke_duoc_hanh_khach_chua_diem_danh(): void
    {
        $khachThuHai = $this->taoHanhKhach($this->schedule);

        $this->service->record(
            $this->guide,
            $this->schedule,
            $this->checkpoint,
            $this->passenger,
            PassengerCheckinStatus::Present,
        );

        $conLai = $this->service->pendingPassengers($this->schedule, $this->checkpoint);

        $this->assertCount(1, $conLai);
        $this->assertSame($khachThuHai->id, $conLai->first()->id);
    }
}
