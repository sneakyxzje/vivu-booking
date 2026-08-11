# 10 - Tài chính và kế toán

Tài liệu này xử lý mảng thiếu thứ hai: nguyên tắc ghi nhận doanh thu, đối soát, và các báo cáo
tài chính. Sổ giao dịch đơn hàng đã mô tả ở tài liệu 02 mục 2.3, ở đây chỉ nói phần bổ sung.

## 1. Ghi nhận doanh thu

### 1.1 Vấn đề

Đây là điểm bộ tài liệu trước bỏ sót hoàn toàn, và là câu hỏi dễ gặp nếu hội đồng có người
am hiểu kế toán.

Trong kinh doanh lữ hành, **tiền thu được không phải là doanh thu**. Khách trả tiền cho tour
khởi hành tháng sau thì tại thời điểm thu, công ty chưa cung cấp dịch vụ nào. Khoản đó là
nghĩa vụ phải thực hiện, kế toán gọi là **doanh thu chưa thực hiện**.

Doanh thu chỉ được ghi nhận khi dịch vụ đã cung cấp, tức là **khi chuyến kết thúc**.

Hệ quả với hệ thống hiện tại: bảng điều khiển quản trị đang cộng tiền đã thu và gọi đó là
doanh thu. Về mặt kế toán, con số này sai. Nó là dòng tiền vào, không phải doanh thu.

### 1.2 Ba chỉ tiêu phải tách bạch

| Chỉ tiêu | Định nghĩa | Thời điểm ghi nhận |
| --- | --- | --- |
| Dòng tiền vào | Tiền thực nhận từ khách | Khi giao dịch thanh toán thành công |
| Doanh thu chưa thực hiện | Tiền đã nhận cho chuyến chưa khởi hành | Số dư tại một thời điểm |
| Doanh thu | Giá trị dịch vụ đã cung cấp | Khi chuyến chuyển sang `completed` |

Quan hệ:

```
Doanh thu chưa thực hiện cuối kỳ =
      Doanh thu chưa thực hiện đầu kỳ
    + Tiền thu trong kỳ cho các chuyến chưa hoàn thành
    - Doanh thu ghi nhận trong kỳ
    - Tiền hoàn trong kỳ
```

### 1.3 Triển khai

Thêm vào `bookings`: `revenue_recognized_at` datetime nullable, `recognized_amount` decimal.

Khi chuyến chuyển `completed`, với mỗi đơn ở trạng thái `completed` hoặc `no_show`:
ghi nhận doanh thu bằng số tiền đã thu thực tế trừ các khoản hoàn, đặt `revenue_recognized_at`.

Đơn `no_show` vẫn ghi nhận doanh thu đủ, vì dịch vụ đã sẵn sàng và chi phí đã phát sinh,
khách không sử dụng là lựa chọn của khách.

Bảng điều khiển sửa lại thành ba số riêng biệt thay vì một số:

| Ô hiển thị | Nguồn |
| --- | --- |
| Tiền thu trong tháng | Tổng `booking_transactions` loại thu, trạng thái thành công |
| Doanh thu ghi nhận trong tháng | Tổng `recognized_amount` của các đơn có `revenue_recognized_at` trong tháng |
| Doanh thu chưa thực hiện | Tổng đã thu của các đơn thuộc chuyến chưa `completed` |

Việc tách ba số này là thay đổi nhỏ về mã nhưng đáng nói khi bảo vệ, vì cho thấy hiểu bản chất
kế toán chứ không chỉ cộng tiền.

### 1.4 Khi nào không ghi nhận doanh thu

| Trường hợp | Xử lý |
| --- | --- |
| Chuyến bị hủy, đã hoàn tiền cho khách | Không ghi nhận doanh thu, giảm doanh thu chưa thực hiện |
| Chuyến bị hủy, khách chuyển sang chuyến khác | Chuyển nghĩa vụ sang chuyến mới, chưa ghi nhận |
| Khách hủy, công ty giữ phí hủy | Phí hủy ghi nhận là **thu nhập khác** ngay khi hủy, không phải doanh thu bán tour |
| Khách ghi nhận công nợ dùng cho lần sau | Vẫn là nghĩa vụ, chưa ghi nhận doanh thu, chuyển sang số dư công nợ khách |
| Chuyến rút ngắn do sự cố | Ghi nhận phần dịch vụ đã cung cấp, phần hoàn trừ ra |

Trường hợp phí hủy đáng chú ý: đó là khoản công ty được hưởng mà không cung cấp dịch vụ,
nên xếp vào thu nhập khác. Phân biệt được điều này là một chi tiết chuyên môn.

## 2. Đối soát

### 2.1 Ba lớp đối soát

| Lớp | So sánh | Tần suất | Phát hiện được |
| --- | --- | --- | --- |
| Cổng thanh toán | `payment_logs` và `booking_transactions` | Hằng ngày | Giao dịch cổng báo thành công mà hệ thống chưa ghi nhận, và ngược lại |
| Ngân hàng | Sao kê ngân hàng và tổng thu theo cổng | Hằng tháng | Tiền cổng giữ chưa chuyển về, phí dịch vụ cổng |
| Nội bộ | Tổng đã thu theo đơn và tổng theo chuyến | Hằng tuần | Sai lệch do thao tác thủ công |

### 2.2 Báo cáo chênh lệch

Lệnh chạy nền tạo báo cáo liệt kê:

- Giao dịch cổng thành công nhưng đơn không ở trạng thái đã thanh toán.
- Đơn đã thanh toán nhưng không có giao dịch cổng tương ứng.
- Đơn có tổng giao dịch khác `total_amount` mà không có khoản điều chỉnh giải thích.
- Đơn đã hủy còn giao dịch thu chưa hoàn.
- Khoản hoàn ở trạng thái chờ quá số ngày quy định.

Mỗi dòng chênh lệch phải được xử lý và đánh dấu, không được để tồn.

### 2.3 Bảng dữ liệu

`reconciliation_runs`: `id`, `type`, `period_from`, `period_to`, `run_at`, `run_by`,
`total_checked`, `total_mismatched`, `status`.

`reconciliation_issues`: `id`, `reconciliation_run_id`, `booking_id` nullable,
`payment_log_id` nullable, `issue_type`, `description`, `amount_difference`,
`status` (`open`, `resolved`, `ignored`), `resolved_by`, `resolved_at`, `resolution_note`.

## 3. Dòng tiền

### 3.1 Vì sao quan trọng với lữ hành

Công ty lữ hành thu tiền khách trước, trả tiền nhà cung cấp sau hoặc trong khi tour chạy.
Khoảng chênh giữa hai thời điểm tạo ra dòng tiền dương tạm thời, nhưng đó là **tiền của khách**
chứ không phải lợi nhuận. Nhiều doanh nghiệp lữ hành đổ vỡ vì dùng tiền thu trước của tour sau
để trả chi phí tour trước.

Hệ thống cần cảnh báo được điều này.

### 3.2 Báo cáo dòng tiền theo chuyến

| Chỉ tiêu | Nguồn |
| --- | --- |
| Đã thu từ khách | `booking_transactions` loại thu |
| Đã tạm ứng cho hướng dẫn viên | `guide_advances` |
| Đã trả nhà cung cấp | `supplier_payments` |
| Còn phải trả nhà cung cấp | `supplier_payables` chưa thanh toán |
| Còn phải thu từ khách | `total_amount` trừ đã thu |
| Dòng tiền ròng của chuyến | Đã thu trừ đã chi |
| Nghĩa vụ chưa thực hiện | Đã thu của các chuyến chưa hoàn thành |

Cảnh báo cần có: khi tổng nghĩa vụ chưa thực hiện lớn hơn số dư tiền mặt, doanh nghiệp đang
dùng tiền của khách để chi cho việc khác.

## 4. Hóa đơn

Nằm ngoài phạm vi triển khai theo tài liệu 00 mục 3.2, nhưng cấu trúc dữ liệu vẫn dự phòng.

`invoices`: `id`, `booking_id`, `invoice_number`, `invoice_date`, `buyer_name`,
`buyer_tax_code`, `buyer_address`, `subtotal`, `vat_rate`, `vat_amount`, `total_amount`,
`status` (`draft`, `issued`, `cancelled`, `replaced`), `provider_reference`,
`file_path`, timestamps.

Lưu ý về thuế: dịch vụ lữ hành trọn gói tại Việt Nam áp dụng cách tính thuế giá trị gia tăng
riêng, không đơn giản là nhân tổng tiền với một tỷ lệ. Hệ thống chỉ lưu dữ liệu, không tự tính,
và ghi rõ đây là phần thuộc chuyên môn kế toán nằm ngoài phạm vi.

## 5. Bộ báo cáo tối thiểu

| Báo cáo | Nội dung | Người dùng |
| --- | --- | --- |
| Lãi lỗ từng chuyến | Doanh thu, giá vốn, lợi nhuận gộp, chênh lệch dự toán | Điều hành, quản trị |
| Lãi lỗ theo tour | Gộp các chuyến, tìm tour nào thực sự có lãi | Quản trị |
| Doanh thu theo kỳ | Ba chỉ tiêu tách bạch ở mục 1.3 | Quản trị, kế toán |
| Dòng tiền | Theo mục 3.2 | Quản trị, kế toán |
| Công nợ phải trả | Theo nhà cung cấp, theo hạn | Kế toán |
| Quyết toán hướng dẫn viên | Còn treo, quá hạn nộp | Kế toán, điều hành |
| Đối soát cổng thanh toán | Danh sách chênh lệch chưa xử lý | Kế toán |
| Tỷ lệ lấp đầy | Số khách thực trên sức chứa, theo tour và theo tháng | Quản trị |
| Ghế chết | Chỗ hủy sau hạn chốt chưa bán lại, quy ra tiền | Điều hành |
| Tỷ lệ hủy | Theo nguyên nhân và theo mốc thời gian | Quản trị |

Hai báo cáo cuối liên kết thẳng với các quyết định nghiệp vụ ở tài liệu 03 và 04, cho phép
kiểm chứng xem chính sách đặt ra có hợp lý không thay vì chỉ đặt rồi thôi.

## 6. Edge case tài chính

| Tình huống | Xử lý |
| --- | --- |
| Chuyến hoàn thành nhưng chưa có chi phí thực tế | Ghi nhận doanh thu, giá vốn tạm dùng dự toán, đánh dấu chưa quyết toán |
| Chi phí về sau khi đã chốt báo cáo tháng | Ghi vào kỳ phát hiện, có ghi chú thuộc chuyến của kỳ trước |
| Khách trả thừa không đòi lại | Sau thời hạn quy định chuyển sang thu nhập khác, có nhật ký |
| Hoàn tiền cho đơn đã ghi nhận doanh thu | Giảm doanh thu kỳ hiện tại, không sửa kỳ đã chốt |
| Phí dịch vụ cổng thanh toán | Là chi phí tài chính, không trừ vào doanh thu bán tour |
| Chuyến lỗ nhưng vẫn chạy theo quyết định điều hành | Ghi lý do vào chuyến để báo cáo giải thích được |
| Tiền cọc của đoàn hủy hợp đồng | Thành thu nhập khác theo điều khoản hợp đồng |
| Nhiều đơn thuộc một hợp đồng đoàn | Ghi nhận doanh thu theo chuyến, không theo từng đơn con |
