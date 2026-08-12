<?php

namespace App\Models;

use App\Enums\PassengerCheckinStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassengerCheckin extends Model
{
    protected $fillable = [
        'booking_passenger_id',
        'tour_schedule_id',
        'itinerary_checkpoint_id',
        'status',
        'note',
        'checked_by',
        'checked_at',
        'is_late_entry',
    ];

    protected $casts = [
        'status' => PassengerCheckinStatus::class,
        'checked_at' => 'datetime',
        'is_late_entry' => 'boolean',
    ];

    public function bookingPassenger(): BelongsTo
    {
        return $this->belongsTo(BookingPassenger::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
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