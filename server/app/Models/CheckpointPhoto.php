<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tour_schedule_id',
    'tour_itinerary_id',
    'guide_id',
    'image_path',
])]
class CheckpointPhoto extends Model
{
    public function schedule()
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
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
