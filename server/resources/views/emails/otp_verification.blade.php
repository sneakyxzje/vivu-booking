<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã xác nhận OTP đặt tour - Vivu Booking</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            color: #222222;
        }
        .email-container {
            max-w: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }
        .header {
            background: linear-gradient(135deg, #ff385c 0%, #e00b41 100%);
            padding: 32px 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 32px 24px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 16px;
        }
        .otp-box {
            background: #fff5f7;
            border: 2px dashed #ff385c;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 24px 0;
        }
        .otp-label {
            font-size: 12px;
            font-weight: 700;
            color: #ff385c;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .otp-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 36px;
            font-weight: 800;
            color: #ff385c;
            letter-spacing: 8px;
            margin: 8px 0;
        }
        .otp-expiry {
            font-size: 12px;
            color: #6a6a6a;
        }
        .booking-summary {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
            border: 1px solid #e2e8f0;
        }
        .summary-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 8px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .summary-item .label {
            color: #64748b;
        }
        .summary-item .value {
            font-weight: 600;
            color: #1e293b;
        }
        .footer {
            background: #f1f5f9;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #ff385c;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>VIVU BOOKING</h1>
            <p>Xác minh email để hoàn tất đặt tour du lịch</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Xin chào <strong>{{ $customerName ?? 'Quý khách' }}</strong>,
            </div>
            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                Cảm ơn bạn đã lựa chọn dịch vụ đặt tour của Vivu Booking. Để bảo mật tài khoản và xác nhận địa chỉ email của bạn trước khi thanh toán, vui lòng nhập mã xác thực OTP bên dưới vào ứng dụng:
            </p>

            <!-- OTP Box -->
            <div class="otp-box">
                <div class="otp-label">MÃ XÁC THỰC OTP CỦA BẠN</div>
                <div class="otp-code">{{ $otpCode ?? '123456' }}</div>
                <div class="otp-expiry">⏱️ Mã này có hiệu lực trong vòng <strong>5 phút</strong>. Tuyệt đối không chia sẻ mã OTP với bất kỳ ai.</div>
            </div>

            <!-- Booking Summary -->
            <div class="booking-summary">
                <div class="summary-title">📍 TÓM TẮT THÔNG TIN ĐẶT TOUR</div>
                
                @if(isset($tourTitle))
                <div class="summary-item">
                    <span class="label">Tên Tour:</span>
                    <span class="value">{{ $tourTitle }}</span>
                </div>
                @endif

                <div class="summary-item">
                    <span class="label">Họ và tên người đặt:</span>
                    <span class="value">{{ $customerName ?? 'N/A' }}</span>
                </div>

                <div class="summary-item">
                    <span class="label">Email liên hệ:</span>
                    <span class="value">{{ $customerEmail ?? 'N/A' }}</span>
                </div>

                @if(isset($customerPhone))
                <div class="summary-item">
                    <span class="label">Số điện thoại:</span>
                    <span class="value">{{ $customerPhone }}</span>
                </div>
                @endif
            </div>

            <p style="font-size: 12px; color: #94a3b8; margin-top: 24px; text-align: center;">
                Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email hoặc liên hệ với bộ phận hỗ trợ của chúng tôi.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© {{ date('Y') }} Vivu Booking. All rights reserved.</p>
            <p>Hệ thống hỗ trợ đặt tour du lịch trực tuyến hàng đầu.</p>
        </div>
    </div>
</body>
</html>
