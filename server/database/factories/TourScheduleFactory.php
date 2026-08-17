<?php

namespace Database\Factories;

use App\Enums\ScheduleStatus;
use App\Models\Tour;
use App\Models\TourSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourSchedule> */
class TourScheduleFactory extends Factory
{
    protected $model = TourSchedule::class;

    public function definition(): array
    {
        $startDate = now()->addDays(14);

        return [
            'tour_id' => Tour::factory(),
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(2),
            'max_people' => 20,
            'min_people' => 1,
            'booking_deadline' => $startDate->copy()->subDays(3),
            'booked_people' => 0,
            'status' => ScheduleStatus::Open->value,
            'is_private' => false,
        ];
    }
}
