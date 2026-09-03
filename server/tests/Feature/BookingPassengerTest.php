<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingPassengerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dat_tour_luu_danh_sach_hanh_khach(): void
    {
        $admin = User::create([
            'name' => 'Admin Passenger',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $tour = Tour::create([
            'admin_id' => $admin->id,
            'title' => 'Tour Hanh Khach',
            'slug' => 'tour-hanh-khach-' . Str::random(6),
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 1,
            'number_of_nights' => 0,
            'start_location' => 'Ha Noi',
            'status' => 'active',
        ]);

        $schedule = TourSchedule::create([
            'tour_id' => $tour->id,
            'start_date' => now()->addDays(5),
            'max_people' => 10,
            'booked_people' => 0,
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/bookings', [
            'tour_id' => $tour->id,
            'tour_schedule_id' => $schedule->id,
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'vana@example.com',
            'adult_count' => 1,
            'child_count' => 1,
            'accept_terms' => true,
            'passengers' => [
                [
                    'name' => 'Nguyen Van A',
                    'type' => 'adult',
                    'identity_number' => '079095001234',
                ],
                [
                    'name' => 'Nguyen Be Bi',
                    'type' => 'child',
                    'date_of_birth' => '2018-04-02',
                    'note' => 'Di ung hai san',
                ],
            ],
        ]);

        $response->assertCreated();

        $booking = Booking::query()->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertSame(2, $booking->passengers()->count());
        $this->assertSame('Nguyen Be Bi', $booking->passengers()->where('type', 'child')->value('name'));
    }
}

