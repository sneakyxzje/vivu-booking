<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bút toán trên sổ giao dịch của đơn hàng - phần lõi của điểm 12.
 *
 * Chỉ thêm dòng, không sửa dòng cũ: ghi nhầm thì ghi một dòng điều chỉnh ngược lại. Số đã thu là
 * TỔNG của sổ chứ không phải một cột bị ghi đè, vì tiền là thứ phải đối soát được từng bước -
 * "đơn này đã thu 30 triệu" phải trả lời được câu "thu những lần nào, ai ghi".
 *
 * Số tiền luôn dương; `kind` quyết định dấu (deposit/balance cộng, refund trừ). Không lưu số âm
 * để không bao giờ phải đoán -5.000.000 nghĩa là hoàn hay là ghi nhầm.
 */
class BookingPayment extends Model
{
    public const THU = ['deposit', 'balance'];
    public const HOAN = 'refund';

    protected $fillable = [
        'booking_id',
        'kind',
        'amount',
        'method',
        'reference',
        'note',
        'paid_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /** Giờ Việt Nam dạng mộc, cùng quy ước với các cột thời gian nghiệp vụ khác. */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'deposit' => 'Tiền cọc',
            'balance' => 'Thanh toán phần còn lại',
            'refund' => 'Hoàn tiền',
            default => $this->kind,
        };
    }
}
