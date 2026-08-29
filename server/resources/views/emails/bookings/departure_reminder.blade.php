{{-- Thư nhắc trước ngày khởi hành. Xem App\Console\Commands\SendDepartureReminders. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhắc lịch khởi hành</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#0b817a;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Chuyến đi của bạn sắp khởi hành</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">
                                {{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>, chỉ còn ít ngày nữa là tới
                                ngày khởi hành. Dưới đây là những thông tin cần cho ngày đi &mdash; Quý khách lưu lại
                                để khỏi phải tìm lại thư cũ.
                            </p>

                            {{-- Bốn dòng đầu là bốn câu khách hay gọi lên hỏi nhất. Đặt trên cùng, không lẫn
                                 vào bảng chi tiết bên dưới. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Giờ khởi hành</td>
                                    <td style="padding:12px 14px;font-size:15px;font-weight:800;color:#0b817a;">
                                        {{ optional($schedule?->start_date)->format('H:i, d/m/Y')
                                            ?? \Carbon\Carbon::parse($booking->departure_date)->format('H:i, d/m/Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Điểm đón</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">
                                        {{ $booking->tour?->pickup_location
                                            ?: ($booking->tour?->start_location ?? 'Liên hệ điều hành để xác nhận') }}
                                    </td>
                                </tr>
                                @if($guides->isNotEmpty())
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Hướng dẫn viên</td>
                                        <td style="padding:12px 14px;font-size:13px;font-weight:700;">
                                            @foreach($guides as $guide)
                                                {{ $guide->name }}@if($guide->phone) &mdash; {{ $guide->phone }}@endif
                                                @if(!$loop->last)<br>@endif
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                @if($booking->tour?->vehicle_info)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Phương tiện</td>
                                        <td style="padding:12px 14px;font-size:13px;">{{ $booking->tour->vehicle_info }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Số khách</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $booking->guests }} người</td>
                                </tr>
                            </table>

                            <p style="margin:14px 0 0;font-size:13px;color:#1d4ed8;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;">
                                Có mặt tại điểm đón trước giờ khởi hành ít nhất 30 phút, mang theo giấy tờ tùy thân
                                đúng như đã khai trong danh sách hành khách.
                            </p>

                            <p style="margin:22px 0;">
                                <a href="{{ $frontendBookingUrl }}" style="display:inline-block;background:#0b817a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:8px;">
                                    Xem lại chi tiết đơn
                                </a>
                            </p>

                            <p style="margin:0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Có thay đổi đột xuất, Quý khách gọi ngay cho hướng dẫn viên theo số ở trên hoặc gọi
                                tổng đài 1900 1234.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            Email được gửi tự động từ hệ thống Vivu Booking.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
