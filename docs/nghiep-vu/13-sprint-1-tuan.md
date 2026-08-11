# 13 - Kế hoạch một tuần

Kế hoạch chạy nước rút bảy ngày cho bốn người, thay cho lộ trình ba mốc ở
[11 - Backlog triển khai](11-backlog-trien-khai.md).

## 1. Sức chứa thực tế

Bốn người nhân bảy ngày là hai mươi tám ngày công nếu mỗi người làm được một ngày công mỗi ngày.
Chạy nước rút có thể đẩy lên khoảng ba mươi tư ngày công, không hơn.

Toàn bộ backlog là 151,75 ngày công. Một tuần làm được khoảng **22 phần trăm**.

Kết luận: không phải chia lại lịch mà phải **cắt phạm vi**. Phần dưới đây là lát cắt trả lời
được nhiều câu hỏi hội đồng nhất trong giới hạn đó.

## 2. Phạm vi tuần này

**Làm:** nhóm A, B rút gọn, C, D, E, G, H rút gọn. Tổng khoảng **33,75 ngày công**.

**Không làm:** nhóm F và toàn bộ Mốc 2, Mốc 3.

### Cắt gì bên trong các nhóm được chọn

| Bỏ | Lý do | Tiết kiệm |
| --- | --- | --- |
| B05, B06 quản trị chính sách hủy | Dùng chính sách seeder cố định. Điều gì quan trọng là chính sách chạy đúng, không phải sửa được qua giao diện | 2 |
| A06 phần gửi thư báo chốt chuyến | Giữ phần đổi trạng thái, bỏ phần thư | 0,5 |
| C05 lệnh đối chiếu nhất quán số chỗ | Việc vận hành, không phải việc demo | 0,5 |
| H10 phần tọa độ ảnh check-in | Giữ upload ảnh gắn điểm dừng, bỏ phần cảnh báo khoảng cách | 0,25 |
| H12 cảnh báo khách vắng cho điều hành | Dữ liệu vẫn có, chỉ chưa có cảnh báo chủ động | 0,5 |
| H13 báo cáo điểm danh sau chuyến | Xem được dữ liệu điểm danh là đủ | 1 |
| Nhóm F yêu cầu hủy của khách đã thanh toán | Câu hỏi ai được hủy trả lời bằng ma trận quyền trong tài liệu và bằng nhóm D | 5,75 |

### Kết quả đạt được

| Câu hỏi hội đồng | Trả lời bằng |
| --- | --- |
| 3 - Cập nhật thông tin khách hàng | Mã chạy thật, nhóm G |
| 4 - Validate điểm danh | Mã chạy thật, nhóm H |
| 5 - Điểm danh từng điểm từng ngày | Mã chạy thật, nhóm H |
| 6 - Ghi chú khi khách vắng mặt | Mã chạy thật, nhóm H |
| 7 - Thời gian hủy phải trước bao lâu | Mã chạy thật, nhóm B |
| 8 - Hủy sát giờ có trả slot không | Mã chạy thật, nhóm C |
| 9 - Tour đang chạy không được hủy | Mã chạy thật, nhóm D |
| 2 - Cập nhật lại booking | Một phần, có nhật ký thay đổi từ nhóm E |
| 10 - Ai được hủy, ai xác nhận | Tài liệu, ma trận quyền đã có nhóm D làm nền |
| 1, 11 tới 18 | Tài liệu thiết kế và mô hình dữ liệu |

**Bảy trên mười tám câu trả lời bằng mã chạy thật.** Đây là mức cao nhất khả thi trong bảy ngày.

## 3. Khối lượng theo người

| Người | Ngày công | Trung bình mỗi ngày |
| --- | --- | --- |
| Tuấn Đạt | 10,75 | 1,54 |
| Thế Anh | 8,75 | 1,25 |
| Đặng Tiến Đạt | 6,25 | 0,9 |
| Phạm Đức | 8 | 1,14 |
| **Tổng** | **33,75** | |

Đây là nhịp chạy nước rút, không phải nhịp bền. Nếu tuần sau còn phải làm tiếp thì phải giảm.

## 4. Lịch từng ngày

### Ngày 1

| Người | Việc | Ghi chú |
| --- | --- | --- |
| Thế Anh | A01, A03 rồi A05 | **A01 và A03 phải xong trước trưa.** Cả nhóm chờ hai việc này |
| Đặng Tiến Đạt | B01, B02, B04, G01 | Migration độc lập, bắt đầu ngay, không chờ ai. Làm B01 đầu tiên |
| Tuấn Đạt | A02 sau khi có A01, rồi A04 | Sáng dựng nhánh và chốt hình dạng dữ liệu API cho Đức |
| Phạm Đức | A11 dựng khung, A12 | Dùng dữ liệu giả theo hình dạng đã chốt |

### Ngày 2

| Người | Việc |
| --- | --- |
| Thế Anh | A06 rút gọn, A07, A08 |
| Tuấn Đạt | A04 hoàn thiện, A09 |
| Đặng Tiến Đạt | A10 |
| Phạm Đức | A11 ghép API thật, B07 |

### Ngày 3

| Người | Việc |
| --- | --- |
| Thế Anh | C01, C03 |
| Tuấn Đạt | B03, C02 |
| Đặng Tiến Đạt | G02, G03 |
| Phạm Đức | C04, G04 |

### Ngày 4

| Người | Việc |
| --- | --- |
| Thế Anh | D03, H01, H02, H03 |
| Tuấn Đạt | D01, D02, E03 |
| Đặng Tiến Đạt | E01, E02 |
| Phạm Đức | G04 hoàn thiện, G05b, E04b |

### Ngày 5

| Người | Việc |
| --- | --- |
| Thế Anh | H05, H06 |
| Tuấn Đạt | H04, bắt đầu H08 |
| Đặng Tiến Đạt | E04a, G05a, G06 |
| Phạm Đức | H07 |

### Ngày 6

| Người | Việc |
| --- | --- |
| Thế Anh | H09, H10 bản cơ bản |
| Tuấn Đạt | H08 hoàn thiện |
| Đặng Tiến Đạt | Kiểm thử nhóm B và C |
| Phạm Đức | H11 |

### Ngày 7

| Người | Việc |
| --- | --- |
| Thế Anh | Kiểm thử nhóm A và phần API của H |
| Tuấn Đạt | Kiểm thử H08, duyệt mã, gộp nhánh, sửa lỗi phát sinh |
| Đặng Tiến Đạt | Kiểm thử nhóm D và E |
| Phạm Đức | H11 hoàn thiện, rà soát toàn bộ giao diện |

Lưu ý về kiểm thử: hai bạn máy chủ viết kiểm thử cho dịch vụ của Tuấn Đạt, không phải cho phần
mình viết. Đây là chỗ dễ sai nhất nên cần con mắt thứ hai, và cũng là cách để cả nhóm hiểu
logic tính tiền và trả chỗ trước khi bảo vệ.

## 5. Quy tắc bắt buộc trong tuần

**Đóng băng phạm vi.** Không thêm bất cứ ý tưởng nào từ ngày 1 tới ngày 7. Nghĩ ra gì thì ghi
vào backlog cho sau.

**Gộp nhánh hằng ngày, không dồn cuối tuần.** Mỗi người gộp phần xong trong ngày vào `dev`.
Dồn tới ngày 7 mới gộp là cách chắc chắn nhất để hỏng.

**Chốt hình dạng dữ liệu API trước khi viết.** Người phụ trách máy chủ dán JSON mẫu vào nhóm chat
trước khi code, Đức dựng giao diện với dữ liệu giả theo đúng hình dạng đó. Không có bước này thì
Đức mất một tới hai ngày làm lại.

**Họp nhanh mười lăm phút mỗi sáng.** Ba câu: hôm qua xong gì, hôm nay làm gì, đang bị chặn bởi ai.

**Dải giờ đặt tên migration theo nhóm** như mục 6.2 của [12 - Phân công nhóm](12-phan-cong-nhom.md).
Bốn người cùng tạo bảng trong một tuần, đây là chỗ chắc chắn sẽ đụng nhau nếu không theo quy ước.

**Chạy `php artisan test` trước mỗi lần gộp.** Bộ kiểm thử hiện có 41 bài đang xanh. Không được
để tuần này làm đỏ mà bỏ qua.

## 6. Rủi ro của việc nén bảy ngày

| Rủi ro | Mức | Cách giảm |
| --- | --- | --- |
| A01 và A03 chậm, cả nhóm đứng | Cao | Thế Anh không nhận việc gì khác trước khi xong. Ba người còn lại có việc độc lập sẵn cho ngày 1 |
| H11 giao diện điểm danh là việc dài nhất của Đức, hai ngày liền cuối tuần | Cao | Đức dựng khung H11 sớm từ ngày 5 với dữ liệu giả, ngày 6 và 7 chỉ ghép API |
| Tuấn Đạt vừa làm phần khó vừa duyệt mã cho ba người | Cao | Duyệt mã dồn vào cuối buổi chiều, không cắt ngang giữa lúc đang viết dịch vụ |
| Bốn migration chuyển dữ liệu chạy sai trên MySQL | Trung bình | A02 và H04 phải chạy thử trên máy nhà trước khi gộp, luôn viết được phần `down` |
| Cắt kiểm thử vì hết giờ | Trung bình | Kiểm thử đã nằm trong lịch ngày 6 và 7, không phải phần thêm. Cắt kiểm thử thì mất luôn khả năng chứng minh nghiệp vụ chạy đúng |
| Kiệt sức, ngày 6 và 7 hiệu suất tụt | Cao | Đã tính nhịp 1,2 tới 1,5 ngày công mỗi ngày, không tính cao hơn |

## 7. Nếu tới ngày 5 thấy không kịp

Cắt tiếp theo thứ tự này, cắt từ trên xuống:

1. Nhóm E, nhật ký thay đổi. Bỏ được ngay, không ai phụ thuộc.
2. Nhóm G, thông tin hành khách. Giữ lại G01 migration để dữ liệu sẵn sàng, bỏ phần giao diện.
3. H07 giao diện quản lý điểm dừng. Thay bằng seeder tạo sẵn điểm dừng cho các tour mẫu,
   demo vẫn chạy được phần điểm danh.

**Không cắt:** nhóm A, C, D và H08 cùng H09. Đây là bốn thứ trả lời trực tiếp câu hỏi hội đồng
đã hỏi, bỏ đi thì cả tuần mất ý nghĩa.

## 8. Sau tuần này

Phần chưa làm không biến mất, nó nằm nguyên trong backlog. Khi trình bày, nói rõ ba điều:

1. Phần nào đã chạy thật, chỉ ra bảy câu ở mục 2.
2. Phần nào đã thiết kế xong nhưng chưa triển khai, dẫn tới tài liệu 01 tới 10 và mô hình dữ liệu
   ở tài liệu 07.
3. Phần nào cố ý nằm ngoài phạm vi và vì sao, dẫn tới
   [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md).

Ba mức đó rõ ràng thì việc chưa làm hết không bị coi là thiếu sót, mà là lựa chọn có cân nhắc
trong ràng buộc thời gian.
