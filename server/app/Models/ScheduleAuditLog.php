<?php

namespace App\Models;

use App\Enums\ScheduleAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAuditLog extends Model
{
    protected $fillable = [
        'tour_schedule_id',
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
            'action' => ScheduleAuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    /** Đặt tên khác cột actor_id, nếu không object người dùng sẽ đè lên chính cột id. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
