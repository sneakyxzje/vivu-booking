<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hợp đồng du lịch của một đơn. Một đơn một hợp đồng, số cấp một lần rồi cố định. */
class BookingContract extends Model
{
    protected $fillable = [
        'booking_id',
        'contract_number',
        'issued_at',
        'issued_by',
        'signed_at',
        'signed_note',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
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

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function daKy(): bool
    {
        return $this->signed_at !== null;
    }
}
