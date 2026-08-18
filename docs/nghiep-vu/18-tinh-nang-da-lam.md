# 18 - Tổng hợp tính năng đã làm

Tài liệu này liệt kê **những gì đã có mã chạy thật**, không phải kế hoạch.

Lý do phải có nó: [08 - Danh mục edge case](08-danh-muc-edge-case.md) và
[11 - Backlog triển khai](11-backlog-trien-khai.md) đều được viết từ lúc còn là thiết kế, cột trạng
thái chưa cập nhật theo mã. Đọc hai tài liệu đó mà không đọc tài liệu này sẽ **khai thấp khối
lượng đã làm** — doc 11 có 18 nhóm nhưng chỉ 2 nhóm được đánh dấu trạng thái.

Đối chiếu theo từng góp ý của hội đồng nằm ở [06 - Đối chiếu feedback](06-doi-chieu-feedback.md).
Tài liệu này nhìn theo **người dùng làm được gì**.

---

## 1. Số liệu

| Hạng mục | Số lượng |
| --- | --- |
| Bảng dữ liệu (migration) | 65 |
| Model | 33 |
| Lớp dịch vụ (`app/Services`) | 25 |
| Enum trạng thái | 16 |
| Controller API | 35 |
| Điểm cuối API | 120 |
| Lệnh chạy nền | 7 |
| Màn hình giao diện | 37 |
| **Kiểm thử tự động** | **510 bài, xanh** |

Ba vai trò: **khách hàng**, **hướng dẫn viên**, **điều hành kiêm quản trị**.

---

## 2. Khách hàng

### Đặt tour

- Tìm kiếm, lọc, xem chi tiết tour kèm lịch trình từng ngày và điểm dừng.
- Đặt chỗ **không cần tài khoản** — nhận mã tra cứu ngẫu nhiên, không phải số thứ tự đoán được.
- Giữ chỗ 10 phút chờ thanh toán, có đếm ngược.
- Thanh toán trực tuyến qua VNPay, kiểm chữ ký HMAC SHA512 trước khi tin dữ liệu trả về.
- Áp mã giảm giá, kiểm lại hiệu lực và số lượt **ngay trong giao dịch tạo đơn**.
- **Đặt xong mới khai danh sách hành khách**, qua liên kết riêng theo mã tra cứu — không cần đăng
  nhập. Lúc bấm đặt chỉ cần số lượng khách và một người đại diện; hạn cuối để khai là hạn chốt
  danh sách của chuyến.
- Gửi lại mã tra cứu về email khi làm mất.

### Sau khi đặt

- Tra cứu đơn bằng mã, xem trạng thái và tiến độ thanh toán.
- **Xem trước mức hoàn dự kiến** nếu hủy ngay bây giờ, trước khi bấm gì.
- Đơn chưa thanh toán: tự hủy được.
- Đơn đã thanh toán: **gửi yêu cầu hủy**, điều hành duyệt — khách không tự hủy tiền của công ty.
- Rút lại yêu cầu hủy khi đổi ý.
- Sửa thông tin liên hệ tới tận lúc đoàn đang đi (đó là số hướng dẫn viên gọi khách).
- Sửa danh sách hành khách trước hạn chốt danh sách; sau đó chỉ điều hành sửa.
- Đánh giá tour sau chuyến.

### Đặt theo đoàn

- Gửi **yêu cầu báo giá** cho đoàn từ 5 người: chọn chuyến, ước tính số người, khai thông tin
  xuất hóa đơn. Chưa phải trả tiền, chưa chiếm chỗ.
- Tra cứu tiến trình bằng mã: đã báo giá chưa, giá bao nhiêu, còn hiệu lực tới khi nào.
- Rút yêu cầu trước khi chốt.

---

## 3. Hướng dẫn viên

- **Chuyến được giao**: xác nhận nhận chuyến hoặc **từ chối kèm lý do** — điều hành biết mà xếp
  người khác thay vì chờ đến ngày đi mới phát hiện.
- Xem tour và danh sách khách của chuyến mình phụ trách.
- **Điểm danh theo từng hành khách, từng điểm dừng, từng ngày** — không phải theo cả đơn.
  Năm trạng thái: có mặt, vắng, đến muộn, rời đoàn sớm, vắng có phép.
- Bắt buộc ghi chú tối thiểu 10 ký tự khi trạng thái khác "có mặt".
- Chụp ảnh check-in gắn với điểm dừng.
- Sửa điểm danh thì **lưu lịch sử**, không ghi đè.
- **Báo cáo sự cố dọc đường** kèm ảnh hiện trường — và **không nhập được số tiền**, vì quyết định
  tiền là việc của điều hành.
- **Gửi yêu cầu bàn giao đoàn** khi ốm hoặc có việc; xem các lần bàn giao đã nhận và đã giao;
  xác nhận đã nắm tình hình khi nhận đoàn.

---

## 4. Điều hành

### Tour và chuyến khởi hành

- Quản lý tour: lịch trình từng ngày, điểm dừng, dịch vụ kèm theo, ảnh, danh mục.
- Chuyến khởi hành có **vòng đời đầy đủ 6 trạng thái**: mở bán → đóng bán → đã chốt → đang khởi
  hành → đã kết thúc, và đã hủy. Chuyển sai bị từ chối ở tầng dịch vụ.
- Đặt `min_people`, `booking_deadline`, `max_people` cho từng chuyến.
- **Dời hạn chốt danh sách** kèm **xem trước tác động** và ghi nhật ký.

### Phân công hướng dẫn viên

- **Nhiều hướng dẫn viên cho một chuyến** — đoàn đông thì thêm người, bao nhiêu do điều hành quyết.
- Danh sách chọn người **đã chấm điểm**: chuyên đúng loại hình và quen tuyến lên trước, đang gánh
  nhiều chuyến thì lùi xuống, kèm **lý do đọc được** chứ không phải một con số.
- Người trùng lịch vẫn hiện nhưng khóa ô chọn, kèm đúng câu máy chủ sẽ từ chối.
- Thấy ai **chưa xác nhận** nhận chuyến, và ai **đã từ chối kèm lý do**.
- Hồ sơ năng lực: ngôn ngữ, tuyến quen, loại hình chuyên, sức dẫn tối đa.
- **Bàn giao giữa chừng**: tự bàn giao, hoặc duyệt yêu cầu của hướng dẫn viên. Có luật **không bỏ
  rơi đoàn** — đoàn một người đang trên đường thì phải mượn người từ đoàn khác, không để trống.

### Đơn hàng

- Danh sách, lọc, xem chi tiết đơn kèm **dòng thời gian lịch sử thay đổi**.
- Xác nhận, hủy kèm lý do bắt buộc, **mở lại đơn hủy nhầm trong 24 giờ**.
- Duyệt hoặc từ chối yêu cầu hủy của khách, có báo số tiền hoàn trước khi quyết.
- Sửa thông tin liên hệ và danh sách hành khách.
- **Xem danh sách khách theo từng nhóm** của một đoàn.

### Chuyển, ghép, hủy chuyến

- **Chuyển chuyến / chuyển tour**: cùng tour hoặc khác tour, tính chênh lệch giá, lần thứ hai thu
  phí đổi lịch, khóa hai chuyến theo id tăng dần để tránh khóa chết.
- **Ghép chuyến**: gộp hai chuyến cùng tour lệch không quá 2 ngày khi mỗi bên ít khách.
- **Hủy cả chuyến — ba bước bắt buộc**: xem tác động (số đơn, số khách, tổng đã thu) → gán phương
  án cho **từng đơn đã thanh toán** (hoàn đủ hoặc chuyển miễn phí) → mới được xác nhận. Còn đơn
  chưa có phương án thì bị chặn kèm số lượng cụ thể.
- **Xóa tour**, thực hiện bằng xóa mềm: tour biến mất khỏi trang khách và màn quản trị, **đơn hàng
  và đánh giá giữ nguyên**, khôi phục lại được. Chặn khi còn chuyến đã chốt hoặc đang khởi hành.
  Khác với **ngừng bán** — cái đó giữ tour trong màn quản trị, chỉ thôi nhận khách mới.

### Tiền

- Chính sách hủy **bậc thang lưu thành dữ liệu**, admin sửa được. **Mỗi tour chọn một chính sách
  riêng** — tour bay vé máy bay không thể cùng điều khoản hoàn với tour đi xe — tour không chọn
  thì dùng chính sách mặc định. Đơn **sao chép chính sách lúc đặt** nên sửa về sau không hồi tố.
- **Sổ giao dịch nhiều đợt cho đơn đoàn**: cọc, thanh toán nốt, hoàn — chỉ thêm dòng, không ghi
  đè. Số đã thu là tổng của sổ.
- **Chi phí phát sinh dọc đường**: duyệt phương án của hướng dẫn viên, phân bổ cho từng đơn, quyết
  ai chịu. Khoản chưa duyệt chưa có hiệu lực.

### Booking đoàn

- Bàn làm việc theo chặng: chờ báo giá → đã báo giá → đã chốt.
- **Báo giá** giá mỗi người + suất miễn phí + hạn hiệu lực; báo giá lại bao nhiêu lần cũng được.
- **Chốt thành đơn thật** — bước duy nhất chiếm chỗ, đi qua đúng luật giữ chỗ của khách lẻ.
- **Giảm số khách** trước hạn chốt danh sách (đặc quyền của đoàn; khách lẻ vẫn phải hủy đặt lại).

### Giám sát

- **Nhật ký hệ thống** gộp đơn hàng và chuyến thành một dòng thời gian, **lọc riêng được các lần
  chạm tiền**.
- Báo cáo điểm danh sau chuyến.
- Bảng điều khiển thống kê.
- Quản lý danh mục, dịch vụ, mã giảm giá, tài khoản hướng dẫn viên.

---

## 5. Chạy nền tự động

Bảy lệnh, chạy theo lịch:

| Lệnh | Việc |
| --- | --- |
| `ExpireStaleHolds` / `ReleaseExpiredBookings` | Nhả chỗ đơn quá hạn thanh toán |
| `CloseExpiredSchedules` | Đóng bán chuyến qua hạn chốt hoặc hết chỗ |
| `ConfirmReadySchedules` | Chốt chuyến đủ khách tối thiểu, cảnh báo chuyến thiếu khách |
| `AdvanceScheduleStatus` | Chuyển sang đang khởi hành / đã kết thúc theo thời gian |
| `FinalizeCompletedBookings` | Chốt đơn sau chuyến thành hoàn thành hoặc không có mặt |
| `CheckSeatConsistency` | Đối chiếu `booked_people` với tổng đơn thực tế |

---

## 6. Xử lý kỹ thuật đáng nói

Đây là phần nên chủ động nêu khi bảo vệ, vì đã xử lý sâu hơn mức thường thấy.

**Chống bán vượt chỗ.** Khóa dòng bi quan trong giao dịch, áp ở **cả năm đường thay đổi số chỗ**.
Khuôn chung: khóa → đọc lại → kiểm tra → mới ghi. Đọc lại sau khi khóa là điểm mấu chốt: hai luồng
song song thì luồng sau phải thấy thay đổi của luồng trước.

**Khóa hai chuyến theo id tăng dần.** Chuyển chuyến và ghép chuyến chạm hai chuyến cùng lúc; khóa
theo thứ tự cố định để hai luồng chéo nhau không gây khóa chết.

**Ghế chết.** Hủy sau hạn chốt danh sách thì **không** trả chỗ về kho, vì phòng và suất ăn đã đặt
theo danh sách gửi nhà cung cấp — trả về là bán ra một chỗ không có dịch vụ đi kèm. Chỗ đó giữ
nguyên tới khi chuyến kết thúc; muốn bán tiếp thì tăng sức chứa chuyến, không có nút mở lại riêng.

**Một mốc điều khiển năm luật.** `booking_deadline` ban đầu chỉ định dùng để ngừng bán, cuối cùng
điều khiển năm quy tắc thuộc năm nhóm: bán chỗ, trả chỗ, sửa tên khách, chuyển chuyến, ghép chuyến.
Đó là khác biệt giữa hiểu bài toán và ghép tính năng.

**Luật nằm ở tầng dịch vụ, không ở giao diện.** Mỗi nghiệp vụ chạm tiền hoặc chạm chỗ có đúng một
đường ghi. Lỗi lặp lại nhiều lần trong dự án luôn cùng một khuôn — luật có ở đường ghi mà thiếu ở
đường đọc — và mỗi lần phát hiện đều khóa lại bằng một bài kiểm thử giữ **cả hai** đường.

**Xem trước hậu quả trước khi bấm** ở mọi thao tác nặng: hủy đơn, hủy chuyến, ghép chuyến, chuyển
chuyến, dời hạn chốt.

**Bốn lớp nhả chỗ** cho đơn giữ chỗ quá hạn: lười (khi có người đặt mới), tác vụ nền, hạn cổng
thanh toán, và khôi phục khi tiền về muộn.

**Giờ Việt Nam.** Toàn hệ thống chạy `Asia/Ho_Chi_Minh`; cột thời gian nghiệp vụ lưu giờ treo tường
và trả về đúng như đã lưu, không quy về UTC.

**Nhật ký đầy đủ** cho cả đơn hàng lẫn chuyến: ai làm, lúc nào, giá trị trước và sau, lý do.

---

## 7. Cố ý không làm

Nêu ra kèm lý do thì là quyết định thiết kế; im lặng thì là thiếu sót.

| Không làm | Lý do |
| --- | --- |
| Sửa số lượng khách của đơn lẻ | Sửa thứ gõ nhầm thì được, đổi thứ đã mua thì không — đổi số người là đổi chỗ và tiền, phải hủy đặt lại theo chính sách |
| Bậc giá đoàn tự động | Giảm bao nhiêu phụ thuộc mùa, quan hệ, chỗ trống — điều hành quyết, hệ thống không tính hộ |
| Tỷ lệ hướng dẫn viên trên số khách | Khác nhau theo loại tour và cách từng công ty vận hành |
| Chặn theo thẻ hành nghề | Hội đồng hỏi về **chuyên môn**; hiệu lực thẻ là việc của quản lý nhân sự |
| Màn hình mở lại ghế chết | Phí hủy đã bù chi phí đã cam kết. Việc còn lại chỉ là **đừng bán ra thứ không giao được**, và luật đã lo trọn — thêm màn hình không giải quyết thêm gì |
| Hoàn tiền tự động qua cổng | Cần hợp đồng thương mại thật với đơn vị thanh toán |
| Hóa đơn điện tử | Phải phát hành qua tổ chức được cấp phép, cần chữ ký số thật |
| Đại lý, kênh phân phối | Là mô hình bán buôn, khối lượng ngang toàn bộ phần bán lẻ hiện có |

Chi tiết ở [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md).

---

## 8. Còn nợ

Ba mảng, đều thuộc góp ý hội đồng, đều đã ghi lý do lùi ở
[06 mục 6](06-doi-chieu-feedback.md):

| Mảng | Còn thiếu |
| --- | --- |
| **12** Đặt cọc | Cọc cho **khách lẻ** (cổng thanh toán thu một phần), nhắc nợ tự động |
| **14** Booking đoàn | Nhập danh sách khách bằng **tệp Excel**; phát hành hóa đơn VAT |
| **18** Hợp đồng | Sinh PDF từ mẫu, đánh số theo năm, lưu bản ký |

Hai đầu thừa nhỏ trong mã: quy tắc 8 điểm danh (`assertCheckpointCompletable`) có luật và có kiểm
thử nhưng chưa có thao tác nào gọi tới; `IncidentType::thuongDoHangChiu()` viết rồi chưa nối vào
luồng quyết định.

Hai trụ **cung ứng** (doc 09) và **tài chính** (doc 10) mới có mô hình, chưa triển khai —
**không nằm trong 18 góp ý của hội đồng**, đừng để chúng làm tưởng còn nợ nhiều hơn thực tế.
