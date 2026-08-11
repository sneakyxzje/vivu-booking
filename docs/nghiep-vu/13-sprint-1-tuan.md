# 13 - Kế hoạch một tuần

Kế hoạch chạy nước rút bảy ngày cho bốn người, thay cho lộ trình ba mốc ở
[11 - Backlog triển khai](11-backlog-trien-khai.md).

Tài liệu 11 dùng một mức ước lượng chung cho mọi loại việc. Tài liệu này tính lại chi tiết hơn
theo từng loại, vì khối lượng thực tế chênh nhau rất xa.

## 1. Tính lại theo loại công việc

Dự án có hai lợi thế khiến một phần lớn backlog nhẹ hơn con số ở tài liệu 11:

- **Đã có khuôn mẫu trong mã nguồn.** Quản lý danh mục và dịch vụ đã cho sẵn mẫu CRUD kèm hộp
  thoại, đã có mẫu thư điện tử, đã có 41 bài kiểm thử để bám theo. Việc mới chỉ là lặp lại mẫu.
- **Đã có đặc tả nghiệp vụ đầy đủ.** Tài liệu 01 tới 10 mô tả sẵn quy tắc, nên phần lớn việc còn
  lại là dịch tài liệu thành mã, không phải vừa nghĩ vừa viết.

Nhưng lợi thế đó không áp dụng cho mọi loại việc. Đây là điểm phải nắm trước khi hứa tiến độ.

### Nhẹ hơn nhiều, khoảng ba tới bốn lần

| Loại việc | Vì sao nhanh |
| --- | --- |
| Migration bảng mới, model, quan hệ | Khuôn mẫu cố định, thiết kế cột đã có sẵn ở tài liệu 07 |
| Controller CRUD theo mẫu đã có | Dự án đã có mẫu quản lý danh mục để bám theo |
| Biểu mẫu và bảng danh sách phía giao diện | Đã có bộ thành phần và mẫu hộp thoại |
| Thư điện tử, mẫu PDF, lớp xuất Excel | Thuần khuôn mẫu |
| Khung kiểm thử | Bám theo 41 bài đã có, chỉ đổi phần khẳng định |
| Seeder, dữ liệu mẫu | Gần như không tốn thời gian |

### Nhẹ hơn vừa phải, khoảng hai lần

Lớp dịch vụ có quy tắc nghiệp vụ rõ ràng, API có kiểm tra đầu vào, truy vấn báo cáo.
Nhanh ở phần viết vì đặc tả đã có, nhưng vẫn phải đọc lại vì mã chỉ đúng đến mức tài liệu
đã mô tả, chỗ nào tài liệu nói chưa rõ thì chỗ đó phải tự quyết.

### Không nhẹ đi được

| Loại việc | Vì sao không nhanh |
| --- | --- |
| Migration chuyển dữ liệu thật | Viết thì nhanh, nhưng phải tự kiểm chứng từng trường hợp trên dữ liệu thật. Sai một lần là hỏng dữ liệu |
| Mã khóa đồng thời | Viết ra thứ trông đúng thì dễ. Sai chỉ lộ khi có hai yêu cầu vào cùng lúc, không lộ lúc thử tay |
| Gộp nhánh và sửa xung đột | Bốn người, hơn hai mươi bảng mới. Đây là việc thủ công |
| Vòng thử ở máy nhà | Đẩy lên, kéo về, chạy thử. Chi phí cố định mỗi vòng |
| Đọc và duyệt mã | Đọc kỹ không nhanh hơn viết. Đây là nút thắt mới |
| Bấm tay kiểm tra giao diện | Số màn hình phải kiểm tra không giảm một chút nào |

### Ba rủi ro đi kèm khi tốc độ viết tăng

1. **Nút thắt dịch chuyển sang khâu duyệt.** Mã ra nhanh gấp ba thì khối lượng cần duyệt cũng
   gấp ba, trong khi người duyệt vẫn là một. Đây là chỗ vỡ phổ biến nhất khi nhóm chạy nước rút.
2. **Gộp mã mình không đánh giá được.** Hai bạn máy chủ chưa làm khóa bi quan bao giờ sẽ gặp
   đoạn mã đúng cú pháp, chạy được, và sai về nghiệp vụ mà không nhận ra.
3. **Khối lượng kiểm tra thủ công không đổi.** Làm xong chín mươi màn hình và điểm cuối nhanh hơn
   không có nghĩa là kiểm tra chúng nhanh hơn.

## 2. Tính lại sức chứa

| Chỉ tiêu | Giá trị |
| --- | --- |
| Backlog theo mức ước lượng chung ở tài liệu 11 | 151,75 ngày công |
| Sau khi tính lại theo loại công việc | khoảng 85 tới 100 ngày công |
| Sức chứa bốn người trong bảy ngày | khoảng 34 ngày công thô |
| Quy đổi sang khối lượng backlog làm được | khoảng **55 tới 60 ngày công** |

Vậy việc tính lại kéo bài toán từ 152 xuống khoảng 90, giảm gần bốn mươi phần trăm. Nhưng sức
chứa một tuần chỉ là 55 tới 60, nên vẫn còn thiếu khoảng một phần ba.

**Kết luận trung thực: một tuần không làm hết 100 phần trăm tính năng. Nhưng làm hết được
Mốc 1 cộng thêm một nhóm của Mốc 2**, thay vì con số 33,75 ngày công của bản kế hoạch trước.

Đòn bẩy lớn nhất còn lại nằm ở chỗ trưởng nhóm không tự dựng từ đầu mọi phần khó, mà làm trên
khung đã có rồi đọc và kiểm chứng lại. Kiểm chứng nhanh hơn dựng từ đầu khoảng hai lần, và đây
là chỗ dồn nhiều ngày công nhất.

## 3. Phạm vi tuần này

**Toàn bộ Mốc 1** cộng **nhóm K hủy chuyến** của Mốc 2. Tổng **54 ngày công** theo thang cũ.

Chọn nhóm K chứ không phải nhóm I chuyển chuyến vì hai lý do: K trả lời câu 15 mà hội đồng
đã hỏi, và K chỉ dồn 2,5 ngày công vào trưởng nhóm trong khi I dồn tới 5,25, không vừa với
chỗ trống còn lại.

### Kết quả đạt được

| Câu hội đồng | Trả lời bằng |
| --- | --- |
| 2 - Cập nhật lại booking | Mã chạy thật, nhóm E nhật ký thay đổi, phần sửa số khách chưa có |
| 3 - Cập nhật thông tin khách hàng | Mã chạy thật, nhóm G |
| 4 - Validate điểm danh | Mã chạy thật, nhóm H |
| 5 - Điểm danh từng điểm từng ngày | Mã chạy thật, nhóm H |
| 6 - Ghi chú khi khách vắng mặt | Mã chạy thật, nhóm H |
| 7 - Thời gian hủy phải trước bao lâu | Mã chạy thật, nhóm B |
| 8 - Hủy sát giờ có trả slot không | Mã chạy thật, nhóm C |
| 9 - Tour đang chạy không được hủy | Mã chạy thật, nhóm D |
| 10 - Ai được hủy, ai xác nhận | Mã chạy thật, nhóm F |
| 15 - Xóa tour khi đã có người thanh toán | Mã chạy thật, nhóm K |
| 1, 11 tới 14, 16 tới 18 | Tài liệu thiết kế và mô hình dữ liệu |

**Mười trên mười tám câu trả lời bằng mã chạy thật**, so với bảy câu ở bản kế hoạch trước.

## 4. Khối lượng theo người

| Người | Mốc 1 | Nhóm K | Tổng ngày công |
| --- | --- | --- | --- |
| Tuấn Đạt | 13,5 | 2,5 | **16** |
| Thế Anh | 12 | 0,5 | **12,5** |
| Phạm Đức | 11,5 | 2 | **13,5** |
| Đặng Tiến Đạt | 9,5 | 2,5 | **12** |
| **Tổng** | 46,5 | 7,5 | **54** |

Quy ra nhịp: khoảng 1,7 tới 2,3 ngày công backlog mỗi ngày mỗi người. Với cách tính lại ở mục 1,
đây là khoảng năm tới sáu giờ làm việc thật mỗi ngày, không phải mười.

## 5. Lịch từng ngày

### Ngày 1

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | A01, A03 rồi A05, A07, A08 | 2,25 |
| Đặng Tiến Đạt | B01, B02, B04, F01, G01, B05 | 2,25 |
| Tuấn Đạt | A02, A04, chốt hình dạng dữ liệu API cho cả tuần | 1,5 |
| Phạm Đức | A11, A12 | 1,5 |

**A01 và A03 phải xong trong buổi sáng.** Ba người còn lại đã có việc độc lập nên không ai
ngồi chờ, nhưng Tuấn Đạt thì chờ thật.

### Ngày 2

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | A06, C01, E01, E02 | 2,5 |
| Tuấn Đạt | A09, B03, E03 | 2,5 |
| Đặng Tiến Đạt | A10, F02 | 2 |
| Phạm Đức | B06, B07, C04 | 2,25 |

### Ngày 3

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | C03, C05, D03 | 2 |
| Tuấn Đạt | C02, D01, D02 | 2 |
| Đặng Tiến Đạt | F04, G02, G03 | 2,25 |
| Phạm Đức | F05, F06 | 2 |

### Ngày 4

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | H01, H02, H03, H05, H06 | 2,5 |
| Tuấn Đạt | H04, H08 | 2,25 |
| Đặng Tiến Đạt | E04a, G05a, G06, F07 | 2,25 |
| Phạm Đức | G04, G05b, E04b | 2 |

### Ngày 5

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | H09, H10, H12 | 2,25 |
| Tuấn Đạt | F03, A13, B08 | 2,75 |
| Đặng Tiến Đạt | H13a, K01, K02 | 2,25 |
| Phạm Đức | H07, bắt đầu H11 | 2 |

### Ngày 6

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | K06, kiểm thử phần API của H | 1,5 |
| Tuấn Đạt | K03, C06, D04 | 2,75 |
| Đặng Tiến Đạt | K05, K07 | 1,75 |
| Phạm Đức | H11 hoàn thiện, H13b | 2,5 |

### Ngày 7

| Người | Việc | Ngày công |
| --- | --- | --- |
| Thế Anh | Kiểm thử nhóm A và C | 1,5 |
| Tuấn Đạt | H14, duyệt mã tồn đọng, gộp nhánh, sửa lỗi | 2,25 |
| Đặng Tiến Đạt | Kiểm thử nhóm D, E, G | 1,5 |
| Phạm Đức | K04, rà soát toàn bộ giao diện | 2 |

## 6. Quy tắc bắt buộc trong tuần

Sáu quy tắc dưới đây quan trọng hơn lịch. Lịch trượt thì bù được, sáu quy tắc này vi phạm thì
tuần này ra một đống mã không dùng được.

**Kiểm thử viết trước với phần khó.** Với B03, C02, D01, H08, K03, người viết kiểm thử là bạn
máy chủ chứ không phải trưởng nhóm, và viết trước khi có mã. Khi đó việc duyệt trở thành đọc
kiểm thử rồi chạy, thay vì đọc từng dòng mã. Đây là cách duy nhất để khâu duyệt không thành
nút thắt.

**Không gộp mã mình không đọc hiểu.** Đoạn nào bạn không giải thích được nó làm gì thì hỏi lại
người viết cho tới khi hiểu rồi mới gộp. Không hiểu mà vẫn gộp là cách chắc chắn để đến buổi
bảo vệ bị hỏi đúng vào đoạn đó và đứng hình.

**Mọi thứ chạm số chỗ hoặc tiền phải có kiểm thử hai luồng đồng thời.** Không phải kiểm thử
gọi lần lượt. Phải mở hai giao dịch trong cùng bài kiểm thử và khẳng định luồng sau bị chặn.
Nếu không có bài kiểm thử này thì coi như chưa xử lý tương tranh, dù mã có gọi `lockForUpdate`.

**Gộp nhánh hằng ngày, không dồn.** Bốn nhánh chạy song song với nhịp này thì xung đột nhiều
hơn hẳn bình thường.

**Chốt hình dạng dữ liệu API vào ngày 1 cho cả tuần.** Không phải chốt dần từng ngày. Trưởng nhóm
viết một tệp mô tả JSON trả về của toàn bộ điểm cuối trong tuần, cả nhóm bám theo đó. Đây là
việc mất nửa ngày và tiết kiệm hai ngày làm lại.

**Bấm tay kiểm tra mỗi màn hình ít nhất một lần.** Chia đôi ngày 7: mỗi người kiểm tra màn hình
của người khác chứ không kiểm tra của mình.

## 7. Mốc kiểm tra và phương án lùi

Chốt tiến độ vào cuối ngày 3 và cuối ngày 5.

| Thời điểm | Phải xong | Nếu chưa xong |
| --- | --- | --- |
| Cuối ngày 3 | Toàn bộ nhóm A, B, C, D | Bỏ nhóm K ra khỏi phạm vi, quay về đúng Mốc 1 |
| Cuối ngày 5 | Thêm nhóm E, F, G và H tới H09 | Bỏ tiếp nhóm F, giữ nhóm H tới cùng |

Thứ tự cắt khi phải cắt, cắt từ trên xuống:

1. Nhóm K, hủy chuyến.
2. Nhóm F, yêu cầu hủy của khách đã thanh toán.
3. Nhóm E, nhật ký thay đổi.
4. H07 giao diện quản lý điểm dừng, thay bằng seeder tạo sẵn điểm dừng cho tour mẫu.

**Không cắt:** nhóm A, C, D và H08 cùng H09. Bốn thứ này trả lời trực tiếp câu hỏi hội đồng đã
hỏi. Bỏ đi thì cả tuần mất ý nghĩa.

## 8. Rủi ro

| Rủi ro | Mức | Cách giảm |
| --- | --- | --- |
| Khâu duyệt mã thành nút thắt | Cao | Kiểm thử viết trước với phần khó, duyệt bằng cách chạy kiểm thử |
| Mã khóa đồng thời trông đúng mà sai | Cao | Bắt buộc kiểm thử hai luồng đồng thời, không có thì không gộp |
| A01 và A03 chậm | Cao | Xong trong buổi sáng ngày 1, ba người còn lại có việc độc lập |
| Bốn migration chuyển dữ liệu sai trên MySQL | Cao | A02 và H04 chạy thử ở máy nhà trước khi gộp, luôn viết được phần lùi |
| Xung đột khi gộp hai mươi bảng mới | Trung bình | Dải giờ đặt tên theo nhóm, gộp hằng ngày |
| Kiểm tra thủ công bị bỏ vì hết giờ | Trung bình | Đã đặt vào ngày 7 như việc chính thức, không phải việc thêm |
| Nhận mã không hiểu để kịp tiến độ | Cao | Quy tắc hai ở mục 6. Đây là rủi ro với buổi bảo vệ chứ không chỉ với mã |

## 9. Trình bày phần chưa làm

Sau tuần này còn tám câu chưa có mã. Cách nói khi bảo vệ, chia ba mức rõ ràng:

1. **Đã chạy thật:** mười câu ở mục 3, demo trực tiếp.
2. **Đã thiết kế xong, chưa triển khai:** dẫn tới tài liệu 01 tới 10 và mô hình dữ liệu ở
   tài liệu 07, nói rõ đã có backlog và ước lượng ở tài liệu 11.
3. **Cố ý ngoài phạm vi:** dẫn tới [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md).

Ba mức rõ ràng thì việc chưa làm hết là lựa chọn có cân nhắc trong ràng buộc thời gian,
không phải thiếu sót.
