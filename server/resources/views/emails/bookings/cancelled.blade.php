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

                            {{--
                                Hãng hủy chuyến thì khác hẳn khách đổi ý, và thư phải nói khác.

                                Lỗi không thuộc về khách nên không có phí hủy nào: hoàn đủ số đã
                                thu. Nói thẳng con số ra đây, vì đó là câu hỏi đầu tiên của người
                                vừa đọc tin chuyến đi bị hủy.
                            --}}
                            @php
                                $tienHoan = (float) ($booking->refund_amount ?? 0);
                                $congTyHuy = $booking->cancel_type === 'by_company';
                            @endphp

                            @if($congTyHuy && $tienHoan > 0)
                                <p style="margin:14px 0 0;font-size:14px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Đây là quyết định từ phía công ty, không phải lỗi của Quý khách,
                                    nên <strong>không áp dụng phí hủy</strong>.<br>
                                    Số tiền hoàn lại:
                                    <strong style="font-size:17px;">{{ number_format($tienHoan, 0, ',', '.') }} VNĐ</strong>
                                    &mdash; đủ 100% số tiền Quý khách đã thanh toán.
                                </p>
                            @elseif($tienHoan > 0)
                                {{--
                                    Khách chủ động hủy: phải nói ra con số thật, kèm việc có trừ phí.

                                    Trước đây nhánh này không tồn tại — mọi lần hủy đều đi vào câu "hoàn đủ 100%"
                                    ở trên vì `cancel_type` bị ghi cứng thành `by_company`. Khách hủy trước 10
                                    ngày bị trừ 30% vẫn nhận được thư nói họ được hoàn đủ, và cuộc gọi khiếu nại
                                    sau đó bắt đầu từ chính lá thư này.
                                --}}
                                <p style="margin:14px 0 0;font-size:14px;color:#1f2937;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Số tiền hoàn lại:
                                    <strong style="font-size:17px;color:#065f46;">{{ number_format($tienHoan, 0, ',', '.') }} VNĐ</strong><br>
                                    <span style="font-size:13px;color:#6b7280;">
                                        Mức hoàn tính theo chính sách hủy có hiệu lực tại thời điểm Quý khách đặt
                                        tour, dựa trên số ngày còn lại tới ngày khởi hành.
                                    </span>
                                </p>
                            @elseif($booking->paid_at)
                                <p style="margin:14px 0 0;font-size:14px;color:#9f1239;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Theo chính sách hủy có hiệu lực tại thời điểm đặt tour, đơn hủy ở thời điểm
                                    này <strong>không được hoàn tiền</strong>. Nếu Quý khách cho rằng có nhầm lẫn,
                                    vui lòng liên hệ tổng đài để chúng tôi kiểm tra lại.
                                </p>
                            @endif

                            @if($booking->vnpay_transaction_no)
                                <p style="margin:14px 0 0;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;">
                                    Đơn hàng đã thanh toán qua VNPay (mã giao dịch {{ $booking->vnpay_transaction_no }}).
                                    Bộ phận chăm sóc khách hàng sẽ liên hệ Quý khách trong vòng 3 ngày làm việc
                                    để hoàn tất thủ tục hoàn tiền.
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
