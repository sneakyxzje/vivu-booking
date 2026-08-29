<?php

namespace App\Models;

use App\Support\GioVietNam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'name',
    'description',
    'effective_from',
])]
class CancellationPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
        ];
    }

    /**
     * `effective_from` là mốc nghiệp vụ nên lưu **giờ Việt Nam dạng mộc**, giống `start_date` và
     * `booking_deadline`. Xem chú thích đầy đủ ở `TourSchedule::serializeDate` và `GioVietNam`.
     *
     * Thiếu chỗ này thì Laravel gắn hậu tố Z vào chuỗi trả về, trình duyệt ở GMT+7 cộng thêm 7
     * tiếng, và mốc hiệu lực người ta hẹn 0h mùng 1 hiện ra thành 7h mùng 1.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Các bậc phí, sắp từ xa tới gần ngày khởi hành.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(CancellationPolicyRule::class)->orderByDesc('min_days_before');
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    /**
     * Bản đang có hiệu lực tại một thời điểm.
     *
     * Là bản có `effective_from` gần nhất mà **chưa vượt quá** thời điểm hỏi. Bản hẹn cho tương lai
     * nằm im trong bảng cho tới đúng giờ của nó, không cần ai bật cờ hay chạy tác vụ nền.
     *
     * Nhận `$luc` để nơi gọi hỏi được "lúc đó bản nào đang áp dụng" - dùng khi cần dựng lại bối
     * cảnh của một đơn cũ. Mặc định là bây giờ.
     *
     * Trả về null nếu chưa có bản nào tới hiệu lực, khi đó lớp dịch vụ dùng bảng phí viết trong mã.
     */
    public static function dangApDung(?Carbon $luc = null): ?self
    {
        return static::query()
            ->where('effective_from', '<=', $luc ?? GioVietNam::bayGio())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->with('rules')
            ->first();
    }

    /**
     * Bản mới nhất, kể cả bản đã hẹn mà chưa tới giờ.
     *
     * Đây là bản màn hình quản trị mở ra để sửa: người vừa hẹn một bảng phí cho tháng sau cần thấy
     * lại đúng thứ họ vừa nhập, chứ không phải bản cũ đang chạy.
     */
    public static function moiNhat(): ?self
    {
        return static::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->with('rules')
            ->first();
    }

    /** Đã tới giờ áp dụng chưa. */
    public function daCoHieuLuc(?Carbon $luc = null): bool
    {
        return $this->effective_from !== null
            && $this->effective_from->lessThanOrEqualTo($luc ?? GioVietNam::bayGio());
    }
}
