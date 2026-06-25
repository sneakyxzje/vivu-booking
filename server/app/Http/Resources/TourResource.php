<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'host_id' => $this->host_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'thumbnail' => $this->thumbnail,
            'number_of_days' => (int) $this->number_of_days,
            'number_of_nights' => (int) $this->number_of_nights,
            'start_location' => $this->start_location,
            'end_location' => $this->end_location,
            'is_featured' => (bool) $this->is_featured,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            'total_booked' => $this->when(isset($this->total_booked), (int) $this->total_booked),

            // Chỉ trả về data quan hệ khi được load để tối ưu hiệu năng
            'host' => $this->whenLoaded('host'),
            'categories' => $this->whenLoaded('categories'),
            'services' => $this->whenLoaded('services'),
            'images' => $this->whenLoaded('images'),
            'itineraries' => $this->whenLoaded('itineraries'),
            'schedules' => $this->whenLoaded('schedules'),
        ];
    }
}
