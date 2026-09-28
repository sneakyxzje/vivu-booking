<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Xác thực Email Đặt Tour</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background-color: #047857; /* Emerald 700 */
            color: #ffffff;
            text-align: center;
            padding: 24px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 32px;
            color: #333333;
            line-height: 1.6;
        }
        .otp-box {
            background-color: #ecfdf5; /* Emerald 50 */
            border: 2px dashed #10b981; /* Emerald 500 */
            border-radius: 8px;
            text-align: center;
            padding: 20px;
            margin: 24px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #047857;
            letter-spacing: 4px;
            margin: 0;
        }
        .footer {
            background-color: #f9fafb;
            text-align: center;
            padding: 16px;
            color: #6b7280;
            font-size: 14px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VivuBooking</h1>
        </div>
        <div class="content">
            <h2>Xin chào,</h2>
            <p>Bạn vừa yêu cầu mã xác thực để tiếp tục quá trình đặt tour trên hệ thống của chúng tôi.</p>
            <p>Vui lòng nhập mã OTP gồm 6 chữ số dưới đây vào trang web để xác thực email của bạn:</p>
            
            <div class="otp-box">
                <p class="otp-code">{{ $otp }}</p>
            </div>
            
            <p><em>Lưu ý: Mã OTP này sẽ hết hạn sau 5 phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.</em></p>
            <p>Nếu bạn không thực hiện yêu cầu này, xin vui lòng bỏ qua email này.</p>
            
            <p>Trân trọng,<br>Đội ngũ VivuBooking</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} VivuBooking. All rights reserved.
        </div>
    </div>
</body>
</html>
