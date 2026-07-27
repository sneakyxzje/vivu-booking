<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingPassenger extends Model
{
    use HasFactory;

    protected $table = 'booking_passengers';

    protected $fillable = [
        'booking_id',
        'full_name',
        'birth_date',
        'gender',
        'phone',
        'email',
        'identity_number',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    /**
     * Một hành khách thuộc một booking
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}