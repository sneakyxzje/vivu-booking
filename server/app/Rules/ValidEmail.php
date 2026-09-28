<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class ValidEmail implements ValidationRule
{
    /**
     * Xác thực email với regex chuẩn, loại trừ các email rác dạng test@test.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $value)) {
            $fail('Địa chỉ email không hợp lệ.');
        }
    }
}
