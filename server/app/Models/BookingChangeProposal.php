<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingChangeProposal extends Model
{
    protected $fillable = [
        'booking_id',
        'admin_id',
        'reason',
        'options',
        'response_deadline',
        'fallback_action',
        'status',
        'customer_choice',
        'customer_note',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'response_deadline' => 'datetime',
            'responded_at' => 'datetime',
            'status' => ProposalStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProposalStatus::Pending->value);
    }
}
