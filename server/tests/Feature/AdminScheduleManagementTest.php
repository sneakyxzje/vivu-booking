<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests cho A10 — API quản trị tạo và sửa chuyến khởi hành.
 *
 * Tài liệu: docs/nghiep-vu/11-backlog-trien-khai.md (A10, A13)
 */
class AdminScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tour $tour;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->token = $this->admin->createToken('test')->plainTextToken;

        $this->tour = Tour::factory()->create([
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'admin_id' => $this->admin->id,
            'status' => 'active',
        ]);
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── Tạo chuyến ─────────────────────────────────────────────────────────

    /** TC01: Tạo chuyến có min_people và booking_deadline — DB lưu đúng. */
    public function test_create_schedule_with_min_people_and_booking_deadline(): void
    {
        $startDate = now()->addDays(30)->format('Y-m-d');

        $response = $this->putJson("/api/admin/tours/{$this->tour->id}", array_merge(
            $this->baseTourPayload(),
            [
                'schedules' => [[
                    'start_date'       => $startDate,
                    'max_people'       => 20,
                    'min_people'       => 8,
                    'booking_deadline' => now()->addDays(25)->format('Y-m-d'),
                ]],
            ]
        ), $this->authHeader());

        $response->assertOk();

        $this->assertDatabaseHas('tour_schedules', [
            'tour_id'    => $this->tour->id,
            'max_people' => 20,
            'min_people' => 8,
        ]);
    }

    /** TC02: Không truyền booking_deadline → tự tính = start_date - 3 ngày. */
    public function test_booking_deadline_defaults_to_three_days_before_start(): void
    {
        $startDate = now()->addDays(30)->startOfDay();

        $response = $this->putJson("/api/admin/tours/{$this->tour->id}", array_merge(
            $this->baseTourPayload(),
            [
                'schedules' => [[
                    'start_date' => $startDate->format('Y-m-d'),
                    'max_people' => 20,
                ]],
            ]
        ), $this->authHeader());

        $response->assertOk();

        $schedule = TourSchedule::where('tour_id', $this->tour->id)->latest('id')->first();
        $this->assertNotNull($schedule->booking_deadline);

        $expected = $startDate->copy()->subDays(3)->startOfDay();
        $actual   = $schedule->booking_deadline->startOfDay();
        $this->assertTrue($expected->equalTo($actual), "booking_deadline không đúng: {$actual} vs {$expected}");
    }

    /** TC03: Không truyền min_people → mặc định = 1. */
    public function test_min_people_defaults_to_one(): void
    {
        $response = $this->putJson("/api/admin/tours/{$this->tour->id}", array_merge(
            $this->baseTourPayload(),
            [
                'schedules' => [[
                    'start_date' => now()->addDays(30)->format('Y-m-d'),
                    'max_people' => 20,
                ]],
            ]
        ), $this->authHeader());

        $response->assertOk();

        $schedule = TourSchedule::where('tour_id', $this->tour->id)->latest('id')->first();
        $this->assertEquals(1, $schedule->min_people);
    }

    /** TC04: end_date tự tính đúng = start_date + (number_of_days - 1) ngày. */
    public function test_end_date_is_calculated_from_start_date_and_number_of_days(): void
    {
        $startDate = now()->addDays(30)->startOfDay();

        $response = $this->putJson("/api/admin/tours/{$this->tour->id}", array_merge(
            $this->baseTourPayload(),
            [
                'schedules' => [[
                    'start_date' => $startDate->format('Y-m-d'),
                    'max_people' => 20,
                ]],
            ]
        ), $this->authHeader());

        $response->assertOk();

        $schedule = TourSchedule::where('tour_id', $this->tour->id)->latest('id')->first();
        $expected = $startDate->copy()->addDays($this->tour->number_of_days - 1)->startOfDay();
        $actual   = $schedule->end_date->startOfDay();

        $this->assertTrue($expected->equalTo($actual), "end_date không đúng: {$actual} vs {$expected}");
    }

    // ─── Đổi trạng thái ─────────────────────────────────────────────────────

    /** TC05: Chuyển open → confirmed thành công. */
    public function test_transition_open_to_confirmed(): void
    {
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'status'  => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
        ]);

        $response = $this->patchJson("/api/admin/schedules/{$schedule->id}/status", [
            'status' => 'confirmed',
        ], $this->authHeader());

        $response->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('tour_schedules', [
            'id'     => $schedule->id,
            'status' => 'confirmed',
        ]);
    }

    /**
     * TC06, TC07: endpoint đổi trạng thái chung KHÔNG hủy chuyến được nữa.
     *
     * Trước đây gửi status=cancelled kèm lý do là xong: chuyến đổi trạng thái, còn đơn của khách
     * không ai đụng tới - không hoàn tiền, không báo ai, mà màn hình lại trông như đã xử lý xong.
     *
     * Hủy chuyến chạm tới tiền của từng khách nên phải qua màn riêng có bước gán phương án. Giữ
     * hai đường hủy, một đường xử lý đơn và một đường không, là khuôn của phần lớn lỗi ở dự án
     * này, nên đường cũ đóng hẳn. Bài này giữ cho nó đóng.
     *
     * Luồng hủy đúng nằm ở ScheduleCancellationTest.
     */
    public function test_endpoint_doi_trang_thai_khong_huy_chuyen_duoc_nua(): void
    {
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'status'  => ScheduleStatus::Open->value,
            'start_date' => now()->addDays(10),
        ]);

        // Có lý do đầy đủ vẫn bị từ chối: vấn đề không nằm ở thiếu lý do.
        $this->patchJson("/api/admin/schedules/{$schedule->id}/status", [
            'status' => 'cancelled',
            'reason' => 'Không đủ khách tối thiểu sau hạn chốt.',
        ], $this->authHeader())->assertStatus(422);

        $schedule->refresh();

        $this->assertSame(ScheduleStatus::Open, $schedule->status);
        $this->assertNull($schedule->cancelled_at);
    }

    /** TC08: Đường chuyển không hợp lệ (confirmed → open) → 422. */
    public function test_invalid_transition_returns_422(): void
    {
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'status'  => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(10),
        ]);

        $this->patchJson("/api/admin/schedules/{$schedule->id}/status", [
            'status' => 'open',
        ], $this->authHeader())->assertStatus(422);
    }

    /** TC09: Admin không được chuyển sang in_progress → 422 (validation). */
    public function test_admin_cannot_set_in_progress_directly(): void
    {
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'status'  => ScheduleStatus::Confirmed->value,
            'start_date' => now()->addDays(10),
        ]);

        $this->patchJson("/api/admin/schedules/{$schedule->id}/status", [
            'status' => 'in_progress',
        ], $this->authHeader())->assertStatus(422);
    }

    /** TC10: Sửa min_people của schedule đang in_progress → 422. */
    public function test_cannot_update_min_people_of_running_schedule(): void
    {
        $schedule = TourSchedule::factory()->create([
            'tour_id' => $this->tour->id,
            'status'  => ScheduleStatus::InProgress->value,
            'start_date' => now()->subDays(1),
            'end_date'   => now()->addDays(2),
        ]);

        $response = $this->putJson("/api/admin/tours/{$this->tour->id}", array_merge(
            $this->baseTourPayload(),
            [
                'schedules' => [[
                    'id'         => $schedule->id,
                    'start_date' => now()->subDays(1)->format('Y-m-d'),
                    'max_people' => 20,
                    'min_people' => 5,  // cố ý truyền để trigger guard
                ]],
            ]
        ), $this->authHeader());

        $response->assertStatus(422);
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    /** Payload cơ bản để cập nhật tour (không thay đổi thông tin tour). */
    private function baseTourPayload(): array
    {
        return [
            'title'           => $this->tour->title,
            'description'     => $this->tour->description ?? 'Mô tả tour',
            'adult_price'     => $this->tour->adult_price ?? 1_000_000,
            'child_price'     => $this->tour->child_price ?? 700_000,
            'infant_price'    => $this->tour->infant_price ?? 0,
            'number_of_days'  => $this->tour->number_of_days,
            'number_of_nights' => $this->tour->number_of_nights,
            'start_location'  => $this->tour->start_location ?? 'Hà Nội',
        ];
    }
}
