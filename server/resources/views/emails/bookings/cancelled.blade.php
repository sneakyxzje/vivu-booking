<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Don dat tour da bi huy</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#be123c;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Don dat tour cua quy khach da bi huy</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Ma dat tour #{{ $booking->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Xin chao <strong>{{ $booking->customer_name }}</strong>,
                                don dat tour duoi day cua quy khach da bi huy. Cho da duoc tra lai cho lich khoi hanh.
                            </p>

                            @if($booking->cancel_reason)
                                <p style="margin:0 0 16px;font-size:13px;color:#9f1239;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:10px 14px;">
                                    Ly do huy: <strong>{{ $booking->cancel_reason }}</strong>
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Tour</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Ngay khoi hanh</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ \Carbon\Carbon::parse($booking->departure_date)->format('d/m/Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">So luong khach</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $booking->guests }} khach</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Gia tri don</td>
                                    <td style="padding:12px 14px;font-size:16px;font-weight:800;color:#dc2626;">
                                        {{ number_format((float) $booking->total_amount, 0, ',', '.') }} VND
                                    </td>
                                </tr>
                            </table>

                            @if($booking->vnpay_transaction_no)
                                <p style="margin:14px 0 0;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;">
                                    Don nay da duoc thanh toan qua VNPay (ma giao dich {{ $booking->vnpay_transaction_no }}).
                                    Chung toi se lien he quy khach de hoan tien trong thoi gian som nhat.
                                </p>
                            @endif

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Quy khach co the xem lai phieu dat tour tai:
                                <a href="{{ $frontendBookingUrl }}" style="color:#0f766e;font-weight:700;">{{ $frontendBookingUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            Email nay duoc gui tu he thong Vivu Booking. Neu quy khach can ho tro, vui long lien he hotline hoac phan hoi email nay.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
