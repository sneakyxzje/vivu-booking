<?php

namespace App\Models;

use App\Support\GioVietNam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hồ sơ năng lực của một hướng dẫn viên.
 *
 * Trả lời câu "ai phù hợp", khác với câu "ai đang rảnh" mà lịch phân công đã trả lời được.
 */
class GuideProfile extends Model
{
    protected $fillable = [
        'user_id',
        'card_number',
        'card_expiry',
        'languages',
        'regions',
        'max_group_size',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'card_expiry' => 'date',
            'languages' => 'array',
            'regions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Thẻ còn hiệu lực tới hết ngày này không.
     *
     * Chưa khai thẻ thì trả về true: không biết không phải là biết rằng sai. Chỗ gọi có trách
     * nhiệm nhắc riêng việc hồ sơ còn trống, chứ không mượn hàm này để chặn.
     */
    public function theConHan(\Carbon\Carbon $den): bool
    {
        return $this->card_expiry === null || $this->card_expiry->gte($den->copy()->startOfDay());
    }

    /** Thẻ đã hết hạn tính tới hôm nay chưa - dùng cho danh sách nhân sự, không gắn với chuyến nào. */
    public function theDaHetHan(): bool
    {
        return $this->card_expiry !== null && $this->card_expiry->lt(GioVietNam::bayGio()->startOfDay());
    }
}
