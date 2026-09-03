{{-- Thư nhắc trả nốt phần còn lại. Xem App\Console\Commands\SendBalanceReminders. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $laCanhBaoCuoi ? 'Cảnh báo cuối về thanh toán' : 'Nhắc thanh toán phần còn lại' }}</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    {{-- Màu tiêu đề đổi theo mức độ: nhắc thường thì xanh, cảnh báo cuối thì đỏ. --}}
                    <tr>
                        <td style="background:{{ $laCanhBaoCuoi ? '#b91c1c' : '#0b817a' }};padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">
                                {{ $laCanhBaoCuoi
                                    ? 'Quý khách còn ít ngày để thanh toán'
                                    : 'Nhắc thanh toán phần còn lại' }}
                            </h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">
                                {{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }} &mdash; đơn #{{ $booking->id }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $booking->customer_name }}</strong>,
                                @if ($laCanhBaoCuoi)
                                    đây là lần nhắc cuối về khoản còn lại của đơn tour sắp tới.
                                @else
                                    Quý khách đã đặt cọc giữ chỗ thành công. Còn một khoản cần thanh toán
                                    trước ngày đi.
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:42%;">Tổng giá trị đơn</td>
                                    <td style="padding:12px 14px;font-size:14px;">
                                        {{ number_format($booking->total_amount, 0, ',', '.') }} đ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Đã thanh toán</td>
                                    <td style="padding:12px 14px;font-size:14px;color:#047857;">
                                        {{ number_format($daThu, 0, ',', '.') }} đ
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Còn phải thanh toán</td>
                                    <td style="padding:12px 14px;font-size:17px;font-weight:800;color:{{ $laCanhBaoCuoi ? '#b91c1c' : '#0b817a' }};">
                                        {{ number_format($conThieu, 0, ',', '.') }} đ
                                    </td>
                                </tr>
                                @if ($hanTraNot)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Hạn thanh toán</td>
                                        <td style="padding:12px 14px;font-size:15px;font-weight:700;">
                                            {{ $hanTraNot->format('d/m/Y') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            @if ($paymentUrl)
                                <p style="margin:22px 0;">
                                    <a href="{{ $paymentUrl }}" style="display:inline-block;background:{{ $laCanhBaoCuoi ? '#b91c1c' : '#0b817a' }};color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 22px;border-radius:8px;">
                                        Thanh toán {{ number_format($conThieu, 0, ',', '.') }} đ ngay
                                    </a>
                                </p>
                            @endif

                            {{-- Hậu quả nói thẳng, và chỉ nói ở lá cuối. Đặt nó ở cả hai lá thì lá đầu
                                 thành lời đe dọa với người mới vừa đặt cọc xong. --}}
                            @if ($laCanhBaoCuoi)
                                <p style="margin:14px 0 0;font-size:14px;color:#7f1d1d;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;line-height:1.6;">
                                    <strong>Nếu quá hạn trên mà chúng tôi chưa nhận được thanh toán</strong>, đơn của
                                    Quý khách sẽ được hủy để nhường chỗ cho khách khác, và
                                    <strong>khoản đã đặt cọc không được hoàn lại</strong> theo điều khoản Quý khách
                                    đã đồng ý khi đặt tour.
                                </p>
                                <p style="margin:12px 0 0;font-size:14px;line-height:1.6;color:#4b5563;">
                                    Nếu có khó khăn về thanh toán, Quý khách gọi tổng đài
                                    <strong>1900 1234</strong> trước hạn để chúng tôi cùng tìm cách xử lý.
                                </p>
                            @else
                                <p style="margin:14px 0 0;font-size:14px;line-height:1.6;color:#4b5563;">
                                    Quý khách cũng có thể thanh toán bằng chuyển khoản hoặc tới trực tiếp văn phòng.
                                    Sau khi thanh toán đủ, chúng tôi sẽ gửi xác nhận và thông tin chuẩn bị cho chuyến đi.
                                </p>
                            @endif

                            <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">
                                Xem lại chi tiết đơn tại
                                <a href="{{ $frontendBookingUrl }}" style="color:#0b817a;">trang tra cứu đơn</a>.
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
