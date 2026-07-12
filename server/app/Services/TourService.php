<?php

namespace App\Services;

use App\Repositories\TourRepository;
use App\Models\Tour;
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
            'price' => $data['price'],
            'discount_price' => $data['discount_price'] ?? null,
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
                    'content' => $itinerary['content'],
                ]);
            }
        }

        if (!empty($data['schedules'])) {
            foreach ($data['schedules'] as $schedule) {
                $tour->schedules()->create([
                    'start_date' => $schedule['start_date'],
                    'max_people' => $schedule['max_people'],
                    'booked_people' => 0,
                    'status' => 'active',
                ]);
            }
        }

        return $tour->load(['categories', 'services', 'images', 'itineraries', 'schedules']);
    }
}

