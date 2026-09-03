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

    /**
     * Chỉ đánh giá đã duyệt — nguồn duy nhất cho điểm trung bình và số đánh giá hiện ra ngoài.
     *
     * Tách thành một quan hệ riêng thay vì nhớ gắn `->approved()` ở từng chỗ gọi `withAvg`: quên
     * một chỗ thì một đánh giá bị từ chối vẫn kéo điểm của tour xuống, và không màn hình nào cho
     * thấy vì sao.
     */
    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->approved();
    }

    /*
     * ĐÃ GỠ: `cancellationPolicy()`.
     *
     * Cả hệ thống dùng **một bảng phí hủy duy nhất**, sửa ở màn Chính sách hủy. Tour không chọn
     * riêng — `AdminTourController` đã bỏ `cancellation_policy_id` khỏi cả `store()` lẫn `update()`
     * từ lâu, nhưng quan hệ này cùng ô `fillable` của nó vẫn ở lại, và mấy seeder vẫn ghi vào cột.
     * Tức là mã nguồn tự nói ngược nhau: một chỗ bảo tour không có chính sách riêng, một chỗ vẫn
     * dựng sẵn đường để nó có.
     *
     * Đường duy nhất từ bảng phí tới một đơn là `CancellationPolicy::dangApDung()` tại lúc đặt, rồi
     * đơn giữ lấy bản đó (`bookings.cancellation_policy_id`). Cột `tours.cancellation_policy_id`
     * giữ nguyên trong cơ sở dữ liệu cho dữ liệu cũ, nhưng không còn đường nào đọc hay ghi.
     */
}

