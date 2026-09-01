<?php

namespace App\Models;

use App\Enums\ScheduleAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Một dòng nhật ký ở mức chuyến khởi hành. Ghi rồi là xong, không sửa và không xóa.
 *
 * Bất biến là toàn bộ giá trị của bảng này: nó là cơ chế kiểm soát duy nhất đặt lên quyền dời hạn
 * chốt của quản trị viên. Một dòng sửa được thì người bị nó ghi lại cũng là người sửa được nó.
 */
class ScheduleAuditLog extends Model
{
    /**
     * Chặn sửa và xóa ngay ở tầng model.
     *
     * Hôm nay không có màn hình nào làm hai việc ấy, nhưng "hôm nay không có" là một sự thật về
     * hiện trạng chứ không phải một ràng buộc. Bất biến mà chỉ dựa vào việc chưa ai viết thêm mã
     * thì tới lúc có người viết, không gì báo lại cả.
     */
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new RuntimeException(
                'Nhật ký chuyến khởi hành là bất biến: không sửa được dòng đã ghi.',
            );
        });

        static::deleting(static function (): never {
            throw new RuntimeException(
                'Nhật ký chuyến khởi hành là bất biến: không xóa được dòng đã ghi.',
            );
        });
    }

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
