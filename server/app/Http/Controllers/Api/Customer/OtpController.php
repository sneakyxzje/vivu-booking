<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    /**
     * Khởi tạo yêu cầu gửi OTP.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', new \App\Rules\ValidEmail(), 'max:255'],
        ], [
            'email.required' => 'Vui lòng nhập email để nhận mã OTP.',
        ]);

        return $this->success(null, 'Mã OTP đã được gửi đến email của bạn.');
    }
}
