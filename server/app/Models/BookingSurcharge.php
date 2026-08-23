<?php

namespace App\Models;

use App\Enums\CostBearer;
use App\Enums\SurchargeKind;
use App\Enums\SurchargeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Khoản khách trả thêm hoặc được hoàn, sinh ra từ một sự cố cụ thể. */
class BookingSurcharge extends Model
{
    protected $fillable = [
        'booking_id',
        'schedule_incident_id',
        'kind',
        // Ai chịu khoản NÀY. Sự cố cũng có cột cùng tên nhưng ở đó nó chỉ là gợi ý mặc định.
        'who_bears',
        'amount',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'customer_consent_at',
        'consent_note',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SurchargeKind::class,
            'who_bears' => CostBearer::class,
            'status' => SurchargeStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'customer_consent_at' => 'datetime',
        ];
    }

    /**
     * Nhãn tiếng Việt đi kèm khi trả về cho giao diện.
     *
     * Ghép ở đây chứ không để mỗi màn tự ánh xạ: đơn của khách, màn xử lý sự cố và màn quản trị
     * đơn cùng đọc bảng này, mà ba chỗ tự dịch thì sớm muộn gọi cùng một trạng thái bằng ba tên.
     */
    protected $appends = ['kind_label', 'status_label', 'who_bears_label'];

    /** Cột nội bộ, khách không cần và không nên thấy. */
    protected $hidden = ['approved_by', 'schedule_incident_id'];

    public function getKindLabelAttribute(): string
    {
        return $this->kind->label();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getWhoBearsLabelAttribute(): ?string
    {
        return $this->who_bears?->label();
    }

    /** Khoản đã có hiệu lực với khách: đã duyệt hoặc đã thu. */
    public function scopeCoHieuLuc(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SurchargeStatus::Approved->value,
            SurchargeStatus::Paid->value,
        ]);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(ScheduleIncident::class, 'schedule_incident_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
