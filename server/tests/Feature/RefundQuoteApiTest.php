<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * B07 - Khách xem được mức hoàn dự kiến trước khi bấm hủy.
 *
 * Doc 03 mục 5.2 nêu bước này là bắt buộc: phần lớn khiếu nại sau hủy đến từ việc khách
 * không biết trước mình sẽ mất bao nhiêu.
 */
class RefundQuoteApiTest extends TestCase
{
    use RefreshDatabase;

    private function taoDon(int $gioToiKhoiHanh, float $tongTien = 10_000_000): Booking
    {
        $policy = CancellationPolicy::create([
            'name' => 'Chính sách hủy tiêu chuẩn',
            'is_default' => true,
        ]);

        foreach ([
            [360, null, 90],
            [192, 360, 70],
            [96, 192, 50],
            [48, 96, 30],
            [0, 48, 0],
        ] as [$min, $max, $percent]) {
            $policy->rules()->create([
                'min_hours_before' => $min,
                'max_hours_before' => $max,
                'refund_percent' => $percent,
            ]);
        }

        $tour = Tour::factory()->create([
            'status' => 'active',
            'cancellation_policy_id' => $policy->id,
        ]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'status' => ScheduleStatus::Open->value,
            'start_date' => now()->addHours($gioToiKhoiHanh),
            'max_people' => 20,
            'booked_people' => 2,
        ]);

        return Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'cancellation_policy_id' => $policy->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => $schedule->start_date,
            'guests' => 2,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => $tongTien,
            'status' => 'confirmed',
            'paid_at' => now()->subDay(),
            'confirmed_at' => now()->subDay(),
        ]);
    }

    public function test_khach_xem_duoc_muc_hoan_du_kien(): void
    {
        $don = $this->taoDon(gioToiKhoiHanh: 24 * 10);

        $this->getJson("/api/bookings/{$don->public_token}/refund-quote")
            ->assertOk()
            ->assertJsonPath('data.refund_percent', 70)
            ->assertJsonPath('data.cancellation_fee', 3_000_000)
            ->assertJsonPath('data.refund_amount', 7_000_000)
            ->assertJsonPath('data.policy_name', 'Chính sách hủy tiêu chuẩn');
    }

    public function test_tra_ve_ca_bang_phi_de_khach_doi_chieu(): void
    {
        $don = $this->taoDon(gioToiKhoiHanh: 24 * 10);

        $response = $this->getJson("/api/bookings/{$don->public_token}/refund-quote")->assertOk();

        $rules = $response->json('data.rules');

        $this->assertCount(5, $rules);
        $this->assertSame('Từ 15 ngày trở lên', $rules[0]['window']);
        $this->assertSame(90, $rules[0]['refund_percent']);
    }

    public function test_huy_sat_ngay_di_thi_khong_hoan(): void
    {
        $don = $this->taoDon(gioToiKhoiHanh: 12);

        $this->getJson("/api/bookings/{$don->public_token}/refund-quote")
            ->assertOk()
            ->assertJsonPath('data.refund_percent', 0)
            ->assertJsonPath('data.refund_amount', 0);
    }

    public function test_ma_tra_cuu_sai_thi_khong_lo_thong_tin(): void
    {
        $this->getJson('/api/bookings/khong-ton-tai/refund-quote')->assertStatus(404);
    }
}
