<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lời nhắn gửi qua trang liên hệ.
 *
 * Không có đường sửa nội dung: đây là chữ của người ngoài gửi tới, và sửa nó đi thì bản ghi không
 * còn là bằng chứng về điều họ đã nói. Điều hành chỉ thêm được ghi chú xử lý.
 */
class ContactMessage extends Model
{
    public const CHUA_XU_LY = 'new';
    public const DA_XU_LY = 'handled';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'handled_at',
        'handled_by',
        'handling_note',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
