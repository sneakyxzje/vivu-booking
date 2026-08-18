<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hồ sơ năng lực của một hướng dẫn viên.
 *
 * Trả lời câu "ai phù hợp", khác với câu "ai đang rảnh" mà lịch phân công đã trả lời được.
 *
 * Toàn bộ hồ sơ này **chỉ để xếp thứ tự và nhắc**, không chặn ai. Luật chặn duy nhất khi phân
 * công vẫn là luật vật lý - một người không đứng ở hai đoàn cùng lúc - và nó nằm ở
 * `ScheduleGuideService::lyDoChan()`.
 */
class GuideProfile extends Model
{
    protected $fillable = [
        'user_id',
        'languages',
        'regions',
        'max_group_size',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'regions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
