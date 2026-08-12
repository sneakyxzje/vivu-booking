<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassengerCheckinHistory extends Model
{
    protected $fillable = [
        'passenger_checkin_id',
        'old_status',
        'new_status',
        'note',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function passengerCheckin(): BelongsTo
    {
        return $this->belongsTo(PassengerCheckin::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}