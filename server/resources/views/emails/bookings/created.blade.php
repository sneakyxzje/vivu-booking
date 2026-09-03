<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Xác nhận đặt tour</title>
</head>

<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#0f766e;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Vivu Booking đã tiếp nhận yêu cầu đặt tour</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Mã đặt tour #{{ $booking->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>,
                                Vivu Booking đã tiếp nhận thông tin đặt tour của Quý khách. Vui lòng kiểm tra thông tin bên dưới và hoàn tất thanh toán
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
                                @if($booking->tour?->pickup_location)
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Điểm đón khách</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $booking->tour->pickup_location }}</td>
                                </tr>
                                @endif
                                @if($booking->tour?->vehicle_info)
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Phương tiện</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $booking->tour->vehicle_info }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Số lượng khách</td>
                                    <td style="padding:12px 14px;font-size:13px;">
                                        {{ $booking->adult_count }} người lớn,
                                        {{ $booking->child_count }} trẻ em,
                                        {{ $booking->infant_count }} em bé
                                        <span style="color:#6b7280;">(tổng {{ $booking->guests }} khách)</span>
                                    </td>
                                </tr>
                                @if($booking->discount_amount > 0)
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Mã giảm giá</td>
                                    <td style="padding:12px 14px;font-size:13px;">
                                        {{ $booking->discount_code }} &ndash;
                                        giảm {{ number_format((float) $booking->discount_amount, 0, ',', '.') }} VNĐ
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Tổng giá trị đơn</td>
                                    <td style="padding:12px 14px;font-size:15px;font-weight:700;">
                                        {{ number_format((float) $booking->total_amount, 0, ',', '.') }} VNĐ
                                    </td>
                                </tr>
                                {{-- Số phải trả NGAY, tách khỏi giá trị đơn.

                                     Trước đây thư chỉ in giá tour ở đây rồi in lại chính nó lên nút bấm, trong khi
                                     liên kết sau nút ấy đòi tiền cọc. Khách đọc thư yên trí rằng trả xong lần này
                                     là hết nghĩa vụ — và cả lá thư không có một chữ nào về đợt hai. --}}
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">
                                        {{ $conNo > 0 ? 'Đặt cọc hôm nay' : 'Cần thanh toán' }}
                                    </td>
                                    <td style="padding:12px 14px;font-size:18px;font-weight:800;color:#dc2626;">
                                        {{ number_format($tienPhaiTraNgay, 0, ',', '.') }} VNĐ
                                    </td>
                                </tr>
                                @if($conNo > 0)
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Còn lại</td>
                                    <td style="padding:12px 14px;font-size:14px;">
                                        {{ number_format($conNo, 0, ',', '.') }} VNĐ
                                        @if($hanTraNot)
                                            &ndash; hạn <strong>{{ $hanTraNot->format('d/m/Y') }}</strong>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            </table>

                            @if($conNo > 0)
                            <p style="margin:14px 0 0;font-size:13px;line-height:1.6;color:#4b5563;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;">
                                Đơn này thanh toán làm hai đợt. Chúng tôi sẽ gửi thư nhắc trước hạn đợt hai. Quá hạn
                                mà chưa nhận được thanh toán, đơn được hủy và khoản đã thanh toán xử lý theo bảng phí hủy.
                            </p>
                            @endif

                            @if($booking->expires_at)
                            <p style="margin:14px 0 0;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;">
                                {{-- "Giữ chỗ tới", không phải "hạn thanh toán".

                                     Cụm "hạn thanh toán" đã thuộc về hạn trả nốt — mốc cách đây hàng tuần mà quá đi
                                     thì mất cọc. Dùng cùng một cái tên cho hai mốc cách nhau xa như vậy là mời khách
                                     nhầm cái mười phút với cái mười ngày. --}}
                                Chỗ được giữ tới:
                                <strong>{{ $booking->expires_at->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</strong>
                                (giờ Việt Nam).
                            </p>
                            @endif

                            @if($paymentUrl)
                            <p style="margin:22px 0;">
                                {{-- Nút ghi rõ số tiền: khách bấm sang cổng và thấy đúng con số
                                         vừa đọc, không phải đối chiếu lại xem có nhầm không. --}}
                                <a href="{{ $paymentUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:10px;">
                                    {{ $conNo > 0 ? 'Đặt cọc' : 'Thanh toán' }}
                                    {{ number_format($tienPhaiTraNgay, 0, ',', '.') }} VNĐ
                                </a>
                            </p>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0;border-collapse:collapse;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0 0 6px;font-size:13px;color:#6b7280;">Mã tra cứu đơn hàng</p>
                                        <p style="margin:0;font-family:Consolas,Menlo,monospace;font-size:15px;font-weight:700;color:#111827;word-break:break-all;">
                                            {{ $booking->public_token }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Xem phiếu đặt tour:
                                <a href="{{ $frontendBookingUrl }}" style="color:#0f766e;font-weight:700;">{{ $frontendBookingUrl }}</a>
                                <br>
                                Tra cứu bằng mã đơn hàng:
                                <a href="{{ $lookupUrl }}" style="color:#0f766e;font-weight:700;">{{ $lookupUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            Email được gửi tự động từ hệ thống Vivu Booking. Nếu Quý khách không thực hiện yêu cầu này, vui lòng liên hệ tổng đài 1900 1234.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>