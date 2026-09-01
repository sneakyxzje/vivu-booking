<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'booking_id',
    'name',
    'gender',
    'type',
    'date_of_birth',
    'identity_number',
    'id_type',
    'nationality',
    'phone',
    'special_request',
    'is_contact',
    'note',
])]
class BookingPassenger extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_contact' => 'boolean',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Tuổi tại ngày khởi hành, không phải tuổi hôm nay.
     *
     * Một bé sinh nhật trước ngày đi thì đi với tư cách lứa tuổi mới, và giá vé cũng theo đó.
     * Tính theo hôm nay sẽ xếp sai loại cho những trường hợp đặt trước vài tháng.
     */
    public function ageAtDeparture(?string $departureDate = null): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        $moc = $departureDate ?? $this->booking?->departure_date;

        if (!$moc) {
            return null;
        }

        return $this->date_of_birth->diffInYears($moc);
    }
}
