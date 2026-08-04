<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiscountCodeController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'order_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $discountCode = DiscountCode::query()
            ->where('code', Str::upper($validated['code']))
            ->first();

        $orderAmount = (float) $validated['order_amount'];

        if (! $discountCode || ! $discountCode->isUsableFor($orderAmount)) {
            return $this->error('Mã giảm giá không hợp lệ hoặc không còn khả dụng', 422);
        }

        $discountAmount = $discountCode->calculateDiscount($orderAmount);

        return $this->success([
            'code' => $discountCode->code,
            'name' => $discountCode->name,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $orderAmount - $discountAmount),
        ], 'Áp dụng mã giảm giá thành công');
    }
}
