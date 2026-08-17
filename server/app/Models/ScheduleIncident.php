<?php

namespace App\Models;

use App\Enums\CostBearer;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleIncident extends Model
{
    protected $fillable = [
        'tour_schedule_id',
        'tour_itinerary_id',
        'type',
        'severity',
        'status',
        'occurred_at',
        'reported_late',
        'description',
        'reported_by',
        'resolution',
        'cost_delta',
        'who_bears',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'who_bears' => CostBearer::class,
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'reported_late' => 'boolean',
            'cost_delta' => 'decimal:2',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(TourItinerary::class, 'tour_itinerary_id');
    }

    /** Hướng dẫn viên báo cáo. Người này không quyết chi phí. */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class, 'schedule_incident_id');
    }

    public function surcharges(): HasMany
    {
        return $this->hasMany(BookingSurcharge::class, 'schedule_incident_id');
    }
}
