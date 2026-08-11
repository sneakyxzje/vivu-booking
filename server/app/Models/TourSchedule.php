<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model lịch khởi hành.
 *
 * Vòng đời đầy đủ: open → closed → confirmed → in_progress → completed
 *                                              ↘ cancelled
 *
 * Tài liệu: docs/nghiep-vu/01-tac-nhan-va-vong-doi.md §4
 *
 * @property int $id
 * @property int $tour_id
 * @property int|null $guide_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property int $max_people
 * @property int $min_people
 * @property \Illuminate\Support\Carbon|null $booking_deadline
 * @property int $booked_people
 * @property ScheduleStatus $status
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancelled_reason
 * @property int|null $merged_into_schedule_id
 * @property bool $is_private
 */
class TourSchedule extends Model
{
    protected $fillable = [
        'tour_id',
        'guide_id',
        'start_date',
        'end_date',
        'max_people',
        'min_people',
        'booking_deadline',
        'booked_people',
        'status',
        'confirmed_at',
        'cancelled_at',
        'cancelled_by',
        'cancelled_reason',
        'merged_into_schedule_id',
        'is_private',
    ];

    protected $casts = [
        'start_date'      => 'datetime',
        'end_date'        => 'datetime',
        'booking_deadline' => 'datetime',
        'confirmed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'status'          => ScheduleStatus::class,
        'is_private'      => 'boolean',
    ];

    // ─── Quan hệ ────────────────────────────────────────────────────────────

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guide_id');
    }

    /** Người đã ra lệnh hủy chuyến (admin/operator). */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Chuyến cha nếu chuyến này đã bị ghép vào chuyến khác. */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_schedule_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    /** Chỉ lấy các chuyến đang mở bán (khách được đặt). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ScheduleStatus::Open->value);
    }

    /** Các chuyến sắp khởi hành (confirmed hoặc open, start_date trong tương lai). */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [ScheduleStatus::Open->value, ScheduleStatus::Confirmed->value])
            ->where('start_date', '>', now());
    }

    /** Các chuyến đang chạy (đoàn đang đi). */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', ScheduleStatus::InProgress->value);
    }

    // ─── Helper methods ──────────────────────────────────────────────────────

    /**
     * Chuyến có thể nhận đặt chỗ mới không?
     * Chỉ true khi status = open VÀ còn chỗ VÀ chưa qua hạn chốt.
     */
    public function isBookable(): bool
    {
        $status = $this->status instanceof ScheduleStatus
            ? $this->status
            : ScheduleStatus::tryFrom((string) $this->status) ?? ScheduleStatus::Open;

        if (!$status->isBookable()) {
            return false;
        }

        if ($this->remainingSeats() <= 0) {
            return false;
        }

        if ($this->booking_deadline && now()->gte($this->booking_deadline)) {
            return false;
        }

        return true;
    }

    /** Số chỗ còn trống. */
    public function remainingSeats(): int
    {
        return max(0, (int) $this->max_people - (int) $this->booked_people);
    }

    /** Đoàn đang đi hay không. */
    public function isRunning(): bool
    {
        $status = $this->status instanceof ScheduleStatus
            ? $this->status
            : ScheduleStatus::tryFrom((string) $this->status) ?? ScheduleStatus::Open;

        return $status->isRunning();
    }

    /**
     * Trạng thái không thể sửa thông tin vận hành (min_people, booking_deadline...).
     * Dùng để guard trong AdminTourController::update().
     */
    public function isOperationallyLocked(): bool
    {
        $status = $this->status instanceof ScheduleStatus
            ? $this->status
            : ScheduleStatus::tryFrom((string) $this->status) ?? ScheduleStatus::Open;

        return in_array($status, [
            ScheduleStatus::InProgress,
            ScheduleStatus::Completed,
            ScheduleStatus::Cancelled,
        ], true);
    }
}