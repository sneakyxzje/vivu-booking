<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'public_token',
    'tour_id',
    'customer_id',
    'guest_id',
    'tour_schedule_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'departure_date',
    'guests',
    'adult_count',
    'child_count',
    'infant_count',
    'total_amount',
    'discount_code_id',
    'discount_code',
    'discount_amount',
    'status',
    'completed_at',
    'expires_at',
    'note',
    'cancel_reason',
    'cancel_type',
    'cancelled_at',
    'cancelled_by',
    'seats_released',
    'seats_released_at',
    'seats_released_by',
    'refund_amount',
    'cancellation_plan',
    'cancellation_policy_id',
    'vnpay_transaction_no',
    'paid_at',
    'confirmed_at',
    'reopen_reason',
    'reopened_at',
    'reopened_by',
])]
class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'seats_released_at' => 'datetime',
            'seats_released' => 'boolean',
            'reopened_at' => 'datetime',
        ];
    }

    /**
     * Đơn đã hủy nhưng chỗ chưa được trả về kho.
     *
     * Đây là ghế chết: chỗ trống về mặt vật lý nhưng chưa bán lại được vì phòng và suất ăn
     * đã chốt theo danh sách gửi nhà cung cấp. Điều hành mở lại thủ công khi xin thêm được suất.
     */
    public function scopeWithHeldSeats($query)
    {
        return $query->where('status', 'cancelled')->where('seats_released', false);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function schedule()
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function passengers()
    {
        return $this->hasMany(BookingPassenger::class);
    }

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * Chính sách hủy đã sao chép lúc đặt. Đọc từ đây chứ không đọc qua tour, vì tour có thể
     * đã đổi sang chính sách khác sau khi khách đặt.
     */
    public function cancellationPolicy()
    {
        return $this->belongsTo(CancellationPolicy::class);
    }
}


