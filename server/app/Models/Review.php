<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'rating' => 'integer',
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
}