<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingChangeRequest extends Model
{
    protected $fillable = [
        'booking_id',
        'type',
        'payload',
        'estimated_refund',
        'estimated_refund_percent',
        'status',
        'requested_by',
        'requested_email',
        'request_note',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChangeRequestType::class,
            'status' => ChangeRequestStatus::class,
            'payload' => 'array',
            'estimated_refund' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /*
     * Đặt tên quan hệ khác tên cột khóa ngoại.
     *
     * Gọi là requestedBy() thì Eloquent tuần tự hóa quan hệ thành khóa "requested_by", đè lên
     * chính cột id cùng tên: JSON trả về một object người dùng ở chỗ đáng lẽ là số. Không có
     * lỗi nào được ném ra, chỉ có phía client đọc sai kiểu.
     */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ChangeRequestStatus::Pending->value);
    }
}
