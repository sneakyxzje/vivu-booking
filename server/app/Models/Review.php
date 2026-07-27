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

    /**
     * Một đánh giá thuộc một tour
     */
    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Một đánh giá thuộc một người dùng
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}