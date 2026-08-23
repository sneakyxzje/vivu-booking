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
    'group_booking_request_id',
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

    /**
     * Tour của đơn — đọc được cả khi tour đã bị xóa mềm.
     *
     * Không có `withTrashed` thì xóa một tour làm mọi đơn cũ của nó mất tên tour trên màn hình
     * và trên chứng từ. Đó đúng là thứ việc xóa mềm sinh ra để tránh.
     */
    public function tour()
    {
        return $this->belongsTo(Tour::class)->withTrashed();
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

    /**
     * Sổ giao dịch: từng khoản thu và hoàn, chỉ thêm dòng không ghi đè.
     *
     * Hiện chỉ đơn đoàn có dòng ở đây - đơn lẻ vẫn trả một lần qua cổng và đọc `paid_at`.
     * `CancellationPolicyService::paidAmount()` tự chọn nguồn theo việc sổ có dòng hay không.
     */
    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    /**
     * Các khoản phụ thu và hoàn sinh ra từ sự cố dọc đường.
     *
     * Không liên quan gì tới giá tour: khách trả 4,5 triệu là đã trả xong tour. Đây là những thứ
     * xảy ra sau khi đoàn lên đường mà không ai lường trước - kẹt bão phải ở thêm đêm, hoặc ngược
     * lại, buổi tham quan đã bán mà không đi được nên phải hoàn.
     *
     * Khách phải thấy được các dòng này trong đơn của mình, nếu không thì hệ thống lập một khoản
     * phải trả rồi không nói với người phải trả nó.
     */
    public function surcharges()
    {
        return $this->hasMany(BookingSurcharge::class);
    }

    /** Yêu cầu đoàn đã sinh ra đơn này, null với đơn lẻ. */
    public function groupRequest()
    {
        return $this->belongsTo(GroupBookingRequest::class, 'group_booking_request_id');
    }

    /** Đơn này là đơn đoàn (sinh từ luồng yêu cầu - báo giá - chốt). */
    public function isGroup(): bool
    {
        return $this->group_booking_request_id !== null;
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


