{{-- Thư đặt lại mật khẩu. Liên kết trỏ về giao diện React, xem App\Mail\PasswordResetMail. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #2563eb; margin: 0; }
        .btn-reset { display: inline-block; background: #2563eb; color: #ffffff !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .fallback { word-break: break-all; font-size: 12px; color: #475569; background: #f1f5f9; padding: 12px; border-radius: 8px; }
        .footer { text-align: center; font-size: 12px; color: #64748b; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Vivu Booking - Đặt lại mật khẩu</h2>
        </div>

        <p>Xin chào <strong>{{ $user->name }}</strong>,</p>
        <p>
            Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản
            <strong>{{ $user->email }}</strong>. Bấm nút bên dưới để chọn mật khẩu mới.
        </p>

        <p style="text-align: center; margin: 28px 0;">
            <a href="{{ $resetUrl }}" class="btn-reset" target="_blank">Đặt lại mật khẩu</a>
        </p>

        <p>
            Liên kết này hết hạn sau <strong>{{ $expireMinutes }} phút</strong> và chỉ dùng được một
            lần. Nếu nút bên trên không bấm được, sao chép đường dẫn sau vào trình duyệt:
        </p>
        <p class="fallback">{{ $resetUrl }}</p>

        <p>
            Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua thư này - mật khẩu hiện tại của bạn vẫn
            giữ nguyên và không ai đọc được nó.
        </p>

        <div class="footer">
            <p>Vivu Booking - Hệ thống Đặt Tour &amp; Trải nghiệm Du lịch</p>
            <p>Hotline hỗ trợ: 1900 1234 | Email: support@vivubooking.com</p>
        </div>
    </div>
</body>
</html>
