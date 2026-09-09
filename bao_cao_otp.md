# Báo Cáo Nghiệm Thu Chức Năng Xác Thực Email OTP Đặt Tour

## Tổng quan
Chức năng xác thực OTP (One Time Password) qua email được phát triển nhằm tăng cường tính bảo mật và ngăn chặn việc spam đặt tour ảo trên hệ thống VivuBooking. 

Thay vì cho phép tạo đơn (giữ chỗ) ngay lập tức, hệ thống yêu cầu khách hàng phải nhập đúng mã xác thực 6 số gửi về email. Mã này có hiệu lực trong 5 phút. Sau khi xác thực thành công, khách hàng có 15 phút để hoàn tất thông tin đặt tour trước khi quyền xác thực hết hạn.

---

## Chi tiết phân công công việc & Triển khai

### 1. Dangdat296 (Phát triển Nền tảng Gửi & Xác thực OTP)
**Trách nhiệm:** Xây dựng khung giao tiếp email, tạo mã an toàn, tối ưu hiệu năng (Cache) và bảo mật chống spam bằng thuật toán Rate Limiting.

**Công việc chi tiết:**
- **Thiết kế & Tích hợp Email:** 
  - Khởi tạo mailable class `BookingOtpMail` nhận và truyền tham số mã OTP.
  - Xây dựng giao diện thư (Blade template) chuyên nghiệp, đáp ứng (responsive), màu sắc chuẩn nhận diện thương hiệu VivuBooking.
- **Phát triển lõi Controller (`OtpController`):**
  - Xây dựng API `sendOtp`: Sinh ngẫu nhiên chuỗi mã 6 chữ số an toàn. Sử dụng Redis Cache để lưu mã với vòng đời (TTL) 5 phút, tự động dọn rác giúp tối ưu hóa bộ nhớ.
  - Xây dựng API `verifyOtp`: Thực hiện đối chiếu OTP bảo mật. Nếu thành công, cấp một "cờ xác thực" (verification flag) lưu trữ tạm thời trong 15 phút vào bộ nhớ Cache.
- **Bảo mật hệ thống (Rate Limiting):**
  - Đấu nối bộ lọc chống spam vào API `sendOtp`, giới hạn gắt gao **tối đa 3 lần gửi / 1 giờ** cho mỗi địa chỉ email. Kỹ thuật này giúp hệ thống miễn nhiễm với các bot spam thư tự động và tiết kiệm chi phí máy chủ.
- **Định tuyến (Routing):**
  - Khai báo và đấu nối các đường dẫn API public (`/api/bookings/send-otp` và `/api/bookings/verify-otp`) vào hệ thống lõi.

**Các file đã xử lý:**
- `server/app/Mail/BookingOtpMail.php`
- `server/resources/views/emails/booking_otp.blade.php`
- `server/app/Http/Controllers/Api/Customer/OtpController.php`
- `server/routes/api.php`

---

### 2. NTA0811 (Tích hợp Hệ thống & Đảm bảo Chất lượng - QA)
**Trách nhiệm:** Gắn kết tính năng OTP vào luồng nghiệp vụ hiện tại, chặn các rủi ro bảo mật (bypass) và viết kịch bản kiểm thử tự động.

**Công việc chi tiết:**
- **Tích hợp Core Logic Đặt Tour:** 
  - Chỉnh sửa `BookingController@store` để bổ sung chốt chặn kiểm duyệt: Mọi giao dịch tạo đơn đều phải vượt qua bài kiểm tra cờ xác thực OTP từ Cache. Nếu hệ thống ghi nhận chưa có cờ, lập tức ném ngoại lệ bảo mật 403 (Forbidden).
- **Kiểm soát Vòng đời Cờ Xác thực:**
  - Viết logic "thu hồi quyền": Ngay sau khi một đơn đặt tour được ghi nhận thành công vào Database, cờ xác thực của email đó lập tức bị xóa (forget) khỏi hệ thống Cache. Điều này chống lại lỗ hổng Replay Attack (sử dụng lại 1 email đã xác thực để đặt nhiều đơn cùng lúc).
- **Kiểm thử Tự động (Automated Testing):**
  - Viết Unit/Feature Test `BookingOtpTest` giả lập hành vi hệ thống:
    - Test Rate Limiting: Giả lập gọi API 4 lần liên tục để kiểm chứng hệ thống báo lỗi HTTP 429 sau lần thứ 3.
    - Test Send OTP: Kiểm tra Cache có lưu dữ liệu và hệ thống gửi thư thành công bằng `Mail::fake()`.
    - Test Verify OTP: Kiểm chứng việc cấp quyền nếu đúng mã, từ chối cấp quyền nếu sai mã.
    - Test Integration Booking: Đóng vai tin tặc cố tình gọi thẳng API đặt tour, kiểm chứng hệ thống trả về mã lỗi 403 đúng như thiết kế.

**Các file đã xử lý:**
- `server/app/Http/Controllers/Api/Customer/BookingController.php`
- `server/tests/Feature/Api/Customer/BookingOtpTest.php`

---
*Báo cáo được trích xuất tự động từ hệ thống quản lý mã nguồn.*
