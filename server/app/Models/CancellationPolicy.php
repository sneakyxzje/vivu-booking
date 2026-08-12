<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'is_default',
])]
class CancellationPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Các bậc phí, sắp từ xa tới gần ngày khởi hành.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(CancellationPolicyRule::class)->orderByDesc('min_hours_before');
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    /**
     * Chính sách áp dụng khi tour chưa chọn riêng.
     * Trả về null nếu chưa seed chính sách nào, khi đó lớp dịch vụ dùng bảng phí mặc định.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->with('rules')->first();
    }
}
