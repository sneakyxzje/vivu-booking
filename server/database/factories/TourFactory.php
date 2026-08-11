<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tour> */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $title = 'Tour ' . $this->faker->unique()->words(3, true);

        return [
            'admin_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(6)),
            'description' => $this->faker->paragraph(),
            'price' => 1000000,
            'adult_price' => 1000000,
            'child_price' => 700000,
            'infant_price' => 0,
            'number_of_days' => 3,
            'number_of_nights' => 2,
            'start_location' => 'Ha Noi',
            'end_location' => 'Da Nang',
            'is_featured' => false,
            'status' => 'active',
        ];
    }
}
