<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'booking_id',
    'tour_itinerary_id',
    'guide_id',
    'present',
    'checked_at',
])]
class BookingCheckin extends Model
{
    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function itinerary()
    {
        return $this->belongsTo(TourItinerary::class, 'tour_itinerary_id');
    }

    public function guide()
    {
        return $this->belongsTo(User::class, 'guide_id');
    }
}
