<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';

    protected $fillable = [
        'tour_id',
        'user_id',
        'rating',
        'comment',
        'status',
        'moderated_at',
        'moderated_by',
        'moderation_note',
        'reply',
        'replied_at',
        'replied_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => ReviewStatus::class,
        'moderated_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Đọc được cả tour đã xóa mềm, để đánh giá cũ không mất tên tour. */
    public function tour()
    {
        return $this->belongsTo(Tour::class)->withTrashed();
    }

    public function moderatedBy()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Chỉ đánh giá đã duyệt.
     *
     * Mọi chỗ hiện đánh giá ra ngoài và mọi chỗ tính điểm trung bình đều phải đi qua scope này.
     * Thiếu nó ở một chỗ là một đánh giá chưa duyệt lọt ra công khai, hoặc — khó thấy hơn — một
     * đánh giá bị từ chối vẫn kéo điểm trung bình của tour xuống.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved->value);
    }
}
