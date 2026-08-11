# 05 - Booking theo đoàn, hợp đồng và hồ sơ

## 1. Booking theo đoàn

Hội đồng nêu ở mục "Booking theo đoàn". Đây không chỉ là đặt nhiều chỗ một lúc, mà là một
quy trình bán hàng khác hẳn với khách lẻ.

### 1.1 Khác biệt so với đặt lẻ

| Tiêu chí | Khách lẻ | Đoàn |
| --- | --- | --- |
| Cách chốt giá | Giá niêm yết | Báo giá riêng theo số lượng và yêu cầu |
| Thanh toán | Một lần hoặc cọc | Nhiều đợt theo tiến độ |
| Danh sách khách | Khai ngay khi đặt | Nộp sau, thường sát ngày |
| Người quyết định | Chính khách | Đại diện đoàn, có thể là doanh nghiệp |
| Hóa đơn | Thường không cần | Thường cần hóa đơn giá trị gia tăng |
| Hợp đồng | Điều khoản chung | Hợp đồng riêng có thương lượng |
| Hủy | Toàn bộ đơn | Có thể hủy một phần số khách |

### 1.2 Mô hình

Thêm vào `bookings`:

| Cột | Ý nghĩa |
| --- | --- |
| `type` | `individual` hoặc `group` |
| `organization_name` | Tên đơn vị, nếu là doanh nghiệp hoặc trường học |
| `tax_code` | Mã số thuế, phục vụ xuất hóa đơn |
| `billing_address` | Địa chỉ xuất hóa đơn |
| `contact_person_name`, `contact_person_phone`, `contact_person_email` | Người đại diện đoàn |
| `quotation_id` | Báo giá đã chốt, nếu có |

### 1.3 Luồng đặt đoàn

1. Khách gửi yêu cầu báo giá: tour quan tâm, khoảng thời gian, số lượng dự kiến,
   yêu cầu đặc biệt.
2. Điều hành lập báo giá: giá theo đầu người, các khoản bao gồm và không bao gồm,
   điều kiện thanh toán, hiệu lực báo giá.
3. Khách chấp nhận, hệ thống tạo đơn từ báo giá, giữ chỗ theo số lượng cam kết.
4. Ký hợp đồng, đóng cọc đợt một.
5. Trước hạn chốt danh sách, đoàn nộp danh sách khách chi tiết.
6. Thanh toán nốt, xuất hóa đơn.

Bảng `quotations`:

| Cột | Ý nghĩa |
| --- | --- |
| `code` | Số báo giá |
| `tour_id`, `tour_schedule_id` | Tour và chuyến, chuyến có thể để trống nếu chưa chốt ngày |
| `customer_name`, `organization_name`, `contact_*` | Bên nhận báo giá |
| `pax_estimate` | Số khách dự kiến |
| `unit_price`, `total_amount` | Giá |
| `inclusions`, `exclusions` | JSON, các khoản bao gồm và không bao gồm |
| `valid_until` | Hiệu lực báo giá |
| `status` | `draft`, `sent`, `accepted`, `rejected`, `expired` |

### 1.4 Bậc giá theo số lượng

Bảng `tour_price_tiers`:

| Cột | Ý nghĩa |
| --- | --- |
| `tour_id` | Tour áp dụng |
| `min_pax`, `max_pax` | Khoảng số khách |
| `discount_percent` hoặc `unit_price` | Mức giảm hoặc giá cố định cho bậc |
| `free_pax` | Số suất miễn phí, thông lệ tặng một suất trưởng đoàn cho đoàn từ 25 khách |

Ví dụ cấu hình thường gặp:

| Số khách | Mức giảm | Suất miễn phí |
| --- | --- | --- |
| 10 đến 19 | 5 phần trăm | 0 |
| 20 đến 29 | 8 phần trăm | 0 |
| 30 đến 49 | 10 phần trăm | 1 |
| Từ 50 | 12 phần trăm | 2 |

### 1.5 Nộp danh sách khách theo tệp

Đoàn đông không nhập tay từng người. Cần chức năng nhập từ tệp Excel hoặc CSV theo mẫu
tải xuống sẵn, có kiểm tra:

- Đủ cột bắt buộc: họ tên, giới tính, ngày sinh, số giấy tờ.
- Số dòng khớp với số khách đã đặt, lệch thì cảnh báo.
- Trùng số giấy tờ trong cùng danh sách.
- Định dạng ngày sinh, tuổi khớp với phân loại người lớn, trẻ em, em bé đã khai khi đặt.
- Báo lỗi theo từng dòng, cho phép sửa và nhập lại phần lỗi.

### 1.6 Edge case đặt đoàn

| Tình huống | Xử lý |
| --- | --- |
| Đoàn giảm số khách sau khi ký hợp đồng | Áp bậc giá mới nếu tụt bậc, tính phí hủy cho phần giảm theo mốc thời gian |
| Đoàn tăng số khách | Kiểm tra chỗ còn lại, có thể lên bậc giá tốt hơn, tính lại toàn đơn |
| Đoàn chiếm trọn chuyến | Chuyển chuyến sang `closed`, ghi `is_private` |
| Danh sách nộp muộn hơn hạn chốt | Cảnh báo, điều hành quyết định có nhận không, ghi rủi ro không kịp mua bảo hiểm |
| Đoàn có khách mang quốc tịch nước ngoài | Cần thêm số hộ chiếu và thị thực, một số điểm tham quan có giá vé khác |
| Trong đoàn có người khuyết tật hoặc cao tuổi | Ghi vào yêu cầu đặc biệt, điều hành xác nhận lịch trình phù hợp trước khi chốt |

## 2. Hợp đồng du lịch

Hội đồng nêu ở mục "Hợp đồng danh sách khách hàng".

### 2.1 Vì sao bắt buộc

Theo Luật Du lịch năm 2017, kinh doanh dịch vụ lữ hành phải có hợp đồng lữ hành bằng văn bản
giữa doanh nghiệp và khách. Hợp đồng phải có chương trình, giá, các dịch vụ bao gồm,
điều khoản hủy và bồi thường. Một hệ thống đặt tour không có hợp đồng là thiếu về mặt pháp lý,
đây là lý do hội đồng nêu điểm này.

### 2.2 Nội dung tối thiểu

1. Số hợp đồng theo định dạng `HD-YYYY-NNNNN`, ngày ký, nơi ký.
2. Thông tin bên A là doanh nghiệp: tên, mã số thuế, địa chỉ, người đại diện,
   số giấy phép kinh doanh lữ hành.
3. Thông tin bên B là khách hoặc đại diện đoàn.
4. Tên chương trình, ngày khởi hành, ngày kết thúc, số lượng khách.
5. Giá trọn gói, đơn giá theo từng nhóm khách.
6. Dịch vụ bao gồm và không bao gồm, liệt kê rõ.
7. Điều khoản thanh toán: mức cọc, hạn thanh toán phần còn lại.
8. Điều khoản hủy và phí hủy, dẫn chiếu đúng bảng phí đã áp cho đơn.
9. Điều khoản bất khả kháng và phân bổ chi phí phát sinh.
10. Trách nhiệm mỗi bên, bảo hiểm du lịch, giải quyết tranh chấp.
11. Chữ ký hai bên.

### 2.3 Triển khai

- Mẫu hợp đồng là một view Blade, dữ liệu bơm từ đơn hàng và tour.
- Xuất PDF bằng `barryvdh/laravel-dompdf`.
- Lưu vào `booking_contracts`: `booking_id`, `contract_number`, `file_path`, `issued_at`,
  `signed_at`, `signed_file_path`, `signature_method`.
- Khách nhận liên kết tải hợp đồng trong thư xác nhận.
- Ký: giai đoạn đầu dùng ký tay rồi tải bản chụp lên. Ghi rõ trong tài liệu rằng chữ ký số
  là hướng phát triển, tránh bị hỏi tại sao chưa có.

Số hợp đồng phải sinh an toàn khi có nhiều đơn cùng lúc. Không dùng `count() + 1`.
Dùng bảng đếm riêng có khóa dòng, hoặc dựa vào khóa chính của bản ghi hợp đồng.

## 3. Danh sách đoàn

Đây là tài liệu vận hành quan trọng nhất, hướng dẫn viên và nhà cung cấp đều cần.

### 3.1 Danh sách khách

| Cột | Nguồn |
| --- | --- |
| Số thứ tự | Sinh khi xuất |
| Họ và tên | `booking_passengers.name` |
| Giới tính | `booking_passengers.gender` |
| Năm sinh | `booking_passengers.dob` |
| Số căn cước hoặc hộ chiếu | `booking_passengers.id_number` |
| Số điện thoại | `booking_passengers.phone` |
| Phân loại | Người lớn, trẻ em, em bé |
| Ghi chú | Ăn chay, dị ứng, cần hỗ trợ, đi cùng ai |
| Mã đơn | Để hướng dẫn viên biết ai đi cùng nhóm nào |

Xuất Excel bằng `maatwebsite/excel` và PDF để in.

Cần bổ sung các cột cho `booking_passengers`: hiện chỉ có `name`, `type`, `note`.
Thiếu `gender`, `dob`, `id_number`, `phone`, `nationality`, `special_request`.

### 3.2 Danh sách phòng

Cần khi tour có lưu trú. Ghép phòng ảnh hưởng trực tiếp tới chi phí, vì khách lẻ không ghép
được phòng phải chịu phụ thu phòng đơn.

Bảng `booking_rooms`:

| Cột | Ý nghĩa |
| --- | --- |
| `tour_schedule_id` | Chuyến |
| `room_number` | Số thứ tự phòng trong danh sách |
| `room_type` | `single`, `double`, `twin`, `triple` |
| `note` | Ghi chú |

Bảng nối `booking_room_passenger` gán từng hành khách vào phòng.

Quy tắc kiểm tra khi xếp phòng:

- Mỗi hành khách chỉ thuộc một phòng.
- Số người trong phòng không vượt sức chứa của loại phòng.
- Em bé không tính vào sức chứa nhưng phải gán theo phòng của cha mẹ.
- Cảnh báo khi có phòng đơn phát sinh mà đơn chưa tính phụ thu phòng đơn.
- Không xếp chung phòng khách khác giới nếu không cùng một đơn, trừ khi có xác nhận.

### 3.3 Hồ sơ bàn giao cho hướng dẫn viên

Trước ngày khởi hành, hệ thống gom thành một bộ hồ sơ tải về:

1. Danh sách khách.
2. Danh sách phòng.
3. Lịch trình chi tiết theo ngày và điểm dừng.
4. Thông tin liên hệ nhà cung cấp: nhà xe, khách sạn, nhà hàng, điểm tham quan.
5. Danh sách yêu cầu đặc biệt tổng hợp: số suất ăn chay, khách dị ứng, khách cần hỗ trợ.
6. Số điện thoại điều hành trực và quy trình xử lý sự cố.
7. Tạm ứng chi phí đoàn nếu có.

### 3.4 Bảo vệ dữ liệu cá nhân

Danh sách đoàn chứa số căn cước và ngày sinh, thuộc dữ liệu cá nhân được bảo vệ theo
Nghị định 13 năm 2023. Cần nêu trong tài liệu:

- Chỉ hướng dẫn viên được phân công và điều hành xem được danh sách đầy đủ.
- Số căn cước hiển thị dạng che một phần trên giao diện, chỉ hiện đủ khi xuất tệp.
- Ghi nhật ký mỗi lần xuất danh sách: ai xuất, lúc nào, chuyến nào.
- Có chính sách xóa hoặc ẩn danh dữ liệu sau thời hạn lưu trữ.

Đây là chi tiết ít đồ án nào nghĩ tới, nêu ra sẽ tạo khác biệt khi bảo vệ.
