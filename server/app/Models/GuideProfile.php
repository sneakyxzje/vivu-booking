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
            /*
             * `date:Y-m-d` chứ không phải `date` trơn.
             *
             * Cast `date` để Laravel serialize Carbon thành ISO-8601 kèm hậu tố Z, tức quy về UTC:
             * thẻ hết hạn 31/12/2028 giờ Việt Nam trả về "2028-12-30T17:00:00.000000Z" — vừa lùi
             * mất một ngày, vừa không còn là một ngày nữa.
             *
             * Hậu quả nặng nhất không nằm ở chỗ hiển thị: ô `<input type="date">` chỉ nhận đúng
             * "YYYY-MM-DD", chuỗi kia bị coi là rỗng. Mở hồ sơ ra thấy ô hạn thẻ trắng, bấm lưu là
             * xóa mất hạn thẻ mà không ai biết — mất hạn thẻ nghĩa là mất luôn luật chặn duy nhất.
             *
             * Cùng lý do với `TourSchedule::serializeDate()`: cột thời gian nghiệp vụ ở dự án này
             * lưu giờ Việt Nam dạng mộc và trả về đúng như đã lưu.
             */
            'card_expiry' => 'date:Y-m-d',
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
