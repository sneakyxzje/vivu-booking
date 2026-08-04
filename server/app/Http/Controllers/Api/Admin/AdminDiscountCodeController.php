<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminDiscountCodeController extends Controller
{
    public function index(): JsonResponse
    {
        $discountCodes = DiscountCode::query()
            ->latest()
            ->paginate(10);

        return $this->success($discountCodes, 'Lấy danh sách mã giảm giá thành công');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['code'] = Str::upper($validated['code']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $discountCode = DiscountCode::create($validated);

        return $this->success($discountCode, 'Tạo mã giảm giá thành công', 201);
    }

    public function show(int $id): JsonResponse
    {
        $discountCode = DiscountCode::find($id);

        if (! $discountCode) {
            return $this->error('Không tìm thấy mã giảm giá', 404);
        }

        return $this->success($discountCode, 'Lấy chi tiết mã giảm giá thành công');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $discountCode = DiscountCode::find($id);

        if (! $discountCode) {
            return $this->error('Không tìm thấy mã giảm giá', 404);
        }

        $validated = $this->validatePayload($request, $discountCode->id);
        $validated['code'] = Str::upper($validated['code']);

        $discountCode->update($validated);

        return $this->success($discountCode, 'Cập nhật mã giảm giá thành công');
    }

    public function destroy(int $id): JsonResponse
    {
        $discountCode = DiscountCode::find($id);

        if (! $discountCode) {
            return $this->error('Không tìm thấy mã giảm giá', 404);
        }

        $discountCode->delete();

        return $this->success(null, 'Xóa mã giảm giá thành công');
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('discount_codes', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
