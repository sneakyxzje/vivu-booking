<?php

namespace Tests\Feature\Api;

use App\Enums\ProposalStatus;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use App\Mail\BookingProposalMail;

class BookingProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active'
        ]);
        
        // Tạo booking giả lập
        $this->booking = Booking::factory()->create([
            'customer_email' => 'test@example.com',
            'customer_name' => 'John Doe',
            'public_token' => 'ABCD123456',
        ]);
    }

    public function test_admin_can_create_proposal_and_email_is_sent()
    {
        Mail::fake();

        $payload = [
            'reason' => 'Thay đổi lịch trình do bão',
            'options' => [
                [
                    'id' => 'opt_1',
                    'title' => 'Đổi sang ngày 20/10/2026',
                    'description' => 'Khởi hành muộn hơn'
                ],
                [
                    'id' => 'opt_2',
                    'title' => 'Hủy và hoàn tiền',
                    'description' => 'Hoàn 100%'
                ]
            ],
            'response_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/bookings/{$this->booking->id}/proposals", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reason', 'Thay đổi lịch trình do bão');

        $this->assertDatabaseHas('booking_change_proposals', [
            'booking_id' => $this->booking->id,
            'status' => ProposalStatus::Pending->value,
        ]);

        Mail::assertQueued(BookingProposalMail::class, function ($mail) {
            return $mail->hasTo('test@example.com') &&
                   $mail->booking->id === $this->booking->id;
        });
    }

    public function test_customer_can_view_proposals_with_valid_token_and_email()
    {
        $proposal = BookingChangeProposal::create([
            'booking_id' => $this->booking->id,
            'admin_id' => $this->admin->id,
            'reason' => 'Đổi giờ bay',
            'options' => [['id' => 'opt_1', 'title' => 'OK']],
            'response_deadline' => now()->addDays(1),
            'status' => ProposalStatus::Pending->value,
        ]);

        // Thiếu email => lỗi
        $this->getJson("/api/bookings/{$this->booking->public_token}/proposals")
            ->assertStatus(404);

        // Sai email => lỗi
        $this->getJson("/api/bookings/{$this->booking->public_token}/proposals?email=wrong@email.com")
            ->assertStatus(404);

        // Đúng token và email => thành công
        $response = $this->getJson("/api/bookings/{$this->booking->public_token}/proposals?email=test@example.com");
        
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $proposal->id);
    }

    public function test_customer_can_accept_proposal()
    {
        $proposal = BookingChangeProposal::create([
            'booking_id' => $this->booking->id,
            'admin_id' => $this->admin->id,
            'reason' => 'Đổi giờ bay',
            'options' => [
                ['id' => 'opt_1', 'title' => 'Đổi sang ngày mai'],
                ['id' => 'opt_2', 'title' => 'Hủy']
            ],
            'response_deadline' => now()->addDays(1),
            'status' => ProposalStatus::Pending->value,
        ]);

        $payload = [
            'action' => 'accept',
            'choice_id' => 'opt_1',
            'note' => 'Tôi đồng ý đổi ngày'
        ];

        $response = $this->postJson(
            "/api/bookings/{$this->booking->public_token}/proposals/{$proposal->id}/respond?email=test@example.com",
            $payload
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('booking_change_proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Accepted->value,
            'customer_choice' => 'opt_1',
            'customer_note' => 'Tôi đồng ý đổi ngày'
        ]);
    }

    public function test_customer_cannot_respond_after_deadline()
    {
        $proposal = BookingChangeProposal::create([
            'booking_id' => $this->booking->id,
            'admin_id' => $this->admin->id,
            'reason' => 'Đổi giờ bay',
            'options' => [['id' => 'opt_1', 'title' => 'Đồng ý']],
            'response_deadline' => now()->subMinutes(10), // Đã qua 10 phút
            'status' => ProposalStatus::Pending->value,
        ]);

        $payload = [
            'action' => 'accept',
            'choice_id' => 'opt_1',
        ];

        $response = $this->postJson(
            "/api/bookings/{$this->booking->public_token}/proposals/{$proposal->id}/respond?email=test@example.com",
            $payload
        );

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Đề xuất này đã quá thời hạn phản hồi.');

        $this->assertDatabaseHas('booking_change_proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Expired->value, // Đã tự động cập nhật sang hết hạn
        ]);
    }
}
