<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'chunk_key',
    'source_type',
    'source_id',
    'title',
    'content',
    'content_hash',
    'embedding',
    'dimensions',
    'indexed_at',
])]
class KnowledgeChunk extends Model
{
    protected $table = 'ai_knowledge_chunks';

    protected function casts(): array
    {
        return [
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * Đóng gói vector để lưu.
     *
     * `g` là float 32 bit, byte nhỏ trước — khai rõ thứ tự byte chứ không dùng `f`
     * (theo máy đang chạy). Sai thứ tự byte vẫn giải mã ra dãy số hợp lệ, chỉ là
     * toàn số vô nghĩa và không có lỗi nào được ném ra.
     *
     * @param  array<int, float>  $vector
     */
    public static function packEmbedding(array $vector): string
    {
        return base64_encode(pack('g*', ...array_map('floatval', $vector)));
    }

    /** @return array<int, float> */
    public function vector(): array
    {
        if (!$this->embedding) {
            return [];
        }

        $raw = base64_decode($this->embedding, true);

        if ($raw === false) {
            return [];
        }

        return array_values(unpack('g*', $raw) ?: []);
    }
}
