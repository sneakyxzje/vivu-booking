<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\ContactOutcome;
use App\Enums\ContactPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lần công ty liên hệ khách về một đơn cụ thể.
 *
 * Bản ghi này **không sửa và không xóa** - không có màn hình nào mở đường cho việc đó. Nhật ký liên
 * hệ chỉ có giá trị khi nó là thứ đã xảy ra; cho sửa thì nó thành thứ người ta muốn nó là.
 */
class CustomerContactLog extends Model
{
    protected $fillable = [
        'booking_id',
        'channel',
        'purpose',
        'outcome',
        'note',
        'contacted_by',
        'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ContactChannel::class,
            'purpose' => ContactPurpose::class,
            'outcome' => ContactOutcome::class,
            'contacted_at' => 'datetime',
        ];
    }

    /** Mốc nghiệp vụ, lưu giờ Việt Nam dạng mộc như mọi cột ngày giờ khác. */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function contactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contacted_by');
    }

    /** Các lần chuyển chuyến lấy cuộc trao đổi này làm căn cứ. Nhiều nhất là một. */
    public function transfers(): HasMany
    {
        return $this->hasMany(BookingTransfer::class, 'contact_log_id');
    }

    /** Có phải là một cái gật đầu cho việc chuyển chuyến hay không. */
    public function laSuDongYChuyenChuyen(): bool
    {
        return $this->purpose === ContactPurpose::Transfer
            && $this->outcome === ContactOutcome::Agreed;
    }
}
