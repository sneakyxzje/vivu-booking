<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Các cột ngày giờ của lịch khởi hành lưu giờ Việt Nam dưới dạng mộc, trong khi ứng dụng chạy
 * múi giờ UTC. Nếu API trả về ISO8601 kèm hậu tố Z thì trình duyệt ở GMT+7 sẽ cộng thêm 7 tiếng
 * và hiển thị sai toàn bộ giờ khởi hành.
 *
 * Bài này khóa định dạng đó lại, vì lỗi kiểu này không làm đỏ bài test nào khác mà chỉ lộ ra
 * khi có người nhìn vào màn hình.
 */
class ScheduleDateSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ngay_gio_tra_ve_dang_moc_khong_kem_mui_gio(): void
    {
        $tour = Tour::factory()->create(['status' => 'active']);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => '2026-08-20 05:30:00',
            'end_date' => '2026-08-22 23:59:00',
            'booking_deadline' => '2026-08-17 05:30:00',
            'max_people' => 20,
            'min_people' => 8,
            'booked_people' => 0,
        ]);

        $payload = $schedule->fresh()->toArray();

        $this->assertSame('2026-08-20 05:30:00', $payload['start_date']);
        $this->assertSame('2026-08-22 23:59:00', $payload['end_date']);
        $this->assertSame('2026-08-17 05:30:00', $payload['booking_deadline']);

        foreach (['start_date', 'end_date', 'booking_deadline'] as $field) {
            $this->assertStringNotContainsString(
                'T',
                $payload[$field],
                "Trường {$field} không được trả về dạng ISO8601, trình duyệt sẽ hiểu là giờ UTC.",
            );
            $this->assertStringNotContainsString('Z', $payload[$field]);
        }
    }

    public function test_phia_may_chu_van_lam_viec_voi_carbon(): void
    {
        $schedule = (new TourSchedule())->forceFill([
            'start_date' => '2026-08-20 05:30:00',
            'booking_deadline' => '2026-08-17 05:30:00',
        ]);

        // Cast vẫn còn nguyên: so sánh thời gian phía máy chủ không bị ảnh hưởng.
        $this->assertTrue($schedule->booking_deadline->lt($schedule->start_date));
        $this->assertSame(2026, $schedule->start_date->year);
    }
}
