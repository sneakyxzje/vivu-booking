<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'booking_id',
    'provider',
    'transaction_no',
    'bank_code',
    'response_code',
    'transaction_status',
    'amount',
    'is_valid_signature',
    'raw_payload',
])]
class PaymentLog extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_valid_signature' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
