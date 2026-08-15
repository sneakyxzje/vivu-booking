<?php

namespace App\Models;

use App\Enums\BookingAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAuditLog extends Model
{
    protected $fillable = [
        'booking_id',
        'actor_id',
        'actor_role',
        'action',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'action' => BookingAuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Đặt tên khác cột actor_id, nếu không object người dùng sẽ đè lên chính cột id. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
