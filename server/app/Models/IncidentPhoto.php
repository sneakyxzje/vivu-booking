<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ảnh hiện trường và ảnh biên bản có xác nhận của khách. */
class IncidentPhoto extends Model
{
    protected $fillable = [
        'schedule_incident_id',
        'image_path',
        'caption',
        'uploaded_by',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(ScheduleIncident::class, 'schedule_incident_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
