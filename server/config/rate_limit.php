<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Công tắc chung
    |--------------------------------------------------------------------------
    |
    | Đặt RATE_LIMIT_ENABLED=false trong .env thì mọi hạn mức dưới đây tắt hết.
    |
    | Đây là công tắc dành cho lúc ngồi thử tay và lúc trình diễn: bấm qua lại
    | vài màn quản trị là chạm trần ngay, và "Too Many Attempts" giữa buổi demo
    | chỉ làm mất thì giờ chứ không chặn được ai. Bật lại là một dòng .env, nên
    | không có lý do gì phải gỡ luật ra khỏi mã.
    |
    | Đừng để tắt trên máy chạy thật — hạn mức ở /login là thứ duy nhất trong
    | hệ thống chặn việc dò mật khẩu.
    |
    */

    'enabled' => (bool) env('RATE_LIMIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Hạn mức từng nhóm, viết theo dạng "số lần,số phút"
    |--------------------------------------------------------------------------
    |
    | Đếm theo tài khoản nếu đã đăng nhập, không thì theo địa chỉ IP.
    |
    | api    Mức trần cho toàn bộ /api, chặn kịch bản tự động.
    |
    |        60 lượt/phút là con số chật với chính giao diện quản trị: một màn
    |        mở ra đã vài chục lượt gọi, biểu mẫu tour còn hỏi "ai đang rảnh"
    |        cho từng chuyến. Ngồi thử tay thì tắt bằng công tắc ở trên; nếu có
    |        ngày chạy thật mà điều hành kêu bị chặn thì nới riêng dòng này
    |        (RATE_LIMIT_API=600,1) chứ đừng tắt cả cụm.
    |
    | login  Hẹp, và đây là chỗ hẹp có ý nghĩa nhất: 60 lần thử mật khẩu mỗi
    |        phút là hơn tám vạn lần một ngày từ một địa chỉ.
    |
    | email  Các tuyến gửi thư đi (quên mật khẩu, gửi lại mã tra cứu, liên hệ).
    |        Một lần bấm đúng là đã có thư trong hộp; bấm tới lần thứ sáu trong
    |        mười phút là đang thử email của người khác, không phải đang chờ
    |        thư của mình.
    |
    */

    /*
    | discount  Ô nhập mã giảm giá ở trang đặt tour.
    |
    |        Cùng loại với ô đăng nhập: đây là chỗ đoán được thứ của người khác. Mã giảm giá là
    |        chuỗi ngắn, dễ đoán ("HE2026", "SALE50"), và không giới hạn thì một kịch bản tự động
    |        dò được cả kho mã trong vài phút — kể cả mã nội bộ chỉ định phát cho một nhóm khách.
    |
    |        Rộng hơn ô đăng nhập vì người thật có thể gõ sai vài lần rồi thử mã khác, nhưng vẫn
    |        đủ chặt để việc dò không còn rẻ.
    */

    'api' => env('RATE_LIMIT_API', '60,1'),
    'login' => env('RATE_LIMIT_LOGIN', '10,1'),
    'register' => env('RATE_LIMIT_REGISTER', '5,1'),
    'email' => env('RATE_LIMIT_EMAIL', '5,10'),
    'reset' => env('RATE_LIMIT_RESET', '10,10'),
    'discount' => env('RATE_LIMIT_DISCOUNT', '20,1'),

];
