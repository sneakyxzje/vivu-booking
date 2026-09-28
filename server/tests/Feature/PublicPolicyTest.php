<?php

namespace Tests\Feature;

use App\Models\CancellationPolicy;
use App\Services\BookingTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_policy_describes_cutoff_and_deposit_without_authentication(): void
    {
        $this->getJson('/api/policies')->assertOk()
            ->assertJsonPath('data.cancellation.version', 'booking_deadline_v1')
            ->assertJsonPath('data.cancellation.rules.0.window', 'Trước hạn chốt danh sách')
            ->assertJsonPath('data.cancellation.rules.0.refund_percent', 100)
            ->assertJsonPath('data.cancellation.rules.1.refund_percent', 50)
            ->assertJsonCount(2, 'data.cancellation.rules')
            ->assertJsonPath('data.payment.balance_due_rule', 'booking_deadline')
            ->assertJsonPath('data.payment.deposit_percent', 50);
    }

    public function test_old_or_future_tier_records_do_not_change_public_policy(): void
    {
        foreach ([-1, 30] as $days) {
            $legacy = CancellationPolicy::create(['name' => 'Old rule '.$days,
                'description' => 'Pay ten days before departure.', 'effective_from' => now()->addDays($days)]);
            $legacy->rules()->create(['min_days_before' => 0, 'max_days_before' => null, 'refund_percent' => 10]);
        }
        $response = $this->getJson('/api/policies')->assertOk();
        $this->assertStringNotContainsString('Pay ten days', $response->getContent());
        $response->assertJsonPath('data.cancellation.name', 'Hoàn hủy theo hạn chốt danh sách')
            ->assertJsonPath('data.cancellation.effective_from', null)
            ->assertJsonPath('data.cancellation.rules.1.refund_percent', 50);
    }

    public function test_policy_uses_actual_booking_configuration(): void
    {
        config(['booking.booking_deadline_days' => 5]);
        $data = $this->getJson('/api/policies')->assertOk()->json('data');
        $this->assertSame(5, $data['booking']['deadline_days']);
        $this->assertSame((int) config('booking.transfer_notice_days'), $data['transfer']['notice_days']);
        $this->assertSame(BookingTransferService::FREE_TRANSFERS, $data['transfer']['free_transfers']);
        $this->assertSame((float) config('booking.transfer_fee'), (float) $data['transfer']['fee']);
        $this->assertSame((int) config('booking.payment_ttl_minutes'), $data['booking']['payment_ttl_minutes']);
    }
}
