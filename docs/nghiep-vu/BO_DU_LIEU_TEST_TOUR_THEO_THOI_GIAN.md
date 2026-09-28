# Bộ dữ liệu tự tạo tour để thử theo thời gian

Để demo A–Z trong một buổi bằng nút chuyển mốc, dùng [Demo vòng đời bằng nút](DEMO_VONG_DOI_BANG_NUT.md). Bộ dưới đây dành cho kiểm thử theo giờ thật, không bật trình diễn.

Áp dụng cho cấu hình đã kiểm tra ngày 20/09/2026, múi giờ Việt Nam (UTC+7): giữ chỗ 10 phút, cọc 50%, trả nốt trước khởi hành 10 ngày, nhắc trước hạn 7 ngày/2 ngày, hạn chốt mặc định trước khởi hành 3 ngày.

Tài liệu này chỉ là dữ liệu để nhập và checklist; chưa tạo tour, đơn hay thay đổi cấu hình. Các ký hiệu A1, B1… là nhãn để ghi lại ID thực sau khi tạo.

## 1. Chọn giờ bắt đầu

T0 là giờ bắt đầu buổi thử, chọn đủ xa để tạo tour và chuẩn bị tài khoản. Ví dụ T0 = **20/09/2026 18:30**. Nếu đã qua giờ này, chọn T0 mới và tính lại tất cả mốc tương đối. Để hoàn tất trong hôm nay, T0 phải cách nửa đêm ít nhất 2 giờ.

Không sửa ngày máy tính. Không sửa trạng thái hay ngày đi của đơn đã có khách để ép qua các bước. Bộ này dùng một chuyến ngắn kết thúc trong buổi thử và những chuyến tương lai có hạn thanh toán rơi vào buổi thử.

## 2. Thông tin hai tour

| Trường | Tour A: đặt đến kết thúc trong buổi thử | Tour B: cọc và các hạn tiền |
|---|---|---|
| Tên | TEST A - Tham quan nội thành 1 ngày | TEST B - Hà Nội - Hạ Long 3N2Đ |
| Số ngày / đêm | 1 / 0 | 3 / 2 |
| Điểm đi | Hà Nội | Hà Nội |
| Điểm đến | Hà Nội | Hạ Long |
| Điểm đón | Điểm tập trung thử nghiệm | Điểm tập trung thử nghiệm |
| Phương tiện | Xe du lịch | Xe du lịch |
| Giá người lớn | 1.000.000 đ | 1.000.000 đ |
| Giá trẻ em | 700.000 đ | 700.000 đ |
| Giá em bé | 0 đ | 0 đ |
| Mô tả | Dữ liệu nội bộ để thử luồng đặt, chốt, khởi hành và điểm danh | Dữ liệu nội bộ để thử cọc, trả nốt và thư nhắc |
| Danh mục / dịch vụ | Chọn mục đang có, phù hợp hồ sơ HDV | Chọn mục đang có, phù hợp hồ sơ HDV |

Không dùng mã giảm giá ở lượt kiểm tra đầu để dễ đối chiếu tiền. Dùng ảnh sẵn có của bạn nếu biểu mẫu yêu cầu ảnh.

## 3. Tour A — chuyến A1 chạy thật theo đồng hồ trong khoảng hai giờ

| Trường | Công thức | Ví dụ ngày 20/09/2026 |
|---|---|---|
| Hạn chốt đặt | T0 + 45 phút | 19:15 |
| Khởi hành | T0 + 60 phút | 19:30 |
| Tới điểm đến | T0 + 75 phút | 19:45 |
| Rời điểm đến | T0 + 105 phút | 20:15 |
| Kết thúc | T0 + 120 phút | 20:30 |
| Trạng thái khi tạo | open / Mở bán | Mở bán |
| Tối thiểu / tối đa | 2 / 8 ghế | 2 / 8 |
| HDV | Một HDV đang hoạt động và rảnh | Ghi ID người thực tế đã chọn |

**Nhập hạn chốt riêng 19:15.** Bỏ trống hoặc giữ mốc mặc định D−3 sẽ làm chuyến hôm nay đóng bán ngay. Chọn đúng giờ kết thúc 20:30, không giữ giờ về mặc định của form. Lịch ngắn này phục vụ kiểm thử.

Hạn trả nốt của chuyến đã qua, nên khách phải trả **100%** ngay từ đầu. Đây là kết quả đúng.

Lịch trình ngày 1:

| Thứ tự | Điểm dừng | Bắt buộc ảnh |
|---|---|---|
| 1 | Điểm tập trung - điểm danh đầu chuyến | Không |
| 2 | Điểm tham quan - điểm danh giữa chuyến | Không |
| 3 | Điểm trả khách - kết thúc hành trình | Không |

Sau khi đã thử điểm danh cơ bản, dùng một lượt thử khác để bật yêu cầu ảnh. Tải ảnh trong đúng ngày của điểm dừng; không cần GPS hoặc tọa độ điểm dừng.

Tạo các đơn sau bằng màn đặt tour và OTP; dùng khách/email nhận được OTP khác nhau để tránh quy tắc chống đặt trùng:

| Đơn | Khách | Số ghế | Tiền | Thao tác |
|---|---|---:|---:|---|
| A-PRESENT | 2 người lớn | 2 | 2.000.000 đ | Trả đủ; ghi có mặt ở điểm đầu |
| A-ABSENT | 1 người lớn | 1 | 1.000.000 đ | Trả đủ; ghi vắng ở điểm đầu, lý do ít nhất 10 ký tự |
| A-UNKNOWN | 1 người lớn | 1 | 1.000.000 đ | Trả đủ; để chưa điểm danh |
| A-EXPIRE | 1 người lớn | 1 tạm giữ | 1.000.000 đ | Đặt sớm trong buổi thử, không thanh toán, đợi quá 10 phút |

Sau khi A-EXPIRE hết hạn và được nhả chỗ: 4 ghế thuộc các đơn đã trả, 4 ghế trống. Điền đủ tên hành khách của ba đơn đã trả trước hạn chốt. HDV mở nhiệm vụ được phân công và xác nhận nhận việc.

Lịch thao tác (chạy lệnh tại thư mục `server`):

1. Trước 19:15: đặt, trả tiền, khai hành khách. Sau khi A-EXPIRE quá hạn, chạy `php artisan bookings:release-expired`.
2. Sau 19:15 và trước 19:30: chạy `php artisan schedules:close-expired`, sau đó `php artisan schedules:confirm-ready`. Chuyến đủ 4 ghế so với tối thiểu 2, kỳ vọng thành `confirmed`. Thử đặt mới và sửa hành khách bằng tài khoản khách: phải bị chặn.
3. Sau 19:30: chạy `php artisan schedules:advance-status`. Kỳ vọng chuyến `in_progress`. HDV vào `/guide/attendance/{ID_A1}` để ghi các trạng thái đã nêu. Thử hủy đơn và sửa danh sách khi đang chạy: phải bị chặn.
4. Sau 20:30, ví dụ 20:31: chạy `php artisan schedules:advance-status`, rồi `php artisan bookings:finalize-completed`.
5. Kỳ vọng chuyến `completed`; A-PRESENT → `completed`; A-ABSENT → `no_show`; A-UNKNOWN → `completed` vì chưa có đủ bằng chứng để kết luận khách vắng. A-EXPIRE giữ trạng thái đã hủy.
6. Chạy `php artisan bookings:check-seat-consistency`; kiểm tra số chỗ, sổ giao dịch, nhật ký và báo cáo điểm danh. Chạy lại các lệnh chốt không được tạo khoản thu mới hoặc chốt lặp gây sai dữ liệu.

Nếu không chạy lệnh chốt trước giờ đi, đừng kỳ vọng `advance-status` tự đưa một chuyến `open` qua toàn bộ vòng đời: đường tự chuyển sang đang chạy bắt đầu từ `confirmed`.

## 4. Tour B — năm chuyến để thử cọc và thời hạn

Mỗi chuyến: trạng thái mở bán, tối thiểu 2, tối đa 12 ghế, tới nơi sau giờ đi 3 giờ, rời điểm đến trước giờ kết thúc 3 giờ. Kết thúc lúc 18:00 ngày thứ ba; nhập đầy đủ từng giờ nếu biểu mẫu đang gợi ý khác.

| Mã | Khởi hành | Kết thúc | Hạn chốt nhập trên form | Hạn trả nốt tự tính | Mục đích |
|---|---|---|---|---|---|
| B1 | 10/10/2026 08:00 | 12/10/2026 18:00 | 07/10/2026 08:00 | 30/09/2026 08:00 | Cọc rồi trả nốt bình thường |
| B2 | 30/09/2026 19:30 | 02/10/2026 18:00 | 27/09/2026 19:30 | **20/09/2026 19:30** | Trước và sau hạn trả nốt ngay hôm nay |
| B3 | 27/09/2026 08:00 | 29/09/2026 18:00 | 24/09/2026 08:00 | 17/09/2026 08:00 | Đặt sát ngày phải thu đủ |
| B4 | 07/10/2026 08:00 | 09/10/2026 18:00 | 04/10/2026 08:00 | 27/09/2026 08:00 | Đang trong cửa sổ nhắc lần đầu |
| B5 | 02/10/2026 08:00 | 04/10/2026 18:00 | 29/09/2026 08:00 | 22/09/2026 08:00 | Đang trong cửa sổ cảnh báo cuối |

Bảng trên dành cho buổi 20/09/2026. Nếu T0 thay đổi trong ngày, chỉnh riêng B2: giờ khởi hành = T0 + 10 ngày + 60 phút; hạn chốt = khởi hành − 3 ngày. Nếu thử ngày khác, dịch cả bảng theo số ngày chênh lệch. Hạn trả nốt không phải ô nhập độc lập, nó suy từ ngày đi.

Lịch trình Tour B: ngày 1 Hà Nội → Hạ Long (điểm đón, nhận phòng); ngày 2 tham quan (hai điểm dừng); ngày 3 trả phòng → Hà Nội (điểm tập trung, trả khách). Dùng thứ tự checkpoint 1, 2 cho từng ngày. Chưa cần bắt buộc ảnh ở lượt đầu.

Không phân cùng một HDV cho B2 và B5 nếu khoảng phụ trách bị trùng ngày. Có thể để chuyến tiền chưa phân công, rồi phân công HDV rảnh khi kiểm tra vận hành.

Các đơn cần tự đặt:

| Chuyến / đơn | Dữ liệu | Kỳ vọng và cách thử |
|---|---|---|
| B1-FAMILY | 2 người lớn + 1 trẻ em + 1 em bé | 4 khách, 3 ghế, tổng 2.700.000 đ, cọc 1.350.000 đ; trả nốt 1.350.000 đ; công nợ về 0 |
| B2-PAID | 1 người lớn, đặt và cọc trước 19:30 | Thu cọc 500.000 đ; trả nốt 500.000 đ trước 19:30; sau hạn không bị nhắc nợ/hủy |
| B2-DUE | 1 người lớn, đặt và cọc trước 19:30 | Giữ dư nợ 500.000 đ; sau 19:30 đã quá hạn, nhưng chưa được tự hủy khi chưa đủ điều kiện cảnh báo và ân hạn |
| B2-NEW | 1 người lớn, chỉ đặt sau 19:30 | Phải trả đủ 1.000.000 đ ngay; không còn lựa chọn cọc 50% |
| B3-FULL | 1 người lớn | Phải trả đủ 1.000.000 đ ngay, vẫn đặt được vì chưa tới hạn chốt |
| B4-REMIND | 1 người lớn, cọc 500.000 đ | Chạy lệnh nhắc: nhận nhắc lần đầu; chạy lại không gửi trùng |
| B5-FINAL | 1 người lớn, cọc 500.000 đ | Chạy lệnh nhắc: nhận cảnh báo cuối; chưa quá hạn trả nốt nên chưa bị hủy |

Hành khách giả lập để nhập: Nguyễn Minh An (người lớn, sinh 15/03/1990), Trần Thu Hà (người lớn, sinh 20/08/1992), Nguyễn Gia Bảo (trẻ em, sinh 10/05/2018), Nguyễn An Nhiên (em bé, sinh 01/06/2025). Dùng email do bạn kiểm soát để nhận OTP; không dùng email giả để thử gửi thư. Điền giấy tờ giả lập riêng cho từng người nếu kiểm tra trường giấy tờ, không dùng giấy tờ thật của người khác.

Lệnh phục vụ nhóm tiền:

```powershell
php artisan bookings:send-balance-reminders
php artisan bookings:cancel-unpaid-balances --dry-run
```

Đọc danh sách dry-run trước khi chạy bản hủy thực. Các lệnh quét toàn bộ database, không chỉ một tour. Với email chạy qua hàng đợi, cần có `php artisan queue:work`. Khi kiểm tra chính xác từng bước, chạy lệnh nền thủ công; sau đó mới bật scheduler để kiểm tra tự động.

## 5. Biến thể edge case

| Case | Thay đổi dữ liệu / thao tác | Kỳ vọng |
|---|---|---|
| Không đủ khách | Tạo tour A khác cùng bộ giờ, min 6/max 8, chỉ bán 4 ghế | Lệnh chốt cảnh báo, không tự xác nhận chuyến |
| Ghế cuối | Một lịch tương lai riêng min 1/max 1; hai khách đặt gần đồng thời | Chỉ một đơn giữ được ghế |
| Người lớn / em bé | 1 người lớn + 2 em bé | Bị chặn; 1 người lớn + 1 em bé hợp lệ, chiếm 1 ghế |
| Hết giữ chỗ | Đơn pending, không thanh toán quá 10 phút | Hủy và trả ghế đúng một lần |
| OTP / đặt trùng | OTP sai/hết hạn; cùng khách đặt lại chuyến đang có đơn | Bị chặn theo quy tắc tương ứng |
| Hạn chốt | Thử đặt/sửa hành khách ngay trước và sau hạn A1 | Trước hạn cho phép khi đủ điều kiện; từ hạn trở đi chặn |
| Điểm danh sai người | HDV không thuộc A1 mở URL và gửi thao tác | Không được truy cập/ghi |
| Lý do vắng | Ghi vắng với lý do dưới 10 ký tự rồi sửa đủ dài | Lần đầu bị chặn, lần sau ghi được |
| Sửa điểm danh | Có mặt → vắng → có mặt khi chuyến đang chạy | Lưu lịch sử thay đổi |
| Thiếu ảnh | Lượt thử có checkpoint bắt buộc ảnh, hoàn tất khi chưa có ảnh | Bị chặn khi hoàn tất checkpoint |
| Ngày tương lai | Một tour nhiều ngày đang chạy, tick checkpoint của ngày mai | Bị chặn; không chứng minh case này bằng tour A một ngày |
| Thu quá | Đơn còn nợ 500.000 đ, nhập thu 600.000 đ | Bị chặn |
| Hủy chuyến | Một bản sao tour A có đơn đã trả; hủy trước giờ đi | Xử lý phương án cho đơn và nghĩa vụ hoàn; không chỉ đổi trạng thái chuyến |
| Callback lặp / tiền về muộn | Cần bài kiểm API/gateway, không chỉ dữ liệu form | Không thu lặp; xử lý tiền về muộn đúng với khả năng giữ lại chỗ |

## 6. Các tình huống không thể tạo lịch sử chỉ bằng đặt mới hôm nay

- Tự hủy vì nợ sau khi đã nhận cảnh báo cuối từ ít nhất 2 ngày trước.
- Điểm danh bù muộn cho những ngày trước đó.
- Cùng một đơn mới cọc, đi qua nhiều ngày và kết thúc tour 3N2Đ ngay trong buổi thử.

Các tình huống này cần bộ fixture có lịch sử thời gian hoặc test tự động điều khiển thời gian. Đặt mới một chuyến đã quá hạn trả nốt sẽ thu đủ ngay, không sinh ra được đơn cọc quá hạn cần thử. Không đổi cấu hình toàn hệ thống thành 0 ngày để thay thế các case này.

## 7. Phiếu ghi kết quả

| Case | ID tour / chuyến / đơn | Trước thao tác | Sau thao tác | Ghế | Đã thu / còn nợ / phải hoàn | Nhật ký / thư | Đạt / lỗi |
|---|---|---|---|---|---|---|---|
| | | | | | | | |

Kiểm tra sổ tiền và số ghế cùng trạng thái. Đơn `confirmed` có thể mới cọc; `paid_at` chỉ đóng khi đã thu đủ. Chuyến `completed` không tự chứng minh công nợ đã hết.
