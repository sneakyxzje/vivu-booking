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

        $numberOfDay = (int) $validated['number_of_days'];
        $numberOfNight = (int) $validated['number_of_nights'];
        $price = (float) $validated['price'];
        $salePrice = isset($validated['discount_price'])
            ? (float) $validated['discount_price']
            : null;

        if ($numberOfNight > $numberOfDay) {
            return $this->error('Số đêm không được lớn hơn số ngày', 400);
        }

        if ($salePrice !== null && $salePrice > $price) {
            return $this->error('Giá giảm không được lớn hơn giá gốc', 400);
        }

        $tour = Tour::create([
            ...$validated,
            'admin_id' => $request->user()->id,
            'status' => 'active',
            'is_featured' => false,
            'slug' => Tour::query()->where('title', $validated['title'])->count() ? Str::slug($validated['title']) . '-' . time() : Str::slug($validated['title']),
        ]);

        return $this->success([
            'tour' => $tour,
        ], 'Tạo tour thành công và đã được kích hoạt');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->success([], 'Placeholder: Admin approve tour endpoint for tour ' . $id);
    }
}
