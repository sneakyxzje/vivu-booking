<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'address', 'avatar', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    public function tours()
    {
        return $this->hasMany(Tour::class, 'admin_id');
    }

    /** Các chuyến người này được phân công dẫn. Một chuyến có thể có nhiều hướng dẫn viên. */
    public function assignedSchedules()
    {
        return $this->belongsToMany(
            TourSchedule::class,
            'tour_schedule_guides',
            'guide_id',
            'tour_schedule_id',
        )->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}