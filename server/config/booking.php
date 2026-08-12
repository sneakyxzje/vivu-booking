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

    /*
    |--------------------------------------------------------------------------
    | Hạn chốt danh sách khách
    |--------------------------------------------------------------------------
    |
    | Số ngày trước khởi hành mà hệ thống ngừng nhận đặt chỗ mới. Mốc này bắt
    | nguồn từ chu kỳ chốt của nhà cung cấp: khách sạn chốt phòng, nhà xe chốt
    | ghế, nhà hàng chốt suất ăn. Chuyến nào không cấu hình riêng thì dùng số
    | này làm mặc định.
    |
    */

    'booking_deadline_days' => (int) env('BOOKING_DEADLINE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Cửa sổ chốt chuyến
    |--------------------------------------------------------------------------
    |
    | Tác vụ nền chỉ xét chốt những chuyến có hạn chốt danh sách rơi vào trong
    | khoảng số giờ này. Chốt sớm hơn sẽ khóa việc bán tiếp, vì chuyến đã chốt
    | không nhận đặt chỗ mới.
    |
    */

    'confirm_window_hours' => (int) env('BOOKING_CONFIRM_WINDOW_HOURS', 24),

];
