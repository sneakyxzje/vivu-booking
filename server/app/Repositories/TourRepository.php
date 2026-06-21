<?php

namespace App\Repositories;

use App\Models\Tour;

class TourRepository
{
    public function create(array $data)
    {
        return Tour::create($data);
    }
}
