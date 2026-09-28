# Vòng đời chuyến và khả năng nhận đặt — cập nhật 21/09/2026

Vòng đời còn năm trạng thái: `open` (Chờ chốt), `confirmed` (Đã chốt chạy),
`in_progress`, `completed`, `cancelled`. Không còn `closed` hoặc nút đóng/mở bán.

- Chuyến chờ chốt chỉ nhận đặt khi còn chỗ và chưa tới hạn đặt, kể cả hạn mặc định.
- Hết chỗ hoặc hết hạn không tạo thêm trạng thái. Giao diện hiện lý do bên cạnh “Chờ chốt”.
- Đến hạn, tác vụ `schedules:confirm-ready` chốt chuyến đủ khách đã thanh toán;
  chuyến thiếu khách tiếp tục chờ điều hành gia hạn, ghép hoặc hủy.
- Gia hạn chuyến chưa chốt hoặc trả lại chỗ trước hạn cho phép nhận đặt tiếp.
  Gia hạn không trả lại các ghế đã giữ do khách hủy sau hạn chốt.
- Chuyến đã chốt vẫn không nhận khách mới theo quy tắc hiện tại.

Migration `2026_09_21_000001_remove_closed_schedule_status` chuyển dữ liệu `closed`
thành `open`, giữ nguyên số chỗ, hạn chốt và các đơn. Không suy đoán chuyến đã chốt chạy.
Không còn tác vụ `schedules:close-expired`.

Sổ giao dịch hiển thị mã GD, mã BK, khách, loại thu/hoàn, số tiền, hình thức,
chứng từ và người ghi. Bấm mã mở chi tiết và lịch sử toàn bộ giao dịch của đơn.
Tìm chính xác bằng BK-ID hoặc GD-ID; tìm chuỗi bằng chứng từ, tên hoặc email.
Tổng thu, tổng hoàn, chênh lệch và CSV đều theo cùng bộ lọc trên toàn bộ kết quả.
Chênh lệch thu–hoàn không phải số dư ngân hàng. Công nợ hiện tại được hiển thị riêng.

Kiểm tra: 320 bài kiểm thử liên quan, 1.106 assertions đạt với SQLite trong bộ nhớ;
lint hai màn FinanceHub/TransactionRegister đạt. TypeScript và build production đạt.
Migration đã áp dụng vào cơ sở dữ liệu phát triển. Chưa kiểm tra trực quan do trình duyệt
tích hợp không khả dụng trong phiên này.
