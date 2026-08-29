<?php

namespace App\Models;

use App\Enums\TransferReasonCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTransfer extends Model
{
    protected $fillable = [
        'booking_id',
        'from_schedule_id',
        'to_schedule_id',
        'from_tour_id',
        'to_tour_id',
        'initiated_by',
        'contact_log_id',
        'reason_category',
        'price_difference',
        'fee',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_category' => TransferReasonCategory::class,
            'price_difference' => 'decimal:2',
            'fee' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /** Cuộc trao đổi với khách làm căn cứ cho lần chuyển này. */
    public function contactLog(): BelongsTo
    {
        return $this->belongsTo(CustomerContactLog::class, 'contact_log_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function fromSchedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'from_schedule_id');
    }

    public function toSchedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'to_schedule_id');
    }

    /** Đặt tên khác cột khóa ngoại, nếu không object người dùng đè lên chính cột id. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
