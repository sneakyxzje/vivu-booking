# 03 - Luồng hủy và hoàn tiền

Tài liệu này trả lời trực tiếp bốn câu hỏi của hội đồng:

- Thời gian hủy tour phải trước bao lâu.
- Nếu hủy sát giờ thì có nên cộng lại slot cho tour không.
- Nếu tour đang chạy thì có được hủy không.
- Ai là người được hủy, ai xác nhận.

## 1. Phân loại hủy

Không phải mọi lần hủy đều giống nhau. Hệ thống phải phân biệt bốn loại vì hệ quả tài chính
khác hẳn nhau.

| Loại | Nguyên nhân | Ai chịu phí | Hoàn tiền |
| --- | --- | --- | --- |
| Hủy giữ chỗ | Khách không thanh toán trong 10 phút | Không ai | Không phát sinh, chưa thu tiền |
| Hủy do khách | Khách đổi ý, bận việc | Khách | Theo bảng phí hủy |
| Hủy do hãng | Không đủ khách tối thiểu, thiếu phương tiện, thiếu hướng dẫn viên | Hãng | Hoàn 100 phần trăm, không trừ phí |
| Hủy do bất khả kháng | Bão, lụt, dịch bệnh, lệnh cấm của cơ quan chức năng | Chia sẻ | Hoàn phần dịch vụ chưa sử dụng, trừ chi phí đã chi không thu hồi được |

Cần lưu vào cột `cancel_type` của đơn hàng, vì báo cáo doanh thu và trách nhiệm pháp lý
phụ thuộc vào phân loại này.

## 2. Thời gian hủy phải trước bao lâu

### 2.1 Bảng phí hủy đề xuất

Áp dụng cho tour nội địa, tính theo số ngày trước ngày khởi hành, theo giờ Việt Nam.

| Thời điểm hủy | Phí hủy | Khách nhận lại |
| --- | --- | --- |
| Từ 15 ngày trở lên | 10 phần trăm giá tour | 90 phần trăm |
| Từ 8 đến 14 ngày | 30 phần trăm | 70 phần trăm |
| Từ 4 đến 7 ngày | 50 phần trăm | 50 phần trăm |
| Từ 2 đến 3 ngày | 70 phần trăm | 30 phần trăm |
| Dưới 48 giờ | 100 phần trăm | 0 |
| Không có mặt lúc khởi hành | 100 phần trăm | 0 |

Với tour lễ tết và tour nước ngoài, phí hủy cao hơn vì vé máy bay và phòng khách sạn đã
xuất trước, đề xuất áp dụng hệ số riêng hoặc bảng phí riêng gắn vào tour.

Cơ sở của bảng này: chi phí của một chuyến đi không phát sinh đều theo thời gian mà nhảy bậc
tại các mốc đặt cọc với nhà cung cấp. Khách sạn thường yêu cầu chốt phòng trước 7 ngày,
nhà xe chốt trước 3 ngày, suất ăn chốt trước 1 đến 2 ngày. Khách hủy càng sát thì phần chi phí
hãng đã cam kết mà không hủy được càng lớn.

### 2.2 Mô hình hóa chính sách

Không nên viết cứng bảng phí vào mã. Đề xuất hai bảng:

`cancellation_policies`: `id`, `name`, `description`, `is_default`.

`cancellation_policy_rules`: `policy_id`, `min_hours_before`, `max_hours_before`,
`refund_percent`, `note`.

Tour tham chiếu tới một chính sách qua `tours.cancellation_policy_id`. Đơn hàng **sao chép**
`cancellation_policy_id` tại thời điểm đặt, để việc sửa chính sách về sau không hồi tố lên
đơn đã ký. Đây là nguyên tắc giống với việc đơn hàng lưu giá tại thời điểm đặt.

Tính phí hủy:

```php
$hoursBefore = now('Asia/Ho_Chi_Minh')
    ->diffInHours($booking->schedule->start_date, false);

$rule = $policy->rules()
    ->where('min_hours_before', '<=', $hoursBefore)
    ->where(function ($q) use ($hoursBefore) {
        $q->whereNull('max_hours_before')
          ->orWhere('max_hours_before', '>', $hoursBefore);
    })
    ->orderByDesc('min_hours_before')
    ->first();

// $hoursBefore âm nghĩa là đã qua giờ khởi hành, không rơi vào quy tắc nào, hoàn 0.
$refundPercent = $rule?->refund_percent ?? 0;
```

Số tiền hoàn tính trên **số tiền đã thực thu**, không phải trên tổng giá trị đơn:

```
Tiền hoàn = (tổng đã thu) x (phần trăm hoàn) / 100
```

Ví dụ đơn 10 triệu, khách mới đóng cọc 3 triệu, hủy trước 10 ngày với mức hoàn 70 phần trăm.
Có hai cách hiểu, phải chọn một và ghi vào điều khoản:

- Cách A, hoàn trên số đã thu: khách nhận lại 2,1 triệu. Đơn giản, dễ giải thích.
- Cách B, phí hủy tính trên giá trị đơn: phí là 3 triệu, đúng bằng tiền cọc, khách không nhận
  lại gì và cũng không phải nộp thêm.

Đề xuất chọn **cách B** vì đúng bản chất, tiền cọc là khoản đảm bảo cho cam kết. Nhưng phải
chặn ở mức không bao giờ đòi khách nộp thêm khi khách hủy, tức là:

```
Tiền hoàn = max(0, đã thu - phí hủy)
phí hủy   = tổng giá trị đơn x (100 - phần trăm hoàn) / 100
```

## 3. Hủy sát giờ có nên cộng lại slot không

Đây là câu hỏi tốt nhất trong danh sách feedback, và câu trả lời là **có điều kiện**.

### 3.1 Quy tắc

| Thời điểm hủy | Có trả chỗ về kho không | Lý do |
| --- | --- | --- |
| Chuyến còn `open`, chưa qua `booking_deadline` | Có, trả chỗ ngay | Còn thời gian bán lại cho khách khác |
| Đã qua `booking_deadline`, chuyến đã `closed` hoặc `confirmed` | Không trả tự động | Danh sách đã chốt với nhà cung cấp, số phòng và số suất ăn đã đặt theo danh sách này. Trả chỗ về kho sẽ khiến hệ thống bán ra một chỗ mà thực tế không có dịch vụ đi kèm |
| Chuyến đã `in_progress` | Không | Không bán được nữa |

Nói cách khác: chỗ trống về mặt vật lý không đồng nghĩa với chỗ bán được. Sau hạn chốt,
một chỗ trống là **ghế chết**, hãng đã trả tiền cho nó nhưng không có khách.

### 3.2 Cơ chế mở lại chỗ thủ công

Vẫn phải cho điều hành mở lại chỗ, vì có trường hợp gọi được cho nhà cung cấp để bổ sung
suất. Nhưng đó là quyết định của con người, không phải mặc định của hệ thống.

Thiết kế: thêm cột `seats_released` kiểu boolean vào `bookings`.

- Khi hủy trước hạn chốt: `seats_released = true`, trừ `booked_people` ngay.
- Khi hủy sau hạn chốt: `seats_released = false`, **giữ nguyên** `booked_people`.
  Đơn vẫn là `cancelled` nhưng chỗ chưa về kho.
- Màn hình quản trị hiển thị nhóm "Chỗ trống chưa mở bán lại" với nút mở lại từng đơn.
  Khi điều hành bấm, hệ thống trừ `booked_people`, đặt `seats_released = true`,
  ghi nhật ký ai mở và lý do.

Nhờ cột này, số liệu vẫn nhất quán: `booked_people` luôn bằng tổng số khách của các đơn
chưa hủy cộng với các đơn đã hủy nhưng chưa mở lại chỗ. Có thể viết một lệnh kiểm tra
tính nhất quán để chạy định kỳ.

### 3.3 Ảnh hưởng tới doanh thu

Ghế chết là chi phí thực. Báo cáo cần tách riêng để điều hành thấy được:

- Số chỗ đã bán.
- Số chỗ hủy trước hạn chốt, đã bán lại được bao nhiêu.
- Số chỗ hủy sau hạn chốt, tức là ghế chết, và giá vốn tương ứng.

## 4. Tour đang chạy thì không được hủy

Quy tắc cứng: khi chuyến ở `in_progress` hoặc `completed`, mọi đường dẫn hủy đơn đều bị chặn,
kể cả của quản trị viên.

Kiểm tra phải đặt ở tầng dịch vụ, không phải ở giao diện, vì có bốn lối vào khác nhau:
khách tự hủy, quản trị hủy, chuyển chuyến, và tác vụ nền nhả chỗ quá hạn.

```php
public function assertCancellable(Booking $booking): void
{
    $schedule = $booking->schedule;

    if ($schedule && in_array($schedule->status, ['in_progress', 'completed'], true)) {
        throw new BusinessRuleException(
            'Chuyến đi đã khởi hành, không thể hủy đơn. Vui lòng liên hệ điều hành để ghi nhận trường hợp rời đoàn.'
        );
    }

    if (in_array($booking->status, ['cancelled', 'transferred', 'completed', 'no_show'], true)) {
        throw new BusinessRuleException('Đơn hàng không ở trạng thái có thể hủy.');
    }
}
```

Thay cho hủy, khi chuyến đang chạy hệ thống cung cấp hai nghiệp vụ khác:

| Nghiệp vụ | Khi nào dùng | Hệ quả |
| --- | --- | --- |
| Ghi nhận vắng mặt | Khách không có mặt lúc khởi hành | Đơn chuyển `no_show`, không hoàn tiền, có ghi chú của hướng dẫn viên |
| Ghi nhận rời đoàn giữa chừng | Khách về sớm vì lý do sức khỏe, việc riêng | Đơn vẫn `completed`, ghi nhận từ chặng nào, hoàn lại phần dịch vụ chưa sử dụng theo giá vốn nếu hủy kịp với nhà cung cấp |

Trường hợp rời đoàn vì lý do sức khỏe cần lưu thêm: ai đưa khách về, có báo người nhà chưa,
có phát sinh chi phí y tế không. Đây là dữ liệu bảo hiểm cần khi bồi thường.

## 5. Ai được hủy, ai xác nhận

### 5.1 Bảng phân quyền hủy

| Người hủy | Đơn được phép hủy | Cần duyệt | Hiệu lực |
| --- | --- | --- | --- |
| Hệ thống | `pending` quá `expires_at` | Không | Ngay, trả chỗ ngay |
| Khách vãng lai | `pending` của chính mình, xác thực bằng mã tra cứu | Không | Ngay |
| Khách đã đăng nhập | `pending` của chính mình | Không | Ngay |
| Khách đã thanh toán | Tạo yêu cầu hủy, không tự hủy | Có, điều hành duyệt | Sau khi duyệt |
| Đại diện đoàn | Như khách, thêm quyền hủy một phần số khách | Có | Sau khi duyệt |
| Hướng dẫn viên | Không được hủy đơn | - | - |
| Điều hành | Mọi đơn của chuyến chưa khởi hành | Không, nhưng bắt buộc nhập lý do | Ngay |
| Quản trị viên | Như điều hành, thêm quyền mở lại đơn đã hủy nhầm trong 24 giờ | Không | Ngay |

### 5.2 Luồng yêu cầu hủy của khách đã thanh toán

1. Khách bấm "Yêu cầu hủy" trên trang chi tiết đơn, nhập lý do.
2. Hệ thống tính trước mức hoàn dự kiến theo chính sách và **hiển thị cho khách xác nhận**
   trước khi gửi. Đây là bước quan trọng, tránh khiếu nại về sau.
3. Tạo bản ghi `booking_change_requests` với `type = cancel`, trạng thái `pending`.
4. Điều hành nhận thông báo, xem lại, duyệt hoặc từ chối kèm lý do.
5. Nếu duyệt: đơn chuyển `cancelled`, tạo giao dịch `refund` ở trạng thái `pending`,
   xử lý chỗ theo quy tắc mục 3, gửi thư cho khách.
6. Kế toán thực hiện chuyển khoản, tải chứng từ, chuyển giao dịch sang `succeeded`.
7. Hệ thống gửi thư xác nhận đã hoàn tiền kèm số tiền và ngày chuyển.

Bảng `booking_change_requests` dùng chung cho hủy, đổi chuyến, đổi số khách:

| Cột | Ý nghĩa |
| --- | --- |
| `booking_id` | Đơn liên quan |
| `type` | `cancel`, `transfer`, `change_guests`, `change_passenger` |
| `payload` | JSON mô tả yêu cầu |
| `estimated_refund` | Mức hoàn hệ thống tính tại thời điểm gửi |
| `status` | `pending`, `approved`, `rejected`, `cancelled_by_customer` |
| `requested_by` | Khách hoặc để trống nếu khách vãng lai, kèm `requested_email` |
| `reviewed_by`, `reviewed_at`, `review_note` | Người duyệt |

### 5.3 Nguyên tắc tách quyền

Ba vai trò khác nhau trong một lần hoàn tiền, không được là cùng một người trên hệ thống thật:

- Người **duyệt** phương án hoàn: điều hành.
- Người **chi** tiền: kế toán.
- Người **đối soát**: quản trị, xem báo cáo chênh lệch giữa `payment_logs` và
  `booking_transactions`.

Trong phạm vi đồ án, ba vai trò gộp vào `admin`, nhưng nhật ký vẫn ghi rõ từng bước để
chứng minh thiết kế có tính tới kiểm soát nội bộ.

## 6. Hoàn tiền

### 6.1 Phương thức

| Phương thức | Khi nào | Ghi chú |
| --- | --- | --- |
| Hoàn qua cổng VNPay | Giao dịch trong vòng 30 ngày, hoàn toàn phần hoặc một phần | Cần hợp đồng merchant thật để gọi được API hoàn tiền, ngoài phạm vi đồ án |
| Chuyển khoản thủ công | Mặc định trong phạm vi đồ án | Kế toán nhập số tài khoản khách, chuyển, tải ảnh chứng từ |
| Ghi nhận công nợ | Khách đồng ý giữ lại để dùng cho lần sau | Tạo `credit` gắn với email khách, có hạn sử dụng |

Trong tài liệu bảo vệ nên nêu rõ: **hoàn tiền tự động qua VNPay không triển khai vì cần
hợp đồng thương mại thật với đơn vị thanh toán**, hệ thống thay bằng luồng hoàn thủ công có
kiểm soát và đối soát đầy đủ. Đây là giới hạn có lý do, không phải thiếu sót.

### 6.2 Edge case hoàn tiền

| Tình huống | Xử lý |
| --- | --- |
| Khách hủy khi tiền chưa về tới tài khoản hãng | Chờ đối soát cổng thanh toán rồi mới hoàn, tránh hoàn khống |
| Cổng báo thanh toán thành công sau khi đơn đã bị hủy | Ghi cảnh báo, tạo giao dịch `refund` chờ xử lý. Đã có trong `vnpayReturn` |
| Khách yêu cầu hoàn về tài khoản khác người đứng tên đơn | Yêu cầu xác nhận bằng văn bản, ghi vào ghi chú, tránh rủi ro gian lận |
| Hoàn một phần do giảm số khách | Tạo giao dịch `refund` riêng, đơn vẫn còn hiệu lực |
| Mã giảm giá đã dùng trên đơn bị hủy | Trả lượt về cho mã. Đã xử lý trong `releaseHold` |
| Hoàn nhiều lần cho cùng một đơn | Tổng các khoản hoàn không được vượt tổng đã thu, kiểm tra ở tầng dịch vụ |
| Khách khiếu nại sau khi chuyến đã hoàn thành | Ngoài luồng hoàn tự động, tạo phiếu khiếu nại, xử lý riêng |

## 7. Hủy chuyến do hãng

Xem chi tiết tại [04 - Luồng điều hành chuyến đi](04-luong-dieu-hanh.md), mục hủy chuyến.
Điểm khác biệt cốt lõi so với hủy đơn: hủy chuyến ảnh hưởng tới nhiều khách cùng lúc,
nên hệ thống **không cho phép hoàn tất thao tác** khi còn đơn đã thu tiền chưa được gán
phương án xử lý.
