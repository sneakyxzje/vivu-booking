<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Don dat tour da duoc xac nhan</title>
</head>
<body style="margin:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#16a34a;padding:24px 28px;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;line-height:1.35;">Don dat tour cua quy khach da duoc xac nhan</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:.92;">Ma dat tour #{{ $booking->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Xin chao <strong>{{ $booking->customer_name }}</strong>,
                                don dat tour cua quy khach da duoc Vivu Booking xac nhan. Cho cua quy khach da duoc giu chinh thuc.
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
                                @if($booking->tour?->pickup_location)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Diem don khach</td>
                                        <td style="padding:12px 14px;font-size:13px;font-weight:700;">{{ $booking->tour->pickup_location }}</td>
                                    </tr>
                                @endif
                                @if($booking->tour?->vehicle_info)
                                    <tr>
                                        <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Phuong tien</td>
                                        <td style="padding:12px 14px;font-size:13px;">{{ $booking->tour->vehicle_info }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">So luong khach</td>
                                    <td style="padding:12px 14px;font-size:13px;">{{ $booking->guests }} khach</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f9fafb;font-size:13px;color:#6b7280;">Tong gia tri don</td>
                                    <td style="padding:12px 14px;font-size:18px;font-weight:800;color:#16a34a;">
                                        {{ number_format((float) $booking->total_amount, 0, ',', '.') }} VND
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:14px 0 0;font-size:13px;color:#1d4ed8;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;">
                                Quy khach vui long co mat tai diem don truoc gio khoi hanh it nhat 30 phut
                                va mang theo giay to tuy than. Huong dan vien se lien he truoc ngay di.
                            </p>

                            <p style="margin:22px 0;">
                                <a href="{{ $frontendBookingUrl }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:10px;">
                                    Xem chi tiet booking
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                            Email nay duoc gui tu he thong Vivu Booking. Vui long luu email de doi chieu khi can ho tro.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
