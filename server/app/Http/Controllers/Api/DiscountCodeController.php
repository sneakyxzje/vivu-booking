<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiscountCodeController extends Controller
{
    /**
     * Kiểm một mã giảm giá trước khi khách bấm đặt.
     *
     * Điểm cuối này chỉ **xem trước**; con số cuối cùng do lượt tạo đơn quyết, vì mã có thể hết
     * lượt trong lúc khách còn đang điền thông tin. Nhưng hai bên phải trả lời giống nhau tại cùng
     * một thời điểm — nếu không, khách thấy "giảm 400.000đ" ở bước xem giá rồi bị tính giá gốc ở
     * bước cuối mà không hiểu vì sao.
     */
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'order_amount' => ['required', 'numeric', 'min:0'],
            /*
             * Địa chỉ thư khách đang điền ở form đặt tour, không bắt buộc.
             *
             * Cần nó để kiểm luôn giới hạn theo NGƯỜI. `usage_limit` đếm tổng lượt của cả mã, còn
             * `per_customer_limit` đếm theo từng khách — và lượt tạo đơn có kiểm cái thứ hai
             * (`DiscountCode::conLuotCho`) trong khi màn xem trước thì không. Hệ quả: khách đã dùng
             * hết phần của mình vẫn thấy báo "áp dụng thành công", rồi đơn tạo ra theo giá gốc.
             */
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $discountCode = DiscountCode::query()
            ->where('code', Str::upper($validated['code']))
            ->first();

        $orderAmount = (float) $validated['order_amount'];

        if (! $discountCode || ! $discountCode->isUsableFor($orderAmount)) {
            return $this->error('Mã giảm giá không hợp lệ hoặc không còn khả dụng', 422);
        }

        // Cùng phép đếm mà lượt tạo đơn dùng, nên hai bước không nói ngược nhau nữa.
        $khachDangDangNhap = auth('sanctum')->user();

        if (! $discountCode->conLuotCho($khachDangDangNhap?->id, $validated['email'] ?? null)) {
            return $this->error(
                sprintf(
                    'Mã %s đã được dùng đủ số lần cho phép với một khách hàng.',
                    $discountCode->code,
                ),
                422,
            );
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
