<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tour_id',
    'customer_id',
    'guest_id',
    'tour_schedule_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'departure_date',
    'guests',
    'total_amount',
    'status',
    'note',
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
}
