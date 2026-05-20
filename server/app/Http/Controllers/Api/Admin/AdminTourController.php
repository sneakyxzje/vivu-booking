<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminTourController extends Controller
{
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->success([], 'Placeholder: Admin approve tour endpoint for tour ' . $id);
    }
}
