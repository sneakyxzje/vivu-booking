<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'booking_id',
    'name',
    'type',
    'date_of_birth',
    'identity_number',
    'note',
])]
class BookingPassenger extends Model
{
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
