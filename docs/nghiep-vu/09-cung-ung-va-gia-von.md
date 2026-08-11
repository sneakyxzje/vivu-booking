# 09 - Cung ứng và giá vốn

Tài liệu này bổ sung mảng còn thiếu lớn nhất: nhà cung cấp, tồn chỗ được giữ, giá vốn và
lãi lỗ từng chuyến.

Lý do phải có: hai lập luận đã dùng ở các tài liệu trước đều đứng trên mảng này.
`min_people` được định nghĩa là "số khách tối thiểu để chuyến chạy có lãi" nhưng không có
cách nào tính ra con số đó. Quy tắc hoàn tiền khi rút ngắn chương trình viết là "hoàn theo
giá vốn dịch vụ chưa sử dụng" nhưng giá vốn không tồn tại trong dữ liệu. Thiếu tài liệu này
thì hai chỗ đó là lời nói suông.

## 1. Nhà cung cấp

### 1.1 Phân loại

| Loại | Ví dụ | Đơn vị tính | Đặc điểm |
| --- | --- | --- | --- |
| Lưu trú | Khách sạn, homestay, resort | Phòng đêm | Có giữ phòng theo hợp đồng, hủy có thời hạn |
| Vận chuyển | Nhà xe, hãng tàu, hãng bay | Chuyến hoặc ghế | Chi phí gần như cố định theo chuyến |
| Ăn uống | Nhà hàng | Suất | Chốt số lượng sát ngày |
| Tham quan | Ban quản lý điểm, vé cáp treo | Vé | Giá cố định, mua theo đầu khách |
| Dịch vụ khác | Thuê thuyền, thuê thiết bị, biểu diễn | Tùy | Thường theo đoàn |
| Nhân sự thuê ngoài | Hướng dẫn viên cộng tác, phụ xe | Ngày công | Khi đội nội bộ không đủ |

### 1.2 Mô hình

`suppliers`: `id`, `name`, `type`, `tax_code`, `address`, `contact_name`, `contact_phone`,
`contact_email`, `bank_account`, `payment_terms` (số ngày công nợ), `rating`,
`is_active`, `note`, timestamps.

`supplier_services`: dịch vụ cụ thể của một nhà cung cấp.
`id`, `supplier_id`, `name`, `unit` (`room_night`, `seat`, `meal`, `ticket`, `trip`, `day`),
`net_price` decimal, `currency` mặc định VND, `valid_from`, `valid_to`, `note`, timestamps.

`supplier_contracts`: `id`, `supplier_id`, `contract_number`, `signed_at`, `valid_from`,
`valid_to`, `file_path`, `cancellation_terms` text, `note`.

Điểm quan trọng: `net_price` là **giá net**, tức giá nhà cung cấp bán cho công ty lữ hành,
khác giá niêm yết cho khách lẻ. Đây chính là giá vốn.

### 1.3 Tồn chỗ được giữ

Khái niệm quan trọng nhất của mảng cung ứng. Khách sạn ký hợp đồng giữ cho công ty một số
phòng nhất định mỗi ngày, gọi là allotment. Quá hạn không dùng thì phải trả lại.

`supplier_allotments`: `id`, `supplier_id`, `supplier_service_id`, `date`,
`quantity` (số lượng được giữ), `used` (đã dùng), `release_days_before` (phải trả trước bao
nhiêu ngày nếu không dùng), `note`.

Ý nghĩa với hệ thống đặt tour: **sức chứa thật của một chuyến là giá trị nhỏ nhất trong các
ràng buộc tài nguyên**, không phải một con số gõ tay.

```
Sức chứa thật = min(
    số ghế xe,
    số phòng giữ được x số khách mỗi phòng,
    số suất ăn chốt được,
    số vé tham quan mua được,
    max_people do điều hành đặt
)
```

Ví dụ cụ thể đáng nêu khi bảo vệ: xe 45 chỗ nhưng khách sạn chỉ giữ 20 phòng đôi, tức 40 khách.
Sức chứa thật là 40 chứ không phải 45. Hệ thống hiện tại chỉ có `max_people` phẳng nên bán được
tới 45 rồi mới phát hiện thiếu phòng.

Cách triển khai theo hai giai đoạn:

- Giai đoạn một, giữ nguyên `max_people` nhưng thêm màn hình cảnh báo khi số khách đã bán vượt
  tồn chỗ của bất kỳ tài nguyên nào.
- Giai đoạn hai, tính `max_people` tự động từ tồn chỗ, điều hành chỉ được đặt thấp hơn chứ
  không được cao hơn.

Giai đoạn một đủ để trình bày rằng đã hiểu vấn đề mà không phải viết lại toàn bộ luồng đặt chỗ.

### 1.4 Đặt dịch vụ với nhà cung cấp

`supplier_bookings`: `id`, `tour_schedule_id`, `supplier_id`, `supplier_service_id`,
`quantity`, `unit_price`, `total_amount`, `service_date`,
`status` (`requested`, `confirmed`, `cancelled`), `confirmation_code`,
`cancellation_deadline` datetime, `requested_by`, `confirmed_at`, `note`, timestamps.

Trường `cancellation_deadline` là mấu chốt của quy tắc "hủy sát giờ không trả chỗ" ở tài liệu 03.
Hạn chốt danh sách của chuyến nên được suy ra từ hạn hủy sớm nhất trong các dịch vụ đã đặt,
thay vì mặc định cứng 3 ngày:

```
booking_deadline = min(cancellation_deadline của tất cả supplier_bookings đã confirmed)
```

Đây là cách trả lời chặt chẽ hơn cho câu hỏi của hội đồng: không phải "quy định 3 ngày" mà là
"hạn chốt phụ thuộc dịch vụ nào phải chốt sớm nhất".

### 1.5 Edge case cung ứng

| Tình huống | Xử lý |
| --- | --- |
| Nhà cung cấp báo hết phòng sau khi đã nhận đặt | Ghi sự cố, điều hành tìm nhà cung cấp thay, chênh lệch chi phí do hãng chịu |
| Bán vượt tồn chỗ giữ được | Cảnh báo khi đặt vượt, điều hành xin thêm hoặc dừng bán |
| Tồn chỗ hết hạn phải trả lại | Nhắc trước theo `release_days_before`, tự động giảm sức chứa nếu không gia hạn |
| Giá net thay đổi giữa chừng | Giá vốn của chuyến chốt tại thời điểm đặt dịch vụ, không hồi tố |
| Nhà cung cấp không xuất hóa đơn | Đánh dấu để kế toán xử lý, ảnh hưởng chi phí được khấu trừ |
| Hủy chuyến sau hạn hủy của nhà cung cấp | Vẫn phải trả tiền, ghi nhận chi phí không thu hồi được vào lỗ của chuyến |

## 2. Dự toán chi phí và điểm hòa vốn

### 2.1 Cấu trúc chi phí một chuyến

| Nhóm | Ví dụ | Tính chất |
| --- | --- | --- |
| Chi phí cố định theo chuyến | Thuê xe, thù lao hướng dẫn viên, phí cầu đường, xăng dầu | Không đổi dù đoàn đông hay vắng |
| Chi phí biến đổi theo khách | Phòng, suất ăn, vé tham quan, bảo hiểm | Tỷ lệ với số khách |
| Chi phí chung phân bổ | Chi phí điều hành, tiếp thị, quản lý | Phân bổ theo tỷ lệ |

Đây là lý do tồn tại `min_people`. Chi phí cố định chia cho ít khách thì giá thành mỗi khách
tăng vọt.

### 2.2 Công thức điểm hòa vốn

```
Điểm hòa vốn (số khách) = Tổng chi phí cố định / (Giá bán mỗi khách - Chi phí biến đổi mỗi khách)
```

Ví dụ minh họa nên đưa vào bài bảo vệ:

| Khoản | Giá trị |
| --- | --- |
| Thuê xe 29 chỗ 3 ngày | 9.000.000 |
| Thù lao hướng dẫn viên 3 ngày | 2.400.000 |
| Cầu đường, bến bãi | 1.600.000 |
| **Tổng chi phí cố định** | **13.000.000** |
| Phòng nghỉ mỗi khách 2 đêm | 700.000 |
| Ăn uống mỗi khách | 540.000 |
| Vé tham quan mỗi khách | 260.000 |
| Bảo hiểm mỗi khách | 30.000 |
| **Chi phí biến đổi mỗi khách** | **1.530.000** |
| Giá bán mỗi khách | 2.890.000 |

Điểm hòa vốn = 13.000.000 / (2.890.000 - 1.530.000) = 13.000.000 / 1.360.000 = 9,56
tức **10 khách**.

Vậy `min_people` của chuyến này là 10, không phải con số cảm tính. Với 15 khách, lợi nhuận là
15 x 1.360.000 - 13.000.000 = 7.400.000. Với 8 khách, lỗ 2.120.000.

Chính con số này là căn cứ để điều hành quyết định ở tài liệu 04 mục 1.3: vẫn chạy, ghép chuyến,
hay hủy chuyến.

### 2.3 Mô hình

`schedule_cost_estimates`: dự toán trước chuyến.
`id`, `tour_schedule_id`, `category` (`transport`, `guide`, `accommodation`, `meal`,
`ticket`, `insurance`, `overhead`, `other`), `description`,
`cost_type` (`fixed` hoặc `variable`), `unit_price`, `quantity`, `total_amount`,
`supplier_id` nullable, timestamps.

`schedule_actual_costs`: chi phí thực tế sau chuyến.
Cấu trúc tương tự, thêm `supplier_booking_id`, `guide_settlement_id`, `invoice_number`,
`paid_at`, `variance_note` giải thích chênh lệch so với dự toán.

Trường tính toán trên `tour_schedules`:

| Trường | Cách tính |
| --- | --- |
| `fixed_cost` | Tổng dự toán loại cố định |
| `variable_cost_per_pax` | Tổng dự toán loại biến đổi chia số khách dự kiến |
| `break_even_pax` | Theo công thức trên, làm tròn lên |
| `min_people` | Mặc định bằng `break_even_pax`, điều hành sửa được |

### 2.4 Báo cáo lãi lỗ từng chuyến

Sau khi chuyến kết thúc:

| Chỉ tiêu | Cách tính |
| --- | --- |
| Doanh thu | Tổng đã thu của các đơn hoàn thành, trừ các khoản đã hoàn |
| Phụ thu | Tổng `booking_surcharges` đã thu |
| Giá vốn | Tổng `schedule_actual_costs` |
| Lợi nhuận gộp | Doanh thu cộng phụ thu trừ giá vốn |
| Tỷ suất lợi nhuận gộp | Lợi nhuận gộp chia doanh thu |
| Chênh lệch dự toán | Giá vốn thực tế trừ dự toán, kèm giải thích |
| Số khách thực đi | Từ dữ liệu điểm danh, không phải từ số đã đặt |
| Ghế chết | Số chỗ hủy sau hạn chốt chưa bán lại, nhân chi phí biến đổi mỗi khách |

Chỉ tiêu cuối liên kết trực tiếp với quyết định ở tài liệu 03 mục 3: hệ thống đo được thiệt hại
thật của việc không trả chỗ về kho, thay vì chỉ nói lý thuyết.

### 2.5 Giá vốn phục vụ hoàn tiền

Khi chương trình bị rút ngắn, phần hoàn cho khách tính theo giá vốn dịch vụ chưa sử dụng:

```
Tiền hoàn mỗi khách = Tổng chi phí biến đổi của các dịch vụ đã đặt nhưng không dùng
                      và nhà cung cấp đồng ý hoàn
```

Ví dụ tour 3 ngày bị cắt còn 2 ngày do bão. Ngày thứ ba gồm phòng 350.000, ăn 180.000,
vé tham quan 260.000. Khách sạn không hoàn vì báo muộn, nhà hàng và điểm tham quan hoàn đủ.
Tiền hoàn mỗi khách là 440.000, phần 350.000 công ty chịu.

Cách tính này minh bạch và giải thích được với khách, khác hẳn việc lấy giá bán chia cho số ngày.

## 3. Thù lao và tạm ứng hướng dẫn viên

### 3.1 Vì sao thuộc mảng này

Thù lao hướng dẫn viên là chi phí cố định lớn thứ hai sau phương tiện. Tiền tạm ứng cho hướng
dẫn viên chi dọc đường là dòng tiền thật của công ty, phải quyết toán.

### 3.2 Mô hình

`guide_rates`: `id`, `guide_id` nullable, `tour_type` nullable, `daily_rate`,
`overnight_allowance`, `effective_from`. Để trống `guide_id` nghĩa là mức chung.

`guide_advances`: tạm ứng trước chuyến.
`id`, `tour_schedule_id`, `guide_id`, `amount`, `purpose`, `issued_by`, `issued_at`,
`status` (`issued`, `settled`), timestamps.

`guide_settlements`: quyết toán sau chuyến.
`id`, `guide_advance_id`, `tour_schedule_id`, `guide_id`, `advance_amount`,
`spent_amount`, `returned_amount`, `additional_claim`, `status`
(`draft`, `submitted`, `approved`, `rejected`), `submitted_at`, `approved_by`,
`approved_at`, `note`, timestamps.

`guide_settlement_items`: từng khoản chi.
`id`, `guide_settlement_id`, `category`, `description`, `amount`, `receipt_path`,
`spent_at`, `is_approved`, `reject_reason`.

### 3.3 Luồng

1. Trước chuyến, điều hành duyệt mức tạm ứng dựa trên dự toán chi phí phải trả bằng tiền mặt.
2. Kế toán chi tiền, ghi `guide_advances`.
3. Trong chuyến, hướng dẫn viên chi và giữ chứng từ, có thể nhập dần vào ứng dụng kèm ảnh
   hóa đơn.
4. Sau chuyến, hướng dẫn viên nộp quyết toán trong thời hạn quy định, đề xuất 3 ngày làm việc.
5. Kế toán đối chiếu từng khoản, duyệt hoặc từ chối kèm lý do.
6. Chênh lệch: chi ít hơn tạm ứng thì nộp lại, chi nhiều hơn thì công ty chi bù.
7. Các khoản đã duyệt tự động ghi vào `schedule_actual_costs` của chuyến.

Bước 7 là điểm nối quan trọng: quyết toán của hướng dẫn viên chảy thẳng vào báo cáo lãi lỗ,
không phải nhập lại lần hai.

### 3.4 Edge case

| Tình huống | Xử lý |
| --- | --- |
| Hướng dẫn viên chi vượt tạm ứng | Cho phép, ghi `additional_claim`, cần duyệt riêng vì vượt hạn mức |
| Khoản chi không có hóa đơn | Cho phép với hạn mức nhỏ và có xác nhận, ví dụ phí gửi xe, bến bãi |
| Nộp quyết toán muộn | Nhắc tự động, chặn tạm ứng chuyến tiếp theo nếu còn quyết toán treo |
| Hướng dẫn viên nghỉ việc còn tạm ứng chưa quyết toán | Đánh dấu công nợ phải thu, chặn xóa tài khoản |
| Chi phí phát sinh do sự cố | Gắn với bản ghi sự cố, xét riêng vì có thể thu lại từ khách hoặc bảo hiểm |
| Thay hướng dẫn viên giữa chừng | Mỗi người quyết toán phần của mình, gắn theo giai đoạn phân công |

Trường hợp cuối liên kết với bảng `schedule_guide_assignments` ở tài liệu 04: có phân công theo
giai đoạn thì mới quyết toán tách bạch được.

## 4. Công nợ nhà cung cấp

`supplier_payables`: `id`, `supplier_id`, `supplier_booking_id` nullable,
`tour_schedule_id` nullable, `amount`, `due_date`, `status`
(`pending`, `partially_paid`, `paid`, `disputed`), `invoice_number`, `note`, timestamps.

`supplier_payments`: `id`, `supplier_payable_id`, `amount`, `paid_at`, `method`,
`reference`, `evidence_path`, `paid_by`.

Báo cáo cần có: công nợ đến hạn trong 7 ngày, công nợ quá hạn, tổng phải trả theo nhà cung cấp.

## 5. Liên kết với các tài liệu khác

| Chỗ dùng | Tài liệu | Vai trò của tài liệu này |
| --- | --- | --- |
| `min_people` | 01, 04 | Cung cấp cách tính từ điểm hòa vốn |
| `booking_deadline` | 01, 03 | Suy ra từ hạn hủy của nhà cung cấp |
| Không trả chỗ khi hủy sát giờ | 03 | Cung cấp lý do định lượng và đo được thiệt hại |
| Hoàn theo giá vốn khi rút ngắn chương trình | 03, 04 | Cung cấp dữ liệu giá vốn |
| Ba lựa chọn khi chuyến thiếu khách | 04 | Cung cấp căn cứ tài chính cho quyết định |
| Sức chứa chuyến | 01 | Cung cấp ràng buộc tài nguyên thật |
| Chi phí phát sinh do sự cố | 04 | Cung cấp giá vốn để phân bổ chi phí |
