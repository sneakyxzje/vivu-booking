{{--
    Hợp đồng du lịch trọn gói — bản in.

    Trang này KHÔNG dùng thư viện dựng PDF. Trình duyệt in ra PDF, và đó là lựa chọn có chủ ý:

      - Tiếng Việt có dấu là chỗ các bộ dựng PDF hay vỡ, vì phải nhúng phông có đủ Latin mở rộng.
        Trình duyệt thì không gặp vấn đề ấy bao giờ.
      - Không thêm phụ thuộc nào để cài, để hỏng, để phải cài lại trên máy chấm.

    Nên toàn bộ trình bày nằm ở @media print, và trang tự gọi hộp thoại in khi mở.

    Số liệu đọc từ đơn tại thời điểm in, trừ số hợp đồng và các mốc thời gian. Chính sách hủy lấy
    theo bản đơn đã chép lúc đặt, nên hợp đồng ghi đúng bậc hoàn có hiệu lực lúc khách mua.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->contract_number }} — Hợp đồng du lịch</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 13pt;
            line-height: 1.55;
            color: #000;
            margin: 0;
            padding: 24px;
            background: #f1f1f1;
        }

        .to-giay {
            background: #fff;
            max-width: 210mm;
            margin: 0 auto;
            padding: 18mm 16mm;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .15);
        }

        .quoc-hieu { text-align: center; line-height: 1.4; }
        .quoc-hieu strong { display: block; text-transform: uppercase; }
        .quoc-hieu em { font-style: normal; text-decoration: underline; }

        h1 {
            text-align: center;
            font-size: 16pt;
            text-transform: uppercase;
            margin: 22px 0 4px;
            letter-spacing: .5px;
        }

        .so-hd { text-align: center; margin-bottom: 6px; }
        .can-cu { font-style: italic; font-size: 11.5pt; margin: 14px 0 18px; }
        .can-cu p { margin: 2px 0; }

        h2 {
            font-size: 13pt;
            text-transform: uppercase;
            margin: 18px 0 6px;
        }

        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { border: 1px solid #333; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #eee; font-weight: bold; }
        td.so { text-align: right; white-space: nowrap; }

        .doi-ben { width: 100%; border: 0; }
        .doi-ben td { border: 0; padding: 1px 0; vertical-align: top; }
        .doi-ben td:first-child { width: 42%; }

        ol.dieu-khoan { padding-left: 20px; margin: 6px 0; }
        ol.dieu-khoan li { margin-bottom: 4px; }

        .chu-ky { width: 100%; border: 0; margin-top: 26px; }
        .chu-ky td { border: 0; text-align: center; width: 50%; padding-top: 4px; }
        .chu-ky .vai { font-weight: bold; text-transform: uppercase; }
        .chu-ky .ghi-chu { font-style: italic; font-size: 11pt; }
        .chu-ky .cho-trong { height: 70px; }

        .thanh-cong-cu {
            max-width: 210mm;
            margin: 0 auto 14px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .thanh-cong-cu button {
            font: inherit;
            font-size: 12pt;
            padding: 8px 18px;
            border: 1px solid #0b817a;
            background: #0b817a;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        .thanh-cong-cu span { font-size: 11pt; color: #444; }

        /* Bản in: bỏ nền, bỏ bóng, bỏ thanh công cụ — chỉ còn tờ giấy. */
        @media print {
            body { background: #fff; padding: 0; }
            .to-giay { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            .thanh-cong-cu { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
            h2 { page-break-after: avoid; }
            .chu-ky { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="thanh-cong-cu">
    <button type="button" onclick="window.print()">In / Lưu thành PDF</button>
    <span>Chọn “Đích đến: Lưu dưới dạng PDF” trong hộp thoại in để tải tệp về.</span>
</div>

<div class="to-giay">

    <div class="quoc-hieu">
        <strong>Cộng hòa xã hội chủ nghĩa Việt Nam</strong>
        <em>Độc lập &ndash; Tự do &ndash; Hạnh phúc</em>
    </div>

    <h1>Hợp đồng dịch vụ du lịch trọn gói</h1>
    <p class="so-hd">Số: <strong>{{ $contract->contract_number }}</strong></p>

    <div class="can-cu">
        <p>Căn cứ Bộ luật Dân sự và Luật Du lịch hiện hành;</p>
        <p>Căn cứ nhu cầu và khả năng của hai bên,</p>
    </div>

    <p>Hôm nay, ngày {{ $ngayCap->format('d') }} tháng {{ $ngayCap->format('m') }} năm
        {{ $ngayCap->format('Y') }}, tại {{ $congTy['address'] }}, chúng tôi gồm:</p>

    <h2>Bên A &ndash; Bên cung cấp dịch vụ</h2>
    <table class="doi-ben">
        <tr><td>Tên đơn vị:</td><td><strong>{{ $congTy['name'] }}</strong></td></tr>
        <tr><td>Địa chỉ:</td><td>{{ $congTy['address'] }}</td></tr>
        <tr><td>Điện thoại:</td><td>{{ $congTy['phone'] }} &ndash; Email: {{ $congTy['email'] }}</td></tr>
        <tr><td>Mã số thuế:</td><td>{{ $congTy['tax_code'] }}</td></tr>
        <tr><td>Giấy phép kinh doanh lữ hành:</td><td>{{ $congTy['license_no'] }}</td></tr>
        <tr><td>Người đại diện:</td><td>{{ $congTy['representative'] }} &ndash; Chức vụ: {{ $congTy['representative_title'] }}</td></tr>
    </table>

    <h2>Bên B &ndash; Khách hàng</h2>
    <table class="doi-ben">
        <tr><td>Họ và tên:</td><td><strong>{{ $booking->customer_name }}</strong></td></tr>
        <tr><td>Điện thoại:</td><td>{{ $booking->customer_phone ?: '……………………' }}</td></tr>
        <tr><td>Email:</td><td>{{ $booking->customer_email }}</td></tr>
        <tr><td>Mã đơn đặt:</td><td>BK-{{ $booking->id }}</td></tr>
    </table>

    <p>Hai bên thống nhất ký kết hợp đồng với các điều khoản sau:</p>

    {{-- ĐIỀU 1 — thứ Bên A bán. Đây là mục quyết định mọi tranh chấp về sau: cái gì đã hứa. --}}
    <h2>Điều 1. Nội dung chương trình du lịch</h2>
    <table>
        <tr><th style="width:32%">Tên chương trình</th><td>{{ $tour->title }}</td></tr>
        <tr><th>Hành trình</th><td>{{ $tour->start_location }}{{ $tour->end_location ? ' → ' . $tour->end_location : '' }}</td></tr>
        <tr><th>Thời gian</th><td>{{ $tour->number_of_days }} ngày {{ $tour->number_of_nights }} đêm</td></tr>
        <tr><th>Khởi hành</th><td>{{ $khoiHanh?->format('H:i, d/m/Y') ?? 'Theo thông báo của Bên A' }}</td></tr>
        @if ($ketThuc)
            <tr><th>Dự kiến kết thúc</th><td>{{ $ketThuc->format('d/m/Y') }}</td></tr>
        @endif
        @if ($tour->pickup_location)
            <tr><th>Điểm đón</th><td>{{ $tour->pickup_location }}</td></tr>
        @endif
        @if ($tour->vehicle_info)
            <tr><th>Phương tiện</th><td>{{ $tour->vehicle_info }}</td></tr>
        @endif
    </table>

    @if ($tour->itineraries->isNotEmpty())
        <p style="margin-top:10px"><strong>Lịch trình chi tiết:</strong></p>
        <table>
            <thead>
                <tr><th style="width:16%">Ngày</th><th>Nội dung</th></tr>
            </thead>
            <tbody>
                @foreach ($tour->itineraries as $chang)
                    <tr>
                        <td>Ngày {{ $chang->day_number }}</td>
                        <td>{{ $chang->title }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ĐIỀU 2 — số người và tiền. --}}
    <h2>Điều 2. Số lượng khách và giá trị hợp đồng</h2>
    <table>
        <thead>
            <tr><th>Đối tượng</th><th style="width:18%">Số lượng</th><th style="width:26%">Đơn giá</th><th style="width:26%">Thành tiền</th></tr>
        </thead>
        <tbody>
            @foreach ($dongGia as $dong)
                <tr>
                    <td>{{ $dong['label'] }}</td>
                    <td class="so">{{ $dong['count'] }}</td>
                    <td class="so">{{ number_format($dong['unit'], 0, ',', '.') }} đ</td>
                    <td class="so">{{ number_format($dong['total'], 0, ',', '.') }} đ</td>
                </tr>
            @endforeach
            @if ($giamGia > 0)
                <tr>
                    <td colspan="3"><em>Giảm giá{{ $booking->discount_code ? ' (mã ' . $booking->discount_code . ')' : '' }}</em></td>
                    <td class="so">&minus; {{ number_format($giamGia, 0, ',', '.') }} đ</td>
                </tr>
            @endif
            <tr>
                <th colspan="3">Tổng giá trị hợp đồng</th>
                <th class="so">{{ number_format($tongTien, 0, ',', '.') }} đ</th>
            </tr>
        </tbody>
    </table>
    <p><em>Bằng chữ: {{ $tongTienBangChu }}.</em></p>

    {{--
        ĐIỀU 3 — bao gồm và không bao gồm.

        Mục này là căn cứ phân bổ chi phí khi có sự cố dọc đường: thứ đã bán thì Bên A lo, thứ
        không bán thì Bên B tự trả. Xem thêm Điều 6.
    --}}
    <h2>Điều 3. Dịch vụ bao gồm và không bao gồm</h2>
    <p><strong>3.1. Giá trên đã bao gồm:</strong></p>
    @if ($tour->services->isNotEmpty())
        <ol class="dieu-khoan">
            @foreach ($tour->services as $dv)
                <li>{{ $dv->name }}@if ($dv->description) &ndash; {{ $dv->description }}@endif</li>
            @endforeach
        </ol>
    @else
        <p>Theo mô tả chương trình đính kèm.</p>
    @endif

    <p><strong>3.2. Giá trên không bao gồm:</strong></p>
    <ol class="dieu-khoan">
        <li>Chi phí cá nhân: đồ uống, giặt là, điện thoại, mua sắm và các dịch vụ ngoài chương trình.</li>
        <li>Các bữa ăn, đêm nghỉ phát sinh ngoài lịch trình đã thỏa thuận tại Điều 1.</li>
        <li>Chi phí làm hộ chiếu, thị thực (nếu có) và chi phí đi lại tới điểm đón.</li>
        <li>Tiền bồi dưỡng hướng dẫn viên và lái xe.</li>
    </ol>

    {{-- ĐIỀU 4 — thanh toán. --}}
    <h2>Điều 4. Phương thức thanh toán</h2>
    <ol class="dieu-khoan">
        <li>Bên B thanh toán cho Bên A tổng số tiền ghi tại Điều 2 bằng tiền mặt hoặc chuyển khoản.</li>
        <li>Tài khoản nhận: {{ $congTy['bank_account'] }}.</li>
        <li>
            Tình trạng thanh toán tại thời điểm ký:
            <strong>{{ $daThanhToanLuc ? 'đã thanh toán ngày ' . $daThanhToanLuc->format('d/m/Y') : 'chưa thanh toán' }}</strong>.
        </li>
    </ol>

    {{-- ĐIỀU 5 — hủy và hoàn. Bậc lấy từ chính sách đơn đã chép lúc đặt. --}}
    <h2>Điều 5. Hủy chương trình và mức hoàn tiền</h2>
    @if ($chinhSach && $bacHoan->isNotEmpty())
        <p>Bên B hủy chương trình, mức hoàn được tính theo thời điểm báo hủy so với giờ khởi hành:</p>
        <table>
            <thead>
                <tr><th>Thời điểm báo hủy</th><th style="width:24%">Mức hoàn</th></tr>
            </thead>
            <tbody>
                @foreach ($bacHoan as $bac)
                    <tr>
                        <td>{{ $bac->windowLabel() }}@if ($bac->note) &ndash; {{ $bac->note }}@endif</td>
                        <td class="so">{{ $bac->refund_percent }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p><em>Áp dụng chính sách: {{ $chinhSach->name }}.</em></p>
    @else
        <p>Áp dụng theo chính sách hủy do Bên A công bố tại thời điểm Bên B đặt chương trình.</p>
    @endif
    <ol class="dieu-khoan">
        <li>Mức hoàn tính trên số tiền Bên B đã thực trả, và không bao giờ vượt quá số tiền đó.</li>
        <li>Chương trình đã khởi hành thì không hủy được; trường hợp Bên B tự rời đoàn được xử lý theo Điều 6.</li>
        <li>Bên A hủy chương trình vì lý do chủ quan thì hoàn Bên B 100% số tiền đã nhận.</li>
    </ol>

    {{--
        ĐIỀU 6 — bất khả kháng.

        Điều khoản quan trọng nhất khi có sự cố dọc đường, và là điều khoản mà hệ thống thi hành
        thật chứ không chỉ in ra: mỗi khoản chi phát sinh được ghi kèm người chịu, và khoản Bên B
        phải trả chỉ có hiệu lực sau khi Bên B xác nhận đồng ý.
    --}}
    <h2>Điều 6. Trường hợp bất khả kháng</h2>
    <ol class="dieu-khoan">
        <li>
            Bất khả kháng gồm thiên tai, bão lũ, dịch bệnh, đình công, sự cố phương tiện và các
            trường hợp khách quan khác mà hai bên không lường trước và không khắc phục được.
        </li>
        <li>
            Khi xảy ra, Bên A có trách nhiệm bảo đảm an toàn cho Bên B, thu xếp phương tiện đưa
            Bên B trở về điểm xuất phát, và cung cấp đủ các dịch vụ đã cam kết tại Điều 1 và Điều 3.
            <strong>Chi phí vận chuyển đưa khách trở về do Bên A chịu, không giới hạn.</strong>
        </li>
        <li>
            Các chi phí ăn ở phát sinh <strong>ngoài phạm vi đã cam kết</strong> &ndash; bữa ăn và
            đêm nghỉ vượt quá số lượng ghi tại Điều 1 &ndash; do Bên B tự thanh toán. Bên A có
            trách nhiệm thu xếp giúp Bên B, kể cả với những khoản Bên B tự trả.
        </li>
        <li>
            Phần chương trình đã thu tiền mà không thực hiện được, Bên A hoàn lại cho Bên B tương
            ứng phần chưa sử dụng.
        </li>
        <li>
            Mọi khoản Bên B phải trả thêm đều được thông báo và <strong>chỉ thu sau khi Bên B đồng
            ý</strong>, kèm diễn giải cụ thể từng khoản.
        </li>
    </ol>

    <h2>Điều 7. Quyền và nghĩa vụ của hai bên</h2>
    <p><strong>7.1. Bên A:</strong></p>
    <ol class="dieu-khoan">
        <li>Thực hiện đúng và đủ chương trình, dịch vụ đã cam kết.</li>
        <li>Bố trí hướng dẫn viên và phương tiện theo đúng nội dung Điều 1.</li>
        <li>Mua bảo hiểm du lịch cho khách theo quy định.</li>
        <li>Bảo mật thông tin cá nhân của khách, chỉ cung cấp cho nhà cung cấp dịch vụ trong phạm vi cần thiết.</li>
    </ol>
    <p><strong>7.2. Bên B:</strong></p>
    <ol class="dieu-khoan">
        <li>Cung cấp đầy đủ, chính xác thông tin hành khách trước hạn chốt danh sách.</li>
        <li>Thanh toán đúng hạn theo Điều 4.</li>
        <li>Có mặt đúng giờ, đúng điểm đón; tuân thủ hướng dẫn về an toàn của Bên A.</li>
        <li>Tự chịu trách nhiệm về tài sản cá nhân và về việc tự ý tách đoàn.</li>
    </ol>

    <h2>Điều 8. Điều khoản chung</h2>
    <ol class="dieu-khoan">
        <li>Hai bên ưu tiên giải quyết tranh chấp bằng thương lượng; không đạt được thì đưa ra tòa án có thẩm quyền.</li>
        <li>Hợp đồng có hiệu lực từ ngày ký và thanh lý khi hai bên hoàn thành nghĩa vụ.</li>
        <li>Hợp đồng lập thành 02 bản có giá trị như nhau, mỗi bên giữ 01 bản.</li>
    </ol>

    <table class="chu-ky">
        <tr>
            <td class="vai">Đại diện Bên A</td>
            <td class="vai">Đại diện Bên B</td>
        </tr>
        <tr>
            <td class="ghi-chu">(Ký, ghi rõ họ tên, đóng dấu)</td>
            <td class="ghi-chu">(Ký, ghi rõ họ tên)</td>
        </tr>
        <tr>
            <td class="cho-trong"></td>
            <td class="cho-trong"></td>
        </tr>
        <tr>
            <td><strong>{{ $congTy['representative'] }}</strong></td>
            <td><strong>{{ $booking->customer_name }}</strong></td>
        </tr>
    </table>

</div>

<script>
    /*
      Mở hộp thoại in ngay, để thao tác của người dùng chỉ còn "Lưu thành PDF".
      Chờ tải xong rồi mới gọi, nếu không thì bản in thiếu phần bố cục.

      Nút trên thanh công cụ vẫn còn cho người bấm Hủy rồi đổi ý.
    */
    window.addEventListener('load', function () {
        window.setTimeout(function () { window.print(); }, 350);
    });
</script>

</body>
</html>
