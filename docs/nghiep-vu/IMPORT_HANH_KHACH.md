# Nhập thông tin hành khách từ file

Áp dụng ở trang **Khai thông tin hành khách** (`/bookings/:publicToken/passengers`).

1. Nếu đã đăng nhập đúng tài khoản sở hữu đơn, có thể sửa/nhập file ngay. Khách vãng lai hoặc tài khoản không sở hữu đơn nhập email đã đặt, bấm **Gửi mã OTP**, nhập mã 6 số rồi bấm **Xác thực**.
2. Bấm **Tải mẫu Excel** để lấy file `.xlsx` có tiêu đề, hướng dẫn, cột định dạng sẵn và số dòng theo đơn. Điền thông tin; hoặc dùng Excel `.xlsx`, CSV UTF-8 `.csv`, Word `.docx` có bảng tương ứng.
3. Bấm **Nhập từ file**. Nếu file có nhiều sheet/bảng, chọn đúng danh sách ở màn xem trước.
4. Sửa các lỗi được báo trong file rồi nhập lại. Bấm **Thay danh sách trên form** khi dữ liệu đúng.
5. Kiểm tra và bổ sung trên form, sau đó bấm **Lưu danh sách**.

Import thay toàn bộ nội dung đang nhập trên form sau khi xác nhận; vị trí thiếu được để trống. Hủy màn xem trước giữ nguyên form. Import chưa ghi vào cơ sở dữ liệu. File được đọc trên thiết bị, không tải file lên máy chủ. Lưu danh sách kiểm tra chủ đơn bằng tài khoản hoặc phiên OTP của đúng đơn, cùng hạn chốt, số người, loại khách, tuổi và giấy tờ trùng.

Quyền chủ đơn được máy chủ xác định từ phiên Sanctum và `customer_id`, không suy từ email tài khoản và không nhận cờ quyền do trình duyệt gửi. Tài khoản bị khóa không được sử dụng phiên đăng nhập để truy cập. Đăng xuất/đổi tài khoản sẽ tải lại biểu mẫu theo quyền mới. Các mốc khóa chỉnh sửa vẫn áp dụng kể cả với chủ đơn.

## Xác thực OTP

- Mã gửi riêng đến email đặt tour, có hạn 5 phút và dùng một lần. Nhập sai 5 lần thì mã bị hủy; gửi lại sau ít nhất 60 giây. Giới hạn yêu cầu gửi 10 lần/giờ/IP và 10 lần/giờ/email; xác thực 30 lần/10 phút/IP.
- Sau xác thực, quyền xem đầy đủ giấy tờ và sửa/import có hạn 30 phút, áp dụng cho đúng đơn và tài khoản đang dùng. Lưu nhiều lần trong khoảng này không cần OTP mới. OTP đặt tour không cấp quyền sửa hành khách.
- Token chỉ giữ trong bộ nhớ trang, gửi qua header `X-Passenger-Access`, không đưa vào URL hoặc localStorage. Tải lại/rời trang hoặc đổi tài khoản cần xác thực lại. Hết hạn khi đang nhập sẽ khóa sửa nhưng giữ bản nháp để xác thực lại và tiếp tục.
- API gửi/kiểm mã: `POST /api/bookings/{publicToken}/passengers/send-otp` (`email`) và `/verify-otp` (`challenge_id`, `otp`). Phiên hợp lệ dùng cho GET/PUT danh sách; không có phiên thì PUT trả 403 với `code: passenger_verification_required`.
- Cả API danh sách lẫn API tra cứu đơn đều che số giấy tờ khi chưa xác thực; chỉ nhập đúng email không mở được số giấy tờ. OTP vẫn có thể dùng để xem đầy đủ khi quá hạn, nhưng không mở lại quyền sửa.

## Định dạng

| Cột | Giá trị |
| --- | --- |
| Họ và tên | Bắt buộc, tối đa 255 ký tự |
| Loại khách | Bắt buộc: Người lớn, Trẻ em, Em bé (hoặc adult, child, infant) |
| Giới tính | Nam, Nữ, Khác; có thể bỏ trống |
| Ngày sinh | dd/mm/yyyy hoặc yyyy-mm-dd; Excel cũng nhận ô ngày |
| Loại giấy tờ | CCCD, CMND, Hộ chiếu, Giấy khai sinh |
| Số giấy tờ | Dạng văn bản; form hiện chỉ khai giấy tờ người lớn |
| Điện thoại | Dạng văn bản, 8–20 chữ số, có thể bắt đầu bằng +; không khai cho em bé |
| Yêu cầu riêng | Tối đa 500 ký tự |
| Người đại diện | Có/Không, tối đa một người; nếu bỏ trống dùng người lớn đầu tiên |

Đặt **Text** cho cột giấy tờ và điện thoại trong Excel trước khi điền để giữ số 0 đầu. Nếu Excel đã làm mất số 0, cần nhập lại dữ liệu gốc; hệ thống không tự đoán thêm chữ số. Word phải dùng bảng thường, không gộp ô. Không nhận ảnh chụp, PDF, file `.xls`/`.doc` đời cũ hoặc file đặt mật khẩu; hãy lưu thành định dạng được hỗ trợ.

Giới hạn: 5 MB/file, 50 hành khách/lần; không vượt số lượng từng loại khách đã đặt. Giới hạn đọc nội dung Office: 20 MB giải nén, 1.000 mục ZIP, 20 sheet/bảng và 1.000 dòng/50 cột mỗi bảng.

## Kiểm thử

Trong thư mục `client`, với Node.js 24:

```powershell
npm.cmd run test:passenger-import
npm.cmd run build
```

Kiểm thử đọc file XLSX/DOCX thực trong bộ nhớ, CSV có dấu tiếng Việt, ngày Excel, số 0 đầu, loại khách lẫn thứ tự, lỗi dữ liệu, giới hạn file và nội dung nén.
