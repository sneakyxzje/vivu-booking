{{-- Thư báo đơn vừa được dời sang chuyến khác do ghép chuyến. Xem App\Services\ScheduleMergeService. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chuyến đi đổi ngày</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#b45309;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Chuyến đi của Quý khách đổi sang ngày khác</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">
                                {{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }} &mdash; đơn #{{ $booking->id }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>, công ty vừa gộp chuyến của Quý
                                khách với một đoàn khác cùng tour để chuyến chắc chắn khởi hành. Mọi thứ trong đơn giữ
                                nguyên &mdash; cùng hành trình, cùng số khách, <strong>cùng số tiền</strong>. Chỉ có ngày
                                đi là thay đổi.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Ngày khởi hành cũ</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#6b7280;text-decoration:line-through;">
                                        {{ $ngayCu->format('H:i, d/m/Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Ngày khởi hành mới</td>
                                    <td style="padding:12px 14px;font-size:15px;font-weight:800;color:#b45309;">
                                        {{ $ngayMoi->format('H:i, d/m/Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Lý do</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $lyDo }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Số tiền</td>
                                    <td style="padding:12px 14px;font-size:13px;">Không đổi</td>
                                </tr>
                            </table>

                            {{-- Quyền từ chối. Đây là phần quan trọng nhất của lá thư: khách không chọn việc
                                 đổi ngày, nên từ chối không phải là hủy đơn tự nguyện. --}}
                            <p style="margin:14px 0 0;font-size:13px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                <strong>Nếu ngày mới không phù hợp với Quý khách:</strong> gọi tổng đài 1900 1234 hoặc
                                trả lời thư này để hủy đơn, và <strong>công ty hoàn lại đủ 100% số tiền đã thanh
                                toán</strong>. Quý khách không chịu bất kỳ khoản phí hủy nào, vì đây là thay đổi do
                                công ty thực hiện.
                            </p>

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Nếu ngày mới vẫn đi được, Quý khách không cần làm gì thêm. Danh sách hành khách và mọi
                                thông tin khác của đơn đã được chuyển sang chuyến mới.
                            </p>

                            <p style="margin:22px 0;">
                                <a href="{{ $frontendBookingUrl }}" style="display:inline-block;background:#0b817a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:8px;">
                                    Xem lại chi tiết đơn
                                </a>
                            </p>

                            <p style="margin:0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Chúng tôi xin lỗi vì sự thay đổi này và cảm ơn Quý khách đã thông cảm.
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
