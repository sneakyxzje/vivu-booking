<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPassengerSupplement extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['payload_hash', 'request_key'];

    protected function casts(): array
    {
        return ['passengers' => 'array', 'recorded_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
