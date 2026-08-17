<?php

namespace App\Models;

use App\Enums\SurchargeKind;
use App\Enums\SurchargeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Khoản khách trả thêm hoặc được hoàn, sinh ra từ một sự cố cụ thể. */
class BookingSurcharge extends Model
{
    protected $fillable = [
        'booking_id',
        'schedule_incident_id',
        'kind',
        'amount',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'customer_consent_at',
        'consent_note',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SurchargeKind::class,
            'status' => SurchargeStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'customer_consent_at' => 'datetime',
        ];
    }

    /** Khoản đã có hiệu lực với khách: đã duyệt hoặc đã thu. */
    public function scopeCoHieuLuc(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SurchargeStatus::Approved->value,
            SurchargeStatus::Paid->value,
        ]);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(ScheduleIncident::class, 'schedule_incident_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
