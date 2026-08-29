<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bậc trong bảng phí hủy.
 *
 * `min_days_before` và `max_days_before` là số ngày còn lại tới lúc khởi hành. Để trống
 * `max_days_before` nghĩa là bậc xa nhất, không có giới hạn trên.
 *
 * Khoảng đóng ở dưới, mở ở trên: bậc 8-15 nhận đúng mốc 8 ngày và nhường mốc 15 ngày cho bậc trên.
 * Nhờ vậy các bậc nối liền nhau mà không chồng lên nhau, và không có khe hở nào rơi ra ngoài.
 */
#[Fillable([
    'cancellation_policy_id',
    'min_days_before',
    'max_days_before',
    'refund_percent',
    'note',
])]
class CancellationPolicyRule extends Model
{
    protected function casts(): array
    {
        return [
            'min_days_before' => 'integer',
            'max_days_before' => 'integer',
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
        if ($this->max_days_before === null) {
            return "Từ {$this->min_days_before} ngày trở lên";
        }

        if ($this->min_days_before === 0) {
            return "Dưới {$this->max_days_before} ngày";
        }

        return "Từ {$this->min_days_before} đến dưới {$this->max_days_before} ngày";
    }
}
