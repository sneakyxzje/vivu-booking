<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'host_id',
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
    'is_featured',
    'status'
])]
class Tour extends Model
{
    // Một Tour thuộc về một Host (User)
    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    // Một Tour có nhiều Danh mục 
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_tour');
    }

    // Một Tour có nhiều Dịch vụ/Tiện ích 
    public function services()
    {
        return $this->belongsToMany(Service::class, 'tour_service');
    }

    // Một Tour có nhiều Ảnh trong thư viện gallery
    public function images()
    {
        return $this->hasMany(TourImage::class);
    }

    // Một Tour có nhiều Ngày lịch trình chi tiết
    public function itineraries()
    {
        return $this->hasMany(TourItinerary::class);
    }

    // Một Tour có nhiều Lịch khởi hành theo ngày cụ thể
    public function schedules()
    {
        return $this->hasMany(TourSchedule::class);
    }
}
