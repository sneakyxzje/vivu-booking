# Hoàn hủy theo hạn chốt danh sách

Chốt ngày 29/09/2026. Quy tắc này thay bảng phí theo các mốc 20/15/12/8/2 ngày.

| Trường hợp | Tiền hoàn còn phải trả |
|---|---|
| Khách gửi yêu cầu trước hạn chốt | Toàn bộ số đã thu còn lại |
| Khách gửi yêu cầu từ hạn chốt đến trước khởi hành | Số đã thu còn lại trừ cọc 50% giá trị đơn, tối thiểu 0 |
| Đến hạn chốt chưa trả đủ | Tự hủy, giữ cọc; hoàn phần đã trả vượt cọc |
| Công ty hủy; từ chối/hết hạn phản hồi ghép khi chuyến nguồn bị hủy | Hoàn đủ số đã thu còn lại |
| Chuyến đã khởi hành hoặc kết thúc | Chặn thao tác hủy |

- Hạn lấy từ `booking_deadline` của chuyến hiện tại. Nếu thiếu, dùng hạn mặc định trước khởi hành theo cấu hình (hiện 3 ngày). Đúng thời điểm chốt đã thuộc nhánh giữ cọc. Đồng hồ demo áp dụng như các luồng khác.
- Phí tối đa 50% giá trị đơn khi khách hủy sau hạn; không tính phần trăm trên số đã cọc và không thu thêm khi tiền đã thu thấp hơn phí. Ví dụ đơn 10 triệu, đã trả 7 triệu: sau hạn giữ 5 triệu, hoàn 2 triệu.
- Đơn chưa thanh toán được hủy trực tiếp. Đơn đã thanh toán vẫn gửi yêu cầu để điều hành duyệt. Phí chốt tại lúc gửi; tiền thực nhận cập nhật theo sổ giao dịch khi xử lý.
- Nếu yêu cầu trước hạn còn chờ khi tác vụ hủy đơn thiếu tiền chạy, hệ thống hủy đơn, đóng yêu cầu và giữ quyền hoàn đủ. Yêu cầu đã rút hoặc bị từ chối không có quyền này. Hủy sau hạn vẫn giữ chỗ đã cam kết theo quy tắc chỗ hiện có; không tự mở lại bán.
- Yêu cầu cũ còn chờ tính theo luật mới tại thời điểm gửi. Đơn đã hủy, yêu cầu đã xử lý và khoản hoàn đã ghi nhận không bị tính lại.
- Ghi nhận khoản cần hoàn không đồng nghĩa đã chuyển tiền. Bộ phận điều hành ghi giao dịch hoàn riêng; các khoản hoàn trước đó không bị trừ hai lần.
- API chính sách, trang FAQ, màn xem trước/duyệt hủy, hợp đồng và chatbot đều dùng quy tắc hạn chốt. Bảng phí cũ trong DB chỉ còn là dữ liệu lịch sử, không quyết định lần hủy mới.

Không cần migration hoặc chạy lại seed để áp dụng. Không sửa số tiền hay trạng thái của đơn đã xử lý trong DB khi cập nhật code.
