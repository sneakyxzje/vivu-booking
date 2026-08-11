# 12 - Phân công nhóm

Phân bổ 163 công việc trong [11 - Backlog triển khai](11-backlog-trien-khai.md) cho bốn thành viên.

## 1. Thành viên và vai trò

| Tên | Ký hiệu | Vai trò | Nhận |
| --- | --- | --- | --- |
| Tuấn Đạt | TĐ | Trưởng nhóm, toàn phần | Phần khó: tiền, số chỗ, khóa đồng thời, chuyển dữ liệu, máy trạng thái |
| Thế Anh | TA | Máy chủ | CRUD, lệnh chạy nền, tích hợp, xuất tệp |
| Đặng Tiến Đạt | ĐTĐ | Máy chủ | CRUD, luồng duyệt, báo giá, hợp đồng, báo cáo |
| Phạm Đức | PĐ | Giao diện | Toàn bộ phía khách, quản trị, hướng dẫn viên |

## 2. Nguyên tắc phân công

**Việc gì về TĐ.** Bốn loại, không phân cho ai khác:

1. Chạm tới **số chỗ** hoặc **tiền**: tính phí hủy, tính lại tổng đơn, sổ thanh toán, chuyển chỗ.
2. Cần **khóa nhiều tài nguyên** cùng lúc: chuyển chuyến, ghép chuyến, hủy chuyến.
3. **Chuyển dữ liệu cũ** sang cấu trúc mới: sai một lần là hỏng dữ liệu thật.
4. **Máy trạng thái** và các quy tắc chặn ở tầng dịch vụ.

Lý do: đây là chỗ lỗi không lộ ra ngay mà chỉ hiện khi có hai người thao tác đồng thời hoặc
khi đối soát cuối kỳ. Người chưa quen dễ viết ra thứ chạy đúng lúc thử tay nhưng sai lúc thật.

**Việc gì về TA và ĐTĐ.** Tạo bảng mới không có chuyển dữ liệu, CRUD theo mẫu đã có ở
quản lý danh mục, lệnh chạy nền dạng vòng lặp đơn giản, thư điện tử, xuất Excel và PDF,
báo cáo dạng tổng hợp rồi hiển thị, kiểm thử cho phần mình viết.

**Việc gì về PĐ.** Toàn bộ thư mục `client`. Không nhận việc phía máy chủ.

**Kiểm thử.** Ai viết dịch vụ thì viết kiểm thử cho dịch vụ đó, trừ ba trường hợp mà TA hoặc
ĐTĐ viết kiểm thử cho dịch vụ của TĐ, để có con mắt thứ hai lên phần dễ sai nhất.

## 3. Tổng khối lượng

| Người | Mốc 1 | Mốc 2 | Mốc 3 | Tổng ngày công |
| --- | --- | --- | --- | --- |
| TĐ | 13,5 | 15,75 | 16,75 | **46** |
| TA | 12 | 8,5 | 14,75 | **35,25** |
| PĐ | 11,5 | 7,75 | 16 | **35,25** |
| ĐTĐ | 9,5 | 5 | 20,75 | **35,25** |
| **Tổng** | 46,5 | 37 | 68,25 | **151,75** |

Ba người bằng nhau đúng 35,25 ngày công. TĐ nặng hơn khoảng 30 phần trăm, đúng yêu cầu nhận
phần khó.

Phân bổ theo mốc không đều là có chủ ý. Mốc 2 gần như toàn việc khóa đồng thời nên dồn vào TĐ,
ĐTĐ chỉ có 5 ngày ở mốc này rồi gánh 20,75 ngày ở Mốc 3 với mảng đoàn, hợp đồng và báo cáo.
Nếu cắt Mốc 3 theo mục 8 thì ĐTĐ dư việc, khi đó chuyển bớt nhóm L và M05 từ TA sang.

## 4. Phân công chi tiết

Ký hiệu độ khó: `D` dễ, `TB` trung bình, `K` khó.

### MỐC 1

#### Nhóm A - Vòng đời chuyến khởi hành

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| A01 | Migration thêm cột vào `tour_schedules` | TA | D | 0,5 |
| A02 | Migration backfill dữ liệu chuyến cũ | **TĐ** | K | 0,5 |
| A03 | Model, hằng số trạng thái, scope | TA | D | 0,5 |
| A04 | `ScheduleLifecycleService`, bảng chuyển trạng thái hợp lệ | **TĐ** | K | 1 |
| A05 | Lệnh đóng bán chuyến quá hạn | TA | TB | 0,5 |
| A06 | Lệnh chốt chuyến đủ khách, cảnh báo chuyến thiếu | TA | TB | 1 |
| A07 | Lệnh chuyển đang chạy và đã kết thúc | TA | D | 0,5 |
| A08 | Đăng ký lịch chạy nền | TA | D | 0,25 |
| A09 | Chặn tạo đơn khi chuyến không mở bán | **TĐ** | TB | 0,5 |
| A10 | API quản trị tạo và sửa chuyến | ĐTĐ | TB | 1 |
| A11 | Giao diện quản trị chuyến | PĐ | TB | 1 |
| A12 | Giao diện khách hiển thị hạn đặt | PĐ | D | 0,5 |
| A13 | Kiểm thử nhóm A | **TĐ** | TB | 1 |

#### Nhóm B - Chính sách hủy

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| B01 | Migration bảng chính sách và quy tắc | ĐTĐ | D | 0,5 |
| B02 | Seeder năm mốc mặc định | ĐTĐ | D | 0,25 |
| B03 | `CancellationPolicyService` tính phí và tiền hoàn | **TĐ** | K | 1 |
| B04 | Sao chép chính sách vào đơn khi đặt | ĐTĐ | D | 0,25 |
| B05 | API quản trị chính sách hủy | ĐTĐ | D | 1 |
| B06 | Giao diện quản trị chính sách | PĐ | D | 1 |
| B07 | Giao diện khách hiển thị điều khoản hủy | PĐ | D | 0,5 |
| B08 | Kiểm thử nhóm B | **TĐ** | TB | 0,75 |

#### Nhóm C - Trả chỗ khi hủy

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| C01 | Migration cột hủy và trả chỗ | TA | D | 0,5 |
| C02 | `BookingHoldService` quyết định trả chỗ theo hạn chốt | **TĐ** | K | 1 |
| C03 | API danh sách chỗ chưa mở bán lại, hành động mở lại | TA | TB | 0,75 |
| C04 | Giao diện quản trị mở lại chỗ | PĐ | D | 0,75 |
| C05 | Lệnh đối chiếu nhất quán số chỗ | TA | TB | 0,5 |
| C06 | Kiểm thử nhóm C | **TĐ** | TB | 0,75 |

#### Nhóm D - Chặn hủy khi chuyến đang chạy

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| D01 | `BookingPolicyService::assertCancellable` | **TĐ** | K | 0,5 |
| D02 | Áp cho cả bốn lối vào | **TĐ** | K | 0,5 |
| D03 | Trạng thái `no_show` và `completed`, lệnh tự chuyển | TA | TB | 0,75 |
| D04 | Kiểm thử nhóm D | **TĐ** | TB | 0,5 |

#### Nhóm E - Nhật ký thay đổi

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| E01 | Migration `booking_audit_logs` | TA | D | 0,25 |
| E02 | `BookingAuditLogger` | TA | TB | 0,75 |
| E03 | Gắn vào mọi chỗ đổi trạng thái, tiền, số chỗ | **TĐ** | TB | 1 |
| E04a | API lịch sử đơn | ĐTĐ | D | 0,5 |
| E04b | Giao diện tab lịch sử | PĐ | D | 0,5 |
| E05 | Kiểm thử nhóm E | TA | TB | 0,5 |

#### Nhóm F - Yêu cầu thay đổi của khách

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| F01 | Migration `booking_change_requests` | ĐTĐ | D | 0,25 |
| F02 | API khách xem mức hoàn dự kiến và gửi yêu cầu | ĐTĐ | TB | 1 |
| F03 | API quản trị duyệt và thực thi hủy | **TĐ** | K | 1 |
| F04 | Thư điện tử ba loại | ĐTĐ | D | 0,75 |
| F05 | Giao diện khách yêu cầu hủy | PĐ | TB | 1 |
| F06 | Giao diện quản trị duyệt yêu cầu | PĐ | TB | 1 |
| F07 | Kiểm thử nhóm F | ĐTĐ | TB | 0,75 |

#### Nhóm G - Thông tin hành khách

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| G01 | Migration bổ sung cột hành khách | ĐTĐ | D | 0,25 |
| G02 | Quy tắc kiểm tra giấy tờ và độ tuổi | ĐTĐ | TB | 0,75 |
| G03 | API sửa hành khách phân quyền theo mốc thời gian | ĐTĐ | TB | 0,75 |
| G04 | Giao diện biểu mẫu hành khách đầy đủ | PĐ | TB | 1,25 |
| G05a | API cảnh báo khai thiếu hành khách | ĐTĐ | D | 0,25 |
| G05b | Hiển thị cảnh báo trên quản trị | PĐ | D | 0,25 |
| G06 | Kiểm thử nhóm G | ĐTĐ | TB | 0,75 |

#### Nhóm H - Điểm danh chi tiết

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| H01 | Migration `itinerary_checkpoints` | TA | D | 0,25 |
| H02 | Migration `passenger_checkins` và lịch sử | TA | D | 0,5 |
| H03 | Migration bổ sung cho ảnh check-in | TA | D | 0,25 |
| H04 | Migration chuyển dữ liệu điểm danh cũ | **TĐ** | K | 0,75 |
| H05 | Model và quan hệ | TA | D | 0,5 |
| H06 | API quản lý điểm dừng theo ngày | TA | TB | 1 |
| H07 | Giao diện quản trị điểm dừng | PĐ | TB | 1,25 |
| H08 | `AttendanceService` với chín quy tắc kiểm tra | **TĐ** | K | 1,5 |
| H09 | API điểm danh theo hành khách và điểm dừng | TA | TB | 1 |
| H10 | Ảnh check-in gắn tọa độ, cảnh báo khoảng cách | TA | TB | 0,75 |
| H11 | Giao diện hướng dẫn viên điểm danh | PĐ | TB | 2 |
| H12 | Cảnh báo khách vắng cho điều hành | TA | TB | 0,5 |
| H13a | API báo cáo điểm danh | ĐTĐ | TB | 0,5 |
| H13b | Giao diện báo cáo điểm danh | PĐ | D | 0,5 |
| H14 | Kiểm thử nhóm H | **TĐ** | TB | 1,25 |

### MỐC 2

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| I01 | Migration `booking_transfers` | TA | D | 0,5 |
| I02 | `BookingTransferService` khóa hai chuyến | **TĐ** | K | 2 |
| I03 | Tính chênh lệch giá và khoản thu bổ sung | **TĐ** | K | 1 |
| I04 | Tách đơn khi chuyển một phần | **TĐ** | K | 1 |
| I05 | API chuyển chuyến | ĐTĐ | TB | 1 |
| I06 | Giao diện chuyển chuyến | PĐ | TB | 1,25 |
| I07 | Thư thông báo chuyển chuyến | TA | D | 0,5 |
| I08 | Kiểm thử chuyển chuyến, gồm khóa chết | **TĐ** | K | 1,25 |
| J01 | `BookingAdjustmentService` sửa số khách | **TĐ** | K | 1,5 |
| J02 | Tăng khách sinh khoản thu bổ sung | **TĐ** | K | 1 |
| J03 | Giảm khách áp chính sách hủy một phần | **TĐ** | K | 1 |
| J04a | API sửa số khách | ĐTĐ | TB | 0,5 |
| J04b | Giao diện sửa số khách | PĐ | TB | 0,75 |
| J05 | Kiểm thử sửa số khách | TA | TB | 1 |
| K01 | API đánh giá tác động hủy chuyến | ĐTĐ | TB | 0,75 |
| K02 | API gán phương án cho từng đơn | ĐTĐ | TB | 1 |
| K03 | `ScheduleCancellationService` | **TĐ** | K | 1,5 |
| K04 | Giao diện hủy chuyến ba bước | PĐ | K | 2 |
| K05 | Thư xin lỗi kèm phương án | ĐTĐ | D | 0,75 |
| K06 | Chặn xóa cứng tour còn đơn hiệu lực | TA | D | 0,5 |
| K07 | Kiểm thử hủy chuyến | ĐTĐ | TB | 1 |
| L01 | `ScheduleMergeService` | **TĐ** | K | 1,5 |
| L02 | Xử lý đơn chưa thanh toán khi ghép | TA | TB | 0,5 |
| L03a | API ghép chuyến và gợi ý chuyến ghép được | TA | TB | 0,75 |
| L03b | Giao diện ghép chuyến | PĐ | TB | 0,75 |
| L04 | Loại tour ghép và tour riêng | TA | D | 0,75 |
| L05 | Kiểm thử ghép chuyến | **TĐ** | TB | 0,75 |
| M01 | Migration `guide_profiles` | TA | D | 0,25 |
| M02 | Migration phân công theo giai đoạn, chuyển dữ liệu | **TĐ** | K | 0,5 |
| M03a | API hồ sơ năng lực hướng dẫn viên | TA | D | 0,75 |
| M03b | Giao diện hồ sơ hướng dẫn viên | PĐ | D | 0,75 |
| M04 | `GuideAvailabilityService` kiểm tra trùng lịch | **TĐ** | K | 1 |
| M05 | `GuideSuggestionService` xếp hạng | TA | TB | 1,25 |
| M06 | Giao diện phân công có gợi ý | PĐ | TB | 1,25 |
| M07 | API bàn giao hướng dẫn viên giữa chừng | **TĐ** | K | 1 |
| M08 | Đổi kiểm tra quyền điểm danh sang tra phân công | **TĐ** | K | 0,75 |
| M09 | Giao diện hướng dẫn viên mới nhận bàn giao | PĐ | TB | 1 |
| M10 | Thư thông báo đổi hướng dẫn viên | TA | D | 0,5 |
| M11 | Kiểm thử nhóm hướng dẫn viên | TA | TB | 1,25 |

### MỐC 3

| ID | Việc | Người | Khó | Ngày |
| --- | --- | --- | --- | --- |
| N01 | Migration sổ giao dịch | TA | D | 0,5 |
| N02 | Migration chuyển dữ liệu thanh toán cũ | **TĐ** | K | 0,5 |
| N03 | Trường đặt cọc trên tour | TA | D | 0,5 |
| N04 | `PaymentLedgerService` | **TĐ** | K | 1,25 |
| N05 | Luồng đóng cọc | **TĐ** | K | 1,25 |
| N06 | Thanh toán phần còn lại | **TĐ** | K | 1 |
| N07 | Lệnh nhắc thanh toán | TA | TB | 0,75 |
| N08 | Giao diện khách theo dõi tiến độ thanh toán | PĐ | TB | 1,25 |
| N09a | API sổ giao dịch cho quản trị | ĐTĐ | TB | 0,5 |
| N09b | Giao diện sổ giao dịch | PĐ | TB | 1 |
| N10 | Luồng hoàn tiền thủ công có chứng từ | ĐTĐ | TB | 1,5 |
| N11 | Kiểm thử sổ giao dịch và đặt cọc | TA | TB | 1,25 |
| O01 | Migration sự cố và phụ thu | TA | D | 0,5 |
| O02 | API hướng dẫn viên báo cáo sự cố | TA | TB | 1 |
| O03 | Giao diện báo cáo sự cố tại hiện trường | PĐ | TB | 1,25 |
| O04 | Thông báo cho điều hành | TA | D | 0,5 |
| O05 | API duyệt phương án và phân bổ chi phí | **TĐ** | K | 1,25 |
| O06 | Giao diện xử lý sự cố | PĐ | TB | 1,5 |
| O07 | Xác nhận của khách với khoản phụ thu | TA | TB | 1 |
| O08 | Hoàn phần dịch vụ chưa dùng theo giá vốn | **TĐ** | K | 1 |
| O09 | Kiểm thử sự cố và phụ thu | TA | TB | 0,75 |
| P01 | Migration báo giá và bậc giá | ĐTĐ | D | 0,75 |
| P02a | API yêu cầu báo giá | ĐTĐ | D | 0,5 |
| P02b | Giao diện yêu cầu báo giá | PĐ | D | 0,75 |
| P03a | API lập và gửi báo giá | ĐTĐ | TB | 1 |
| P03b | Giao diện lập báo giá | PĐ | TB | 1 |
| P04 | Tạo đơn từ báo giá | ĐTĐ | TB | 1 |
| P05 | Bậc giá theo số lượng và suất miễn phí | **TĐ** | K | 1,25 |
| P06 | Nhập danh sách khách từ tệp Excel | ĐTĐ | TB | 2 |
| P07 | Hủy một phần số khách cho đơn đoàn | **TĐ** | K | 1 |
| P08 | Kiểm thử đoàn và bậc giá | ĐTĐ | TB | 1 |
| Q01 | Cài gói xuất PDF và Excel | ĐTĐ | D | 0,25 |
| Q02 | Migration hợp đồng và danh sách phòng | ĐTĐ | D | 0,75 |
| Q03 | Sinh số hợp đồng an toàn khi đồng thời | **TĐ** | K | 0,75 |
| Q04 | Mẫu hợp đồng mười một mục | ĐTĐ | TB | 1,5 |
| Q05 | Xuất hợp đồng PDF | ĐTĐ | TB | 1 |
| Q06 | Tải lên bản hợp đồng đã ký | ĐTĐ | D | 0,5 |
| Q07 | Xuất danh sách đoàn Excel và PDF | ĐTĐ | TB | 1,25 |
| Q08a | API danh sách phòng và kiểm tra xếp phòng | TA | TB | 0,75 |
| Q08b | Giao diện xếp phòng | PĐ | TB | 1 |
| Q09 | Hồ sơ bàn giao cho hướng dẫn viên | TA | TB | 1,25 |
| Q10 | Che số giấy tờ, nhật ký xuất tệp | TA | TB | 0,75 |
| Q11 | Kiểm thử hợp đồng và hồ sơ | ĐTĐ | TB | 1 |
| R01 | Migration nhà cung cấp | TA | D | 0,5 |
| R02a | API nhà cung cấp và dịch vụ | TA | D | 1 |
| R02b | Giao diện nhà cung cấp | PĐ | D | 1 |
| R03 | Migration tồn chỗ và đặt dịch vụ | TA | D | 0,5 |
| R04a | API đặt dịch vụ cho chuyến | TA | TB | 0,75 |
| R04b | Giao diện đặt dịch vụ | PĐ | TB | 0,75 |
| R05 | Migration dự toán và chi phí thực tế | TA | D | 0,5 |
| R06 | Dự toán chi phí, tính điểm hòa vốn | **TĐ** | K | 1,5 |
| R07 | Giao diện dự toán và điểm hòa vốn | PĐ | TB | 1,25 |
| R08 | Cảnh báo bán vượt tồn chỗ | **TĐ** | K | 1 |
| R09 | Suy hạn chốt từ hạn hủy nhà cung cấp | **TĐ** | K | 0,5 |
| R10a | API báo cáo lãi lỗ từng chuyến | **TĐ** | K | 0,75 |
| R10b | Giao diện báo cáo lãi lỗ | PĐ | TB | 0,75 |
| R11 | Migration thù lao và tạm ứng | TA | D | 0,5 |
| R12 | Luồng tạm ứng và quyết toán hướng dẫn viên | ĐTĐ | TB | 2 |
| R13 | Giao diện hướng dẫn viên nhập chi phí | PĐ | TB | 1,5 |
| R14 | Công nợ nhà cung cấp | ĐTĐ | TB | 1,25 |
| R15 | Kiểm thử cung ứng và giá vốn | TA | TB | 1,25 |
| S01 | Migration ghi nhận doanh thu | TA | D | 0,25 |
| S02 | Ghi nhận doanh thu khi chuyến kết thúc | **TĐ** | K | 1 |
| S03a | Sửa API bảng điều khiển thành ba chỉ tiêu | **TĐ** | K | 0,5 |
| S03b | Giao diện bảng điều khiển ba chỉ tiêu | PĐ | TB | 0,75 |
| S04 | Phí hủy ghi nhận là thu nhập khác | **TĐ** | K | 0,75 |
| S05 | Migration đối soát | ĐTĐ | D | 0,25 |
| S06 | Lệnh đối soát cổng thanh toán | **TĐ** | K | 1,5 |
| S07a | API đối soát | ĐTĐ | TB | 0,5 |
| S07b | Giao diện đối soát | PĐ | TB | 0,75 |
| S08a | API báo cáo dòng tiền | ĐTĐ | TB | 0,5 |
| S08b | Giao diện báo cáo dòng tiền | PĐ | TB | 0,75 |
| S09a | API báo cáo lấp đầy, ghế chết, tỷ lệ hủy | ĐTĐ | TB | 0,75 |
| S09b | Giao diện các báo cáo trên | PĐ | TB | 0,75 |
| S10 | Kiểm thử ghi nhận doanh thu và đối soát | ĐTĐ | TB | 1 |

## 5. Lịch Mốc 1

Giả định mỗi người làm được khoảng một ngày công mỗi ngày lịch. Mốc 1 khoảng ba tuần.

### Tuần 1

| Người | Việc | Ghi chú |
| --- | --- | --- |
| TA | A01, A03 rồi A05, A07, A08 | **Làm A01 và A03 ngay ngày đầu**, cả nhóm chờ |
| TĐ | A02, A04 sau khi có A01 và A03, rồi A09 | Đường găng |
| ĐTĐ | B01, B02, B04, F01, G01 rồi B05 | Toàn migration độc lập, làm được ngay |
| PĐ | A11, A12 dựng khung với dữ liệu giả, rồi B06, B07 | Dựng giao diện trước, ghép API sau |

### Tuần 2

| Người | Việc |
| --- | --- |
| TA | A06, C01, C03, C05, E01, E02 |
| TĐ | B03, C02, D01, D02, E03 |
| ĐTĐ | F02, F04, G02, G03, E04a |
| PĐ | C04, F05, F06, G04, E04b |

### Tuần 3

| Người | Việc |
| --- | --- |
| TA | D03, H01, H02, H03, H05, H06 |
| TĐ | H04, H08, A13, B08, C06, D04 |
| ĐTĐ | G05a, G06, F07, A10, H13a |
| PĐ | G05b, H07, H11 |

### Tuần 4 nếu cần

Dồn nốt H09, H10, H12, H13b, H14 và các kiểm thử còn thiếu.

**Rủi ro lớn nhất của tuần 1:** cả nhóm chờ A01 và A03. TA phải xong trong ngày đầu tiên,
không được nhận việc gì khác trước đó.

## 6. Quy tắc phối hợp

### 6.1 Nhánh và commit

- Mỗi nhóm công việc một nhánh: `feat/a-schedule-lifecycle`, `feat/h-attendance`.
- Nhánh gốc là `dev`, không ai đẩy thẳng lên `dev`.
- Conventional Commits, thông điệp tiếng Anh, mỗi commit một chủ đề.
- Trước khi mở yêu cầu gộp phải chạy được `php artisan test` và `npm run build`.

### 6.2 Đặt tên migration để không đụng nhau

Đây là rủi ro thực tế khi bốn người cùng tạo bảng. Laravel sắp xếp migration theo tên tệp,
nên thứ tự phải tôn trọng phụ thuộc khóa ngoại. Quy ước chia dải giờ theo nhóm công việc,
không theo người:

| Nhóm | Dải giờ trong tên tệp |
| --- | --- |
| A | `2026_08_20_0900xx` |
| B | `2026_08_20_1000xx` |
| C | `2026_08_20_1100xx` |
| E | `2026_08_20_1200xx` |
| F | `2026_08_20_1300xx` |
| G | `2026_08_20_1400xx` |
| H | `2026_08_20_1500xx` |
| Mốc 2 | `2026_09_xx` |
| Mốc 3 | `2026_10_xx` |

Nhóm nào tạo bảng mà nhóm khác trỏ khóa ngoại tới thì phải có dải giờ sớm hơn.

### 6.3 Giao diện không chờ máy chủ

PĐ là người dễ bị chặn nhất vì phụ thuộc API của ba người. Cách làm:

1. Trước khi viết API, người phụ trách máy chủ dán **hình dạng dữ liệu trả về** vào phần mô tả
   công việc, dạng JSON mẫu.
2. PĐ dựng giao diện với dữ liệu giả đúng hình dạng đó.
3. Khi API xong thì chỉ đổi nguồn dữ liệu, không sửa giao diện.

Không thống nhất hình dạng dữ liệu trước là nguyên nhân số một khiến giao diện phải làm lại.

### 6.4 Duyệt mã

TĐ duyệt bắt buộc với mọi yêu cầu gộp chạm tới: số chỗ, tiền, trạng thái đơn hoặc chuyến,
migration có chuyển dữ liệu. Các phần còn lại hai người backend duyệt chéo nhau.

### 6.5 Chạy thử trên máy có cơ sở dữ liệu thật

Máy phát triển dùng SQLite, máy chạy thử dùng MySQL. Mọi migration có chuyển dữ liệu, tức là
A02, H04, M02, N02, phải chạy thử trên MySQL trước khi gộp. Bốn migration này đều thuộc TĐ.

## 7. Rủi ro và cách giảm

| Rủi ro | Ảnh hưởng | Cách giảm |
| --- | --- | --- |
| Cả nhóm chờ A01 và A03 ở tuần 1 | Mất một đến hai ngày của ba người | TA làm ngay ngày đầu, ĐTĐ có sẵn năm migration độc lập để làm song song |
| PĐ là người duy nhất làm giao diện, tổng 35,25 ngày | Giao diện thành nút thắt ở Mốc 3 | TĐ hỗ trợ giao diện khi rảnh, ưu tiên các màn hình quản trị đơn giản |
| TĐ vừa làm phần khó vừa duyệt mã cho ba người | Duyệt mã bị dồn, gộp chậm | Đặt khung giờ duyệt cố định trong ngày, không duyệt rải rác |
| Migration đụng nhau khi gộp | Phải sửa tên tệp và chạy lại | Theo quy ước dải giờ ở mục 6.2 |
| Bốn migration chuyển dữ liệu chạy sai trên MySQL | Hỏng dữ liệu thử | Chạy thử trước khi gộp, luôn viết được phần `down` |
| Ước lượng lệch | Trễ toàn bộ | Chốt lại tiến độ cuối mỗi tuần, cắt phạm vi theo mục 8 chứ không kéo dài |

## 8. Nếu không kịp thì cắt gì

Thứ tự cắt, từ cắt trước tới cắt sau:

1. Mốc 3 toàn bộ, trừ S03 sửa bảng điều khiển ba chỉ tiêu. Riêng S03 nên giữ vì tốn ít
   mà sửa được một chỗ đang sai.
2. Nhóm P đoàn và Q hợp đồng của Mốc 3.
3. Nhóm L ghép chuyến của Mốc 2.
4. Nhóm M từ M05 trở đi, giữ lại M01 tới M04 và M07, M08 vì liên quan câu hỏi thay hướng dẫn viên.

Không được cắt: toàn bộ Mốc 1. Đây là phần hội đồng đã hỏi trực tiếp và sẽ hỏi lại.

Phần bị cắt trình bày bằng tài liệu thiết kế kèm mô hình dữ liệu, và nêu rõ là lựa chọn có
cân nhắc theo [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md).
