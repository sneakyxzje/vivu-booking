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
    'content'
])]
class TourItinerary extends Model
{
    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}

