<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingCheckin;
use App\Models\Tour;
use App\Models\TourSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoBookingSeeder extends Seeder
{
    private const DEMO_NOTE = '[demo] Đơn dữ liệu trình diễn';

    public function run(): void
    {
        $tours = Tour::query()
            ->with(['schedules', 'itineraries'])
            ->whereIn('slug', [
                'tour-ha-long-3n2d',
                'tour-da-nang-hoi-an-4n3d',
                'tour-sapa-fansipan-2n1d',
                'tour-phu-quoc-3n2d',
            ])
            ->get()
            ->keyBy('slug');

        $customers = User::query()
            ->where('role', 'customer')
            ->orderBy('id')
            ->take(4)
            ->get()
            ->values();

        if ($tours->isEmpty() || $customers->isEmpty()) {
            return;
        }

        $guide = User::query()->where('role', 'guide')->first();

        // Xóa dữ liệu demo cũ để seed lại được nhiều lần
        Booking::query()->where('note', 'like', '[demo]%')->delete();

        // [slug, số tháng trước, trạng thái, người lớn, trẻ em, thanh toán online?]
        $entries = [
            ['tour-ha-long-3n2d', 5, 'confirmed', 2, 0, true],
            ['tour-ha-long-3n2d', 4, 'confirmed', 2, 1, true],
            ['tour-ha-long-3n2d', 3, 'confirmed', 1, 0, false],
            ['tour-ha-long-3n2d', 2, 'confirmed', 3, 1, true],
            ['tour-ha-long-3n2d', 1, 'confirmed', 2, 0, true],
            ['tour-ha-long-3n2d', 0, 'confirmed', 2, 1, true],
            ['tour-ha-long-3n2d', 0, 'pending', 1, 0, false],
            ['tour-ha-long-3n2d', 1, 'cancelled', 2, 0, false],
            ['tour-da-nang-hoi-an-4n3d', 4, 'confirmed', 2, 0, true],
            ['tour-da-nang-hoi-an-4n3d', 2, 'confirmed', 2, 2, true],
            ['tour-da-nang-hoi-an-4n3d', 0, 'confirmed', 1, 1, false],
            ['tour-da-nang-hoi-an-4n3d', 3, 'cancelled', 1, 0, false],
            ['tour-sapa-fansipan-2n1d', 1, 'confirmed', 2, 0, true],
            ['tour-sapa-fansipan-2n1d', 0, 'confirmed', 1, 0, true],
            ['tour-phu-quoc-3n2d', 2, 'confirmed', 2, 1, true],
            ['tour-phu-quoc-3n2d', 0, 'pending', 2, 0, false],
        ];

        foreach ($entries as $index => [$slug, $monthsAgo, $status, $adults, $children, $paidOnline]) {
            $tour = $tours->get($slug);
            $schedule = $tour?->schedules->first();

            if (!$tour || !$schedule) {
                continue;
            }

            $customer = $customers[$index % $customers->count()];
            $guests = $adults + $children;
            $amount = $adults * (float) $tour->adult_price + $children * (float) $tour->child_price;
            $createdAt = now()->subMonths($monthsAgo)->startOfMonth()->addDays(4 + ($index * 2) % 20)->setTime(9 + $index % 8, 15);

            $booking = Booking::create([
                'public_token' => (string) Str::uuid(),
                'tour_id' => $tour->id,
                'customer_id' => $customer->id,
                'tour_schedule_id' => $schedule->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => '09' . str_pad((string) (10000000 + $index * 137), 8, '0', STR_PAD_LEFT),
                'departure_date' => $schedule->start_date,
                'guests' => $guests,
                'adult_count' => $adults,
                'child_count' => $children,
                'infant_count' => 0,
                'total_amount' => $amount,
                'status' => $status,
                'expires_at' => $status === 'pending' ? now()->addDay() : null,
                'cancel_reason' => $status === 'cancelled' ? 'Khách bận việc đột xuất, không tham gia được' : null,
                'vnpay_transaction_no' => ($status === 'confirmed' && $paidOnline) ? 'VNP' . (14500000 + $index * 331) : null,
                'paid_at' => ($status === 'confirmed' && $paidOnline) ? $createdAt->copy()->addMinutes(6) : null,
                'confirmed_at' => $status === 'confirmed' ? $createdAt->copy()->addMinutes(6) : null,
                'note' => self::DEMO_NOTE,
            ]);

            $booking->created_at = $createdAt;
            $booking->updated_at = $createdAt->copy()->addMinutes(6);
            $booking->save();

            // Vài đơn có danh sách hành khách chi tiết
            if ($index % 3 === 0) {
                $booking->passengers()->create([
                    'name' => mb_strtoupper($customer->name),
                    'type' => 'adult',
                    'identity_number' => '0790950' . str_pad((string) (10000 + $index * 91), 5, '0', STR_PAD_LEFT),
                ]);

                if ($children > 0) {
                    $booking->passengers()->create([
                        'name' => mb_strtoupper($customer->name) . ' (BE)',
                        'type' => 'child',
                        'note' => 'Bé hay say xe',
                    ]);
                }
            }
        }

        // Đồng bộ lại số chỗ đã đặt theo đơn còn hiệu lực
        TourSchedule::query()->each(function (TourSchedule $schedule) {
            $schedule->update([
                'booked_people' => (int) Booking::query()
                    ->where('tour_schedule_id', $schedule->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->sum('guests'),
            ]);
        });

        // Điểm danh mẫu cho chuyến Hạ Long gần nhất: chặng ngày 1
        $haLong = $tours->get('tour-ha-long-3n2d');
        $firstItinerary = $haLong?->itineraries->sortBy('day_number')->first();
        $firstSchedule = $haLong?->schedules->first();

        if ($firstItinerary && $firstSchedule) {
            Booking::query()
                ->where('tour_schedule_id', $firstSchedule->id)
                ->where('status', 'confirmed')
                ->where('note', 'like', '[demo]%')
                ->get()
                ->each(function (Booking $booking, int $i) use ($firstItinerary, $guide) {
                    BookingCheckin::query()->updateOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'tour_itinerary_id' => $firstItinerary->id,
                        ],
                        [
                            'present' => $i % 4 !== 3,
                            'checked_at' => now()->subHours(2),
                            'guide_id' => $guide?->id,
                        ],
                    );
                });
        }
    }
}
