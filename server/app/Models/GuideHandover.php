<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Biên bản bàn giao hướng dẫn viên giữa chừng chuyến. Chỉ ghi thêm, không sửa. */
class GuideHandover extends Model
{
    protected $fillable = [
        'tour_schedule_id',
        'from_guide_id',
        'to_guide_id',
        'handed_over_at',
        'reason',
        'handover_note',
        'is_emergency_cover',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'handed_over_at' => 'datetime',
            'is_emergency_cover' => 'boolean',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function fromGuide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_guide_id');
    }

    public function toGuide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_guide_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
