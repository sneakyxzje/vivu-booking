<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đơn đặt tour đã được hủy</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#be123c;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Đơn đặt tour đã được hủy</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Mã đặt tour #{{ $booking->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>,
                                đơn đặt tour dưới đây của Quý khách đã được hủy.
                            </p>

                            @if($booking->cancel_reason)
                                <p style="margin:0 0 16px;font-size:13px;color:#9f1239;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:10px 14px;">
                                    Lý do hủy: <strong>{{ $booking->cancel_reason }}</strong>
                                </p>
                            @endif

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
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Giá trị đơn</td>
                                    <td style="padding:12px 14px;font-size:16px;font-weight:800;color:#dc2626;">
                                        {{ number_format((float) $booking->total_amount, 0, ',', '.') }} VNĐ
                                    </td>
                                </tr>
                            </table>

                            @if($booking->vnpay_transaction_no)
                                <p style="margin:14px 0 0;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;">
                                    Đơn hàng đã thanh toán qua VNPay (mã giao dịch {{ $booking->vnpay_transaction_no }}).
                                    Bộ phận chăm sóc khách hàng sẽ liên hệ Quý khách để hoàn tất thủ tục hoàn tiền.
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Xem chi tiết đơn hàng:
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
