<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Thời gian giữ chỗ chờ thanh toán
    |--------------------------------------------------------------------------
    |
    | Đơn đặt tour ở trạng thái pending sẽ giữ chỗ trong số phút này.
    | Quá hạn mà chưa thanh toán, đơn tự hủy và chỗ được trả lại cho
    | khách khác.
    |
    */

    'payment_ttl_minutes' => (int) env('BOOKING_PAYMENT_TTL_MINUTES', 10),

];
