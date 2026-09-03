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
    | Đặt cọc và hạn trả nốt
    |--------------------------------------------------------------------------
    |
    | Khách trả trước một phần để giữ chỗ, phần còn lại trả trước ngày khởi hành.
    | Đây là cách bán phổ biến của lữ hành nội địa: Vietravel lấy cọc 50% và thu
    | nốt 7 đến 10 ngày trước khởi hành (20 đến 25 ngày với tour lễ, Tết).
    |
    | Hai con số dưới đây phải NHÌN NHAU, không đặt độc lập. Lý do:
    |
    | Khi khách bỏ ngang, hệ thống hủy đơn và áp bảng phí hủy tại đúng thời điểm
    | ấy. Muốn "bỏ ngang thì mất cọc" thành sự thật thì bậc phí tại hạn trả nốt
    | phải vừa đúng bằng tiền cọc — không cần một điều khoản riêng nào cả.
    |
    | Với cọc 50% và hạn trả nốt 10 ngày trước khởi hành, bậc phí ở mốc ấy là
    | 50% giá tour (xem DEFAULT_RULES), tức khách mất đúng phần đã đặt cọc. Đổi
    | một trong hai số mà quên số kia thì hoặc khách mất nhiều hơn cọc, hoặc còn
    | được hoàn lại một phần cọc — cả hai đều khó giải thích.
    |
    | Đặt `deposit_percent` bằng 100 là quay lại lối cũ: thu đủ ngay khi đặt.
    |
    */

    'deposit_percent' => (int) env('BOOKING_DEPOSIT_PERCENT', 50),

    'balance_due_days' => (int) env('BOOKING_BALANCE_DUE_DAYS', 10),

    /*
    |--------------------------------------------------------------------------
    | Nhắc trả nốt
    |--------------------------------------------------------------------------
    |
    | Số ngày TRƯỚC HẠN TRẢ NỐT của hai lần nhắc. Quá hạn là khách mất tiền
    | thật, nên không được để chuyện đó xảy ra với người chưa từng được nhắc.
    |
    | Hai lần chứ không một: lần đầu là lời nhắc bình thường lúc còn thong thả,
    | lần sau là cảnh báo cuối khi chỉ còn vài ngày. Một lần duy nhất thì ai lỡ
    | bỏ qua đúng lá thư ấy là mất cọc mà không kịp biết.
    |
    */

    'balance_reminder_days' => (int) env('BOOKING_BALANCE_REMINDER_DAYS', 7),

    'balance_final_notice_days' => (int) env('BOOKING_BALANCE_FINAL_NOTICE_DAYS', 2),

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
    | Hạn báo trước khi khách xin đổi chuyến
    |--------------------------------------------------------------------------
    |
    | Số ngày trước khởi hành mà khách còn được tự xin đổi chuyến. 0 nghĩa là
    | không có mốc riêng: khách đổi được tới tận hạn chốt danh sách.
    |
    | Mặc định 0, và đó là một quyết định chứ không phải bỏ trống. Trước hạn
    | chốt thì chưa có gì gửi đi nhà cung cấp, chỗ khách rời khỏi vẫn quay lại
    | kho và bán tiếp được, danh sách chưa in. Đổi chuyến lúc ấy không tốn của
    | công ty đồng nào — dựng thêm một cái vạch nữa chỉ để chặn một việc vô hại.
    |
    | Hạn chốt mới là mốc mà thay đổi bắt đầu có giá, và nó đã chặn cả khách
    | lẫn công ty. Hai cái vạch cho cùng một câu hỏi thì cái thứ hai luôn là
    | cái tùy tiện.
    |
    | Công ty nào có chính sách báo trước riêng thì đặt số ngày ở đây; luật sẽ
    | áp lại, và chỉ áp cho phía khách — hãng chủ động đổi thì vẫn miễn.
    |
    */

    'transfer_notice_days' => (int) env('BOOKING_TRANSFER_NOTICE_DAYS', 0),

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
