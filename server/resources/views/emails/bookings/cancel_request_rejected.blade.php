<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Yêu cầu hủy chưa được chấp nhận</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#b45309;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Yêu cầu hủy chưa được chấp nhận</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Mã đặt tour #{{ $booking->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>, chúng tôi đã xem xét yêu cầu
                                hủy đơn đặt tour của Quý khách nhưng chưa thể chấp nhận.
                            </p>

                            <p style="margin:0 0 16px;font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;">
                                Lý do: <strong>{{ $lyDo }}</strong>
                            </p>

                            {{--
                                Điều quan trọng nhất của lá thư này: đơn VẪN CÒN HIỆU LỰC.

                                Người đã gửi yêu cầu hủy mặc định tin rằng mình sẽ không đi nữa. Không nói rõ
                                điều ngược lại thì họ vắng mặt ngày khởi hành, và lúc ấy mất trắng theo đúng
                                chính sách — một hậu quả sinh ra từ việc thiếu một câu.
                            --}}
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;">
                                <strong>Đơn của Quý khách vẫn còn hiệu lực.</strong> Chỗ vẫn được giữ và Quý khách
                                vẫn đi được bình thường. Nếu Quý khách không có mặt lúc khởi hành, đơn sẽ được ghi
                                nhận là vắng mặt và không được hoàn tiền.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Tour</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Thời gian khởi hành</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ \Carbon\Carbon::parse($booking->departure_date)->format('d/m/Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Số lượng khách</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $booking->guests }} khách</td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Nếu Quý khách chưa đồng ý với quyết định này, vui lòng liên hệ tổng đài để trao đổi
                                thêm. Xem chi tiết đơn hàng:
                                <a href="{{ $frontendBookingUrl }}" style="color:#0f766e;font-weight:700;">{{ $frontendBookingUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            Email được gửi tự động từ hệ thống Vivu Booking. Mọi thắc mắc xin liên hệ tổng đài 1900 1234.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
