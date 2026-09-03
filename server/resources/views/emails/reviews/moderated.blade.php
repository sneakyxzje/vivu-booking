<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $daDuyet ? 'Đánh giá đã được đăng' : 'Về đánh giá của bạn' }}</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:{{ $daDuyet ? '#047857' : '#b45309' }};padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">
                                {{ $daDuyet ? 'Đánh giá của bạn đã được đăng' : 'Đánh giá của bạn chưa được đăng' }}
                            </h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">{{ $review->tour?->title }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Kính chào <strong>{{ $review->user?->name }}</strong>,
                                @if($daDuyet)
                                    cảm ơn bạn đã dành thời gian viết nhận xét. Đánh giá của bạn giờ đã hiện công
                                    khai và được tính vào điểm trung bình của tour.
                                @else
                                    chúng tôi rất tiếc chưa thể đăng nhận xét của bạn.
                                @endif
                            </p>

                            {{-- In lại đúng chữ họ đã viết: người ta cần thấy mình đang được nói về bài nào. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:30%;">Số sao</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $review->rating }}/5</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;vertical-align:top;">Nội dung</td>
                                    <td style="padding:12px 14px;font-size:13px;line-height:1.6;font-style:italic;color:#4b5563;">
                                        {{ $review->comment }}
                                    </td>
                                </tr>
                            </table>

                            @if(! $daDuyet)
                                @if($review->moderation_note)
                                    <p style="margin:0 0 16px;font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;">
                                        Lý do: <strong>{{ $review->moderation_note }}</strong>
                                    </p>
                                @endif

                                {{--
                                    Điều quan trọng nhất khi từ chối: họ vẫn sửa lại và gửi lần nữa được.

                                    Không nói ra thì người ta hiểu thành "bị cấm đánh giá", và đó là một hiểu lầm
                                    dễ dẫn tới bực bội hơn hẳn bản thân việc bị từ chối.
                                --}}
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;">
                                    Bạn <strong>vẫn có thể sửa lại và gửi lần nữa</strong>. Mở trang tour, viết lại
                                    nhận xét và bấm gửi — nội dung mới sẽ được xem xét lại từ đầu.
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Xem trang tour:
                                <a href="{{ $tourUrl }}" style="color:#0f766e;font-weight:700;">{{ $tourUrl }}</a>
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
