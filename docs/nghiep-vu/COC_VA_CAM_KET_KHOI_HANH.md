# Đặt cọc và cam kết khởi hành — chốt ngày 26/09/2026

Tài liệu này thay thế các quy tắc cũ về hạn trả nốt riêng, số khách tối thiểu bắt buộc và ghép chuyến không cần khách đồng ý. Áp dụng cho luồng bán chỗ trên chuyến có sẵn. Báo giá đoàn riêng vẫn theo luồng hợp đồng đoàn hiện có.

## Quy tắc

- Còn chỗ và chưa tới hạn chốt danh sách: được đặt, cọc 50%, kể cả đặt sát ngày đi.
- Phần còn lại phải trả **trước hạn chốt danh sách**, mặc định trước khởi hành 3 ngày hoặc theo hạn riêng của chuyến. Đúng thời điểm hạn chốt là đã hết hạn.
- Thời gian giữ chỗ chờ cọc tối đa 10 phút và không vượt hạn chốt.
- Tại hạn chốt, đơn đã cọc nhưng chưa trả đủ bị hủy, mất khoản cọc 50%. Nếu đã trả thêm nhưng chưa đủ, phần vượt tiền cọc được ghi nghĩa vụ hoàn. Không có ân hạn phụ thuộc thời điểm gửi email.
- Đơn thiếu tiền bị loại trước khi lập danh sách cuối cùng; số chỗ được cập nhật. Chuyến đã hết hạn vẫn không nhận đặt mới.
- `min_people` giữ tên cột để tương thích dữ liệu, nhưng mang nghĩa **số khách mục tiêu**, không phải điều kiện bắt buộc để chạy. `max_people` vẫn là giới hạn sức chứa.
- Có khách đã trả đủ thì chốt chạy tại hạn chốt, dù dưới mục tiêu. Không tự chốt một chuyến không còn khách đủ điều kiện.
- Rà soát thiếu khách mặc định 72 giờ trước hạn chốt. Ưu tiên đề xuất ghép chuyến phù hợp; không ghép được vẫn tổ chức chuyến. Ghép 8 + 1 thành 9, dưới mục tiêu 10, vẫn chạy.

## Ghép và phản hồi

1. Điều hành chọn chuyến nhận và nhập lý do. Hệ thống lưu phương án cụ thể, lịch trình và hạn trả nốt, rồi xếp email vào hàng đợi sau khi giao dịch dữ liệu hoàn tất.
2. Hạn phản hồi mặc định tối đa 48 giờ, không vượt hạn chốt của cả hai chuyến. Trong lúc chờ, đơn vẫn thuộc chuyến gốc.
3. Khách mở liên kết trong email, xem phương án và chọn **Đồng ý** hoặc **Từ chối**. Phản hồi cần mã tra cứu và email khớp đơn.
4. Chỉ khi đồng ý, hệ thống kiểm lại lịch trình, hạn chốt và sức chứa rồi chuyển đơn, giữ giá và tiền đã thu. Nếu phương án đã đổi hoặc hết chỗ, không chuyển đơn và không mất chỗ ở chuyến gốc.
5. Từ chối hoặc không phản hồi: giữ chuyến gốc; khách vẫn phải trả đủ trước hạn chốt của chuyến đang đặt. Không coi im lặng là đồng ý, không hủy đơn vì không phản hồi.
6. Chuyến nguồn chỉ kết thúc do ghép khi không còn đơn đang hoạt động. Nếu còn khách từ chối/chưa phản hồi hoặc đang giữ chỗ, tiếp tục giữ chuyến nguồn.

Hạn chế ghép hiện có được giữ: hai chuyến đang mở bán và chưa tới hạn chốt; cùng tour lệch tối đa 2 ngày, khác tour phải trùng giờ khởi hành; không ghép tour riêng. Đề xuất không giữ thêm chỗ ở chuyến đích, nên phải kiểm lại sức chứa lúc khách đồng ý.

## Vận hành

Migration mới, chạy sau khi bật cơ sở dữ liệu:

```powershell
cd server
php artisan migrate --path=database/migrations/2026_09_26_000001_add_schedule_targets_to_booking_proposals.php
```

Scheduler xử lý hủy đơn quá hạn trước chốt chuyến mỗi phút; đề xuất hết hạn được cập nhật mà không thay đổi booking. Đồng hồ demo dùng chung hạn chốt/trả nốt, không còn mốc ân hạn riêng. Cần scheduler và hàng đợi email hoạt động để tự động hóa chạy và gửi thư.

Tiền VNPay báo về sau hạn chốt được lưu nhật ký và cảnh báo để đối chiếu/hoàn khoản thu muộn, không dùng để xác nhận đơn đã hết hạn.

Kiểm thử dùng SQLite trong bộ nhớ. Trên máy có extension nhưng chưa bật trong php.ini:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter=CommittedDeparturePolicyTest
```
