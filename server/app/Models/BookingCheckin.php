<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Điểm danh theo mô hình CŨ — bảng lưu trữ, chỉ đọc.
 *
 * Mô hình hiện hành là `PassengerCheckin`: điểm danh từng hành khách tại từng điểm dừng. Bảng này
 * ghi theo đơn hàng và theo ngày, đã được chuyển đổi sang mô hình mới ở migration
 * 2026_08_12_150000 và từ đó không còn đường ghi nào.
 *
 * Giữ lại vì đây là bản gốc để đối chiếu khi có khiếu nại. Cố ý KHÔNG khai `$fillable`: model này
 * không phải chỗ để ghi, và một model có `$fillable` là một lời mời ghi vào.
 */
class BookingCheckin extends Model
{
    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
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
