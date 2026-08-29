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

    /*
    |--------------------------------------------------------------------------
    | Phí đổi lịch
    |--------------------------------------------------------------------------
    |
    | Lần chuyển chuyến đầu tiên miễn phí. Từ lần thứ hai thu khoản này, vì mỗi
    | lần đổi đều kéo theo việc báo lại với khách sạn và nhà xe. Hãng khởi xướng
    | thì không bao giờ thu, lỗi không thuộc về khách.
    |
    */

    'transfer_fee' => (int) env('BOOKING_TRANSFER_FEE', 200000),

    /*
    |--------------------------------------------------------------------------
    | Nhắc trước ngày khởi hành
    |--------------------------------------------------------------------------
    |
    | Gửi thư nhắc trước bao nhiêu ngày. Ba ngày là khoảng đủ để khách còn kịp
    | thu xếp công việc và hỏi lại nếu có gì chưa rõ, nhưng chưa xa tới mức đọc
    | xong lại quên.
    |
    | Mỗi đơn chỉ nhận đúng một thư, xem cột `departure_reminder_sent_at`.
    |
    */

    'departure_reminder_days' => (int) env('BOOKING_DEPARTURE_REMINDER_DAYS', 3),

];
