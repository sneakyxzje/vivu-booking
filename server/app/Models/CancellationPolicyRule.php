<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bậc trong bảng phí hủy.
 *
 * min_hours_before và max_hours_before là khoảng thời gian còn lại tới lúc khởi hành, tính bằng
 * giờ. Để trống max_hours_before nghĩa là bậc xa nhất, không có giới hạn trên.
 */
#[Fillable([
    'cancellation_policy_id',
    'min_hours_before',
    'max_hours_before',
    'refund_percent',
    'note',
])]
class CancellationPolicyRule extends Model
{
    protected function casts(): array
    {
        return [
            'min_hours_before' => 'integer',
            'max_hours_before' => 'integer',
            'refund_percent' => 'integer',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'cancellation_policy_id');
    }

    /** Nhãn khoảng thời gian để hiển thị, ví dụ "Từ 15 ngày trở lên". */
    public function windowLabel(): string
    {
        $tuNgay = (int) floor($this->min_hours_before / 24);

        if ($this->max_hours_before === null) {
            return "Từ {$tuNgay} ngày trở lên";
        }

        $denNgay = (int) floor($this->max_hours_before / 24);

        if ($this->min_hours_before === 0) {
            return $denNgay > 0
                ? "Dưới {$denNgay} ngày"
                : "Dưới {$this->max_hours_before} giờ";
        }

        return "Từ {$tuNgay} đến dưới {$denNgay} ngày";
    }
}
