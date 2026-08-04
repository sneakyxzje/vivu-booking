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
    'note',
    'cancel_reason',
    'vnpay_transaction_no',
    'paid_at',
    'confirmed_at',
])]
class Booking extends Model
{
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

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }
}


