<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | VNPay
    |--------------------------------------------------------------------------
    |
    | Bốn giá trị này trước đây được đọc thẳng bằng `env()` trong `VNPayService` và trong hàm kiểm
    | chữ ký ở `BookingController`. Đó là một quả bom hẹn giờ: `env()` trả về null ngay khi cấu
    | hình được nạp sẵn bằng `config:cache` — bước gần như bắt buộc khi triển khai — và khi ấy
    | `hasValidVnpaySignature()` không báo lỗi gì cả, nó chỉ lặng lẽ trả về false.
    |
    | Hậu quả: mọi lượt khách trả tiền quay về đều bị coi là thất bại, đơn tự chuyển sang đã hủy
    | và chỗ được nhả ra — trong khi tiền đã nằm trong tài khoản công ty.
    |
    */

    'vnpay' => [
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
    ],

];
