<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\TourSchedule;
use App\Services\BookingHoldService;
use Tests\TestCase;

/**
 * C02 - Quy tắc trả chỗ về kho khi hủy đơn.
 *
 * Câu hỏi số 8 của hội đồng: hủy sát giờ thì có cộng lại slot cho tour không.
 * Câu trả lời là có điều kiện, xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 3.
 *
 * Toàn bộ quyết định chỉ dựa vào booking_deadline của chuyến và việc đơn đã vào danh sách
 * đoàn hay chưa, nên kiểm thử được bằng model chưa lưu, không cần cơ sở dữ liệu.
 */
class SeatReleaseRuleTest extends TestCase
{
    private BookingHoldService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingHoldService::class);
    }

    private function schedule(array $attributes = []): TourSchedule
    {
        return (new TourSchedule())->forceFill(array_merge([
            'id' => 1,
            'tour_id' => 1,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
            'booking_deadline' => now()->addDays(7),
            'max_people' => 20,
            'booked_people' => 4,
        ], $attributes));
    }

    private function booking(array $attributes = []): Booking
    {
        return (new Booking())->forceFill(array_merge([
            'id' => 1,
            'guests' => 2,
            'status' => 'cancelled',
            'paid_at' => null,
            'confirmed_at' => null,
        ], $attributes));
    }

    public function test_don_giu_cho_chua_thanh_toan_thi_luon_tra_cho(): void
    {
        // Đã qua hạn chốt từ lâu, nhưng đơn này chưa bao giờ vào danh sách đoàn.
        $schedule = $this->schedule(['booking_deadline' => now()->subDays(5)]);

        $this->assertTrue(
            $this->service->shouldReleaseSeats($this->booking(), $schedule),
            'Giữ chỗ chưa thanh toán phải được trả về kho, nếu không thì khách vào giữ chỗ '
            . 'rồi bỏ đi cũng làm mất vĩnh viễn một chỗ bán được.',
        );
    }

    public function test_don_da_thanh_toan_huy_truoc_han_chot_thi_tra_cho(): void
    {
        $schedule = $this->schedule(['booking_deadline' => now()->addDays(2)]);
        $booking = $this->booking(['paid_at' => now()->subDay(), 'confirmed_at' => now()->subDay()]);

        $this->assertTrue($this->service->shouldReleaseSeats($booking, $schedule));
    }

    public function test_don_da_thanh_toan_huy_sau_han_chot_thi_khong_tra_cho(): void
    {
        $schedule = $this->schedule(['booking_deadline' => now()->subHour()]);
        $booking = $this->booking(['paid_at' => now()->subDays(3), 'confirmed_at' => now()->subDays(3)]);

        $this->assertFalse(
            $this->service->shouldReleaseSeats($booking, $schedule),
            'Sau hạn chốt, phòng và suất ăn đã đặt theo danh sách. Trả chỗ về kho là bán ra '
            . 'một chỗ không có dịch vụ đi kèm.',
        );
    }

    /**
     * Đơn được quản trị xác nhận tay không có paid_at nhưng vẫn nằm trong danh sách gửi
     * nhà cung cấp, nên phải bị xử lý như đơn đã thanh toán.
     */
    public function test_don_quan_tri_xac_nhan_tay_cung_tinh_la_da_vao_danh_sach(): void
    {
        $schedule = $this->schedule(['booking_deadline' => now()->subHour()]);
        $booking = $this->booking(['paid_at' => null, 'confirmed_at' => now()->subDay()]);

        $this->assertFalse($this->service->shouldReleaseSeats($booking, $schedule));
    }

    /**
     * Chuyến cũ có thể còn trống booking_deadline. Không được vì thế mà bỏ qua quy tắc,
     * phải lấy mốc mặc định để hành vi vẫn nhất quán.
     */
    public function test_chuyen_khong_dat_han_chot_thi_dung_moc_mac_dinh(): void
    {
        $ngayMacDinh = (int) config('booking.booking_deadline_days', 3);

        $daQuaMoc = $this->schedule([
            'booking_deadline' => null,
            'start_date' => now()->addDays($ngayMacDinh - 1),
        ]);

        $chuaQuaMoc = $this->schedule([
            'booking_deadline' => null,
            'start_date' => now()->addDays($ngayMacDinh + 5),
        ]);

        $booking = $this->booking(['confirmed_at' => now()->subDay()]);

        $this->assertFalse($this->service->shouldReleaseSeats($booking, $daQuaMoc));
        $this->assertTrue($this->service->shouldReleaseSeats($booking, $chuaQuaMoc));
    }

    public function test_don_khong_gan_chuyen_thi_khong_co_cho_de_tra(): void
    {
        $this->assertFalse($this->service->shouldReleaseSeats($this->booking(), null));
    }

    /**
     * Ranh giới: đúng thời điểm hạn chốt thì đã coi là quá hạn, không còn trả chỗ.
     */
    public function test_dung_thoi_diem_han_chot_thi_khong_tra_cho(): void
    {
        $schedule = $this->schedule(['booking_deadline' => now()]);
        $booking = $this->booking(['confirmed_at' => now()->subDay()]);

        $this->assertFalse($this->service->shouldReleaseSeats($booking, $schedule));
    }
}
