<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class ValidPhone implements ValidationRule
{
    /**
     * Xác thực số điện thoại hỗ trợ cả số Việt Nam và quốc tế.
     * Cho phép dấu + ở đầu, dài 8-20 ký tự số. Khoảng trắng, gạch ngang hợp lệ.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Loại bỏ khoảng trắng và gạch ngang trước khi kiểm tra
        $cleanedPhone = preg_replace('/[\s\-]/', '', $value);

        if (!preg_match('/^\+?[0-9]{8,20}$/', $cleanedPhone)) {
            $fail('Số điện thoại không hợp lệ (hỗ trợ 8-20 ký tự số, có thể bắt đầu bằng dấu +).');
        }
    }
}
