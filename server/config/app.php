<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Địa chỉ giao diện người dùng
    |--------------------------------------------------------------------------
    |
    | Máy chủ này chỉ có API; mọi liên kết gửi cho người dùng - thư xác nhận đơn, thư đặt lại mật
    | khẩu, chỗ VNPay trả khách về - đều phải trỏ sang ứng dụng React, không trỏ về `app.url`.
    |
    | Khai ở đây thay vì gọi `env('FRONTEND_URL')` rải rác trong mã: `env()` trả về null ngay khi
    | cấu hình được nạp sẵn bằng `config:cache`, và triệu chứng khi ấy là các liên kết trong thư
    | im lặng trỏ về sai chỗ chứ không phải một lỗi nhìn thấy được.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Múi giờ của ứng dụng.
    |
    | Đặt giờ Việt Nam chứ không để UTC. Các cột ngày giờ nghiệp vụ lưu giờ Việt Nam dưới dạng
    | mộc - điều hành gõ 07:00 nghĩa là 07:00 giờ Việt Nam - nên nếu now() trả về UTC thì mọi
    | phép so sánh với chúng lệch đúng 7 tiếng.
    |
    | Lệch đó không lộ ra trong kiểm thử, vì kiểm thử dựng dữ liệu bằng chính now() nên cả hai vế
    | cùng UTC và độ lệch triệt tiêu. Nó chỉ lộ ra khi có người thật gõ giờ từ trình duyệt: hạn
    | chốt đã qua vẫn còn bán được thêm 7 tiếng, và bậc phí hủy tính dư 7 tiếng nên khách rơi vào
    | mức hoàn cao hơn chính sách.
    |
    | Đây là công ty lữ hành nội địa, mọi mốc nghiệp vụ đều là giờ Việt Nam, nên chạy thẳng múi
    | giờ đó là đúng bản chất chứ không phải mẹo vá.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
