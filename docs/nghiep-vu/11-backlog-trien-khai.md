# 11 - Backlog triển khai

Danh sách công việc để hiện thực hóa toàn bộ nghiệp vụ trong bộ tài liệu này thành mã nguồn.

## Hiện trạng — đối chiếu mã nguồn ngày 18/08/2026

Tài liệu này viết lúc chưa làm gì và **tiêu đề nhóm không được cập nhật trong suốt quá trình**,
nên đọc nó một mình sẽ tưởng còn nợ gần hết. Bảng dưới là hiện trạng thật.

| Trạng thái | Nhóm |
| --- | --- |
| **Đã làm** | A, B, C, D, E, F, G, H, I, K, L, M, O — 13 nhóm |
| **Một phần** | N (sổ giao dịch xong, cọc khách lẻ chưa) · P (đường ống đoàn xong, còn nhập Excel) |
| **Chưa làm** | Q hợp đồng · R cung ứng · S doanh thu và đối soát |
| **Đã bỏ** | J sửa số lượng khách — có lý do, xem mục của nhóm |

**R và S không nằm trong 18 góp ý của hội đồng.** Đó là hai trụ nhóm tự mở rộng ở tài liệu 09 và
10; tài liệu 00 mục 7 đã tự đánh giá là "có mô hình, chưa triển khai". Đừng để chúng làm tưởng còn
nợ hội đồng nhiều hơn thực tế.

Đối chiếu theo từng góp ý của hội đồng: [06](06-doi-chieu-feedback.md).
Danh sách tính năng đã chạy, nhìn theo vai trò người dùng: [18](18-tinh-nang-da-lam.md).
Danh mục tình huống ngoại lệ, đã rà cùng đợt: [08](08-danh-muc-edge-case.md).

## Cách dùng

**Mã công việc:** chữ cái là nhóm chức năng, số là thứ tự trong nhóm. Ví dụ `A03`.

**Ước lượng:** đơn vị là ngày công, hiểu là một ngày làm việc tập trung của một người, bao gồm
cả viết kiểm thử. Ước lượng cho người đã quen codebase.

**Phụ thuộc:** công việc phải hoàn thành trước. Trống nghĩa là làm được ngay.

**Quy ước chung cho mọi công việc:**

- Migration phải chạy được trên cả SQLite và MySQL, không sửa kiểu cột đã có.
- Mọi thay đổi trạng thái, tiền hoặc số chỗ đều nằm trong giao dịch có khóa dòng.
- Mọi ràng buộc nghiệp vụ kiểm tra ở tầng dịch vụ, không chỉ ở giao diện.
- Không có công việc nào được coi là xong khi chưa có kiểm thử tương ứng.
- Không dùng biểu tượng cảm xúc trong giao diện.

---

# MỐC 1 - Nền tảng điều hành

Mục tiêu: xử lý 12 trên 18 điểm hội đồng nêu. Đây là phần bắt buộc.

## Nhóm A - Vòng đời chuyến khởi hành — ĐÃ LÀM

Tài liệu tham chiếu: 01 mục 4, 07 mục 1.1.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| A01 | Migration thêm `end_date`, `min_people`, `booking_deadline`, `status`, `confirmed_at`, `cancelled_at`, `cancelled_by`, `cancelled_reason`, `merged_into_schedule_id`, `is_private` vào `tour_schedules` kèm ba chỉ mục | `database/migrations` | | 0.5 |
| A02 | Migration backfill dữ liệu cũ: `end_date` từ `number_of_days`, `booking_deadline` bằng `start_date` trừ 3 ngày, `status` theo mốc thời gian | `database/migrations` | A01 | 0.5 |
| A03 | Model `TourSchedule`: hằng số trạng thái, cast ngày giờ, scope `open`, `upcoming`, `running`, phương thức `isRunning`, `isBookable`, `remainingSeats` | `app/Models/TourSchedule.php` | A01 | 0.5 |
| A04 | `ScheduleLifecycleService` với bảng chuyển trạng thái hợp lệ và phương thức `transitionTo` ném lỗi khi chuyển sai | `app/Services` | A03 | 1 |
| A05 | Lệnh `schedules:close-expired` đóng bán chuyến qua hạn chốt hoặc hết chỗ | `app/Console/Commands` | A04 | 0.5 |
| A06 | Lệnh `schedules:confirm-ready` chốt chuyến đủ khách tối thiểu, gửi thư báo cho khách, cảnh báo chuyến thiếu khách | `app/Console/Commands`, `app/Mail` | A04 | 1 |
| A07 | Lệnh `schedules:advance-status` chuyển sang đang chạy và đã kết thúc theo thời gian | `app/Console/Commands` | A04 | 0.5 |
| A08 | Đăng ký ba lệnh trên vào lịch chạy nền | `routes/console.php` | A05, A06, A07 | 0.25 |
| A09 | Chặn tạo đơn khi chuyến không mở bán, đã qua hạn chốt, hoặc tour không hoạt động | `Api/Customer/BookingController.php` | A03 | 0.5 |
| A10 | API quản trị: tạo và sửa chuyến có `min_people`, `booking_deadline`, `end_date` tự tính, đổi trạng thái thủ công | `Api/Admin/AdminTourController.php`, `routes/api.php` | A04 | 1 |
| A11 | Giao diện quản trị: cột trạng thái chuyến, bộ lọc theo trạng thái, biểu mẫu nhập các trường mới | `client/src/pages/admin` | A10 | 1 |
| A12 | Giao diện khách: chỉ hiện chuyến đang mở bán, hiển thị hạn đặt còn lại | `client/src/components`, `client/src/pages` | A09 | 0.5 |
| A13 | Kiểm thử: không đặt được chuyến đã đóng, chuyến tự đóng khi qua hạn, chuyến đủ khách thì chốt, chuyển trạng thái sai bị từ chối | `tests/Feature` | A01-A10 | 1 |

**Tổng nhóm A: 8,75 ngày**

## Nhóm B - Chính sách hủy — ĐÃ LÀM

Tài liệu tham chiếu: 03 mục 2, 07 mục 1.2.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| B01 | Migration `cancellation_policies` và `cancellation_policy_rules`, thêm khóa ngoại vào `tours` và `bookings` | `database/migrations` | | 0.5 |
| B02 | Seeder chính sách mặc định năm mốc theo tài liệu 03 | `database/seeders` | B01 | 0.25 |
| B03 | `CancellationPolicyService`: tìm quy tắc theo số giờ còn lại, tính phí hủy và số tiền hoàn, chặn không cho ra số âm | `app/Services` | B01 | 1 |
| B04 | Sao chép `cancellation_policy_id` từ tour vào đơn khi tạo đơn | `Api/Customer/BookingController.php` | B01 | 0.25 |
| B05 | API quản trị quản lý chính sách hủy, dạng danh sách kèm hộp thoại như danh mục | `Api/Admin`, `routes/api.php` | B01 | 1 |
| B06 | Giao diện quản trị quản lý chính sách và gán cho tour | `client/src/pages/admin` | B05 | 1 |
| B07 | Giao diện khách: hiển thị điều khoản hủy trên trang tour và trang xác nhận đặt | `client/src/pages` | B03 | 0.5 |
| B08 | Kiểm thử: đúng mức hoàn tại từng mốc, quá giờ khởi hành hoàn 0, sửa chính sách không hồi tố đơn cũ | `tests/Feature` | B01-B04 | 0.75 |

**Tổng nhóm B: 5,25 ngày**

## Nhóm C - Trả chỗ khi hủy — ĐÃ LÀM

Tài liệu tham chiếu: 03 mục 3, 07 mục 1.3.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| C01 | Migration thêm `cancel_type`, `cancelled_by`, `cancelled_at`, `seats_released`, `seats_released_at`, `seats_released_by`, `refund_amount`, `cancellation_plan` vào `bookings`, backfill đơn cũ | `database/migrations` | | 0.5 |
| C02 | `BookingHoldService`: quyết định trả chỗ hay không dựa trên `booking_deadline` của chuyến, đặt `seats_released` tương ứng | `app/Services/BookingHoldService.php` | A01, C01 | 1 |
| C03 | API quản trị: danh sách chỗ đã hủy chưa mở bán lại, hành động mở lại kèm lý do | `Api/Admin/AdminBookingController.php` | C02 | 0.75 |
| C04 | Giao diện quản trị cho danh sách trên | `client/src/pages/admin` | C03 | 0.75 |
| C05 | Lệnh `bookings:check-seat-consistency` đối chiếu `booked_people` với tổng đơn thực tế | `app/Console/Commands` | C02 | 0.5 |
| C06 | Kiểm thử: hủy trước hạn chốt giảm số chỗ, hủy sau hạn chốt không giảm, mở lại thủ công giảm và ghi nhật ký | `tests/Feature` | C01-C03 | 0.75 |

**Tổng nhóm C: 4,25 ngày**

## Nhóm D - Chặn hủy khi chuyến đang chạy — ĐÃ LÀM

Tài liệu tham chiếu: 03 mục 4.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| D01 | `BookingPolicyService::assertCancellable` kiểm tra trạng thái chuyến và trạng thái đơn | `app/Services` | A03 | 0.5 |
| D02 | Áp dụng cho cả bốn lối vào: khách tự hủy, quản trị hủy, tác vụ nền, chuyển chuyến | `Api/Customer/BookingController.php`, `Api/Admin/AdminBookingController.php`, `app/Services/BookingHoldService.php` | D01 | 0.5 |
| D03 | Bổ sung trạng thái `no_show` và `completed` cho đơn, lệnh tự chuyển khi chuyến kết thúc | `app/Models/Booking.php`, `app/Console/Commands` | A07 | 0.75 |
| D04 | Kiểm thử: không hủy được đơn của chuyến đang chạy qua cả bốn lối vào | `tests/Feature` | D01-D03 | 0.5 |

**Tổng nhóm D: 2,25 ngày**

## Nhóm E - Nhật ký thay đổi đơn hàng — ĐÃ LÀM

Tài liệu tham chiếu: 02 mục 3.3, 07 mục 1.4.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| E01 | Migration `booking_audit_logs` | `database/migrations` | | 0.25 |
| E02 | `BookingAuditLogger` ghi nhật ký với người thao tác, giá trị cũ mới, lý do, địa chỉ mạng | `app/Services` | E01 | 0.75 |
| E03 | Gắn vào mọi chỗ đổi trạng thái, tiền hoặc số chỗ hiện có | Nhiều controller và service | E02 | 1 |
| E04 | API và giao diện quản trị: tab lịch sử trên trang chi tiết đơn | `Api/Admin`, `client/src/pages/admin` | E02 | 1 |
| E05 | Kiểm thử: mỗi thao tác sinh đúng một bản ghi với nội dung đúng | `tests/Feature` | E02, E03 | 0.5 |

**Tổng nhóm E: 3,5 ngày**

## Nhóm F - Yêu cầu thay đổi của khách — ĐÃ LÀM

Tài liệu tham chiếu: 03 mục 5.2, 07 mục 1.7.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| F01 | Migration `booking_change_requests` | `database/migrations` | | 0.25 |
| F02 | API khách: xem mức hoàn dự kiến trước khi gửi, gửi yêu cầu hủy | `Api/Customer/BookingController.php` | B03, F01 | 1 |
| F03 | API quản trị: danh sách yêu cầu, duyệt hoặc từ chối kèm lý do, thực thi hủy khi duyệt | `Api/Admin` | F01, B03, C02 | 1 |
| F04 | Thư điện tử: xác nhận đã nhận yêu cầu, kết quả duyệt, xác nhận đã hoàn tiền | `app/Mail`, `resources/views/emails` | F03 | 0.75 |
| F05 | Giao diện khách: nút yêu cầu hủy, hộp thoại xác nhận mức hoàn, theo dõi trạng thái yêu cầu | `client/src/pages` | F02 | 1 |
| F06 | Giao diện quản trị duyệt yêu cầu | `client/src/pages/admin` | F03 | 1 |
| F07 | Kiểm thử: khách đã thanh toán không tự hủy được, chỉ tạo yêu cầu; duyệt thì đơn hủy và có bản ghi hoàn | `tests/Feature` | F01-F03 | 0.75 |

**Tổng nhóm F: 5,75 ngày**

## Nhóm G - Thông tin hành khách — ĐÃ LÀM

Tài liệu tham chiếu: 02 mục 3.1, 07 mục 1.5.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| G01 | Migration bổ sung `gender`, `dob`, `id_number`, `id_type`, `passport_expiry`, `nationality`, `phone`, `special_request`, `is_contact` vào `booking_passengers` | `database/migrations` | | 0.25 |
| G02 | Quy tắc kiểm tra: trùng số giấy tờ trong đơn, tuổi khớp phân loại khách, hộ chiếu hết hạn trước ngày về | `app/Http/Requests` hoặc `app/Services` | G01 | 0.75 |
| G03 | API sửa hành khách phân quyền theo mốc thời gian: khách sửa trước hạn chốt, sau đó chỉ quản trị | `Api/Customer`, `Api/Admin` | G01, A01 | 0.75 |
| G04 | Giao diện khách: biểu mẫu hành khách đầy đủ khi đặt và khi sửa | `client/src/pages`, `client/src/components` | G03 | 1.25 |
| G05 | Cảnh báo khi số hành khách khai báo ít hơn số khách đã đặt | `Api/Admin`, `client/src/pages/admin` | G01 | 0.5 |
| G06 | Kiểm thử: các quy tắc kiểm tra, phân quyền sửa theo mốc thời gian | `tests/Feature` | G02, G03 | 0.75 |

**Tổng nhóm G: 4,25 ngày**

## Nhóm H - Điểm danh chi tiết — ĐÃ LÀM (trừ H07 chốt điểm dừng, xem doc 08 mục G10)

Tài liệu tham chiếu: 04 mục 5, 07 mục 1.6. Đây là nhóm lớn nhất của Mốc 1.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| H01 | Migration `itinerary_checkpoints` | `database/migrations` | | 0.25 |
| H02 | Migration `passenger_checkins` và `passenger_checkin_histories` | `database/migrations` | H01 | 0.5 |
| H03 | Migration bổ sung `itinerary_checkpoint_id`, tọa độ, `captured_at` cho `checkpoint_photos` | `database/migrations` | H01 | 0.25 |
| H04 | Migration chuyển dữ liệu: sinh điểm dừng mặc định cho mỗi chặng, chuyển `booking_checkins` sang `passenger_checkins` theo từng hành khách | `database/migrations` | H01, H02 | 0.75 |
| H05 | Model và quan hệ cho các bảng mới | `app/Models` | H01-H03 | 0.5 |
| H06 | API quản trị: quản lý điểm dừng theo từng ngày của lịch trình, trong biểu mẫu tạo và sửa tour | `Api/Admin/AdminTourController.php` | H05 | 1 |
| H07 | Giao diện quản trị: thêm sửa xóa điểm dừng, đặt thứ tự, đánh dấu bắt buộc ảnh | `client/src/pages/admin` | H06 | 1.25 |
| H08 | `AttendanceService` với đủ chín quy tắc kiểm tra theo tài liệu 04 mục 5.3 | `app/Services` | H05, A03 | 1.5 |
| H09 | API điểm danh theo hành khách và điểm dừng, trạng thái năm giá trị, ghi chú bắt buộc, lưu lịch sử khi sửa | `Api/Guide/AttendanceController.php` | H08 | 1 |
| H10 | Ảnh check-in gắn điểm dừng, lưu tọa độ, cảnh báo khi chụp xa điểm dừng | `Api/Guide/AttendanceController.php` | H09 | 0.75 |
| H11 | Giao diện hướng dẫn viên: điểm danh theo điểm dừng, chọn trạng thái, nhập ghi chú, xem tiến độ theo ngày | `client/src/pages/guide` | H09 | 2 |
| H12 | Cảnh báo cho điều hành khi có khách vắng ở điểm đón đầu tiên hoặc điểm cuối | `app/Services`, `app/Notifications` | H09 | 0.5 |
| H13 | Báo cáo điểm danh sau chuyến theo tài liệu 04 mục 5.5 | `Api/Admin`, `client/src/pages/admin` | H09 | 1 |
| H14 | Kiểm thử: chín quy tắc kiểm tra, chuyển dữ liệu cũ đúng, sửa điểm danh lưu lịch sử | `tests/Feature` | H08, H09 | 1.25 |

**Tổng nhóm H: 12,5 ngày**

**TỔNG MỐC 1: 46,5 ngày công, 63 công việc**

---

# NHÓM X - Quy tắc kiểm tra và tình huống ngoại lệ còn thiếu

Nhóm này sinh ra từ việc đối chiếu [08 - Danh mục edge case](08-danh-muc-edge-case.md) với các
nhóm A tới S. Đây là các tình huống đã được mô tả trong tài liệu nghiệp vụ nhưng không rơi vào
nhóm công việc nào, nên nếu không tách riêng thì sẽ bị bỏ quên.

Phần lớn là việc nhỏ, nhưng đúng loại việc hội đồng hay hỏi vì nó thể hiện mức độ chỉn chu.

| ID | Công việc | Edge case | Mốc | Chạm tới | Ngày |
| --- | --- | --- | --- | --- | --- |
| X01 | Khóa chống trùng khi khách bấm đặt hai lần, theo email, chuyến và tổng tiền trong 60 giây | A04 | 1 | `Api/Customer/BookingController.php` | 0,5 |
| X02 | Chặn đơn không có người lớn đi kèm, chặn trẻ dưới 12 tuổi đi một mình | A09 | 1 | `app/Http/Requests` | 0,25 |
| X03 | Kiểm tra lại hiệu lực và số lượt mã giảm giá trong giao dịch tạo đơn, hết lượt thì tạo đơn giá gốc kèm thông báo | A11 | 1 | `Api/Customer/BookingController.php` | 0,5 |
| X04 | Tách `seat_count` khỏi `guests`, em bé không chiếm ghế nhưng vẫn tính sức chứa với tàu và máy bay | A14 | 1 | `database/migrations`, `app/Services` | 0,75 |
| X05 | Ghi nhật ký thư gửi hỏng, hiển thị cảnh báo trên trang quản trị | A15 | 1 | `app/Mail`, `Api/Admin` | 0,5 |
| X06a | API gửi lại mã tra cứu về email đã dùng khi đặt | A16 | 1 | `Api/Customer` | 0,25 |
| X06b | Giao diện gửi lại mã tra cứu trên trang tra cứu đơn | A16 | 1 | `client/src/pages/BookingLookup.tsx` | 0,25 |
| X07a | Mở lại đơn bị hủy nhầm trong 24 giờ, kiểm tra chuyến còn chỗ, bắt buộc nhập lý do | C06 | 1 | `app/Services`, `Api/Admin` | 0,75 |
| X07b | Giao diện mở lại đơn đã hủy | C06 | 1 | `client/src/pages/admin` | 0,25 |
| X08 | Hoàn tiền về tài khoản khác người đứng tên đơn, yêu cầu xác nhận và ghi chú | C11 | 3 | `Api/Admin` | 0,25 |
| X09a | Chuyển nhượng suất, đổi hoàn toàn người đi, cần duyệt và có thể thu phí đổi tên | D08 | 2 | `app/Services`, `Api/Admin` | 0,5 |
| X09b | Giao diện chuyển nhượng suất | D08 | 2 | `client/src/pages/admin` | 0,25 |
| X10a | Bảng `customer_credits` và dịch vụ ghi nhận, sử dụng, hết hạn công nợ khách | D15, C, K02 | 2 | `database/migrations`, `app/Services` | 1 |
| X10b | Áp dụng công nợ khi đặt đơn mới và khi chuyển chuyến rẻ hơn | D15 | 2 | `Api/Customer`, `app/Services` | 0,5 |
| X11 | Cảnh báo chuyến đã chốt nhưng chưa có hướng dẫn viên phụ trách | E12 | 2 | `app/Console/Commands` | 0,5 |
| X12 | Lệnh dọn đơn `pending` tồn đọng của chuyến đã kết thúc | J06 | 1 | `app/Console/Commands` | 0,5 |
| X13 | Phụ thu phòng đơn vào công thức tính tiền, dùng `single_supplement` của tour | Doc 02 §1.2, I08 | 3 | `app/Services`, `Api/Admin` | 0,75 |
| X14 | Kiểm thử nhóm X | | 1 | `tests/Feature` | 1 |

**Tổng nhóm X: 9,25 ngày công, 18 công việc**

Phân bổ theo mốc: Mốc 1 là 5,5 ngày (X01 tới X07, X12, X14), Mốc 2 là 2,75 ngày
(X09, X10, X11), Mốc 3 là 1 ngày (X08, X13).

---

# MỐC 2 - Vận hành chuyến đi

## Nhóm I - Chuyển chuyến và chuyển tour — ĐÃ LÀM (trừ I04 tách đơn khi chuyển một phần)

Tài liệu tham chiếu: 02 mục 4, 07 mục 2.1.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| I01 | Migration `booking_transfers`, thêm `transfer_count` và `split_from_booking_id` vào `bookings` | `database/migrations` | | 0.5 |
| I02 | `BookingTransferService`: khóa hai chuyến theo thứ tự khóa chính tăng dần, kiểm tra điều kiện, chuyển chỗ, ghi nhật ký | `app/Services` | I01, A03 | 2 |
| I03 | Tính chênh lệch giá và tạo khoản thu bổ sung hoặc công nợ | `app/Services` | I02 | 1 |
| I04 | Tách đơn khi chỉ chuyển một phần số khách | `app/Services` | I02 | 1 |
| I05 | API quản trị thực hiện chuyển, API khách gửi yêu cầu chuyển | `Api/Admin`, `Api/Customer` | I02, F01 | 1 |
| I06 | Giao diện quản trị: chọn chuyến đích, xem trước chênh lệch, xác nhận | `client/src/pages/admin` | I05 | 1.25 |
| I07 | Thư thông báo chuyển chuyến | `app/Mail` | I02 | 0.5 |
| I08 | Kiểm thử: chuyến đích hết chỗ thì quay lui, hai luồng chuyển chéo không gây khóa chết, đơn đã điểm danh không chuyển được | `tests/Feature` | I02-I04 | 1.25 |

**Tổng nhóm I: 8,5 ngày**

## ~~Nhóm J - Sửa số lượng khách~~ — ĐÃ BỎ KHỎI PHẠM VI

**Không làm.** Nhóm này sinh ra từ một diễn giải của chính chúng ta, không phải từ lời hội đồng:
hội đồng nêu *"cập nhật lại booking, thực tế và trên web"*, không nói tới số lượng.

Ranh giới đã chốt: **sửa thứ gõ nhầm thì được, đổi thứ đã mua thì không.** Số lượng khách là thứ
đã mua — đổi nó là đổi số chỗ giữ ở chuyến, tổng tiền đơn, và nếu giảm thì phải tính phí hủy trên
phần bớt đi. Khách cần đổi số người thì hủy và đặt lại theo đúng chính sách hủy, chứ không đi cửa
sau qua màn sửa đơn.

Phần thật sự cần đã làm bằng `BookingContactService`: sửa tên, điện thoại, thư điện tử của người
đặt. Xem [06 mục 1](06-doi-chieu-feedback.md).

Bảng dưới giữ lại để đối chiếu, không phải việc phải làm.

Tài liệu tham chiếu: 02 mục 3.2.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| J01 | `BookingAdjustmentService`: tăng giảm số khách trong giao dịch có khóa chuyến, tính lại tổng tiền | `app/Services` | A03, E02 | 1.5 |
| J02 | Tăng số khách sinh khoản thu bổ sung và liên kết thanh toán mới | `app/Services`, `Api/Admin` | J01 | 1 |
| J03 | Giảm số khách áp chính sách hủy một phần, tạo khoản hoàn | `app/Services` | J01, B03 | 1 |
| J04 | API và giao diện quản trị sửa số khách kèm lý do bắt buộc | `Api/Admin`, `client/src/pages/admin` | J01 | 1.25 |
| J05 | Kiểm thử: tăng vượt sức chứa bị từ chối, giảm về 0 bị từ chối, số tiền tính đúng, có nhật ký | `tests/Feature` | J01-J03 | 1 |

**Tổng nhóm J: 5,75 ngày**

## Nhóm K - Hủy chuyến — ĐÃ LÀM

Tài liệu tham chiếu: 04 mục 3.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| K01 | API đánh giá tác động: số đơn theo trạng thái, số khách, tổng đã thu, số ngày còn lại | `Api/Admin` | A03 | 0.75 |
| K02 | API gán phương án cho từng đơn: hoàn đủ, chuyển chuyến, chuyển tour, ghi công nợ | `Api/Admin` | K01, I02 | 1 |
| K03 | `ScheduleCancellationService` chặn khi còn đơn chưa có phương án, thực thi toàn bộ trong một giao dịch | `app/Services` | K02 | 1.5 |
| K04 | Giao diện quản trị ba bước | `client/src/pages/admin` | K01-K03 | 2 |
| K05 | Thư xin lỗi kèm phương án cho từng khách | `app/Mail`, `resources/views/emails` | K03 | 0.75 |
| K06 | Chặn xóa cứng tour còn đơn hiệu lực, thông báo lỗi nêu rõ số đơn đang chặn | `Api/Admin/AdminTourController.php` | | 0.5 |
| K07 | Kiểm thử: còn đơn chưa có phương án thì không hủy được, hủy xong mọi đơn có bản ghi tương ứng | `tests/Feature` | K03 | 1 |

**Tổng nhóm K: 7,5 ngày**

## Nhóm L - Ghép chuyến — ĐÃ LÀM

Tài liệu tham chiếu: 04 mục 2.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| L01 | `ScheduleMergeService`: kiểm tra điều kiện, chuyển toàn bộ đơn, cộng dồn số chỗ, đặt tham chiếu chuyến đích | `app/Services` | I02 | 1.5 |
| L02 | Xử lý đơn chưa thanh toán: hủy kèm thư mời đặt lại | `app/Services` | L01 | 0.5 |
| L03 | API và giao diện quản trị: gợi ý chuyến có thể ghép, xem trước tác động, xác nhận | `Api/Admin`, `client/src/pages/admin` | L01 | 1.5 |
| L04 | Thêm `type` cho tour phân biệt tour ghép và tour riêng, khóa bán lẻ khi đoàn đặt trọn chuyến | `database/migrations`, `Api/Admin` | | 0.75 |
| L05 | Kiểm thử: chuyến đích không đủ chỗ thì từ chối, sau ghép tổng số khách đúng, ghép dây chuyền hiển thị đúng chuyến cuối | `tests/Feature` | L01 | 0.75 |

**Tổng nhóm L: 5 ngày**

## Nhóm M - Hướng dẫn viên — ĐÃ LÀM (M11 phần thẻ hành nghề đã cố ý bỏ, xem doc 06)

Tài liệu tham chiếu: 04 mục 4, 07 mục 2.2.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| M01 | Migration `guide_profiles` | `database/migrations` | | 0.25 |
| M02 | Migration `schedule_guide_assignments` và chuyển dữ liệu từ `tour_schedules.guide_id` | `database/migrations` | M01 | 0.5 |
| M03 | API và giao diện quản trị hồ sơ năng lực hướng dẫn viên | `Api/Admin/AdminGuideController.php`, `client/src/pages/admin` | M01 | 1.5 |
| M04 | `GuideAvailabilityService`: kiểm tra trùng lịch bằng điều kiện giao nhau khoảng thời gian, cộng khoảng nghỉ tối thiểu | `app/Services` | M02, A01 | 1 |
| M05 | `GuideSuggestionService`: lọc theo tiêu chí bắt buộc, xếp hạng theo tiêu chí ưu tiên | `app/Services` | M03, M04 | 1.25 |
| M06 | Giao diện phân công có gợi ý xếp hạng kèm lý do | `client/src/pages/admin` | M05 | 1.25 |
| M07 | Bàn giao giữa chừng: API đóng phân công cũ, mở phân công mới, ghi chú bàn giao | `Api/Admin` | M02, M04 | 1 |
| M08 | Đổi kiểm tra quyền điểm danh sang tra phân công có hiệu lực tại thời điểm thao tác | `Api/Guide/AttendanceController.php`, `app/Services` | M02, H08 | 0.75 |
| M09 | Giao diện hướng dẫn viên mới xem được tình trạng đoàn và ghi chú bàn giao | `client/src/pages/guide` | M07 | 1 |
| M10 | Thư thông báo đổi hướng dẫn viên cho khách trong đoàn | `app/Mail` | M07 | 0.5 |
| M11 | Kiểm thử: trùng lịch bị từ chối, thẻ hết hạn bị từ chối, người cũ mất quyền ghi sau bàn giao nhưng vẫn đọc được | `tests/Feature` | M04, M07, M08 | 1.25 |

**Tổng nhóm M: 10,25 ngày**

**TỔNG MỐC 2: 37 ngày công, 36 công việc**

---

# MỐC 3 - Tài chính và hồ sơ

## Nhóm N - Đặt cọc và sổ giao dịch — MỘT PHẦN: sổ giao dịch xong, cọc cho khách lẻ chưa

Tài liệu tham chiếu: 02 mục 2.2 và 2.3, 07 mục 3.1.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| N01 | Migration `booking_transactions`, thêm `deposit_amount`, `final_due_at`, `paid_amount` vào `bookings` | `database/migrations` | | 0.5 |
| N02 | Migration chuyển dữ liệu: sinh giao dịch cho các đơn đã thanh toán | `database/migrations` | N01 | 0.5 |
| N03 | Thêm `deposit_percent` và `final_payment_days_before` vào tour, mặc định giữ nguyên hành vi hiện tại | `database/migrations`, `Api/Admin` | | 0.5 |
| N04 | `PaymentLedgerService`: tính số còn phải thu, suy ra trạng thái thanh toán thay vì gán tay | `app/Services` | N01 | 1.25 |
| N05 | Luồng đặt cọc: tính tiền cọc, tạo liên kết thanh toán, chuyển trạng thái | `Api/Customer/BookingController.php` | N04 | 1.25 |
| N06 | Thanh toán phần còn lại, liên kết riêng, xử lý khi tổng tiền đã thay đổi | `Api/Customer/BookingController.php` | N05 | 1 |
| N07 | Lệnh nhắc thanh toán phần còn lại trước hạn 3 ngày và 1 ngày | `app/Console/Commands`, `app/Mail` | N05 | 0.75 |
| N08 | Giao diện: chọn phương thức đóng cọc, hiển thị tiến độ thanh toán trên trang đơn | `client/src/pages` | N05 | 1.25 |
| N09 | Giao diện quản trị: sổ giao dịch của đơn, ghi nhận thu tiền mặt hoặc chuyển khoản kèm chứng từ | `Api/Admin`, `client/src/pages/admin` | N04 | 1.5 |
| N10 | Luồng hoàn tiền thủ công: duyệt, chi, tải chứng từ, đổi trạng thái, gửi thư xác nhận | `Api/Admin`, `app/Mail` | N04, F03 | 1.5 |
| N11 | Kiểm thử: tổng hoàn không vượt tổng thu, đóng thừa xử lý đúng, trạng thái suy ra đúng từ sổ giao dịch | `tests/Feature` | N04-N06, N10 | 1.25 |

**Tổng nhóm N: 11,25 ngày**

## Nhóm O - Sự cố và chi phí phát sinh — ĐÃ LÀM

Đã cài đặt O01, O02, O03, O05, O06, O07, O09.

**O04 (thông báo đẩy cho điều hành) làm gọn hơn thiết kế:** sự cố mức nghiêm trọng chưa ai xử lý
được đánh dấu `needs_attention` và đẩy lên đầu danh sách, thay vì dựng hệ thống thông báo riêng.
Với quy mô một công ty lữ hành thì danh sách chờ xử lý có sắp thứ tự là đủ.

**O07 (xác nhận của khách) đã xong**, trừ phần ký điện tử của từng khách. `recordConsent()` có
tuyến `PUT /admin/surcharges/{id}/consent`, và **đây là chốt chặn trước khi thu**: khoản khách phải
trả mà chưa ghi nhận đồng ý thì `settleSurcharge()` từ chối. Hướng dẫn viên vẫn tải được ảnh biên
bản. Khoản hoàn không cần bước này.

**O08 (hoàn theo giá vốn) bỏ:** phụ thuộc R05, mà nhóm R đã ra khỏi phạm vi. Số tiền hoàn do điều
hành nhập kèm diễn giải bắt buộc, hệ thống không tự tính. Ghi rõ chỗ này khi trình bày.

### Ba việc bổ sung ngoài danh sách gốc

Danh sách O01–O09 dừng ở chỗ "lập được khoản và duyệt được khoản". Ba việc dưới đây nối tiếp cho
tới lúc tiền thật sự đổi tay, và cả ba đều phát hiện khi rà lại chứ không nằm trong thiết kế ban
đầu:

**O10 — người chịu chi phí tính theo từng khoản.** Trước đây `who_bears` nằm trên `schedule_incidents`,
một giá trị cho cả sự cố, nên tình huống thật nhất của cả nhóm lại là tình huống không ghi được:
bão làm tàu không chạy thì xe thuê chạy đường bộ là hãng chịu, còn đêm phòng ở thêm là khách chịu.
Cột chuyển xuống `booking_surcharges`, cột trên sự cố giữ lại làm mặc định gợi ý. Migration
`2026_08_23_000001`.

**O11 — bước thu tiền.** `SurchargeStatus::Paid` có khai báo từ đầu nhưng **không dòng mã nào đặt
được giá trị ấy**: khoản duyệt xong nằm mãi ở "đã duyệt" và sổ giao dịch không biết tới số tiền đó.
`settleSurcharge()` ghi một dòng vào `booking_payments` và đóng khoản, đi cả hai chiều. Hai loại
bút toán mới `surcharge` / `surcharge_refund`, cố ý tách khỏi `deposit`/`balance`/`refund` của giá
tour để chính sách hủy không đọc nhầm tiền phụ thu thành tiền đã trả cho tour.

**O12 — khách nhìn thấy khoản của mình.** `Booking` chưa có quan hệ tới `booking_surcharges` và
không controller nào của khách trả nó về, nên hệ thống lập một khoản phải trả rồi không nói với
người phải trả. Nay cả hai cửa (đăng nhập xem và tra cứu bằng mã) đều trả về, **chỉ khoản đã có
hiệu lực** — con số còn đang cân nhắc thì chưa phải thứ nói với khách.

Kèm theo là một lỗi thật do O11 tạo ra và đã chặn bằng test: `CancellationPolicyService::paidAmount()`
chọn nguồn theo câu hỏi "sổ đã có dòng chưa". Ghi phụ thu vào sổ làm một đơn lẻ đã trả đủ qua cổng
rơi vào nhánh sổ, cộng các loại tiền giá tour ra 0, và **báo đã thu 0 đồng** — hủy đơn thì hoàn 0.

Tài liệu tham chiếu: 04 mục 6, 07 mục 3.2.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| O01 | Migration `schedule_incidents`, `incident_photos`, `booking_surcharges` | `database/migrations` | | 0.5 |
| O02 | API hướng dẫn viên báo cáo sự cố kèm ảnh, không cho nhập mức phí | `Api/Guide` | O01 | 1 |
| O03 | Giao diện hướng dẫn viên báo cáo sự cố tại hiện trường | `client/src/pages/guide` | O02 | 1.25 |
| O04 | Thông báo ngay cho điều hành, mức nghiêm trọng cao đánh dấu riêng | `app/Notifications` | O02 | 0.5 |
| O05 | API điều hành duyệt phương án, phân bổ chi phí, tạo phụ thu cho từng đơn | `Api/Admin` | O01, N04 | 1.25 |
| O06 | Giao diện điều hành xử lý sự cố và phân bổ chi phí | `client/src/pages/admin` | O05 | 1.5 |
| O07 | Xác nhận của khách với khoản phụ thu, tải ảnh biên bản | `Api/Guide`, `Api/Admin` | O05 | 1 |
| O08 | Hoàn phần dịch vụ chưa sử dụng khi chương trình rút ngắn, tính theo giá vốn | `app/Services` | O05, R05 | 1 |
| O09 | Kiểm thử: hướng dẫn viên không tạo được phụ thu, phụ thu cần duyệt mới có hiệu lực | `tests/Feature` | O02, O05 | 0.75 |
| O10 | Người chịu chi phí chuyển xuống từng khoản, sự cố giữ lại làm mặc định | `database/migrations`, `app/Services` | O05 | 0.5 |
| O11 | Ghi nhận đã thu / đã hoàn, đẩy vào sổ giao dịch, hai loại bút toán riêng | `app/Services`, `Api/Admin` | O05, N04 | 0.75 |
| O12 | Khách đọc được khoản của mình trong đơn, chỉ khoản đã có hiệu lực | `Api/Customer`, `client/src/components/profile` | O05 | 0.5 |

**Tổng nhóm O: 10,5 ngày** (8,75 theo thiết kế gốc, cộng 1,75 cho O10–O12)

## Nhóm P - Booking theo đoàn — MỘT PHẦN: đường ống xong, còn P06 nhập Excel

Tài liệu tham chiếu: 05 mục 1, 07 mục 3.3.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| P01 | Migration `quotations`, `tour_price_tiers`, thêm các trường đoàn vào `bookings` | `database/migrations` | | 0.75 |
| P02 | API và giao diện khách gửi yêu cầu báo giá | `Api`, `client/src/pages` | P01 | 1.25 |
| P03 | API và giao diện quản trị lập báo giá, gửi cho khách, theo dõi trạng thái | `Api/Admin`, `client/src/pages/admin` | P01 | 2 |
| P04 | Tạo đơn từ báo giá đã chấp nhận | `Api/Admin` | P03 | 1 |
| P05 | Bậc giá theo số lượng, áp dụng khi tính tiền, gồm suất miễn phí | `app/Services` | P01 | 1.25 |
| P06 | Nhập danh sách khách từ tệp, mẫu tải xuống, kiểm tra theo từng dòng, báo lỗi chi tiết | `Api/Admin`, `client/src/pages/admin` | G01 | 2 |
| P07 | Hủy một phần số khách cho đơn đoàn | `app/Services` | J03 | 1 |
| P08 | Kiểm thử: bậc giá áp đúng, nhập tệp sai định dạng báo đúng dòng lỗi | `tests/Feature` | P05, P06 | 1 |

**Tổng nhóm P: 10,25 ngày**

## Nhóm Q - Hợp đồng và hồ sơ — CHƯA LÀM

Tài liệu tham chiếu: 05 mục 2 và 3, 07 mục 3.4.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| Q01 | Cài `barryvdh/laravel-dompdf` và `maatwebsite/excel` | `composer.json` | | 0.25 |
| Q02 | Migration `booking_contracts`, `contract_number_sequences`, `booking_rooms`, `booking_room_passenger`, `document_exports` | `database/migrations` | | 0.75 |
| Q03 | Sinh số hợp đồng an toàn khi có nhiều yêu cầu đồng thời, dùng khóa dòng trên bảng đếm | `app/Services` | Q02 | 0.75 |
| Q04 | Mẫu hợp đồng dạng Blade với đủ mười một mục bắt buộc | `resources/views/contracts` | Q01 | 1.5 |
| Q05 | Xuất hợp đồng PDF, lưu tệp, gửi liên kết cho khách | `Api/Admin`, `app/Services` | Q03, Q04 | 1 |
| Q06 | Tải lên bản hợp đồng đã ký, ghi thời điểm ký | `Api/Admin` | Q05 | 0.5 |
| Q07 | Xuất danh sách đoàn dạng Excel và PDF | `app/Exports`, `Api/Admin` | Q01, G01 | 1.25 |
| Q08 | Danh sách phòng: xếp phòng, kiểm tra sức chứa và giới tính, cảnh báo phụ thu phòng đơn | `Api/Admin`, `client/src/pages/admin` | Q02 | 1.75 |
| Q09 | Hồ sơ bàn giao cho hướng dẫn viên gồm bảy mục theo tài liệu 05 | `Api/Admin`, `Api/Guide` | Q07, Q08 | 1.25 |
| Q10 | Che một phần số giấy tờ trên giao diện, ghi nhật ký mỗi lần xuất tệp | `Api`, `client` | Q02, Q07 | 0.75 |
| Q11 | Kiểm thử: số hợp đồng không trùng khi sinh đồng thời, xếp phòng vượt sức chứa bị từ chối, xuất tệp có ghi nhật ký | `tests/Feature` | Q03, Q08, Q10 | 1 |

**Tổng nhóm Q: 10,75 ngày**

## Nhóm R - Cung ứng và giá vốn — CHƯA LÀM, ngoài 18 góp ý của hội đồng

Tài liệu tham chiếu: 09.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| R01 | Migration `suppliers`, `supplier_services`, `supplier_contracts` | `database/migrations` | | 0.5 |
| R02 | API và giao diện quản trị nhà cung cấp và dịch vụ, dạng danh sách kèm hộp thoại | `Api/Admin`, `client/src/pages/admin` | R01 | 2 |
| R03 | Migration `supplier_allotments` và `supplier_bookings` | `database/migrations` | R01 | 0.5 |
| R04 | Đặt dịch vụ cho chuyến, theo dõi trạng thái xác nhận và hạn hủy | `Api/Admin`, `client/src/pages/admin` | R03 | 1.5 |
| R05 | Migration `schedule_cost_estimates` và `schedule_actual_costs` | `database/migrations` | | 0.5 |
| R06 | Dự toán chi phí chuyến, tính điểm hòa vốn, gợi ý `min_people` | `app/Services`, `Api/Admin` | R05, A01 | 1.5 |
| R07 | Giao diện dự toán và hiển thị điểm hòa vốn khi tạo chuyến | `client/src/pages/admin` | R06 | 1.25 |
| R08 | Cảnh báo khi số khách đã bán vượt tồn chỗ của bất kỳ tài nguyên nào | `app/Services`, `client/src/pages/admin` | R03, R04 | 1 |
| R09 | Suy ra `booking_deadline` từ hạn hủy sớm nhất của các dịch vụ đã đặt | `app/Services` | R04, A01 | 0.5 |
| R10 | Báo cáo lãi lỗ từng chuyến theo tài liệu 09 mục 2.4 | `Api/Admin`, `client/src/pages/admin` | R05, N01 | 1.5 |
| R11 | Migration `guide_rates`, `guide_advances`, `guide_settlements`, `guide_settlement_items` | `database/migrations` | | 0.5 |
| R12 | Luồng tạm ứng và quyết toán hướng dẫn viên, duyệt từng khoản, đẩy vào chi phí thực tế của chuyến | `Api/Admin`, `Api/Guide`, `app/Services` | R11, R05 | 2 |
| R13 | Giao diện hướng dẫn viên nhập chi phí kèm ảnh hóa đơn và nộp quyết toán | `client/src/pages/guide` | R12 | 1.5 |
| R14 | Migration và luồng công nợ nhà cung cấp | `database/migrations`, `Api/Admin` | R01, R04 | 1.25 |
| R15 | Kiểm thử: điểm hòa vốn tính đúng, cảnh báo vượt tồn chỗ, quyết toán duyệt xong vào đúng chi phí chuyến | `tests/Feature` | R06, R08, R12 | 1.25 |

**Tổng nhóm R: 17,25 ngày**

## Nhóm S - Ghi nhận doanh thu và đối soát — CHƯA LÀM, ngoài 18 góp ý của hội đồng

Tài liệu tham chiếu: 10.

| ID | Công việc | Chạm tới | Phụ thuộc | Ngày |
| --- | --- | --- | --- | --- |
| S01 | Migration thêm `revenue_recognized_at` và `recognized_amount` vào `bookings` | `database/migrations` | | 0.25 |
| S02 | Ghi nhận doanh thu khi chuyến chuyển sang đã kết thúc | `app/Services`, `app/Console/Commands` | S01, A07 | 1 |
| S03 | Sửa bảng điều khiển: tách ba chỉ tiêu tiền thu, doanh thu ghi nhận, doanh thu chưa thực hiện | `Api/Admin/AdminController.php`, `client/src/pages/admin` | S02 | 1.25 |
| S04 | Phí hủy ghi nhận là thu nhập khác, tách khỏi doanh thu bán tour | `app/Services` | S02, B03 | 0.75 |
| S05 | Migration `reconciliation_runs` và `reconciliation_issues` | `database/migrations` | | 0.25 |
| S06 | Lệnh đối soát cổng thanh toán, sinh danh sách chênh lệch theo năm loại | `app/Console/Commands` | S05, N01 | 1.5 |
| S07 | Giao diện đối soát, xử lý và đánh dấu từng dòng chênh lệch | `Api/Admin`, `client/src/pages/admin` | S06 | 1.25 |
| S08 | Báo cáo dòng tiền theo chuyến | `Api/Admin`, `client/src/pages/admin` | R14, N01 | 1.25 |
| S09 | Báo cáo tỷ lệ lấp đầy, ghế chết, tỷ lệ hủy theo nguyên nhân | `Api/Admin`, `client/src/pages/admin` | C01, R06 | 1.5 |
| S10 | Kiểm thử: doanh thu chỉ ghi nhận khi chuyến kết thúc, hoàn tiền làm giảm đúng, đối soát phát hiện đúng chênh lệch | `tests/Feature` | S02, S06 | 1 |

**Tổng nhóm S: 10 ngày**

**TỔNG MỐC 3: 68,25 ngày công, 64 công việc**

---

# Tổng hợp

| Mốc | Nhóm | Số công việc | Ngày công |
| --- | --- | --- | --- |
| 1 - Nền tảng điều hành | A đến H | 63 | 46,5 |
| 2 - Vận hành chuyến đi | I đến M | 36 | 37 |
| 3 - Tài chính và hồ sơ | N đến S | 64 | 68,25 |
| Rải đều ba mốc | X | 18 | 9,25 |
| **Tổng** | | **181** | **161** |

## Đánh giá thực tế về khối lượng

161 ngày công là khoảng **7 tháng làm việc toàn thời gian của một người**. Với nhóm ba người
làm song song ở các nhóm ít phụ thuộc nhau thì khoảng 3 tháng. Đây là con số thật, không nên
tự trấn an rằng làm nhanh hơn được.

Kết luận thực tế: **không thể làm hết trước buổi bảo vệ**. Việc cần làm là chọn đúng phần và
tuyên bố rõ phần còn lại.

## Đề xuất phạm vi cho buổi bảo vệ

| Phương án | Nội dung | Ngày công | Kết quả |
| --- | --- | --- | --- |
| Tối thiểu | Nhóm A, C, D, B03 | 17 | Trả lời được câu 7, 8, 9 bằng mã chạy thật, các câu khác bằng tài liệu |
| Khuyến nghị | Toàn bộ Mốc 1 cộng phần Mốc 1 của nhóm X | 52 | Trả lời 12 trên 18 câu bằng mã chạy thật |
| Tham vọng | Mốc 1 và Mốc 2 cùng nhóm X tương ứng | 91,75 | Trả lời 17 trên 18 câu, chỉ thiếu mảng tài chính |

**Khuyến nghị chọn phương án giữa.** Lý do: Mốc 1 chứa toàn bộ phần hội đồng hỏi trực tiếp về
hủy, điểm danh và trạng thái chuyến, tức là những câu họ đã chứng minh là sẽ hỏi. Mốc 2 và 3
trình bày bằng tài liệu thiết kế kèm mô hình dữ liệu, và nói rõ đó là lựa chọn có cân nhắc
theo tài liệu 00.

## Đường găng của Mốc 1

Thứ tự bắt buộc, các nhóm khác chèn vào giữa được:

```
A01 → A02 → A03 → A04 → A05,A06,A07 → A09
                    ↓
                   C01 → C02 → C03
                    ↓
                   D01 → D02
                    ↓
       B01 → B03 → F02 → F03
                    ↓
       H01 → H02 → H04 → H05 → H08 → H09 → H11
```

Nhóm E và G không phụ thuộc nhóm nào, làm song song bất cứ lúc nào.

Nhóm H là nhóm dài nhất và độc lập với B, C, D, F sau khi có A03. Nếu làm nhóm thì nên tách
một người chuyên nhóm H ngay từ đầu.

## Quy tắc chia commit

Theo thói quen dự án: Conventional Commits, thông điệp tiếng Anh, mỗi commit một chủ đề.

| Loại công việc | Tiền tố |
| --- | --- |
| Migration và model mới | `feat(db):` |
| Dịch vụ nghiệp vụ mới | `feat(<miền>):` |
| Giao diện quản trị | `feat(admin):` |
| Giao diện khách | `feat(client):` |
| Giao diện hướng dẫn viên | `feat(guide):` |
| Sửa lỗi | `fix(<miền>):` |
| Kiểm thử bổ sung | `test(<miền>):` |
| Tài liệu | `docs:` |

Mỗi nhóm công việc kết thúc bằng một commit kiểm thử riêng, để lịch sử cho thấy rõ phần nào
đã được kiểm chứng.
