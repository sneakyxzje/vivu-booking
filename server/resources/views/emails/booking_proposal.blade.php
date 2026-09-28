<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Thông báo thay đổi Đơn hàng</title>
</head>
<body style="font-family: sans-serif; line-height: 1.5; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee;">
        <h2 style="color: #0369a1;">VivuBooking - Thông báo thay đổi Đơn hàng</h2>

        <p>Xin chào <strong>{{ $booking->customer_name }}</strong>,</p>

        <p>Cảm ơn quý khách đã đặt tour tại VivuBooking (Mã đơn: <strong>{{ $booking->public_token }}</strong>).</p>

        <p>Chúng tôi rất tiếc phải thông báo rằng có một số thay đổi liên quan đến lịch trình/dịch vụ của quý khách với lý do sau:</p>

        <div style="background-color: #f3f4f6; padding: 15px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
            {{ $proposal->reason }}
        </div>

        @if($proposal->schedule_snapshot)
        <p><strong>Chuyến ban đầu:</strong> {{ $proposal->schedule_snapshot['from']['tour_title'] }} · {{ $proposal->schedule_snapshot['from']['start_date'] }}</p>
        <p><strong>Chuyến đề xuất:</strong> {{ $proposal->schedule_snapshot['to']['tour_title'] }} · {{ $proposal->schedule_snapshot['to']['start_date'] }}</p>
        <p><strong>Kết thúc:</strong> {{ $proposal->schedule_snapshot['to']['end_date'] }}</p>
        <p><strong>Hạn trả nốt nếu đồng ý:</strong> {{ $proposal->schedule_snapshot['to']['booking_deadline'] }}. Giá đơn giữ nguyên.</p>
        <p><strong>Điểm đón:</strong> {{ $proposal->schedule_snapshot['to']['pickup_location'] ?? '' }}</p>
        @foreach($proposal->schedule_snapshot['to']['itineraries'] ?? [] as $day)
        <p><strong>Ngày {{ $day['day_number'] }}: {{ $day['title'] }}</strong><br>{{ strip_tags($day['content'] ?? '') }}</p>
        @endforeach
        @endif
        <p>Vui lòng mở trang đơn hàng để chọn <strong>Đồng ý</strong> hoặc <strong>Từ chối</strong> trước {{ $proposal->response_deadline->format('H:i d/m/Y') }}.</p>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ rtrim(config('app.frontend_url'), '/') }}/booking-success/{{ $booking->public_token }}?email={{ urlencode($booking->customer_email) }}"
               style="background-color: #0369a1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Xem Chi tiết Đơn hàng
            </a>
        </p>

        <p style="color: #dc2626; font-size: 0.9em; font-weight: bold;">
            Từ chối hoặc không phản hồi: giữ nguyên chuyến ban đầu. Chỉ chuyển khi Quý khách đồng ý. Quý khách vẫn cần thanh toán đủ trước hạn chốt của chuyến đang đặt.
        </p>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="font-size: 0.85em; color: #666;">
            Trân trọng,<br>
            Đội ngũ VivuBooking
        </p>
    </div>
</body>
</html>
