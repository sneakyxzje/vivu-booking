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

        @if($proposal->proposed_date)
        <p style="font-size: 1.1em;"><strong>Đề xuất phương án:</strong> Chúng tôi đề xuất dời lịch khởi hành của quý khách sang ngày: <strong style="color: #0369a1;">{{ \Carbon\Carbon::parse($proposal->proposed_date)->format('d/m/Y H:i') }}</strong>.</p>
        @endif

        <p>Để đảm bảo quyền lợi của quý khách, vui lòng xem chi tiết và phản hồi lại email này hoặc liên hệ hotline công ty để xác nhận đồng ý đổi ngày hoặc yêu cầu hủy chuyến. Hạn chót phản hồi: <strong>{{ $proposal->response_deadline->format('d/m/Y H:i') }}</strong>.</p>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ env('CLIENT_URL', 'http://localhost:5173') }}/tra-cuu/{{ $booking->public_token }}?email={{ urlencode($booking->customer_email) }}"
               style="background-color: #0369a1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Xem Chi tiết Đơn hàng
            </a>
        </p>

        <p style="color: #dc2626; font-size: 0.9em; font-weight: bold;">
            Lưu ý đặc biệt: Nếu quá thời hạn trên mà chúng tôi chưa nhận được phản hồi, công ty sẽ tiến hành HỦY ĐƠN HÀNG của quý khách khỏi chuyến đi này để đảm bảo chỗ trống cho các khách hàng khác, và sẽ xử lý hoàn tiền theo quy định.
        </p>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">
        <p style="font-size: 0.85em; color: #666;">
            Trân trọng,<br>
            Đội ngũ VivuBooking
        </p>
    </div>
</body>
</html>
