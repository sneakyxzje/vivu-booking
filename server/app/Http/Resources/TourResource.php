<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'adult_price' => (float) ($this->adult_price ?? 0),
            'child_price' => (float) ($this->child_price ?? 0),
            'infant_price' => (float) ($this->infant_price ?? 0),
            // null nghĩa là thu đủ ngay khi đặt. Giao diện đọc nó để nói trước cho khách biết
            // họ sẽ phải trả bao nhiêu ở bước thanh toán.
            'deposit_percent' => $this->deposit_percent === null ? null : (int) $this->deposit_percent,
            'thumbnail' => $this->thumbnail,
            'number_of_days' => (int) $this->number_of_days,
            'number_of_nights' => (int) $this->number_of_nights,
            'start_location' => $this->start_location,
            'end_location' => $this->end_location,
            'vehicle_info' => $this->vehicle_info,
            'pickup_location' => $this->pickup_location,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'total_booked' => $this->when(isset($this->total_booked), (int) $this->total_booked),
            'rating' => $this->when($this->rating !== null, fn () => round((float) $this->rating, 1)),
            'review_count' => $this->when(isset($this->reviews_count), (int) $this->reviews_count),
            'admin' => $this->whenLoaded('admin'),
            'categories' => $this->whenLoaded('categories'),
            'services' => $this->whenLoaded('services'),
            'images' => $this->whenLoaded('images'),
            'itineraries' => $this->whenLoaded('itineraries'),
            'schedules' => $this->whenLoaded('schedules'),
        ];
    }
}
