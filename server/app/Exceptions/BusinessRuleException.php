<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lỗi vi phạm quy tắc nghiệp vụ, ném từ tầng dịch vụ.
 *
 * Lý do cần lớp này: các quy tắc chặn như "chuyến đang chạy thì không hủy đơn" phải kiểm tra
 * ở tầng dịch vụ chứ không phải trong từng controller, vì có nhiều lối vào cùng gọi tới
 * (khách tự hủy, quản trị hủy, tác vụ nền, chuyển chuyến). Ném exception cho phép đặt kiểm tra
 * một chỗ duy nhất mà mọi lối vào đều phải đi qua.
 *
 * Có sẵn render() nên Laravel tự trả JSON đúng định dạng chung của API, controller không cần bắt.
 */
class BusinessRuleException extends Exception
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
