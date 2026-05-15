<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'icon'
])]
class Service extends Model
{
    public function tours()
    {
        return $this->belongsToMany(Tour::class, 'tour_service');
    }
}
