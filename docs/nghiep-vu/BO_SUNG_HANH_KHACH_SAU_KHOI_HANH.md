# Bổ sung hành khách còn thiếu

## Quy tắc

- Thiếu thông tin không tự hủy đơn đã thanh toán đủ và không tự giảm số suất đã mua.
- Khách tự khai trước hạn chốt. Sau hạn chốt, khách liên hệ điều hành; nếu đã khởi hành thì báo HDV xác nhận đúng đơn và chuyển thông tin cho điều hành.
- Điều hành có thao tác riêng để bổ sung suất còn thiếu của đơn confirmed. Sau hạn chốt phải thanh toán đủ. Không bổ sung cho chuyến đã hủy/kết thúc hoặc đơn không còn hiệu lực.
- Kiểm tổng số khách, cơ cấu người lớn/trẻ em/em bé của đơn lẻ, tuổi theo ngày khởi hành, giấy tờ trùng và định dạng dữ liệu. Đơn đoàn theo hạn mức tổng đã mua.
- Chỉ tạo dòng hành khách mới. Không sửa/xóa dòng cũ, không đổi số chỗ, giá tiền hoặc dữ liệu điểm danh. Đường thay toàn bộ danh sách vẫn khóa khi chuyến đã khởi hành.
- Lưu người thao tác, thời điểm, người báo, lý do, ID hành khách và bản chụp thông tin cho từng đợt bổ sung. Gửi lại cùng mã yêu cầu không tạo thêm người.
- HDV nhận thông báo trong ứng dụng; danh sách được cập nhật khi trở lại màn hình điểm danh và mỗi 30 giây. Các lựa chọn chưa lưu được giữ nguyên. Không tạo điểm danh tự động, vẫn chỉ ghi được ngày hiện tại.

## Thao tác điều hành

1. Vào **Quản lý đơn → Chi tiết đơn → Hành khách**, hoặc **Quản lý chuyến → Danh sách khách → mở đơn**.
2. Chọn **Bổ sung khách còn thiếu**, điền hành khách, người báo và lý do.
3. Lưu rồi chọn **Tải bản bổ sung**. Tệp CSV mở được bằng Excel, chứa riêng những hành khách của đợt đó.
4. Điều hành tự gửi cho các nhà cung cấp liên quan. Sau khi đã gửi, chọn **Xác nhận đã gửi**, ghi rõ các bên đã nhận và kênh gửi/ghi chú.

Tải tệp không đồng nghĩa đã gửi. Hệ thống không tự gửi email cho bên thứ ba và không tự đánh dấu đã nhận. Mỗi đợt bổ sung có trạng thái gửi riêng; xác nhận gửi không ghi đè lịch sử trước đó. Có thể ghi nhận việc gửi sau khi chuyến kết thúc, nhưng không thêm hành khách mới lúc đó.

## Triển khai và kiểm tra

- Migration: `2026_09_28_000003_create_booking_passenger_supplements`.
- `PassengerSupplementTest`: kiểm tra 9/10 khách, chặn 11/10, sai loại/tuổi/giấy tờ, quyền truy cập, chuyến/đơn hết hiệu lực, gửi lại yêu cầu, bảo toàn điểm danh cũ, điểm danh ngày hiện tại, xuất tệp và xác nhận gửi.
- Không chạy thử luồng gửi thư ngoài đời hoặc ghi vào đơn thật để kiểm thử. Test API chạy SQLite cô lập. Cần kiểm tra thao tác giao diện trên trình duyệt khi môi trường khả dụng.
