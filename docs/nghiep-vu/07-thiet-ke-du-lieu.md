# 07 - Thiết kế dữ liệu bổ sung

Tài liệu này liệt kê toàn bộ thay đổi cơ sở dữ liệu cần thiết để triển khai nghiệp vụ đã mô tả,
sắp theo thứ tự migration để không phá vỡ ràng buộc khóa ngoại.

Lưu ý về môi trường: máy phát triển dùng SQLite, máy chạy thật dùng MySQL. Mọi migration phải
tránh các thao tác SQLite không hỗ trợ tốt, cụ thể là sửa kiểu cột đã có và đổi tên cột trong
cùng một khối `Schema::table`. Với các trường hợp đó, tạo cột mới rồi chuyển dữ liệu.

## 1. Mốc 1 - Nền tảng điều hành

### 1.1 Bổ sung vòng đời chuyến khởi hành

`tour_schedules`:

| Cột | Kiểu | Mặc định | Ghi chú |
| --- | --- | --- | --- |
| `end_date` | datetime, nullable | null | Tính từ `start_date` cộng `number_of_days` của tour khi tạo |
| `min_people` | unsignedInteger | 1 | Số khách tối thiểu để chuyến chạy |
| `booking_deadline` | datetime, nullable | null | Mặc định `start_date` trừ 3 ngày |
| `status` | string(20) | `open` | `open`, `closed`, `confirmed`, `in_progress`, `completed`, `cancelled` |
| `confirmed_at` | datetime, nullable | null | |
| `cancelled_at` | datetime, nullable | null | |
| `cancelled_by` | foreignId nullable | null | Trỏ `users` |
| `cancelled_reason` | text nullable | null | |
| `merged_into_schedule_id` | foreignId nullable | null | Tự trỏ `tour_schedules` |
| `is_private` | boolean | false | Chuyến bị đoàn đặt trọn |

Chỉ mục: `(tour_id, status, start_date)` phục vụ danh sách chuyến đang mở bán,
`(status, booking_deadline)` phục vụ tác vụ nền chốt chuyến,
`(guide_id, start_date, end_date)` phục vụ kiểm tra trùng lịch.

Migration dữ liệu cũ: đặt `end_date` cho các chuyến hiện có bằng
`start_date + (tours.number_of_days - 1) ngày`, đặt `booking_deadline` bằng `start_date` trừ 3 ngày,
đặt `status` bằng `completed` nếu `start_date` đã qua, ngược lại `open`.

### 1.2 Chính sách hủy

`cancellation_policies`: `id`, `name`, `description`, `is_default` boolean, timestamps.

`cancellation_policy_rules`: `id`, `cancellation_policy_id`, `min_hours_before` int,
`max_hours_before` int nullable, `refund_percent` unsignedTinyInteger, `note`, timestamps.
Chỉ mục `(cancellation_policy_id, min_hours_before)`.

`tours`: thêm `cancellation_policy_id` foreignId nullable.

`bookings`: thêm `cancellation_policy_id` foreignId nullable. Sao chép từ tour tại thời điểm
tạo đơn để chính sách không hồi tố.

Bản ghi mặc định do seeder tạo, đúng bảng phí ở tài liệu 03:

| min_hours_before | max_hours_before | refund_percent |
| --- | --- | --- |
| 360 | null | 90 |
| 192 | 360 | 70 |
| 96 | 192 | 50 |
| 48 | 96 | 30 |
| 0 | 48 | 0 |

### 1.3 Hủy đơn và xử lý chỗ

`bookings`:

| Cột | Kiểu | Mặc định | Ghi chú |
| --- | --- | --- | --- |
| `cancel_type` | string(20) nullable | null | `hold_expired`, `by_customer`, `by_company`, `force_majeure` |
| `cancelled_by` | foreignId nullable | null | Để trống nếu do hệ thống |
| `cancelled_at` | datetime nullable | null | |
| `seats_released` | boolean | true | False khi hủy sau hạn chốt, chỗ chưa về kho |
| `seats_released_at` | datetime nullable | null | |
| `seats_released_by` | foreignId nullable | null | Ai thao tác lúc chỗ được trả về kho |
| `refund_amount` | decimal(12,2) nullable | null | Số tiền hoàn đã tính |
| `cancellation_plan` | string(20) nullable | null | Dùng khi hủy chuyến: `refund`, `transfer`, `credit` |

Với đơn hiện có đã `cancelled`, đặt `seats_released = true` để không phá vỡ số liệu.

### 1.4 Nhật ký thay đổi đơn hàng

`booking_audit_logs`: `id`, `booking_id`, `actor_id` nullable, `actor_role` nullable,
`action` string(40), `old_values` json nullable, `new_values` json nullable,
`reason` text nullable, `ip_address` string(45) nullable, timestamps.
Chỉ mục `(booking_id, created_at)`.

### 1.5 Bổ sung thông tin hành khách

`booking_passengers` hiện có `name`, `type`, `note`. Bổ sung:

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `gender` | string(10) nullable | `male`, `female`, `other` |
| `dob` | date nullable | Ngày sinh, dùng cho bảo hiểm và xác định phân loại |
| `id_number` | string(30) nullable | Căn cước hoặc hộ chiếu |
| `id_type` | string(20) nullable | `cccd`, `passport` |
| `passport_expiry` | date nullable | Tour nước ngoài |
| `nationality` | string(60) nullable | |
| `phone` | string(20) nullable | |
| `special_request` | text nullable | Ăn chay, dị ứng, hỗ trợ di chuyển |
| `is_contact` | boolean, mặc định false | Người liên hệ chính của đơn |

### 1.6 Điểm danh theo điểm dừng và theo hành khách

`itinerary_checkpoints`: `id`, `tour_itinerary_id`, `name`, `type` string(20),
`expected_at` time nullable, `requires_attendance` boolean mặc định true,
`requires_photo` boolean mặc định false, `latitude` decimal(10,7) nullable,
`longitude` decimal(10,7) nullable, `order` unsignedSmallInteger, timestamps.
Chỉ mục `(tour_itinerary_id, order)`.

`passenger_checkins`: `id`, `booking_passenger_id`, `itinerary_checkpoint_id`,
`tour_schedule_id`, `status` string(20) mặc định `present`, `note` text nullable,
`checked_at` datetime, `guide_id` nullable, `is_late_entry` boolean mặc định false, timestamps.
Khóa duy nhất `(booking_passenger_id, itinerary_checkpoint_id)`.
Chỉ mục `(tour_schedule_id, itinerary_checkpoint_id)`.

`passenger_checkin_histories`: lưu bản ghi cũ khi sửa điểm danh. `id`, `passenger_checkin_id`,
`old_status`, `old_note`, `changed_by`, `changed_at`.

`checkpoint_photos`: bổ sung `itinerary_checkpoint_id` nullable, `latitude`, `longitude`,
`captured_at`. Giữ `tour_itinerary_id` để tương thích dữ liệu cũ.

Chuyển đổi dữ liệu: với mỗi `tour_itineraries` hiện có, tạo một `itinerary_checkpoints`
mặc định tên "Điểm danh trong ngày" để dữ liệu `booking_checkins` cũ có chỗ chuyển sang.
Với mỗi `booking_checkins`, sinh `passenger_checkins` cho từng hành khách của đơn,
`status` bằng `present` hoặc `absent` theo cột `present` cũ. Giữ bảng `booking_checkins`
ở trạng thái chỉ đọc một thời gian rồi mới xóa.

### 1.7 Yêu cầu thay đổi của khách

`booking_change_requests`: `id`, `booking_id`, `type` string(20), `payload` json,
`estimated_refund` decimal(12,2) nullable, `status` string(20) mặc định `pending`,
`requested_by` foreignId nullable, `requested_email` string nullable, `request_note` text,
`reviewed_by` nullable, `reviewed_at` nullable, `review_note` text nullable, timestamps.
Chỉ mục `(status, created_at)`.

## 2. Mốc 2 - Vận hành chuyến đi

### 2.1 Chuyển chuyến

`booking_transfers`: `id`, `booking_id`, `from_schedule_id`, `to_schedule_id`,
`from_tour_id`, `to_tour_id`, `initiated_by` string(20), `price_difference` decimal(12,2)
mặc định 0, `fee` decimal(12,2) mặc định 0, `reason` text, `approved_by` nullable,
`approved_at` nullable, timestamps.

`bookings`: thêm `transfer_count` unsignedTinyInteger mặc định 0,
`split_from_booking_id` foreignId nullable cho trường hợp tách đơn.

### 2.2 Hồ sơ và phân công hướng dẫn viên

`guide_profiles`: `id`, `user_id` unique, `license_number` nullable,
`license_type` string(20) nullable, `license_expiry` date nullable, `languages` json nullable,
`regions` json nullable, `specialties` json nullable, `max_group_size` unsignedSmallInteger
nullable, `max_days_per_month` unsignedTinyInteger nullable, `bio` text nullable,
`rating` decimal(3,2) nullable, `is_available` boolean mặc định true, timestamps.

`schedule_guide_assignments`: `id`, `tour_schedule_id`, `guide_id`, `role` string(20)
mặc định `lead`, `effective_from` datetime, `effective_to` datetime nullable,
`reason` text nullable, `handover_note` text nullable, `assigned_by` nullable, timestamps.
Chỉ mục `(tour_schedule_id, effective_from)` và `(guide_id, effective_from, effective_to)`.

Chuyển đổi dữ liệu: với mỗi `tour_schedules` có `guide_id`, tạo một bản ghi phân công
`role = lead`, `effective_from = start_date`, `effective_to = null`.
Giữ `tour_schedules.guide_id` làm người phụ trách hiện tại để truy vấn cũ vẫn chạy.

### 2.3 Mô hình tour

`tours`: thêm `type` string(20) mặc định `shared`, `min_people` unsignedSmallInteger mặc định 1,
`deposit_percent` unsignedTinyInteger mặc định 100, `final_payment_days_before`
unsignedTinyInteger mặc định 0, `single_supplement` decimal(12,2) nullable.

Giá trị mặc định giữ nguyên hành vi hiện tại: `deposit_percent` bằng 100 nghĩa là thanh toán
một lần như đang chạy, chỉ tour nào cấu hình khác mới bật luồng đặt cọc.

## 3. Mốc 3 - Tài chính và hồ sơ

### 3.1 Sổ giao dịch

`booking_transactions`: `id`, `booking_id`, `type` string(20), `amount` decimal(12,2),
`method` string(20), `status` string(20) mặc định `pending`, `reference` string nullable,
`actor_id` nullable, `evidence_path` string nullable, `note` text nullable,
`processed_at` datetime nullable, timestamps.
Chỉ mục `(booking_id, type)` và `(status, created_at)`.

`bookings`: thêm `deposit_amount` decimal(12,2) nullable, `final_due_at` datetime nullable,
`paid_amount` decimal(12,2) mặc định 0 làm giá trị tổng hợp sẵn cho danh sách.

Chuyển đổi dữ liệu: với mỗi đơn đã `paid` hoặc `confirmed`, tạo một
`booking_transactions` loại `final`, phương thức `vnpay`, trạng thái `succeeded`,
số tiền bằng `total_amount`, `reference` bằng `vnpay_transaction_no`,
`processed_at` bằng `paid_at`.

### 3.2 Sự cố và phụ thu

`schedule_incidents`: `id`, `tour_schedule_id`, `tour_itinerary_id` nullable,
`type` string(20), `severity` string(10), `occurred_at` datetime, `description` text,
`reported_by` nullable, `resolution` text nullable, `cost_delta` decimal(12,2) mặc định 0,
`who_bears` string(20) nullable, `approved_by` nullable, `approved_at` nullable,
`is_late_entry` boolean mặc định false, timestamps.

`incident_photos`: `id`, `schedule_incident_id`, `image_path`, `uploaded_by`, timestamps.

`booking_surcharges`: `id`, `booking_id`, `schedule_incident_id` nullable, `reason` text,
`amount` decimal(12,2), `status` string(20) mặc định `pending`, `approved_by` nullable,
`customer_consent_at` datetime nullable, `consent_evidence_path` nullable, timestamps.

### 3.3 Đoàn và báo giá

`quotations`: theo mô tả tại tài liệu 05.

`tour_price_tiers`: `id`, `tour_id`, `min_pax`, `max_pax` nullable,
`discount_percent` unsignedTinyInteger nullable, `unit_price` decimal(12,2) nullable,
`free_pax` unsignedTinyInteger mặc định 0, timestamps.

`bookings`: thêm `type` string(20) mặc định `individual`, `organization_name` nullable,
`tax_code` nullable, `billing_address` nullable, `contact_person_name` nullable,
`contact_person_phone` nullable, `contact_person_email` nullable, `quotation_id` nullable.

### 3.4 Hợp đồng và danh sách phòng

`booking_contracts`: `id`, `booking_id`, `contract_number` unique, `file_path`,
`issued_at`, `signed_at` nullable, `signed_file_path` nullable,
`signature_method` string(20) nullable, timestamps.

`contract_number_sequences`: `id`, `year`, `last_number`. Cập nhật bằng
`lockForUpdate` để sinh số an toàn khi có nhiều đơn cùng lúc.

`booking_rooms`: `id`, `tour_schedule_id`, `room_number`, `room_type` string(20),
`note` nullable, timestamps.

`booking_room_passenger`: `booking_room_id`, `booking_passenger_id`, khóa chính kép.

`document_exports`: nhật ký xuất tài liệu chứa dữ liệu cá nhân.
`id`, `user_id`, `document_type` string(30), `tour_schedule_id` nullable,
`booking_id` nullable, `exported_at`, `ip_address`.

## 4. Tổng kết thay đổi

| Nhóm | Bảng mới | Bảng sửa |
| --- | --- | --- |
| Mốc 1 | 6 | 4 |
| Mốc 2 | 3 | 2 |
| Mốc 3 | 11 | 2 |
| Tổng | 20 | 8 |

## 5. Kiểm thử cần bổ sung

Mỗi quy tắc nghiệp vụ nên có ít nhất một kiểm thử. Danh sách tối thiểu:

| Nhóm | Kiểm thử |
| --- | --- |
| Vòng đời chuyến | Không đặt được cho chuyến `closed`; chuyến tự chuyển `closed` khi qua hạn chốt; chuyến đủ khách thì `confirmed` |
| Chính sách hủy | Đúng mức hoàn tại từng mốc; hủy sau giờ khởi hành hoàn 0; chính sách sửa về sau không hồi tố đơn cũ |
| Trả chỗ | Hủy trước hạn chốt thì `booked_people` giảm; hủy sau hạn chốt thì không giảm và giữ nguyên tới hết chuyến |
| Chặn hủy | Không hủy được đơn của chuyến `in_progress` qua cả bốn lối vào |
| Phân quyền hủy | Khách đã thanh toán chỉ tạo được yêu cầu; hướng dẫn viên không hủy được |
| Chuyển chuyến | Chuyến đích hết chỗ thì quay lui; chênh giá tạo đúng giao dịch; hai luồng chuyển đồng thời không làm sai số chỗ |
| Sửa số khách | Tăng vượt sức chứa thì từ chối; giảm tạo đúng khoản hoàn; mọi thay đổi có nhật ký |
| Điểm danh | Hướng dẫn viên không được phân công thì bị từ chối; điểm danh chuyến chưa khởi hành bị từ chối; trạng thái vắng thiếu ghi chú bị từ chối; điểm dừng yêu cầu ảnh mà chưa có ảnh thì không chốt được |
| Bàn giao hướng dẫn viên | Người cũ mất quyền ghi sau thời điểm bàn giao nhưng vẫn đọc được; người mới trùng lịch thì bị từ chối |
| Hủy chuyến | Còn đơn chưa có phương án thì không hủy được; hủy xong mọi đơn có giao dịch hoàn tương ứng |
| Ghép chuyến | Chuyến đích không đủ chỗ thì từ chối; sau ghép tổng số khách bằng tổng hai chuyến |
| Hợp đồng | Số hợp đồng không trùng khi sinh đồng thời |
