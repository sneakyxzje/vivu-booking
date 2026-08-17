<?php

namespace App\Models;

use App\Enums\GroupRequestStatus;
use App\Support\GioVietNam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Yêu cầu booking theo đoàn - giai đoạn thương lượng, TRƯỚC khi có đơn hàng.
 *
 * Chưa phải là đơn: chưa chiếm chỗ của chuyến, chưa có nghĩa vụ tiền. Chỉ khi điều hành chốt thì
 * mới sinh một `Booking` thật (trỏ ngược về đây qua `group_booking_request_id`), và từ đó mọi
 * nghiệp vụ - điểm danh, hủy, chuyển - đi trên đơn như mọi đơn khác.
 */
class GroupBookingRequest extends Model
{
    protected $fillable = [
        'public_token',
        'tour_id',
        'tour_schedule_id',
        'customer_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'estimated_guests',
        'company_name',
        'tax_code',
        'invoice_address',
        'note',
        'status',
        'quoted_price_per_person',
        'quoted_free_slots',
        'quote_note',
        'quote_expires_at',
        'quoted_at',
        'quoted_by',
        'rejected_reason',
        'decided_at',
        'decided_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupRequestStatus::class,
            'quote_expires_at' => 'datetime',
            'quoted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Cột thời gian ở đây lưu giờ Việt Nam dạng mộc, trả về đúng như đã lưu - cùng quy ước với
     * `TourSchedule::serializeDate()`. Không format thế này thì hạn báo giá bị quy về UTC và
     * lệch 7 tiếng trên màn hình.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /** Báo giá đã hết hiệu lực chưa. Suy ra lúc đọc, không phải một trạng thái được lưu. */
    public function quoteExpired(): bool
    {
        return $this->quote_expires_at !== null
            && GioVietNam::bayGio()->gte($this->quote_expires_at);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TourSchedule::class, 'tour_schedule_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Đơn hàng sinh ra khi chốt. Rỗng khi chưa chốt. */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'group_booking_request_id');
    }

    public function quotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }
}
