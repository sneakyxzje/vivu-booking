<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tour_id',
    'day_number',
    'title',
    'start_point',
    'end_point',
    'route_points',
    'rest_stops',
    'content',
    'images',
])]
class TourItinerary extends Model
{
    protected function casts(): array
    {
        return ['images' => 'array'];
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function checkpoints()
    {
        return $this->hasMany(ItineraryCheckpoint::class)
            ->orderBy('sequence');
    }
}
