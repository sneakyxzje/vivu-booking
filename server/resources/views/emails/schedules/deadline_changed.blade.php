{{-- Thư báo hạn chốt danh sách vừa dịch. Xem App\Services\ScheduleDeadlineService. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thay đổi hạn chốt danh sách</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:{{ $rutNgan ? '#b45309' : '#0b817a' }};padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">
                                {{ $rutNgan ? 'Danh sách khách chốt sớm hơn dự kiến' : 'Hạn chốt danh sách vừa được điều chỉnh' }}
                            </h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">
                                {{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }}
                                &mdash; khởi hành
                                {{ optional($booking->schedule?->start_date)->format('H:i, d/m/Y')
                                    ?? \Carbon\Carbon::parse($booking->departure_date)->format('H:i, d/m/Y') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>, đơn
                                <strong>#{{ $booking->id }}</strong> của Quý khách vẫn giữ nguyên: cùng chuyến, cùng
                                số khách, cùng số tiền. Chỉ có <strong>hạn chốt danh sách</strong> &mdash; ngày chúng
                                tôi gửi danh sách khách cho khách sạn và nhà xe &mdash; là thay đổi.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Hạn chốt trước đây</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#6b7280;text-decoration:line-through;">
                                        {{ $hanChotCu?->format('H:i, d/m/Y') ?? 'Chưa đặt' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Hạn chốt mới</td>
                                    <td style="padding:12px 14px;font-size:15px;font-weight:800;color:{{ $rutNgan ? '#b45309' : '#0b817a' }};">
                                        {{ $hanChotMoi?->format('H:i, d/m/Y') ?? 'Không giới hạn' }}
                                    </td>
                                </tr>
                                @if($lyDo)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Lý do</td>
                                        <td style="padding:12px 14px;font-size:13px;">{{ $lyDo }}</td>
                                    </tr>
                                @endif
                            </table>

                            {{-- Điều khách cần làm, không phải điều hệ thống vừa làm. Rút ngắn là mất quyền
                                 nên nói thẳng và nói trước; gia hạn thì chỉ là thêm thời gian. --}}
                            @if($rutNgan)
                                <p style="margin:14px 0 0;font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    <strong>Việc cần làm trước hạn mới:</strong> kiểm tra lại danh sách hành khách
                                    (họ tên đúng như giấy tờ tùy thân) và các yêu cầu riêng về phòng, suất ăn. Sau
                                    mốc trên, danh sách đã gửi nhà cung cấp nên Quý khách không tự sửa được nữa và
                                    cũng không đổi sang chuyến khác được &mdash; mọi thay đổi phải qua bộ phận điều
                                    hành, và có thể không kịp.
                                </p>
                            @else
                                <p style="margin:14px 0 0;font-size:13px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Quý khách có thêm thời gian: từ nay tới mốc trên vẫn sửa được danh sách hành
                                    khách và vẫn xin đổi chuyến được như bình thường.
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Chính sách hủy và mức hoàn tiền của đơn <strong>không đổi</strong>: phần trăm hoàn
                                vẫn tính theo số giờ trước giờ khởi hành, không liên quan tới mốc này.
                            </p>

                            <p style="margin:22px 0;">
                                <a href="{{ $frontendBookingUrl }}" style="display:inline-block;background:#0b817a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:8px;">
                                    Xem đơn và danh sách hành khách
                                </a>
                            </p>

                            <p style="margin:0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Cần hỗ trợ gấp, Quý khách gọi tổng đài 1900 1234 và đọc mã đơn #{{ $booking->id }}.
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
