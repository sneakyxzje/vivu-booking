# Chạy bản demo trên laptop

Bản này dùng dữ liệu từ file `vivu_booking (1).sql` ngày 28/09/2026, đã thay thông tin cá nhân bằng thông tin mẫu. File nằm sẵn trong Git; không cần mang file SQL gốc sang laptop.

## 1. Chuẩn bị

- Cài PHP 8.3 trở lên, Composer, Node.js 22.12 trở lên và MySQL 8. Bật MySQL trong Laragon/XAMPP trước khi chạy.
- PHP cần các extension dự án, đặc biệt `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl` và `zip`.
- Pull nhánh `dev`. Nếu laptop có thay đổi chưa commit, lưu/commit chúng trước khi pull; không dùng reset để bỏ code.
- Giữ `server/.env` đang chạy tốt trên laptop. Máy mới: chép `server/.env.example` thành `server/.env`, đặt `APP_ENV=local`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, cổng/tài khoản/mật khẩu MySQL đúng với máy. Dùng các biến `DB_*` riêng, không dùng `DB_URL`.
- SMTP, Cloudinary, VNPay và khóa chatbot trong `.env` **không đi theo Git**. Chép cấu hình riêng đang dùng thành công sang laptop qua kênh riêng. `APP_URL`, `FRONTEND_URL`, `VNPAY_RETURN_URL` và `client/.env` phải khớp địa chỉ chạy trên laptop.

## 2. Cài và khôi phục

Chạy tại thư mục gốc của repository trong PowerShell:

```powershell
git switch dev
git pull --ff-only origin dev
powershell -ExecutionPolicy Bypass -File .\scripts\setup-demo.ps1
```

Script cài dependency theo lockfile, build giao diện, tạo database **mới** `vivu_demo_defense`, nhập dữ liệu, chạy migration còn thiếu, kiểm tra dữ liệu rồi cập nhật `DB_DATABASE` trong `server/.env`. Các cấu hình SMTP/VNPay/Cloudinary được giữ nguyên. Bản sao `.env` trước khi đổi được lưu trong `server/storage/app/demo-env-*.backup`, không được commit lên Git.

Nếu tên database đã tồn tại, script dừng và không ghi đè. Muốn một bản demo mới:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-demo.ps1 -Database vivu_demo_defense_2
```

Nếu dependency đã cài đúng phiên bản hiện tại, có thể thêm `-SkipInstall`. Script vẫn build và kiểm tra bản khôi phục. Chỉ cần khôi phục dữ liệu khi code/dependency đã sẵn sàng thì chạy trong `server`:

```powershell
php artisan demo:restore --database=vivu_demo_defense_3 --activate
```

Không chạy thêm `db:seed`, `migrate:fresh` hoặc import file gốc vào database demo này. Muốn quay lại database cũ, sửa `DB_DATABASE` về tên cũ rồi chạy `php artisan config:clear` và khởi động lại các tiến trình.

## 3. Mở ứng dụng

Dừng những server/worker cũ trước khi đổi database. Sau khi setup xong, mở các terminal riêng:

```powershell
# Terminal 1: API
cd server
php artisan serve --host=localhost --port=8000
```

```powershell
# Terminal 2: giao diện
cd client
npm run dev -- --host localhost
```

```powershell
# Terminal 3: các mốc tự động, đồng thời xử lý thư chờ mỗi phút
cd server
php artisan schedule:work
```

Muốn thư trong hàng đợi được xử lý ngay, mở thêm terminal `server` chạy `php artisan queue:work --tries=3`. Thông báo realtime có thể bật bằng `php artisan reverb:start`; nếu không bật, giao diện vẫn cập nhật bằng polling.

Mở địa chỉ Vite in ra, thường là `http://localhost:5173`. Nếu Vite phải dùng cổng khác, cập nhật `FRONTEND_URL` và cấu hình CORS tương ứng.

## 4. Tài khoản và dữ liệu

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Admin / điều hành | `admin@example.test` | `Demo-2026!` |
| Hướng dẫn viên | `guide@example.test` | `Demo-2026!` |
| Khách hàng | `customer@example.test` | `Demo-2026!` |

Các hướng dẫn viên còn lại dùng email `guide4@example.test` đến `guide16@example.test`, cùng mật khẩu; hai tài khoản cuối bị khóa để minh họa trạng thái nghỉ việc. Tài khoản guide mặc định vẫn giữ phân công từ dữ liệu gốc.

- 15 tour, 62 chuyến, 31 đơn, 28 bút toán thanh toán, 33 hành khách đã khai.
- Đã bổ sung đủ **59 ngày lịch trình**, có điểm tập trung cho từng ngày. Một số đơn còn thiếu hành khách để thử luồng bổ sung của điều hành.
- Giữ nguyên ngày khởi hành: các chuyến mở từ 30/09 đến 25/12/2026; có bốn chuyến hoàn thành và một chuyến hủy. Bản này chưa có chuyến đang đi sẵn. Dùng điều khiển thời gian demo trên chuyến phù hợp để minh họa chốt danh sách, khởi hành và điểm danh.
- Thư cũ, session, token, OTP/cache, lịch sử chat và payload thanh toán cũ không được nhập. Mật khẩu và đường dẫn tra cứu đơn được tạo lại khi khôi phục.
- Địa chỉ `@example.test` chỉ dùng đăng nhập và minh họa, **không nhận email**. Khi trình diễn OTP hoặc phản hồi chuyển chuyến qua email, tạo đơn mới bằng email thật của mình.
- Ảnh tour dùng URL bên ngoài, cần mạng. Upload ảnh mới cần Cloudinary đã cấu hình. VNPay vẫn dùng sandbox và thông tin kết nối riêng của bạn.

## 5. Kiểm tra trước buổi bảo vệ

1. Đăng nhập admin, mở danh sách tour/chuyến/đơn; xem đủ các ngày ở trang chi tiết tour.
2. Mở một trình duyệt riêng cho guide, kiểm tra phân công và điểm danh theo ngày của chuyến demo.
3. Đặt thử một đơn bằng email thật: nhận OTP → đặt cọc VNPay sandbox → tra cứu → khai hành khách.
4. Thử tải file mẫu, import hành khách, bổ sung người còn thiếu ở admin và tải ảnh ở guide.
5. Sau khi bật server/worker với cấu hình laptop, kiểm tra Cloudinary, SMTP và VNPay trên chính máy đó. Lệnh khôi phục không xác nhận thay cho các kết nối bên ngoài này.

Đây là bộ dữ liệu trình diễn, không dùng các tài khoản/mật khẩu mẫu trên môi trường thật. Lệnh khôi phục chỉ cho phép môi trường `local`/`testing`, MySQL trên máy và tên database bắt đầu bằng `vivu_demo_`.
