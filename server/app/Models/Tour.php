<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'admin_id',
    'title',
    'slug',
    'description',
    'price',
    'discount_price',
    'adult_price',
    'child_price',
    'infant_price',
    'thumbnail',
    'number_of_days',
    'number_of_nights',
    'start_location',
    'end_location',
    'vehicle_info',
    'pickup_location',
    'is_featured',
    'status',
    'type',
    'cancellation_policy_id',
])]
/**
 * Tour — xóa mềm.
 *
 * Xóa một tour không được phép làm mất chứng từ của khách. Đơn hàng, đánh giá và yêu cầu đoàn đều
 * trỏ tới đây, nên xóa cứng là xóa theo cả lịch sử giao dịch. Xóa mềm giữ nguyên hàng dữ liệu:
 * tour biến mất khỏi mọi danh sách, còn đơn cũ vẫn tra ra được tên tour vì các quan hệ trỏ tới
 * `Tour` đều khai `withTrashed`.
 */
class Tour extends Model
{
    use HasFactory, SoftDeletes;
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
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function cancellationPolicy()
    {
        return $this->belongsTo(CancellationPolicy::class);
    }
}

