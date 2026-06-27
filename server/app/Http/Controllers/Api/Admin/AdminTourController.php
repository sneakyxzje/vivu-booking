<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTourController extends Controller
{
    public function create(): JsonResponse
    {
        return $this->success([
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'services' => Service::orderBy('name')->get(['id', 'name']),
        ], 'Lấy dữ liệu tạo tour thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'thumbnail' => ['nullable', 'string'],
            'number_of_days' => ['required', 'integer', 'min:1'],
            'number_of_nights' => ['required', 'integer', 'min:0'],
            'start_location' => ['required', 'string', 'max:255'],
            'end_location' => ['nullable', 'string', 'max:255'],
        ]);

        $tour = Tour::create([
            ...$validated,
            'host_id' => $request->user()->id,
            'status' => 'pending',
            'is_featured' => false,
            'slug' => Tour::query()->where('title', $validated['title'])->count() ? Str::slug($validated['title']).'-'.time() : Str::slug($validated['title']),
        ]);

        return $this->success([
            'tour' => $tour,
        ], 'Tạo tour thành công');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->success([], 'Placeholder: Admin approve tour endpoint for tour ' . $id);
    }
}


