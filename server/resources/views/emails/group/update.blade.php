@php
    use App\Enums\GroupRequestStatus;

    $mau = match ($trangThai) {
        GroupRequestStatus::Confirmed => '#047857',
        GroupRequestStatus::Rejected => '#be123c',
        GroupRequestStatus::Quoted => '#0f766e',
        default => '#1d4ed8',
    };

    $tieuDe = match ($trangThai) {
        GroupRequestStatus::PendingQuote => 'Đã nhận yêu cầu đặt đoàn',
        GroupRequestStatus::Quoted => 'Báo giá cho đoàn của Quý khách',
        GroupRequestStatus::Confirmed => 'Đoàn đã được chốt',
        GroupRequestStatus::Rejected => 'Yêu cầu chưa thể nhận',
        default => 'Cập nhật yêu cầu đặt đoàn',
    };
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $tieuDe }}</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:{{ $mau }};padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">{{ $tieuDe }}</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">
                                Mã tra cứu: {{ $yeuCau->public_token }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $yeuCau->contact_name }}</strong>,
                            </p>

                            @if($trangThai === GroupRequestStatus::PendingQuote)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                    Chúng tôi đã nhận yêu cầu đặt đoàn khoảng <strong>{{ $yeuCau->estimated_guests }} khách</strong>
                                    cho tour <strong>{{ $yeuCau->tour?->title }}</strong>. Bộ phận điều hành sẽ liên hệ
                                    theo số <strong>{{ $yeuCau->contact_phone }}</strong> để báo giá.
                                </p>
                                <p style="margin:0 0 16px;font-size:13px;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Yêu cầu này <strong>chưa giữ chỗ</strong>. Chỗ chỉ được giữ khi hai bên thống nhất
                                    giá và điều hành chốt đoàn.
                                </p>
                            @elseif($trangThai === GroupRequestStatus::Quoted)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                    Dưới đây là mức giá chúng tôi đề xuất cho đoàn của Quý khách.
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:45%;">Giá mỗi khách</td>
                                        <td style="padding:12px 14px;font-size:16px;font-weight:800;color:#0f766e;">
                                            {{ number_format((float) $yeuCau->quoted_price_per_person, 0, ',', '.') }} đ
                                        </td>
                                    </tr>
                                    @if($yeuCau->quoted_free_slots > 0)
                                        <tr>
                                            <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Suất miễn phí</td>
                                            <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $yeuCau->quoted_free_slots }} suất</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Báo giá có hiệu lực tới</td>
                                        <td style="padding:12px 14px;font-size:13px;font-weight:700;">
                                            {{ $yeuCau->quote_expires_at?->format('H:i d/m/Y') ?? 'chưa xác định' }}
                                        </td>
                                    </tr>
                                </table>
                                @if($yeuCau->quote_note)
                                    <p style="margin:0 0 16px;font-size:13px;color:#4b5563;">{{ $yeuCau->quote_note }}</p>
                                @endif
                                <p style="margin:0 0 16px;font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Quá hạn trên, giá này không còn giữ hiệu lực và cần báo giá lại. Chỗ vẫn
                                    <strong>chưa được giữ</strong> cho tới khi đoàn được chốt.
                                </p>
                            @elseif($trangThai === GroupRequestStatus::Confirmed)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                    Đoàn <strong>{{ $yeuCau->booking?->guests }} khách</strong> đã được chốt cho tour
                                    <strong>{{ $yeuCau->tour?->title }}</strong>, khởi hành
                                    <strong>{{ $yeuCau->schedule?->start_date?->format('d/m/Y H:i') }}</strong>.
                                    Chỗ đã được giữ.
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:45%;">Tổng giá trị hợp đồng</td>
                                        <td style="padding:12px 14px;font-size:16px;font-weight:800;color:#047857;">
                                            {{ number_format((float) ($yeuCau->booking?->total_amount ?? 0), 0, ',', '.') }} đ
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 16px;font-size:13px;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;line-height:1.6;">
                                    Hai việc tiếp theo: chuyển tiền cọc theo thỏa thuận, và nộp danh sách khách trước
                                    hạn chốt danh sách của chuyến.
                                </p>
                            @elseif($trangThai === GroupRequestStatus::Rejected)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                    Rất tiếc, chúng tôi chưa thể nhận yêu cầu đặt đoàn này.
                                </p>
                                @if($yeuCau->rejected_reason)
                                    <p style="margin:0 0 16px;font-size:13px;color:#9f1239;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:12px 14px;">
                                        Lý do: <strong>{{ $yeuCau->rejected_reason }}</strong>
                                    </p>
                                @endif
                                <p style="margin:0 0 16px;font-size:13px;color:#4b5563;line-height:1.6;">
                                    Quý khách vẫn có thể gửi yêu cầu mới cho một ngày khởi hành khác, hoặc gọi tổng đài
                                    để chúng tôi tư vấn phương án phù hợp hơn.
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                @if($donUrl)
                                    Theo dõi đơn hàng:
                                    <a href="{{ $donUrl }}" style="color:#0f766e;font-weight:700;">{{ $donUrl }}</a>
                                @else
                                    Theo dõi yêu cầu bằng mã tra cứu:
                                    <a href="{{ $traCuuUrl }}" style="color:#0f766e;font-weight:700;">{{ $traCuuUrl }}</a>
                                @endif
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
