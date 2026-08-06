<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xac nhan dat tour</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#0f766e;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Vivu Booking da nhan yeu cau dat tour</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Ma dat tour #{{ $booking->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Xin chao <strong>{{ $booking->customer_name }}</strong>,
                                chung toi da ghi nhan thong tin dat tour cua quy khach. Vui long kiem tra lai thong tin ben duoi va hoan tat thanh toan de giu cho.
                            </p>

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
                                    <td style="padding:12px 14px;font-size:13px;">
                                        {{ $booking->adult_count }} nguoi lon,
                                        {{ $booking->child_count }} tre em,
                                        {{ $booking->infant_count }} em be
                                        <span style="color:#6b7280;">({{ $booking->guests }} khach)</span>
                                    </td>
                                </tr>
                                @if($booking->discount_amount > 0)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Ma giam gia</td>
                                        <td style="padding:12px 14px;font-size:13px;">
                                            {{ $booking->discount_code }} -
                                            giam {{ number_format((float) $booking->discount_amount, 0, ',', '.') }} VND
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Tong thanh toan</td>
                                    <td style="padding:12px 14px;font-size:18px;font-weight:800;color:#dc2626;">
                                        {{ number_format((float) $booking->total_amount, 0, ',', '.') }} VND
                                    </td>
                                </tr>
                            </table>

                            @if($paymentUrl)
                                <p style="margin:22px 0;">
                                    <a href="{{ $paymentUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:10px;">
                                        Thanh toan ngay
                                    </a>
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
                            Email nay duoc gui tu he thong Vivu Booking. Neu quy khach khong thuc hien yeu cau nay, vui long lien he ho tro.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
