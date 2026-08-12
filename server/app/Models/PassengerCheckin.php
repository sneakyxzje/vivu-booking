<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassengerCheckin extends Model
{
    protected $fillable = [
        'booking_passenger_id',
        'itinerary_checkpoint_id',
        'status',
        'note',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function bookingPassenger(): BelongsTo
    {
        return $this->belongsTo(BookingPassenger::class);
    }

    public function itineraryCheckpoint(): BelongsTo
    {
        return $this->belongsTo(ItineraryCheckpoint::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PassengerCheckinHistory::class);
    }
}