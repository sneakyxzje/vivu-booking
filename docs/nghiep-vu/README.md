# Tài liệu nghiệp vụ Vivu Booking

Bộ tài liệu này mô tả đầy đủ nghiệp vụ của hệ thống đặt tour Vivu Booking: các tác nhân,
vòng đời dữ liệu, luồng xử lý chính, luồng thay thế, các tình huống ngoại lệ (edge case)
và chính sách kinh doanh đi kèm.

Tài liệu được viết lại sau buổi bảo vệ thử, nhằm bổ sung tầng **điều hành tour** vốn còn
thiếu so với tầng **bán tour** đã có.

## Mục lục

| Tài liệu | Nội dung |
| --- | --- |
| [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md) | Hệ thống làm gì, cố ý không làm gì và vì sao; giả định; mức tin cậy của các số liệu |
| [01 - Tác nhân và vòng đời](01-tac-nhan-va-vong-doi.md) | Vai trò, ma trận quyền, máy trạng thái của Tour / Chuyến khởi hành / Đơn đặt tour |
| [02 - Luồng đặt tour và thanh toán](02-luong-dat-tour.md) | Đặt chỗ, giữ chỗ, thanh toán, đặt cọc, sửa đơn, chuyển chuyến, chuyển tour |
| [03 - Luồng hủy và hoàn tiền](03-luong-huy-va-hoan-tien.md) | Ai được hủy, mốc thời gian, chính sách hoàn tiền, xử lý slot khi hủy sát giờ |
| [04 - Luồng điều hành chuyến đi](04-luong-dieu-hanh.md) | Chốt chuyến, ghép chuyến, hủy chuyến, phân công và thay hướng dẫn viên, điểm danh, sự cố phát sinh |
| [05 - Đoàn, hợp đồng và hồ sơ](05-doan-hop-dong-ho-so.md) | Booking theo đoàn, báo giá, hợp đồng du lịch, danh sách đoàn, danh sách phòng |
| [06 - Đối chiếu feedback hội đồng](06-doi-chieu-feedback.md) | 18 điểm hội đồng nêu, hiện trạng, phương án, mức ưu tiên |
| [07 - Thiết kế dữ liệu bổ sung](07-thiet-ke-du-lieu.md) | Bảng mới, cột mới, ràng buộc, chỉ mục, thứ tự migration |
| [08 - Danh mục edge case](08-danh-muc-edge-case.md) | Bảng tổng hợp toàn bộ tình huống ngoại lệ và cách xử lý |
| [09 - Cung ứng và giá vốn](09-cung-ung-va-gia-von.md) | Nhà cung cấp, tồn chỗ giữ được, dự toán chi phí, điểm hòa vốn, lãi lỗ từng chuyến, tạm ứng hướng dẫn viên |
| [10 - Tài chính và kế toán](10-tai-chinh-va-ke-toan.md) | Ghi nhận doanh thu, đối soát, dòng tiền, bộ báo cáo tối thiểu |
| [11 - Backlog triển khai](11-backlog-trien-khai.md) | 168 công việc chia theo nhóm, ước lượng, phụ thuộc, đường găng |

## Tóm tắt hiện trạng

Hệ thống đang hoàn chỉnh ở nhóm chức năng bán hàng:

- Đặt tour cho cả khách vãng lai và khách đã đăng nhập, có giữ chỗ 10 phút.
- Chống bán vượt chỗ bằng khóa dòng bi quan trong giao dịch cơ sở dữ liệu.
- Thanh toán VNPay có kiểm tra chữ ký, nhật ký giao dịch, xử lý thanh toán về muộn.
- Quản trị viên can thiệp đơn: xác nhận thủ công, hủy đơn kèm lý do, gửi thư điện tử.
- Điểm danh theo từng chặng của lịch trình, có ảnh check-in.
- Tra cứu đơn cho khách vãng lai bằng mã tra cứu, lịch sử đơn cho khách đã đăng nhập.

Phần còn thiếu tập trung ở nhóm chức năng điều hành, tức là những gì diễn ra **sau khi
bán được tour**:

1. Chuyến khởi hành chưa có vòng đời riêng, nên không phân biệt được chuyến đang mở bán,
   đã chốt, đang chạy hay đã kết thúc. Đây là gốc rễ của phần lớn vấn đề hội đồng nêu.
2. Chưa có chính sách hủy và hoàn tiền theo mốc thời gian.
3. Chưa có khái niệm đặt cọc và sổ giao dịch tài chính của đơn hàng.
4. Điểm danh còn ở mức đơn hàng và theo ngày, chưa tới từng hành khách và từng điểm dừng.
5. Chưa có hồ sơ năng lực hướng dẫn viên, chưa kiểm tra trùng lịch, chưa hỗ trợ bàn giao giữa chừng.
6. Chưa có hợp đồng, danh sách đoàn, danh sách phòng.
7. Chưa có nhật ký thay đổi (audit log) cho các thao tác can thiệp vào đơn hàng.
8. Chưa có mảng cung ứng: nhà cung cấp, tồn chỗ giữ được, giá vốn. Đây là lý do `min_people`
   và "hoàn theo giá vốn" chưa có căn cứ tính toán.
9. Chưa phân biệt tiền thu và doanh thu. Bảng điều khiển đang cộng tiền đã thu và gọi đó là
   doanh thu, sai về nguyên tắc kế toán.

Ba mảng cố ý nằm ngoài phạm vi và không có kế hoạch triển khai: kênh phân phối qua đại lý,
hóa đơn điện tử, và tour nước ngoài. Lý do đầy đủ tại [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md).

## Nguyên tắc thiết kế xuyên suốt

Các nguyên tắc sau được áp dụng cho toàn bộ tài liệu, mọi luồng đều phải tuân thủ.

**Không xóa cứng dữ liệu vận hành.** Tour, chuyến khởi hành và đơn hàng chỉ được chuyển
trạng thái, không được xóa khỏi cơ sở dữ liệu khi đã phát sinh giao dịch. Lý do là dữ liệu
tài chính và trách nhiệm pháp lý phải truy vết được về sau.

**Mọi thay đổi tiền và chỗ đều phải ghi nhật ký.** Ai thao tác, thời điểm nào, giá trị
trước và sau, lý do. Không có ngoại lệ, kể cả thao tác của quản trị viên.

**Tách bạch người báo cáo, người duyệt và người thu tiền.** Hướng dẫn viên báo cáo sự cố
nhưng không được tự quyết mức phí. Điều hành duyệt phương án. Kế toán ghi nhận thu chi.

**Ràng buộc theo thời gian phải tính bằng giờ Việt Nam.** Hệ thống chạy múi giờ UTC, mọi
so sánh với mốc khởi hành, hạn chốt danh sách, hạn thanh toán đều quy đổi về `Asia/Ho_Chi_Minh`.

**Mọi thao tác đổi số chỗ phải nằm trong giao dịch có khóa dòng.** Khi thao tác chạm tới
hai chuyến khởi hành (chuyển chuyến, ghép chuyến), phải khóa theo thứ tự khóa chính tăng dần
để tránh khóa chết.

## Lộ trình triển khai đề xuất

Chia làm ba mốc, sắp theo tỷ lệ giữa mức độ hội đồng quan tâm và khối lượng công việc.

### Mốc 1 - Nền tảng điều hành

Đây là mốc bắt buộc, xử lý được 12 trên 18 điểm hội đồng nêu.

1. Vòng đời chuyến khởi hành: bổ sung `end_date`, `min_people`, `booking_deadline`, `status`.
2. Chính sách hủy theo mốc thời gian và quy tắc hoàn chỗ.
3. Ma trận quyền hủy, luồng yêu cầu hủy cho đơn đã thanh toán.
4. Nhật ký thay đổi đơn hàng.
5. Điểm danh tới từng hành khách, từng điểm dừng, có trạng thái và ghi chú bắt buộc.

### Mốc 2 - Vận hành chuyến đi

6. Sửa đơn có kiểm soát: số khách, thông tin hành khách, chênh lệch tiền.
7. Chuyển chuyến và chuyển tour.
8. Hủy chuyến có phương án bắt buộc cho từng đơn đã thanh toán.
9. Ghép chuyến.
10. Hồ sơ năng lực hướng dẫn viên, kiểm tra trùng lịch, bàn giao giữa chừng.

### Mốc 3 - Tài chính và hồ sơ

11. Đặt cọc và sổ giao dịch đơn hàng.
12. Hoàn tiền có đối soát.
13. Sự cố và chi phí phát sinh.
14. Booking theo đoàn, báo giá, bậc giá theo số lượng.
15. Hợp đồng, danh sách đoàn, danh sách phòng.
16. Nhà cung cấp, giá vốn, điểm hòa vốn, lãi lỗ từng chuyến.
17. Ghi nhận doanh thu đúng nguyên tắc, đối soát, báo cáo dòng tiền.

### Khối lượng thực tế

Toàn bộ ba mốc là **163 công việc, khoảng 152 ngày công**, tương đương bảy tháng làm việc
toàn thời gian của một người. Không thể hoàn thành trước buổi bảo vệ.

Khuyến nghị: làm trọn **Mốc 1** (46,5 ngày công), đủ để trả lời 12 trên 18 câu hỏi của hội đồng
bằng mã chạy thật. Mốc 2 và 3 trình bày bằng tài liệu thiết kế kèm mô hình dữ liệu, và nêu rõ
đó là lựa chọn có cân nhắc theo [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md).

Chi tiết từng công việc, phụ thuộc và đường găng xem [11 - Backlog triển khai](11-backlog-trien-khai.md).
Đối chiếu với feedback hội đồng xem [06 - Đối chiếu feedback hội đồng](06-doi-chieu-feedback.md).
