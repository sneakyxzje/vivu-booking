{{-- Task X06a - Template Email gửi lại danh sách mã tra cứu đơn hàng cho khách vãng lai --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách mã tra cứu đơn đặt tour</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #2563eb; margin: 0; }
        .booking-item { background: #f1f5f9; padding: 15px; border-radius: 12px; margin-bottom: 12px; border-left: 4px solid #2563eb; }
        .booking-code { font-family: monospace; font-size: 16px; font-weight: bold; color: #1e40af; background: #e0e7ff; padding: 4px 8px; border-radius: 6px; }
        .btn-lookup { display: inline-block; background: #2563eb; color: #ffffff !important; padding: 8px 16px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 13px; margin-top: 8px; }
        .footer { text-align: center; font-size: 12px; color: #64748b; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Vivu Booking - Khôi phục Mã Tra Cứu</h2>
        </div>

        <p>Xin chào quý khách (<strong>{{ $customerEmail }}</strong>),</p>
        <p>Hệ thống vừa nhận được yêu cầu gửi lại thông tin mã tra cứu đơn đặt tour từ bạn. Dưới đây là danh sách các đơn hàng tương ứng với email của bạn:</p>

        @foreach($bookings as $booking)
            <div class="booking-item">
                <p style="margin: 0 0 6px 0;"><strong>Tour:</strong> {{ $booking->tour->title ?? 'N/A' }}</p>
                <p style="margin: 0 0 6px 0;"><strong>Ngày khởi hành:</strong> {{ optional($booking->schedule)->start_date ? \Carbon\Carbon::parse($booking->schedule->start_date)->format('H:i d/m/Y') : 'Chưa xếp' }}</p>
                <p style="margin: 0 0 6px 0;"><strong>Trạng thái:</strong> {{ $booking->status }}</p>
                <p style="margin: 0 0 8px 0;"><strong>Mã tra cứu:</strong> <span class="booking-code">{{ $booking->public_token }}</span></p>
                <a href="{{ $frontendUrl }}/booking-success/{{ $booking->public_token }}" class="btn-lookup" target="_blank">👉 Xem chi tiết đơn hàng</a>
            </div>
        @endforeach

        <p>Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.</p>

        <div class="footer">
            <p>Vivu Booking - Hệ thống Đặt Tour & Trải nghiệm Du lịch</p>
            <p>Hotline hỗ trợ: 1900 1234 | Email: support@vivubooking.com</p>
        </div>
    </div>
</body>
</html>
