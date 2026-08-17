<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hướng dẫn viên từ chối một chuyến được phân công.
 *
 * Từ chối thì gỡ khỏi danh sách phân công, nên lý do phải nằm ở đây - không có bảng này thì nó
 * biến mất cùng bản ghi, và điều hành chỉ thấy chuyến tự dưng thiếu người.
 */
class GuideAssignmentDecline extends Model
{
    protected $fillable = [
        'tour_schedule_id',
        'guide_id',
        'reason',
        'declined_at',
    ];

    protected function casts(): array
    {
        return ['declined_at' => 'datetime'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guide_id');
    }
}
