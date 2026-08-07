# Vivu Booking — Hệ thống đặt tour du lịch trực tuyến

Đồ án tốt nghiệp. Nền tảng đặt tour trọn gói với 3 vai trò: **Khách hàng**, **Hướng dẫn viên (Guide)** và **Quản trị viên (Admin)**.

**Stack:** React 19 + TypeScript + Tailwind CSS 4 (client) · Laravel 13 + Sanctum (server) · MySQL/SQLite · VNPay sandbox · Cloudinary.

## Tính năng chính

### Khách hàng
- Tìm kiếm, lọc (danh mục, dịch vụ, số ngày, khoảng giá, điểm đánh giá) và sắp xếp tour.
- Xem chi tiết tour: lịch trình từng ngày với các chặng đi qua, điểm đón, phương tiện, lịch khởi hành theo giờ.
- Đặt tour cho khách vãng lai lẫn thành viên, giá theo loại khách (người lớn/trẻ em/em bé), kèm danh sách hành khách chi tiết và mã giảm giá.
- **Giữ chỗ 10 phút**: đơn chưa thanh toán tự hủy và nhường chỗ (lazy release + quét định kỳ + `vnp_ExpireDate`), chống oversell bằng khóa bi quan cấp dòng (`SELECT ... FOR UPDATE`).
- Thanh toán VNPay (verify chữ ký HMAC, payment log, khôi phục đơn khi tiền về trễ), email theo từng sự kiện: đặt, thanh toán, xác nhận, hủy.
- Hóa đơn trực tuyến qua public token với đếm ngược giữ chỗ và hướng dẫn tập trung; quản lý đơn của tôi, hủy đơn kèm lý do.
- Đánh giá tour: chỉ khách có đơn đã xác nhận, mỗi người một đánh giá; điểm trung bình hiển thị realtime trên danh sách và chi tiết tour.

### Hướng dẫn viên
- Dashboard, danh sách tour và lịch khởi hành được phân công, xác nhận đặt chỗ.
- **Điểm danh đoàn theo từng chặng** (ngày lịch trình) với danh sách hành khách, kèm **ảnh check-in đoàn** chụp/tải lên Cloudinary.

### Quản trị viên
- Dashboard thống kê thật: doanh thu tổng/theo tháng, đơn theo trạng thái, tỷ lệ lấp đầy, phân bố khách theo điểm đến, giao dịch gần nhất.
- CRUD tour (ảnh, lịch trình với các chặng động, lịch khởi hành theo giờ, phân công guide có kiểm tra lịch rảnh), hướng dẫn viên, mã giảm giá, dịch vụ.
- Quản lý booking: xem chi tiết kèm hành khách và payment log, xác nhận thủ công (thanh toán ngoài hệ thống), hủy kèm lý do và cảnh báo hoàn tiền.
- Xem lại điểm danh + ảnh check-in của từng chuyến; khóa/mở tài khoản người dùng.

### Hạ tầng
- 30 feature test (PHPUnit, SQLite in-memory) chạy qua GitHub Actions; rate limiting; phân quyền middleware theo role + trạng thái tài khoản.

## Cấu trúc thư mục

- `client/` — React SPA (Vite). Alias `@/` → `client/src`.
- `server/` — Laravel API.

## Cài đặt

### 1. Server

```bash
cd server
composer install
cp .env.example .env
php artisan key:generate
```

Cấu hình trong `.env`:

| Biến | Ý nghĩa |
|---|---|
| `DB_*` | Kết nối database (hỗ trợ MySQL hoặc SQLite) |
| `FRONTEND_URL` | URL client, mặc định `http://localhost:5173` |
| `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_URL`, `VNPAY_RETURN_URL` | Thông số VNPay sandbox |
| `CLOUDINARY_URL` | `cloudinary://api_key:api_secret@cloud_name` — upload ảnh tour & check-in |
| `MAIL_*` | SMTP gửi email booking |
| `BOOKING_PAYMENT_TTL_MINUTES` | Số phút giữ chỗ chờ thanh toán (mặc định 10) |

```bash
php artisan migrate
php artisan db:seed      # dữ liệu demo: tour, lịch, đánh giá, tài khoản
php artisan serve        # http://localhost:8000
```

Tùy chọn — bật quét đơn giữ chỗ quá hạn mỗi phút (không bắt buộc, hệ thống vẫn tự nhả chỗ theo cơ chế lazy):

```bash
php artisan schedule:work
```

### 2. Client

```bash
cd client
npm install
npm run dev              # http://localhost:5173
```

## Tài khoản demo (sau khi seed)

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Admin | *(seed trong migration `seed_admin_user`)* | — |
| Guide | *(seed trong migration `seed_guide_user`)* | — |
| Khách hàng | `customer@gmail.com` | `customer123` |

## Chạy test

```bash
cd server
php artisan test
```

## Hướng phát triển

- VNPay IPN (server-to-server) và hoàn tiền tự động qua API.
- Khách tự hủy đơn đã thanh toán theo chính sách hoàn hủy từng mốc thời gian.
- Quên mật khẩu qua email; CRUD danh mục tour; thông báo real-time.
