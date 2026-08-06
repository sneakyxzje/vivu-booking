<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'icon',
    'description',
    'price',
    'is_active',
])]
class Service extends Model
{
    // Quan hệ: 1 dịch vụ có thể thuộc nhiều tour
    public function tours()
    {
        return $this->belongsToMany(Tour::class, 'tour_service');
    }
}
