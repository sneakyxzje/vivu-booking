# 01 - Tác nhân và vòng đời dữ liệu

## 1. Tác nhân trong hệ thống

| Tác nhân | Mô tả | Đăng nhập |
| --- | --- | --- |
| Khách vãng lai | Đặt tour không cần tài khoản, tra cứu đơn bằng mã tra cứu | Không |
| Khách hàng | Có tài khoản, xem lịch sử đơn, đánh giá tour | Có |
| Đại diện đoàn | Người đứng tên cho một đơn nhiều khách, có thể là cá nhân hoặc doanh nghiệp | Tùy |
| Hướng dẫn viên | Dẫn đoàn, điểm danh, báo cáo sự cố | Có |
| Điều hành | Phân công hướng dẫn viên, chốt chuyến, duyệt hủy, duyệt phụ thu | Có |
| Kế toán | Ghi nhận thu chi, thực hiện hoàn tiền, đối soát | Có |
| Quản trị viên | Toàn quyền, quản lý tour, người dùng, danh mục, mã giảm giá | Có |
| Hệ thống | Tác vụ nền: nhả chỗ quá hạn, nhắc thanh toán, cảnh báo chuyến thiếu khách | - |

Hiện tại mã nguồn chỉ có ba vai trò `customer`, `guide`, `admin`. Vai trò Điều hành và
Kế toán được gộp vào `admin`. Đề xuất tách khi triển khai Mốc 3, vì nguyên tắc kiểm soát nội bộ
yêu cầu người duyệt phương án hoàn tiền và người thực hiện chi tiền không được là một.

## 2. Ma trận quyền theo thao tác

Ký hiệu: `X` là được phép, `D` là được phép nhưng cần duyệt, `-` là không được phép.

| Thao tác | Khách vãng lai | Khách hàng | Đại diện đoàn | Hướng dẫn viên | Điều hành | Kế toán | Quản trị |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Tạo đơn đặt tour | X | X | X | - | X | - | X |
| Hủy đơn chưa thanh toán | X | X | X | - | X | - | X |
| Hủy đơn đã thanh toán | D | D | D | - | X | - | X |
| Sửa số lượng khách | D | D | D | - | X | - | X |
| Sửa thông tin hành khách | X | X | X | - | X | - | X |
| Chuyển chuyến, chuyển tour | D | D | D | - | X | - | X |
| Xác nhận đơn thủ công | - | - | - | X | X | - | X |
| Điểm danh | - | - | - | X | - | - | X |
| Báo cáo sự cố | - | - | - | X | X | - | X |
| Duyệt chi phí phát sinh | - | - | - | - | X | - | X |
| Ghi nhận thu tiền | - | - | - | - | - | X | X |
| Thực hiện hoàn tiền | - | - | - | - | - | X | X |
| Chốt chuyến, hủy chuyến, ghép chuyến | - | - | - | - | X | - | X |
| Phân công, thay hướng dẫn viên | - | - | - | - | X | - | X |
| Xuất hợp đồng, danh sách đoàn | - | - | - | X (chỉ xem) | X | X | X |

Ghi chú về mức `D` của khách hàng: khách không tự thực hiện được mà tạo một **yêu cầu**
(`booking_change_requests`). Điều hành duyệt hoặc từ chối. Lý do là các thao tác này chạm tới
tiền đã thu và chỗ đã báo cho nhà cung cấp.

## 3. Vòng đời Tour

Tour là sản phẩm, không phải chuyến đi cụ thể. Một tour có nhiều chuyến khởi hành.

```
draft ──► active ──► inactive
             │           │
             └───────────┘   (bật/tắt bán, không mất dữ liệu)
```

| Trạng thái | Ý nghĩa | Hiển thị công khai | Cho đặt |
| --- | --- | --- | --- |
| `draft` | Đang soạn, chưa đủ thông tin | Không | Không |
| `active` | Đang mở bán | Có | Có, nếu còn chuyến hợp lệ |
| `inactive` | Ngừng bán | Không | Không |

Quy tắc:

- Không cho phép xóa cứng tour nếu tồn tại đơn hàng bất kỳ. Chỉ chuyển sang `inactive`.
- Xóa cứng chỉ hợp lệ khi tour chưa từng có chuyến khởi hành nào phát sinh đơn.
- Chuyển `active` sang `inactive` không ảnh hưởng tới chuyến đã chốt và đơn đã thanh toán.
  Các chuyến đó vẫn phải chạy đúng cam kết, chỉ là không nhận khách mới.
- Sửa giá tour không hồi tố. Đơn đã tạo giữ nguyên giá tại thời điểm đặt. Giá đã lưu vào
  `bookings.total_amount` nên đã đúng nguyên tắc này, cần ghi rõ trong tài liệu để tránh
  hiểu nhầm khi bảo vệ.

## 4. Vòng đời Chuyến khởi hành

Đây là bổ sung quan trọng nhất. Hiện `tour_schedules` chỉ có cột `status` dùng lỏng lẻo,
chưa mô tả được chuyến đang ở giai đoạn nào.

```
                       đủ khách tối thiểu
                       hoặc điều hành chốt
open ──────────────────────────────────► confirmed ──► in_progress ──► completed
  │                                          │              │
  │ hết chỗ / tới hạn chốt                   │              │
  ▼                                          │              │
closed ──────────────────────────────────────┘              │
  │                                                         │
  │ không đủ khách tối thiểu                                 │
  ▼                                                         ▼
cancelled                                              completed
(hủy chuyến, phải xử lý toàn bộ đơn đã thu tiền)   (kể cả khi bị rút ngắn do sự cố)
```

| Trạng thái | Ý nghĩa | Cho đặt mới | Cho khách hủy | Cho điểm danh |
| --- | --- | --- | --- | --- |
| `open` | Đang mở bán | Có | Có, theo chính sách | Không |
| `closed` | Đã đóng bán, chưa chốt chạy | Không | Có, theo chính sách | Không |
| `confirmed` | Đã chốt chắc chắn khởi hành | Không, trừ khi điều hành mở lại | Có, theo chính sách | Không |
| `in_progress` | Đoàn đang đi | Không | Không | Có |
| `completed` | Đã kết thúc | Không | Không | Không, chỉ xem |
| `cancelled` | Hủy chuyến | Không | Không cần, hệ thống đã hủy | Không |

Điều kiện chuyển trạng thái:

| Từ | Sang | Điều kiện | Ai kích hoạt |
| --- | --- | --- | --- |
| `open` | `closed` | `booked_people >= max_people` hoặc `now() >= booking_deadline` | Hệ thống |
| `open`, `closed` | `confirmed` | `paid_people >= min_people` | Hệ thống hoặc Điều hành |
| `open`, `closed` | `cancelled` | Không đủ khách tối thiểu tại hạn chốt, hoặc lý do bất khả kháng | Điều hành, hai bước xác nhận |
| `confirmed` | `in_progress` | `now() >= start_date` hoặc hướng dẫn viên bấm bắt đầu chuyến | Hướng dẫn viên hoặc hệ thống |
| `confirmed` | `cancelled` | Bất khả kháng, phải có phương án cho từng đơn đã thu tiền | Điều hành |
| `in_progress` | `completed` | `now() > end_date` hoặc hướng dẫn viên bấm kết thúc | Hướng dẫn viên hoặc hệ thống |
| `in_progress` | `cancelled` | Không cho phép. Chuyến đã khởi hành thì kết thúc bằng `completed`, sự cố ghi vào nhật ký sự cố | - |

Điểm cần nhấn khi bảo vệ: **không có đường từ `in_progress` về `cancelled`**. Một chuyến đã
khởi hành thì chi phí đã phát sinh, nhà cung cấp đã phục vụ, nên không thể coi như chưa từng
xảy ra. Nếu phải dừng giữa chừng thì vẫn là `completed` kèm bản ghi sự cố và phần hoàn lại
dịch vụ chưa sử dụng.

Trường dữ liệu cần bổ sung cho `tour_schedules`:

| Trường | Kiểu | Mục đích |
| --- | --- | --- |
| `end_date` | datetime | Xác định khoảng thời gian chuyến, phục vụ kiểm tra trùng lịch hướng dẫn viên và xác định chuyến đang chạy |
| `min_people` | int | Số khách tối thiểu để chuyến chạy có lãi |
| `booking_deadline` | datetime | Hạn chốt danh sách, mặc định trước khởi hành 3 ngày |
| `status` | enum | Sáu trạng thái ở trên |
| `cancelled_reason` | text | Lý do hủy chuyến |
| `cancelled_at`, `cancelled_by` | datetime, FK | Truy vết |
| `merged_into_schedule_id` | FK nullable | Chuyến này đã bị ghép vào chuyến nào |
| `confirmed_at` | datetime | Thời điểm chốt chuyến |

## 5. Vòng đời Đơn đặt tour

Trạng thái hiện tại trong mã nguồn: `pending`, `paid`, `confirmed`, `cancelled`.
Đề xuất bổ sung `deposit_paid`, `transferred`, `completed`, `no_show`.

```
                    thanh toán đủ
pending ─────────────────────────────► paid ──────► confirmed ──► completed
   │                                     ▲             │              │
   │ thanh toán cọc                      │             │              │
   ├──────────► deposit_paid ────────────┘             │              │
   │                  │  thanh toán nốt                │              │
   │                  │                                │              │
   │ quá hạn giữ chỗ  │ quá hạn thanh toán phần còn lại │              │
   ▼                  ▼                                ▼              ▼
cancelled ◄───────────────────────────────────────────┘         no_show
   ▲                                                              (khách không xuất hiện)
   │ chuyển sang chuyến khác
transferred
```

| Trạng thái | Ý nghĩa | Chiếm chỗ | Khách hủy được |
| --- | --- | --- | --- |
| `pending` | Đã tạo, chưa trả tiền, đang giữ chỗ tạm | Có, tới `expires_at` | Có, tự động |
| `deposit_paid` | Đã đóng cọc | Có | Có, theo chính sách hoàn |
| `paid` | Đã thanh toán đủ | Có | Có, theo chính sách hoàn |
| `confirmed` | Đã xác nhận vào danh sách đoàn | Có | Có, theo chính sách hoàn |
| `completed` | Đã đi xong | Không, chuyến đã kết thúc | Không |
| `no_show` | Đã thanh toán nhưng không có mặt | Có, chỗ đã mất | Không |
| `cancelled` | Đã hủy | Không, trừ trường hợp hủy sát giờ | - |
| `transferred` | Đã chuyển sang chuyến hoặc tour khác | Không, chỗ đã chuyển | - |

Quy tắc bất biến của đơn hàng:

1. Trạng thái chỉ đi tiến, trừ hai ngoại lệ có kiểm soát: `paid` về `pending` khi hoàn tiền
   một phần do giảm số khách, và mở lại đơn đã hủy nhầm trong vòng 24 giờ bởi quản trị viên
   kèm lý do.
2. Đơn ở `cancelled` hoặc `transferred` không được nhận thanh toán mới. Nếu cổng thanh toán
   trả về thành công cho đơn này, hệ thống ghi nhật ký cảnh báo và đưa vào hàng chờ hoàn tiền
   thủ công. Logic này đã có trong `BookingController::vnpayReturn`.
3. Mọi thay đổi trạng thái ghi vào `booking_audit_logs`.

## 6. Quan hệ giữa ba vòng đời

Ba vòng đời ràng buộc lẫn nhau. Bảng dưới liệt kê các ràng buộc chéo cần kiểm tra trong mã.

| Ràng buộc | Kiểm tra tại |
| --- | --- |
| Không tạo đơn cho chuyến không ở `open` | Tạo đơn |
| Không tạo đơn cho tour không `active` | Tạo đơn |
| Không tạo đơn khi đã qua `booking_deadline` | Tạo đơn |
| Không hủy đơn khi chuyến ở `in_progress` hoặc `completed` | Hủy đơn |
| Không điểm danh khi chuyến chưa `in_progress` | Điểm danh |
| Không chuyển chuyến khi chuyến đích không `open` hoặc không đủ chỗ | Chuyển chuyến |
| Không hủy chuyến khi còn đơn đã thu tiền chưa có phương án | Hủy chuyến |
| Không đặt `completed` cho chuyến còn hành khách chưa điểm danh chặng cuối | Kết thúc chuyến, cảnh báo mềm |
| Không gán hướng dẫn viên trùng khoảng thời gian với chuyến khác | Phân công |
