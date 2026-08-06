<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toan thanh cong</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#16a34a;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Thanh toan thanh cong</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Booking #{{ $booking->id }} da duoc xac nhan</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Xin chao <strong>{{ $booking->customer_name }}</strong>,
                                Vivu Booking da ghi nhan thanh toan thanh cong cho don dat tour cua quy khach.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;width:38%;">Tour</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking->tour?->title ?? 'Tour #' . $booking->tour_id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Ngay khoi hanh</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking->departure_date }}</td>
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
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Ma giao dich VNPay</td>
                                    <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking->vnpay_transaction_no ?? 'Dang cap nhat' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">So tien da thanh toan</td>
                                    <td style="padding:12px 14px;font-size:18px;font-weight:800;color:#16a34a;">
                                        {{ number_format((float) $booking->total_amount, 0, ',', '.') }} VND
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0;">
                                <a href="{{ $frontendBookingUrl }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:10px;">
                                    Xem chi tiet booking
                                </a>
                            </p>

                            <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#4b5563;">
                                Vui long luu email nay de doi chieu khi can ho tro.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            Email nay duoc gui tu he thong Vivu Booking sau khi cong thanh toan xac nhan giao dich thanh cong.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>