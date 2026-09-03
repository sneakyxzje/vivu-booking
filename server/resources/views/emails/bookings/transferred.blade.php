<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chuyến đi đã đổi ngày</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#0f766e;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Chuyến đi của Quý khách đã đổi ngày</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Mã đặt tour #{{ $booking?->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking?->customer_name }}</strong>, theo nội dung hai bên đã
                                trao đổi, đơn đặt tour của Quý khách đã được chuyển sang ngày khởi hành mới.
                            </p>

                            {{--
                                Hai ngày đặt cạnh nhau, ngày cũ gạch ngang.

                                Đây là thông tin quan trọng nhất của lá thư: khách đã xin nghỉ phép và có thể đã
                                đặt vé tới điểm tập kết theo ngày cũ. Nghe qua điện thoại rồi nhớ nhầm một ngày là
                                chuyện thường, nên nó phải đọc được trong một giây.
                            --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Tour</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking?->tour?->title }}</td>
                                </tr>
                                @if($ngayCu)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Ngày khởi hành cũ</td>
                                        <td style="padding:12px 14px;font-size:13px;color:#9ca3af;text-decoration:line-through;">
                                            {{ $ngayCu->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 14px;background:#ecfdf5;font-size:13px;color:#065f46;font-weight:700;">Ngày khởi hành mới</td>
                                    <td style="padding:12px 14px;font-size:17px;font-weight:800;color:#047857;">
                                        {{ $ngayMoi?->format('d/m/Y H:i') ?? 'sẽ thông báo lại' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Tổng giá trị đơn</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">
                                        {{ number_format((float) ($booking?->total_amount ?? 0), 0, ',', '.') }} đ
                                    </td>
                                </tr>
                            </table>

                            @if($chenhLech > 0)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;">
                                    Chuyến mới có mức giá cao hơn, Quý khách vui lòng thanh toán thêm
                                    <strong style="font-size:16px;">{{ number_format($chenhLech, 0, ',', '.') }} đ</strong>@if($phiDoiLich > 0)
                                        (trong đó {{ number_format($phiDoiLich, 0, ',', '.') }} đ là phí đổi lịch)@endif.
                                    Xem hướng dẫn thanh toán ở trang tra cứu đơn bên dưới.
                                </p>
                            @elseif($chenhLech < 0)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;">
                                    Chuyến mới có mức giá thấp hơn. Phần chênh
                                    <strong style="font-size:16px;">{{ number_format(abs($chenhLech), 0, ',', '.') }} đ</strong>
                                    sẽ được hoàn lại cho Quý khách; bộ phận kế toán sẽ liên hệ để xác nhận tài khoản nhận tiền.
                                </p>
                            @else
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;">
                                    Việc đổi chuyến <strong>không phát sinh thêm chi phí nào</strong>. Số tiền Quý khách
                                    đã thanh toán được giữ nguyên cho chuyến mới.
                                </p>
                            @endif

                            @if($banGhi->reason)
                                <p style="margin:0 0 16px;font-size:13px;color:#4b5563;">
                                    Lý do đổi chuyến: <strong>{{ $banGhi->reason }}</strong>
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Nếu có bất kỳ chi tiết nào chưa đúng với nội dung hai bên đã trao đổi, vui lòng liên hệ
                                tổng đài ngay để chúng tôi xử lý. Xem chi tiết đơn hàng:
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
