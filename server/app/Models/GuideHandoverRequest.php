<?php

namespace App\Models;

use App\Enums\HandoverRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hướng dẫn viên xin được bàn giao đoàn. Không có cột người thay: đó là việc của điều hành. */
class GuideHandoverRequest extends Model
{
    protected $fillable = [
        'tour_schedule_id',
        'requested_by',
        'status',
        'reason',
        'group_state',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'guide_handover_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => HandoverRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeDangCho(Builder $query): Builder
    {
        return $query->where('status', HandoverRequestStatus::Pending->value);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Biên bản sinh ra khi duyệt. Null khi chưa duyệt hoặc bị từ chối. */
    public function handover(): BelongsTo
    {
        return $this->belongsTo(GuideHandover::class, 'guide_handover_id');
    }
}
