<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItineraryCheckpoint extends Model
{
    protected $fillable = [
        'tour_itinerary_id',
        'name',
        'description',
        'latitude',
        'longitude',
        'sequence',
        'is_required_photo',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_required_photo' => 'boolean',
    ];

    public function tourItinerary(): BelongsTo
    {
        return $this->belongsTo(TourItinerary::class);
    }

    public function passengerCheckins(): HasMany
    {
        return $this->hasMany(PassengerCheckin::class);
    }

    public function checkpointPhotos(): HasMany
    {
        return $this->hasMany(CheckpointPhoto::class);
    }
}