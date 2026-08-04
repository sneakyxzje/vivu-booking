<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'code',
    'name',
    'type',
    'value',
    'minimum_order_amount',
    'max_discount_amount',
    'usage_limit',
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
