<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Repositories\TourRepository;
use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TourService
{
    protected $tourRepository;

    public function __construct(TourRepository $tourRepository)
    {
        $this->tourRepository = $tourRepository;
    }

    public function create(array $data, int $hostId)
    {
        // 1. Tự động tạo slug nếu không truyền lên và đảm bảo duy nhất
        $slug = $data['slug'] ?? Str::slug($data['title']);
        $originalSlug = $slug;
        $count = 1;
        while (Tour::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        // 2. Chuẩn bị dữ liệu và set default cho các optional fields
        $tourData = [
            'admin_id' => $hostId,
            'title' => $data['title'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['adult_price'] ?? $data['price'],
            'discount_price' => $data['discount_price'] ?? null,
            'adult_price' => $data['adult_price'] ?? $data['price'],
            'child_price' => $data['child_price'] ?? 0,
            'infant_price' => $data['infant_price'] ?? 0,
            'thumbnail' => $data['thumbnail'] ?? null,
            'number_of_days' => $data['number_of_days'],
            'number_of_nights' => $data['number_of_nights'],
            'start_location' => $data['start_location'],
            'end_location' => $data['end_location'] ?? null,
            'is_featured' => $data['is_featured'] ?? false,
            'status' => $data['status'] ?? 'active',
        ];

        // 3. Tạo Tour thông qua Repository
        $tour = $this->tourRepository->create($tourData);

        // 4. Đồng bộ danh mục và dịch vụ đi kèm
        if (!empty($data['category_ids'])) {
            $tour->categories()->sync($data['category_ids']);
        }
        if (!empty($data['service_ids'])) {
            $tour->services()->sync($data['service_ids']);
        }

        if (!empty($data['image_paths'])) {
            foreach ($data['image_paths'] as $imagePath) {
                $tour->images()->create([
                    'image_path' => $imagePath,
                ]);
            }
        }

        if (!empty($data['itineraries'])) {
            foreach ($data['itineraries'] as $itinerary) {
                $tour->itineraries()->create([
                    'day_number' => $itinerary['day_number'],
                    'title' => $itinerary['title'],
                    'start_point' => $itinerary['start_point'] ?? null,
                    'end_point' => $itinerary['end_point'] ?? null,
                    'route_points' => $itinerary['route_points'] ?? null,
                    'rest_stops' => $itinerary['rest_stops'] ?? null,
                    'content' => $itinerary['content'],
                ]);
            }
        }

        if (!empty($data['schedules'])) {
            foreach ($data['schedules'] as $schedule) {
                $startDate = Carbon::parse($schedule['start_date']);

                $tour->schedules()->create([
                    'start_date' => $startDate,
                    'end_date' => $startDate->copy()->addDays(max(0, (int) $tour->number_of_days - 1)),
                    'max_people' => $schedule['max_people'],
                    'min_people' => $schedule['min_people'] ?? 1,
                    'booking_deadline' => isset($schedule['booking_deadline'])
                        ? Carbon::parse($schedule['booking_deadline'])
                        : $startDate->copy()->subDays(3),
                    'booked_people' => 0,
                    'status' => ScheduleStatus::Open->value,
                ]);
            }
        }

        return $tour->load(['categories', 'services', 'images', 'itineraries', 'schedules']);
    }
}




