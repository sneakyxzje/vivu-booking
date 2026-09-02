<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\Booking;

#[Fillable([
    'code',
    'name',
    'type',
    'value',
    'minimum_order_amount',
    'max_discount_amount',
    'usage_limit',
    'per_customer_limit',
    'used_count',
    'starts_at',
    'expires_at',
    'is_active',
])]
class DiscountCode extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'float',
            'minimum_order_amount' => 'float',
            'max_discount_amount' => 'float',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function isUsableFor(float $orderAmount): bool
    {
        $now = Carbon::now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lt($now)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return $orderAmount >= (float) $this->minimum_order_amount;
    }

    /**
     * Khách này còn lượt dùng mã hay đã hết phần của mình.
     *
     * `usage_limit` đếm tổng lượt của cả mã, không đếm theo người: một mã "giảm 500k cho khách mới"
     * phát 100 lượt có thể bị đúng một người dùng cả 100 lần. Đây là phép đếm còn thiếu.
     *
     * Nhận diện theo tài khoản nếu có, ngược lại theo địa chỉ thư đã dùng khi đặt — khách vãng lai
     * không có tài khoản để đếm. Không phải hàng rào kín (đổi email là có lượt mới), nhưng nó chặn
     * đúng chuyện hay xảy ra: cùng một người đặt liên tiếp mấy đơn bằng một mã.
     *
     * Chỉ đếm đơn còn sống. Đơn bị hủy đã hoàn lượt qua `releaseDiscountUsage()`, nên tính cả chúng
     * là trừ hai lần vào cùng một lượt.
     */
    public function conLuotCho(?int $customerId, ?string $email): bool
    {
        if ($this->per_customer_limit === null) {
            return true;
        }

        $daDung = Booking::query()
            ->where('discount_code_id', $this->getKey())
            ->whereNotIn('status', ['cancelled', 'transferred'])
            ->where(function ($q) use ($customerId, $email) {
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }

                if ($email) {
                    $q->orWhere('customer_email', $email);
                }
            })
            ->count();

        return $daDung < (int) $this->per_customer_limit;
    }

    public function calculateDiscount(float $orderAmount): float
    {
        if ($this->type === 'percent') {
            $discount = $orderAmount * ((float) $this->value / 100);
        } else {
            $discount = (float) $this->value;
        }

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return min($orderAmount, max(0, round($discount, 2)));
    }
}
