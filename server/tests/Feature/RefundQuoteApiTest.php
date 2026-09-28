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
            'effective_from' => now()->subDay(),
        ]);

        foreach ([
            [15, null, 90],
            [8, 15, 70],
            [4, 8, 50],
            [2, 4, 30],
            [0, 2, 0],
        ] as [$min, $max, $percent]) {
            $policy->rules()->create([
                'min_days_before' => $min,
                'max_days_before' => $max,
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
            ->assertJsonPath('data.refund_percent', 100)
            ->assertJsonPath('data.cancellation_fee', 0)
            ->assertJsonPath('data.refund_amount', 10_000_000)
            ->assertJsonPath('data.policy_name', 'Hoàn hủy theo hạn chốt danh sách');
    }

    public function test_tra_ve_ca_bang_phi_de_khach_doi_chieu(): void
    {
        $don = $this->taoDon(gioToiKhoiHanh: 24 * 10);

        $response = $this->getJson("/api/bookings/{$don->public_token}/refund-quote")->assertOk();

        $rules = $response->json('data.rules');

        $this->assertCount(2, $rules);
        $this->assertSame('Trước hạn chốt danh sách', $rules[0]['window']);
        $this->assertSame(100, $rules[0]['refund_percent']);
    }

    public function test_huy_sau_han_chot_giu_coc_va_hoan_phan_vuot_coc(): void
    {
        $don = $this->taoDon(gioToiKhoiHanh: 12);

        $this->getJson("/api/bookings/{$don->public_token}/refund-quote")
            ->assertOk()
            ->assertJsonPath('data.refund_percent', 50)
            ->assertJsonPath('data.refund_amount', 5_000_000);
    }

    public function test_ma_tra_cuu_sai_thi_khong_lo_thong_tin(): void
    {
        $this->getJson('/api/bookings/khong-ton-tai/refund-quote')->assertStatus(404);
    }
}
