<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $guides
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
    use HasFactory;
    protected $fillable = [
        'tour_id',
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
        // Cast sang enum để mọi nơi đọc $schedule->status đều nhận đúng kiểu, thay vì
        // phải tự chuẩn hóa chuỗi ở từng chỗ dùng.
        'status'           => ScheduleStatus::class,
        'start_date'       => 'datetime',
        'end_date'         => 'datetime',
        'booking_deadline' => 'datetime',
        'confirmed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
        'is_private'       => 'boolean',
    ];

    /**
     * Trả về chuỗi ngày giờ mộc, không kèm hậu tố múi giờ.
     *
     * Ứng dụng chạy múi giờ UTC nhưng các cột ngày giờ ở đây lưu giờ Việt Nam dưới dạng mộc:
     * admin nhập 05:30 nghĩa là 05:30 giờ Việt Nam, và cột lưu đúng chuỗi đó.
     *
     * Mặc định Laravel serialize Carbon thành ISO8601 kèm hậu tố Z, tức là tuyên bố với client
     * rằng 05:30 là giờ UTC. Trình duyệt ở GMT+7 sẽ hiển thị thành 12:30, lệch 7 tiếng trên
     * mọi giờ khởi hành và hạn chốt.
     *
     * Trước khi các cột này được cast sang datetime, API trả chuỗi mộc nên client hiển thị đúng.
     * Giữ nguyên định dạng đó để không đổi hợp đồng API, trong khi phía máy chủ vẫn có Carbon
     * để so sánh.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    // ─── Quan hệ ────────────────────────────────────────────────────────────

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Các hướng dẫn viên phụ trách chuyến này.
     *
     * Nhiều người chứ không một: đoàn đông thì điểm danh ở nhiều điểm dừng cùng lúc, khách tách
     * nhóm khi tham quan, có khi thêm cả xe thứ hai. Bao nhiêu người là đủ thì điều hành quyết,
     * hệ thống không suy ra hộ từ số khách.
     */
    public function guides(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tour_schedule_guides', 'tour_schedule_id', 'guide_id')
            // accepted_at: người này đã xác nhận nhận chuyến chưa. Chưa xác nhận vẫn là đã được
            // phân công, nên quan hệ này cố ý KHÔNG lọc theo nó.
            ->withPivot('accepted_at')
            ->withTimestamps()
            ->orderBy('tour_schedule_guides.id');
    }

    /** Người này có phụ trách chuyến không. Dùng để chặn hướng dẫn viên thao tác lên chuyến của người khác. */
    public function hasGuide(int $guideId): bool
    {
        return $this->guides()->whereKey($guideId)->exists();
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'tour_schedule_id');
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
        if (!$this->status->isBookable()) {
            return false;
        }

        if ($this->remainingSeats() <= 0) {
            return false;
        }

        if ($this->booking_deadline && now()->gte($this->booking_deadline)) {
            return false;
        }

        // Chuyến chưa đặt hạn chốt thì lấy mặc định để quy tắc không im lặng bỏ qua.
        // Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 3.
        if (!$this->booking_deadline && $this->start_date && now()->gte($this->defaultBookingDeadline())) {
            return false;
        }

        return true;
    }

    /** Hạn chốt danh sách mặc định khi chuyến chưa được cấu hình riêng. */
    public function defaultBookingDeadline(): ?\Illuminate\Support\Carbon
    {
        if (!$this->start_date) {
            return null;
        }

        return $this->start_date->copy()->subDays(
            (int) config('booking.booking_deadline_days', 3)
        );
    }

    /** Số chỗ còn trống. */
    public function remainingSeats(): int
    {
        return max(0, (int) $this->max_people - (int) $this->booked_people);
    }

    /** Đoàn đang đi hay không. */
    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    /**
     * Trạng thái không thể sửa thông tin vận hành (min_people, booking_deadline...).
     * Dùng để guard trong AdminTourController::update().
     */
    public function isOperationallyLocked(): bool
    {
        return in_array($this->status, [
            ScheduleStatus::InProgress,
            ScheduleStatus::Completed,
            ScheduleStatus::Cancelled,
        ], true);
    }
}


