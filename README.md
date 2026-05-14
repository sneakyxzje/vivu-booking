# Vivu Booking

Client: React + TailwindCSS + Typescript
Server: Laravel 13

## Cấu trúc thư mục

- `client/`: React
- `server/`: Laravel.

---

## Hướng dẫn cài đặt

### 1. Server

Di chuyển vào thư mục `server`:

```bash
cd server
```

Cài đặt các dependencies (nếu chưa cài đặt):

```bash
composer install
```

Tạo file cấu hình môi trường:

```bash
cp .env.example .env
```

Tạo key cho ứng dụng:

```bash
php artisan key:generate
```

Sau đó, vào .env sửa thông tin cấu hình kết nối đến Database.

Tiếp theo chạy

```bash
php artisan migrate
```

Chạy server ở cổng 8000:

```bash
php artisan serve
```

Server sẽ chạy tại: `http://localhost:8000`

---

### 2. Client

Mở một terminal mới và di chuyển vào thư mục `client`:

```bash
cd client
```

Cài đặt dependencies:

```bash
npm install
```

Run

```bash
npm run dev
```

---

## Test

Sau khi chạy 2 terminal, start client và server thì vào web và test thử endpoint xem client và server đã chạy được chưa
