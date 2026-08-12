<?php

namespace Tests\Feature;

use App\Models\BookingPassenger;
use App\Models\Booking;
use App\Models\ItineraryCheckpoint;
use App\Models\PassengerCheckin;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sửa thông tin tour không được làm mất dữ liệu điểm danh của các chuyến đã đi.
 *
 * AdminTourController::update xóa toàn bộ lịch trình rồi tạo lại. Khóa ngoại của
 * itinerary_checkpoints và passenger_checkins đều cascadeOnDelete, nên một lần sửa tiêu đề
 * tour cũng kéo theo mất sạch bản ghi điểm danh.
 */
class ItineraryCheckpointPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sua_tour_khong_lam_mat_du_lieu_diem_danh(): void
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $tour = Tour::factory()->create([
            'admin_id' => $admin->id,
            'status' => 'active',
            'number_of_days' => 2,
            'number_of_nights' => 1,
        ]);

        $itinerary = $tour->itineraries()->create([
            'day_number' => 1,
            'title' => 'Ngày 1',
            'content' => 'Khởi hành từ Hà Nội.',
        ]);

        $checkpoint = $itinerary->checkpoints()->create([
            'name' => 'Điểm đón Mỹ Đình',
            'sequence' => 1,
        ]);

        $booking = Booking::create([
            'public_token' => (string) Str::uuid(),
            'tour_id' => $tour->id,
            'customer_name' => 'Khach Test',
            'customer_email' => 'khach@example.com',
            'departure_date' => now()->subDays(5),
            'guests' => 1,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'total_amount' => 1_000_000,
            'status' => 'confirmed',
        ]);

        $passenger = BookingPassenger::create([
            'booking_id' => $booking->id,
            'name' => 'Nguyen Van A',
            'type' => 'adult',
        ]);

        PassengerCheckin::create([
            'booking_passenger_id' => $passenger->id,
            'itinerary_checkpoint_id' => $checkpoint->id,
            'status' => 'present',
            'checked_by' => $admin->id,
            'checked_at' => now()->subDays(5),
        ]);

        $this->assertSame(1, PassengerCheckin::query()->count());

        Sanctum::actingAs($admin);

        // Sửa đúng một thứ không liên quan gì tới lịch trình: tiêu đề tour.
        $this->postJson("/api/admin/tours/{$tour->id}", [
            '_method' => 'PUT',
            'title' => 'Tên tour đã đổi',
            'adult_price' => 1_000_000,
            'child_price' => 700_000,
            'infant_price' => 0,
            'number_of_days' => 2,
            'number_of_nights' => 1,
            'start_location' => 'Hà Nội',
            'itineraries' => [
                [
                    'day_number' => 1,
                    'title' => 'Ngày 1',
                    'content' => 'Khởi hành từ Hà Nội.',
                    'checkpoints' => [
                        ['id' => $checkpoint->id, 'name' => 'Điểm đón Mỹ Đình', 'sequence' => 1],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertSame(
            1,
            ItineraryCheckpoint::query()->count(),
            'Điểm dừng phải được giữ lại, không được xóa rồi tạo mới với id khác.',
        );

        $this->assertSame(
            1,
            PassengerCheckin::query()->count(),
            'Bản ghi điểm danh của chuyến đã đi phải còn nguyên sau khi sửa tour.',
        );
    }
}
