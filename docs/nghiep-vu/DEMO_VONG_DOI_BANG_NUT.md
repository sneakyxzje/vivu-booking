# Chuyển trạng thái chuyến bằng nút

Vào **Quản trị → Quản lý chuyến khởi hành**, mở tour để thấy từng chuyến. Ngay dưới trạng thái có nút **Chốt chạy**, **Khởi hành** hoặc **Hoàn thành**, tùy bước hiện tại. Không có bảng riêng hay bước bật chế độ. Hệ thống tự chuyển thời gian của chuyến khi bấm nút; không cần mở terminal.

**Mốc thời gian** nằm cạnh nút trạng thái, dùng khi cần test giữ chỗ, cọc, nhắc trả nốt, quá hạn hoặc điểm danh ngày tiếp theo. Hủy chuyến vẫn dùng chức năng hủy hiện có để xử lý các đơn và khoản hoàn.

## Chuẩn bị một lần

Tạo tour bằng form hiện có, dùng các giá trị sau. `D` là ngày chuẩn bị, các giờ theo Việt Nam.

| Trường | Giá trị mẫu |
|---|---|
| Tên | DEMO - Hạ Long 2 ngày 1 đêm |
| Giá người lớn / trẻ em / em bé | 1.000.000 / 700.000 / 0 đồng |
| Số ngày / đêm | 2 / 1 |
| Trạng thái tour | Đang hoạt động |
| Khởi hành | D + 30 ngày, 08:00 |
| Kết thúc | D + 31 ngày, 18:00 |
| Hạn chốt đặt | D + 27 ngày, 08:00 |
| Tối thiểu / tối đa | 1 / 8 ghế |
| Trạng thái chuyến | Mở bán |
| Lịch trình | Ngày 1: điểm đón; ngày 2: điểm trả |
| HDV | Phân công một HDV đang hoạt động, cho HDV nhận chuyến |

Mỗi ngày có ít nhất một điểm danh. Có thể bỏ yêu cầu ảnh ở bộ dữ liệu nhập môn; nếu bật thì HDV cần tải ảnh theo quy trình.

Tạo thêm các chuyến khác cho nhánh hủy hoặc thiếu khách. Đừng dùng chuyến đang phục vụ khách thật. Chọn email bạn đọc được để nhận OTP và thư thông báo.

**Không có ô nhập hạn trả nốt riêng.** Hệ thống tính từ ngày khởi hành và chính sách hiện hành. Với cấu hình mặc định: cọc 50%, trả nốt trước 10 ngày, nhắc trước hạn 7 ngày và 2 ngày. Bảng nút lấy các mốc từ chính cấu hình này.

## Kịch bản hoàn tất A–Z

1. Ở màn khách, xác thực OTP, đặt 1 người lớn vào chuyến mẫu, khai tên hành khách, đồng ý điều khoản.
2. Thanh toán cọc qua luồng VNPay hiện có; hoặc dùng chức năng thu tiền/xác nhận đơn của quản trị để ghi khoản chuyển khoản thử 500.000 đồng. Nút chuyển thời gian không xác nhận tiền hộ.
3. Tại dòng chuyến, chọn **Mốc thời gian → Đến ngày nhắc trả nốt**. Mở đơn kiểm tra còn thiếu 500.000 đồng và thư nhắc được tạo.
4. Trả nốt 500.000 đồng qua luồng thanh toán/ghi thu hiện có. Kiểm tra sổ giao dịch đủ 1.000.000 đồng.
5. Bấm **Chốt chạy**. Hệ thống chuyển tới hạn chốt; chuyến đủ khách chuyển sang đã chốt, không nhận đơn mới, danh sách hành khách khóa theo chính sách. Nếu thiếu khách, chuyến đóng bán và thông báo chưa đủ điều kiện chốt chạy.
6. Bấm **Khởi hành**. Chuyển sang tài khoản HDV, tải lại màn điểm danh, ghi có mặt/vắng mặt tại điểm đón.
7. Chọn **Mốc thời gian → Đến ngày 2 để điểm danh**, rồi HDV tải lại và ghi điểm danh ngày 2. Chuyến nhiều ngày có các mốc ngày tương ứng.
8. Bấm **Hoàn thành**. Chuyến và đơn đủ điều kiện hoàn tất. Khách được ghi vắng ở mọi hành khách tại điểm đón đầu tiên được chốt `no_show` theo luật hiện có.

Sau khi thao tác ở tab khách/HDV, có thể chọn **Mốc thời gian → Xử lý các đơn đến hạn**. Sau mỗi lần chuyển trạng thái/mốc, tải lại các tab khách/HDV đang mở.

## Các ca biên dùng chuyến riêng

| Ca | Thao tác | Kết quả cần kiểm tra |
|---|---|---|
| Hết hạn giữ chỗ | Đặt một đơn chưa trả tiền → **Qua hạn giữ chỗ chưa thanh toán** | Đơn hủy, ghế được trả lại; nút xuất hiện khi có đơn đang giữ chỗ |
| Không trả nốt | Cọc → **Đến cảnh báo cuối** → **Qua thời gian ân hạn** | Đơn quá hạn hủy, phí/hoàn tính theo chính sách, ghế được xử lý theo hạn chốt |
| Đã trả đủ | Trả đủ một đơn cùng chuyến với đơn bỏ trả nốt | Đơn trả đủ còn hiệu lực khi đơn thiếu tiền bị hủy |
| Thiếu khách | Đặt tối thiểu 2, chỉ có 1 ghế trả tiền → **Chốt chạy** | Đóng bán nhưng chưa chốt chạy; chưa có nút khởi hành, điều hành chọn phương án hiện có |
| Hủy trước/sau hạn chốt | Dùng hai đơn đã cọc; xem dự báo hủy rồi hủy ở hai mốc | Mức hoàn và việc trả ghế khác nhau theo chính sách, không sửa trạng thái bằng nút demo |
| Điểm danh quá sớm | HDV ghi trước khi khởi hành hoặc ghi trước ngày 2 | Máy chủ từ chối |
| Khách không đến | Thanh toán đủ, điểm danh mọi người trong đơn là vắng ở điểm đón, ghi lý do | Kết thúc thành `no_show` |
| Lặp thao tác | Bấm cập nhật xử lý nhiều lần | Không thu tiền, nhả ghế hoặc chốt đơn trùng |
| Cách ly chuyến | Giữ một đơn ở chuyến không bật demo, chuyển mốc chuyến khác | Đơn và thời gian của chuyến đối chứng giữ nguyên |

Nếu bỏ qua cảnh báo cuối và nhảy thẳng tới quá hạn, quy tắc hiện có có thể gửi cảnh báo và cho thêm ân hạn. Bấm cập nhật xử lý để đọc lại mốc ân hạn; không mặc định rằng vừa tới hạn là mọi đơn sẽ bị hủy ngay.

## Giới hạn và vận hành

- Đồng hồ thuộc **chuyến**, mọi đơn của chuyến dùng cùng thời gian. Ngày khởi hành, ngày về không bị sửa. Sau khi nhảy mốc, đồng hồ vẫn chạy mỗi giây như bình thường.
- Chỉ tiến thời gian; không có quay lùi hoặc xóa sổ giao dịch để diễn lại. Muốn tập lại, tạo chuyến mới.
- Nút chạy ngay các tác vụ nghiệp vụ cho chuyến đã chọn. Không cần đợi lịch chạy nền để chuyển mốc. Email vẫn đi qua hàng đợi hiện có, nên cần cấu hình mail và worker như khi chạy ứng dụng bình thường.
- OTP, phiên đăng nhập, thời gian của VNPay và hàng đợi vẫn dùng giờ thật. Chỉ các hạn nghiệp vụ của chuyến demo dùng giờ demo.
- Không có nút giả giao dịch thành công. Thanh toán VNPay vẫn cần cấu hình và kết nối của cổng; ghi thu thủ công dùng tính năng quản trị hiện có.
- Chỉ admin được gọi API. Tính năng mặc định bật ở `APP_ENV=local`; đặt `DEMO_ENABLED=false` để ẩn. `production` luôn chặn kể cả khi cờ bật. Không tắt cờ giữa một buổi demo vì chuyến sẽ quay về dùng giờ thật trong các phép kiểm thời gian.
- Thay đổi thời gian ghi vào nhật ký chuyến. Các tác vụ vẫn tạo thông báo và thay đổi dữ liệu thật của chuyến được chọn.

Migration `2026_09_20_000002_add_demo_clock_to_tour_schedules.php` bổ sung hai cột thời gian, không sửa dữ liệu tour/đơn sẵn có. Máy local hiện tại đã chạy migration này. Khi cài trên máy mới, chạy migration trong bước chuẩn bị dự án, không phải trong buổi trình diễn.

Kiểm thử mới nằm tại `server/tests/Feature/ScheduleDemoClockTest.php`: luồng đơn qua HTTP, thu cọc/trả nốt, HDV điểm danh, hoàn tất/no-show, hết hạn giữ chỗ, cọc quá hạn, cách ly chuyến, quyền truy cập, chặn production, mốc chỉ tiến và ngày VNPay dùng giờ thật.
