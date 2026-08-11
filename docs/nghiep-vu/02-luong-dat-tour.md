# 02 - Luồng đặt tour và thanh toán

## 1. Đặt tour

### 1.1 Luồng chính

1. Khách chọn tour, chọn chuyến khởi hành trong danh sách chuyến đang `open`.
2. Khách nhập số lượng khách theo ba nhóm: người lớn, trẻ em, em bé.
3. Khách nhập thông tin liên hệ và danh sách hành khách.
4. Khách nhập mã giảm giá nếu có, hệ thống kiểm tra hiệu lực.
5. Hệ thống mở giao dịch, khóa dòng chuyến khởi hành, nhả các chỗ quá hạn của chuyến đó,
   kiểm tra số chỗ còn lại, tạo đơn ở trạng thái `pending`, cộng `booked_people`,
   đặt `expires_at = now() + 10 phút`.
6. Hệ thống sinh liên kết thanh toán VNPay có `vnp_ExpireDate` trùng với `expires_at`.
7. Gửi thư xác nhận tiếp nhận kèm mã tra cứu và hạn thanh toán.

### 1.2 Quy tắc tính tiền

| Thành phần | Cách tính |
| --- | --- |
| Giá người lớn | `tours.adult_price`, mặc định bằng `price` nếu để trống |
| Giá trẻ em | `tours.child_price`, thông lệ 50 đến 75 phần trăm giá người lớn |
| Giá em bé | `tours.infant_price`, thông lệ 0 đến 25 phần trăm, không có ghế riêng, không tính suất ăn |
| Phụ thu phòng đơn | Áp dụng khi khách lẻ không ghép phòng, cần bổ sung |
| Phụ thu cao điểm | Hệ số theo mùa, lễ tết, cần bổ sung ở mức chuyến khởi hành |
| Giảm giá | Theo phần trăm hoặc số tiền cố định, có mức giảm tối đa |
| Tổng | `(số người lớn x giá NL) + (trẻ em x giá TE) + (em bé x giá EB) + phụ thu - giảm giá` |

Quy tắc bất biến: em bé không tính vào số chỗ ngồi trên xe nhưng vẫn tính vào sức chứa nếu
phương tiện là tàu, máy bay. Hiện hệ thống cộng toàn bộ `guests` vào `booked_people`.
Đề xuất thêm cột `seat_count` cho đơn hàng, mặc định bằng `adult_count + child_count`,
để tách bạch số suất bán và số chỗ vật lý.

### 1.3 Edge case khi đặt tour

| Tình huống | Xử lý |
| --- | --- |
| Hai khách đặt cùng lúc, còn đúng một chỗ | Khóa dòng bi quan trên chuyến. Người vào trước giữ khóa, người sau chờ, đọc lại số chỗ đã cập nhật và bị từ chối với thông báo hết chỗ. Đã triển khai. |
| Chỗ bị chiếm bởi đơn quá hạn chưa ai chạm tới | Nhả chỗ lười. Gọi `releaseOverdueForTour` khi xem chi tiết tour và `releaseOverdueForSchedule` khi tạo đơn. Đã triển khai. |
| Khách đặt vượt số chỗ còn lại | Từ chối, trả về số chỗ thực còn |
| Khách đặt cho chuyến đã qua ngày khởi hành | Từ chối, không hiển thị chuyến quá khứ |
| Khách đặt khi đã qua hạn chốt danh sách | Từ chối. Cần bổ sung, hiện chưa kiểm tra |
| Số lượng khách bằng 0 hoặc chỉ có em bé | Từ chối, phải có ít nhất một người lớn đi kèm |
| Trẻ em dưới 12 tuổi đi một mình | Từ chối, quy định phải có người giám hộ |
| Mã giảm giá hết lượt trong lúc khách đang điền form | Kiểm tra lại lần cuối trong giao dịch tạo đơn, nếu hết thì tạo đơn với giá gốc và thông báo rõ |
| Mã giảm giá dùng lại sau khi đơn bị hủy | Trả lượt sử dụng về khi hủy đơn. Đã xử lý trong `releaseHold` |
| Khách bấm nút đặt hai lần | Chống trùng bằng khóa idempotency theo `(email, schedule_id, tổng tiền)` trong 60 giây. Cần bổ sung |
| Email khách sai định dạng hoặc không tồn tại | Xác thực định dạng khi nhập. Thư gửi hỏng thì ghi nhật ký và hiện cảnh báo trên trang quản trị |
| Tour bị chuyển `inactive` khi khách đang điền form | Kiểm tra lại trong giao dịch, từ chối nếu tour không còn `active` |

## 2. Thanh toán

### 2.1 Thanh toán toàn phần qua VNPay

Đã triển khai. Điểm cần nêu khi bảo vệ:

- Chữ ký `vnp_SecureHash` được tính lại phía máy chủ bằng HMAC SHA512 và so sánh trước khi
  tin bất kỳ thông tin nào từ cổng thanh toán.
- `vnp_CreateDate` và `vnp_ExpireDate` phải theo giờ Việt Nam trong khi ứng dụng chạy UTC.
- Mọi lần gọi và mọi lần nhận phản hồi đều ghi vào `payment_logs`, phục vụ đối soát.
- Xử lý bất đồng bộ về muộn: nếu tiền về sau khi đơn đã tự hủy, hệ thống thử khôi phục đơn
  nếu chuyến vẫn còn chỗ, ngược lại ghi cảnh báo đưa vào hàng chờ hoàn tiền thủ công.

### 2.2 Đặt cọc

Đây là bổ sung theo góp ý của hội đồng, thay cho mô hình chỉ có thanh toán một lần.

Cấu hình ở mức tour: `deposit_percent` mặc định 30, `final_payment_days_before` mặc định 7.

Luồng:

1. Khách chọn phương thức đóng cọc.
2. Hệ thống tính `deposit_amount = round(total_amount * deposit_percent / 100)`.
3. Khách thanh toán cọc, đơn chuyển sang `deposit_paid`, chỗ được giữ chắc chắn,
   `expires_at` được xóa.
4. Hệ thống đặt `final_due_at = start_date - final_payment_days_before`.
5. Tác vụ nền nhắc thanh toán phần còn lại trước hạn 3 ngày và 1 ngày.
6. Quá hạn mà chưa thanh toán đủ, hệ thống đưa đơn vào danh sách cảnh báo cho điều hành.
   **Không tự hủy**, vì đã thu tiền của khách. Điều hành liên hệ rồi mới quyết định.

Edge case của đặt cọc:

| Tình huống | Xử lý |
| --- | --- |
| Khách đóng cọc rồi hủy | Áp chính sách hoàn theo mốc thời gian trên phần đã thu, không phải trên tổng đơn |
| Khách đóng thừa so với cọc | Ghi nhận đúng số tiền nhận, phần vượt trừ vào phần còn lại |
| Khách đóng nốt nhưng số khách đã thay đổi | Tính lại tổng, chênh lệch tạo giao dịch bù hoặc hoàn |
| Chuyến bị hủy khi khách mới đóng cọc | Hoàn 100 phần trăm phần đã thu, không trừ phí |
| Khách không đóng nốt tới ngày khởi hành | Điều hành quyết: cho đi và thu tại điểm tập trung, hoặc hủy đơn và trừ tiền cọc theo điều khoản đã ký |

### 2.3 Sổ giao dịch đơn hàng

Hiện đơn hàng chỉ lưu `total_amount`, `paid_at`, `vnpay_transaction_no`. Mô hình này không
biểu diễn được đặt cọc, phụ thu, hoàn một phần.

Đề xuất bảng `booking_transactions`:

| Cột | Ý nghĩa |
| --- | --- |
| `booking_id` | Đơn liên quan |
| `type` | `deposit`, `final`, `surcharge`, `refund`, `adjustment` |
| `amount` | Số dương với khoản thu, số âm với khoản chi |
| `method` | `vnpay`, `bank_transfer`, `cash`, `credit` |
| `status` | `pending`, `succeeded`, `failed` |
| `reference` | Mã giao dịch của cổng hoặc số chứng từ ngân hàng |
| `actor_id` | Người thực hiện, để trống nếu do cổng tự động |
| `note` | Diễn giải |

Số tiền còn phải thu của một đơn bằng `total_amount - SUM(amount của các giao dịch succeeded)`.
Trạng thái `paid` được suy ra khi số này bằng 0, thay vì gán tay.

## 3. Sửa đơn sau khi đã đặt

Hội đồng nêu hai điểm: cập nhật lại booking theo thực tế, và cập nhật thông tin khách hàng.
Đây là hai luồng khác nhau về mức độ rủi ro.

### 3.1 Sửa thông tin hành khách

Rủi ro thấp, cho phép khách tự sửa.

Phạm vi: họ tên, giới tính, ngày sinh, số căn cước hoặc hộ chiếu, số điện thoại,
yêu cầu đặc biệt như ăn chay, dị ứng, cần hỗ trợ di chuyển.

Lý do phải có đủ các trường này: mua bảo hiểm du lịch cần đúng ngày sinh và số giấy tờ,
xuất vé máy bay hoặc vé tàu sai tên là mất vé, khai báo lưu trú tại khách sạn cần căn cước.

Quy tắc:

| Mốc thời gian | Ai sửa được |
| --- | --- |
| Trước `booking_deadline` | Khách tự sửa trực tuyến |
| Sau `booking_deadline`, trước khởi hành | Chỉ điều hành, vì danh sách đã gửi nhà cung cấp |
| Sau khi chuyến `in_progress` | Không ai sửa được, chỉ ghi chú bổ sung |

Edge case:

| Tình huống | Xử lý |
| --- | --- |
| Số hành khách khai báo ít hơn `guests` | Cảnh báo và chặn xuất danh sách đoàn cho tới khi khai đủ |
| Trùng số căn cước giữa hai hành khách trong cùng đơn | Từ chối |
| Hộ chiếu hết hạn trước ngày về với tour nước ngoài | Cảnh báo, yêu cầu xác nhận của điều hành |
| Đổi người đi thay hoàn toàn | Coi là chuyển nhượng suất, cần điều hành duyệt, có thể thu phí đổi tên |

### 3.2 Sửa số lượng khách

Rủi ro cao vì chạm tới chỗ và tiền. Bắt buộc qua điều hành.

Luồng tăng số khách:

1. Điều hành mở đơn, nhập số lượng mới.
2. Hệ thống mở giao dịch, khóa dòng chuyến, kiểm tra `max_people - booked_people >= phần tăng`.
3. Nếu đủ chỗ: cộng `booked_people`, tính lại `total_amount`, tạo giao dịch `surcharge`
   với phần chênh, sinh liên kết thanh toán bổ sung gửi cho khách.
4. Nếu không đủ chỗ: từ chối, gợi ý tách phần thừa sang chuyến khác.
5. Ghi `booking_audit_logs`.

Luồng giảm số khách:

1. Điều hành nhập số lượng mới.
2. Hệ thống tính lại tổng, phần chênh lệch được xử lý theo chính sách hủy một phần
   ở [03 - Luồng hủy và hoàn tiền](03-luong-huy-va-hoan-tien.md), vì bản chất là hủy chỗ.
3. Trừ `booked_people` nếu chưa qua hạn chốt danh sách. Nếu đã qua hạn thì không trả chỗ,
   xem lý do tại tài liệu 03.
4. Tạo giao dịch `refund` với số tiền hoàn thực tế.

Edge case:

| Tình huống | Xử lý |
| --- | --- |
| Giảm số khách về 0 | Không cho phép, phải dùng chức năng hủy đơn để đảm bảo đúng luồng |
| Tăng số khách sau khi đã qua hạn chốt | Cho phép nhưng cảnh báo, vì phải xin thêm suất từ nhà cung cấp. Ghi rõ người duyệt |
| Khách đi thực tế nhiều hơn số đã đặt, phát hiện tại điểm tập trung | Hướng dẫn viên báo cáo, điều hành tạo đơn bổ sung hoặc phụ thu tại chỗ, thu qua kế toán, không thu trực tiếp |
| Khách đi thực tế ít hơn, người vắng mặt | Ghi `no_show` cho hành khách đó, không hoàn tiền, xem tài liệu 04 |
| Sửa đơn khi đơn còn `pending` | Cho phép sửa thoải mái, chỉ cần khóa chuyến khi thay đổi số chỗ |

### 3.3 Nhật ký thay đổi đơn hàng

Bảng `booking_audit_logs` bắt buộc với mọi thao tác thuộc nhóm 3.1 và 3.2, cùng mọi lần
đổi trạng thái.

| Cột | Ý nghĩa |
| --- | --- |
| `booking_id` | Đơn liên quan |
| `actor_id`, `actor_role` | Người thao tác, để trống nếu là hệ thống |
| `action` | `status_changed`, `guests_changed`, `passenger_updated`, `transferred`, `refunded`, `surcharged` |
| `old_values`, `new_values` | JSON, chỉ lưu các trường thay đổi |
| `reason` | Bắt buộc với thao tác thủ công |
| `ip_address` | Truy vết |

## 4. Chuyển chuyến và chuyển tour

Hội đồng nêu ở mục "Hỗ trợ chuyển tour". Có ba tình huống khác nhau.

### 4.1 Ba tình huống chuyển

| Loại | Mô tả | Ai khởi xướng | Thu phí |
| --- | --- | --- | --- |
| Đổi ngày | Cùng tour, sang chuyến khởi hành khác | Khách | Theo mốc thời gian |
| Đổi tour | Sang tour khác của công ty | Khách | Theo mốc thời gian, cộng chênh giá |
| Chuyển do hãng | Chuyến bị hủy hoặc bị ghép | Điều hành | Miễn phí, hoàn chênh nếu tour đích rẻ hơn |

### 4.2 Điều kiện chuyển

1. Đơn phải ở `deposit_paid`, `paid` hoặc `confirmed`. Đơn `pending` thì hủy và đặt lại đơn giản hơn.
2. Chuyến đích phải ở trạng thái `open` và còn đủ chỗ cho toàn bộ số khách của đơn.
3. Nếu do khách khởi xướng, phải trước ngày khởi hành của chuyến gốc ít nhất số ngày quy định
   trong chính sách, đề xuất 7 ngày.
4. Số lần chuyển miễn phí tối đa 1. Từ lần thứ hai thu phí đổi lịch.
5. Nếu do hãng khởi xướng, bỏ qua điều kiện 3 và 4.

### 4.3 Xử lý chênh lệch giá

| Trường hợp | Xử lý |
| --- | --- |
| Chuyến đích đắt hơn | Tạo giao dịch `surcharge`, gửi liên kết thanh toán. Đơn chỉ chuyển sang chuyến mới sau khi thu đủ, hoặc điều hành cho nợ có ghi chú |
| Chuyến đích rẻ hơn, khách khởi xướng | Ghi nhận công nợ dạng credit dùng cho lần đặt sau, không hoàn tiền mặt. Đây là thông lệ thị trường |
| Chuyến đích rẻ hơn, hãng khởi xướng | Hoàn phần chênh cho khách |

### 4.4 Xử lý kỹ thuật

Đây là điểm kỹ thuật đáng nêu khi bảo vệ vì phải khóa hai tài nguyên cùng lúc.

```php
// Khóa theo thứ tự khóa chính tăng dần để tránh khóa chết (deadlock).
// Nếu luồng A khóa chuyến 5 rồi chờ chuyến 9, trong khi luồng B khóa chuyến 9
// rồi chờ chuyến 5, cả hai sẽ chờ nhau vô hạn. Sắp xếp id triệt tiêu khả năng này.
$ids = collect([$fromScheduleId, $toScheduleId])->unique()->sort()->values();

DB::transaction(function () use ($ids, $booking) {
    $schedules = TourSchedule::query()
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    // 1. Kiểm tra chuyến đích còn chỗ
    // 2. Trừ booked_people chuyến gốc
    // 3. Cộng booked_people chuyến đích
    // 4. Cập nhật tour_schedule_id, departure_date của đơn
    // 5. Ghi booking_transfers và booking_audit_logs
});
```

Bảng `booking_transfers`:

| Cột | Ý nghĩa |
| --- | --- |
| `booking_id` | Đơn được chuyển |
| `from_schedule_id`, `to_schedule_id` | Chuyến gốc và chuyến đích |
| `from_tour_id`, `to_tour_id` | Phục vụ trường hợp đổi tour |
| `initiated_by` | `customer` hoặc `operator` |
| `price_difference` | Dương là khách phải bù, âm là hoàn cho khách |
| `fee` | Phí đổi lịch nếu có |
| `reason` | Lý do |
| `approved_by`, `approved_at` | Người duyệt |

### 4.5 Edge case khi chuyển

| Tình huống | Xử lý |
| --- | --- |
| Chuyến đích hết chỗ ngay lúc bấm chuyển | Khóa dòng phát hiện, giao dịch quay lui, báo lỗi |
| Chuyển sang chính chuyến đang ở | Từ chối |
| Chuyển vòng: A sang B rồi B về A | Cho phép nhưng tính là hai lần chuyển, lần hai thu phí |
| Chuyển đơn đã có điểm danh | Không cho phép, vì chuyến gốc đã khởi hành |
| Chuyển một phần số khách trong đơn | Tách đơn: tạo đơn con cho phần chuyển, đơn gốc giảm số khách. Ghi liên kết `split_from_booking_id` |
| Tour đích không còn `active` | Từ chối |
| Chuyến gốc đã bị hủy, đơn chuyển sang chuyến mới | Đây là luồng hãng khởi xướng, xem tài liệu 04 |
