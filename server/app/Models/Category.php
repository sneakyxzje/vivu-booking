<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'description'])]
class Category extends Model
{
    public function tours()
    {
        return $this->belongsToMany(Tour::class, 'category_tour');
    }
}
