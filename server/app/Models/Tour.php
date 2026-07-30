<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'admin_id',
    'title',
    'slug',
    'description',
    'price',
    'discount_price',
    'thumbnail',
    'number_of_days',
    'number_of_nights',
    'start_location',
    'end_location',
    'vehicle_info',
    'is_featured',
    'status'
])]
class Tour extends Model
{
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_tour');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'tour_service');
    }

    public function images()
    {
        return $this->hasMany(TourImage::class);
    }

    public function itineraries()
    {
        return $this->hasMany(TourItinerary::class);
    }

    public function schedules()
    {
        return $this->hasMany(TourSchedule::class);
    }
}