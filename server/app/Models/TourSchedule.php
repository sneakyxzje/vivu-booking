<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tour_id',
    'guide_id',
    'start_date',
    'max_people',
    'booked_people',
    'status'
])]
class TourSchedule extends Model
{
    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function guide()
    {
        return $this->belongsTo(User::class, 'guide_id');
    }
}