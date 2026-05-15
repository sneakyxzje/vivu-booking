<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tour_id',
    'image_path'
])]
class TourImage extends Model
{
    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}
