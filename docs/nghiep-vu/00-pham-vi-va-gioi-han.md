# 00 - Phạm vi và giới hạn

Tài liệu này tuyên bố rõ hệ thống làm gì và **cố ý không làm gì**, kèm lý do.

Mục đích: một khoảng trống được nêu ra kèm lý do là quyết định thiết kế. Cũng khoảng trống đó
mà không nêu thì là thiếu sót. Toàn bộ phần "ngoài phạm vi" dưới đây đều là nghiệp vụ có thật
của công ty lữ hành, được nhận diện nhưng không triển khai trong khuôn khổ đồ án.

## 1. Bối cảnh bài toán

Hệ thống mô phỏng hoạt động của một **công ty lữ hành nội địa quy mô vừa**, bán tour trọn gói
trực tiếp tới khách qua website, tự điều hành đoàn bằng đội hướng dẫn viên của mình.

Ba đối tượng sử dụng: khách hàng, hướng dẫn viên, và bộ phận điều hành kiêm quản trị.

## 2. Trong phạm vi

| Nhóm | Nội dung | Tài liệu |
| --- | --- | --- |
| Bán hàng | Tìm kiếm, xem tour, đặt chỗ, giữ chỗ, thanh toán trực tuyến, tra cứu đơn | 02 |
| Hủy và hoàn | Chính sách hủy theo mốc thời gian, phân quyền hủy, hoàn tiền có đối soát | 03 |
| Điều hành | Vòng đời chuyến, chốt chuyến, ghép chuyến, hủy chuyến, phân công hướng dẫn viên | 04 |
| Dẫn đoàn | Điểm danh theo điểm dừng, ảnh check-in, báo cáo sự cố | 04 |
| Đoàn và hồ sơ | Danh sách đoàn theo nhóm, booking đoàn (yêu cầu → báo giá → chốt, thu nhiều đợt) đã có. **Hợp đồng và nộp danh sách bằng Excel chưa cài đặt** — xem [06 mục 6](06-doi-chieu-feedback.md) | 05 |
| Cung ứng | Nhà cung cấp, allotment, giá vốn, lãi lỗ từng chuyến | 09 |
| Tài chính | Sổ giao dịch đơn hàng, tạm ứng và quyết toán đoàn, ghi nhận doanh thu | 10 |

## 3. Ngoài phạm vi

Tám mảng dưới đây là nghiệp vụ thật, được nhận diện và mô tả ở mức khái niệm, nhưng không
triển khai. Mỗi mảng nêu rõ lý do và hướng phát triển.

### 3.1 Kênh phân phối và đại lý

**Không làm:** tài khoản đại lý với bảng giá riêng, hạn mức công nợ, hoa hồng và đối soát
với đại lý; kết nối các nền tảng đặt tour trung gian; hệ thống cộng tác viên.

**Lý do:** đây là mô hình bán buôn, kéo theo một hệ thống giá hai tầng, quy trình đối soát
công nợ và bài toán phân bổ tồn chỗ giữa các kênh. Khối lượng ngang với toàn bộ phần bán lẻ
hiện có. Đồ án chọn mô hình bán trực tiếp để đi sâu thay vì đi rộng.

**Hướng phát triển:** thêm `sales_channel` vào đơn hàng, bảng `agents` với bảng giá và tỷ lệ
hoa hồng, bảng `agent_transactions` cho công nợ.

### 3.2 Hóa đơn điện tử

**Không làm:** phát hành hóa đơn giá trị gia tăng điện tử, ký số, truyền nhận với cơ quan thuế.

**Lý do:** hóa đơn điện tử tại Việt Nam phải phát hành qua tổ chức cung cấp dịch vụ được cấp
phép, cần chữ ký số của doanh nghiệp và mã số thuế thật. Không thể mô phỏng trong môi trường
đồ án mà vẫn đúng bản chất. Hệ thống dừng ở mức lưu đủ thông tin xuất hóa đơn: mã số thuế,
tên đơn vị, địa chỉ.

**Hướng phát triển:** tích hợp một nhà cung cấp hóa đơn điện tử qua API, lưu số hóa đơn và
trạng thái phát hành trên đơn hàng.

### 3.3 Hoàn tiền tự động qua cổng thanh toán

**Không làm:** gọi API hoàn tiền của VNPay.

**Lý do:** chức năng hoàn tiền của cổng yêu cầu hợp đồng thương mại thật giữa doanh nghiệp và
đơn vị thanh toán, không có trong môi trường thử nghiệm. Hệ thống thay bằng luồng hoàn thủ công
có kiểm soát đầy đủ: điều hành duyệt, kế toán chi và tải chứng từ, hệ thống đối soát với nhật ký
giao dịch. Về mặt kiểm soát nội bộ, luồng này chặt hơn hoàn tự động.

**Hướng phát triển:** bổ sung lời gọi API hoàn tiền, giữ nguyên toàn bộ luồng duyệt hiện có,
chỉ thay bước chi tiền thủ công bằng lời gọi tự động.

### 3.4 Bảo hiểm du lịch

**Không làm:** mua bảo hiểm cho đoàn, quản lý hợp đồng bảo hiểm, luồng yêu cầu bồi thường.

**Lý do:** phụ thuộc hoàn toàn vào hệ thống của đơn vị bảo hiểm.

**Ghi chú quan trọng:** hệ thống vẫn thu thập đủ dữ liệu mà bảo hiểm yêu cầu, gồm họ tên,
ngày sinh và số giấy tờ của từng hành khách, để có thể xuất danh sách gửi đơn vị bảo hiểm.
Đây là lý do thật của việc bắt buộc các trường này ở tài liệu 05, không phải thu thập thừa.

**Hướng phát triển:** bảng `insurance_policies` gắn với chuyến, số hợp đồng, mức bồi thường,
và bảng `insurance_claims` gắn với bản ghi sự cố.

### 3.5 Tour nước ngoài

**Không làm:** hồ sơ xin thị thực, chứng minh tài chính, đặt cọc thị thực, vé máy bay quốc tế,
nhiều loại tiền tệ và tỷ giá.

**Lý do:** tour nước ngoài là một quy trình bán hàng riêng, thời gian chuẩn bị dài, ràng buộc
pháp lý khác. Đưa vào sẽ làm loãng phần điều hành nội địa vốn là trọng tâm.

**Ghi chú:** cấu trúc dữ liệu đã dự phòng cho trường hợp này qua các trường `id_type`,
`passport_expiry`, `nationality` của hành khách.

### 3.6 Thông báo thời gian thực

**Không làm:** thông báo đẩy trên thiết bị di động, nhóm trò chuyện của đoàn, theo dõi vị trí xe.

**Lý do:** cần ứng dụng di động riêng, đồ án chỉ có giao diện web. Mọi thông báo hiện đi qua
thư điện tử.

**Hướng phát triển:** thêm hàng đợi thông báo với nhiều kênh, bổ sung tin nhắn và thông báo đẩy
mà không đổi phần nghiệp vụ.

### 3.7 Chăm sóc khách hàng và khiếu nại

**Không làm:** hệ thống phiếu khiếu nại có cam kết thời gian xử lý, phân loại khách thân thiết,
lịch sử tương tác đa kênh.

**Lý do:** thuộc về hệ thống quản lý quan hệ khách hàng, là một sản phẩm riêng.

**Hướng phát triển:** bảng `complaints` gắn với đơn hàng và bản ghi sự cố, có mức độ ưu tiên và
thời hạn xử lý.

### 3.8 Giá động và khuyến mại nâng cao

**Không làm:** giá theo mùa, đặt sớm giảm giá, giá phút chót, gói kết hợp nhiều tour,
bán chéo dịch vụ.

**Lý do:** cần một công cụ quy tắc giá riêng. Hệ thống hiện dùng giá cố định theo tour cộng mã
giảm giá, đủ để mô tả luồng thanh toán.

**Hướng phát triển:** bảng `price_rules` với điều kiện áp dụng và độ ưu tiên, tính giá qua một
lớp dịch vụ thay vì đọc thẳng cột giá.

## 4. Giả định

Các giả định sau được đặt ra để giới hạn bài toán. Khi trình bày cần nêu rõ, vì đổi giả định
sẽ đổi thiết kế.

| Giả định | Ảnh hưởng nếu bỏ |
| --- | --- |
| Một công ty duy nhất, không phải nền tảng nhiều nhà cung cấp | Phải thêm tầng phân tách dữ liệu theo đơn vị |
| Chỉ tour trọn gói, không bán lẻ dịch vụ đơn lẻ | Phải tách sản phẩm khỏi chương trình tour |
| Tour nội địa, một loại tiền tệ là đồng Việt Nam | Phải thêm tỷ giá và quy đổi |
| Đội hướng dẫn viên là nhân sự của công ty | Phải thêm hợp đồng cộng tác và thanh toán theo chuyến |
| Một cổng thanh toán duy nhất | Phải trừu tượng hóa lớp thanh toán |
| Giao diện web, không có ứng dụng di động | Ảnh hưởng phần điểm danh tại hiện trường |
| Quy mô vài nghìn đơn mỗi năm | Ở quy mô lớn hơn, khóa dòng bi quan có thể trở thành nút thắt, phải chuyển sang hàng đợi |

Giả định cuối đáng chú ý: thiết kế khóa dòng bi quan hiện tại đúng và an toàn ở quy mô này.
Ở quy mô rất lớn, cách làm phổ biến là đưa việc giữ chỗ vào hàng đợi xử lý tuần tự theo chuyến.
Nêu được điều này cho thấy hiểu giới hạn của giải pháp mình chọn, không phải chọn bừa.

## 5. Nguồn tham chiếu và mức độ tin cậy

Phần này cần thiết vì tài liệu có nhiều con số cụ thể.

| Nhóm số liệu | Nguồn | Mức tin cậy |
| --- | --- | --- |
| Bảng phí hủy sáu mốc | Tổng hợp thông lệ thị trường lữ hành nội địa | **Tham khảo, chưa dẫn nguồn cụ thể** |
| Mức cọc 30 phần trăm, thanh toán nốt trước 7 ngày | Thông lệ | Tham khảo |
| Bậc giá đoàn và suất miễn phí cho trưởng đoàn | Thông lệ | Tham khảo |
| Khoảng nghỉ tối thiểu 12 giờ giữa hai chuyến của hướng dẫn viên | Suy luận từ điều kiện làm việc thực tế | Đề xuất |
| Hạn chốt danh sách trước 3 ngày | Suy luận từ chu kỳ chốt của nhà cung cấp | Đề xuất |
| Yêu cầu hợp đồng lữ hành bằng văn bản | Luật Du lịch năm 2017 | **Đúng về nội dung, chưa đối chiếu số điều** |
| Yêu cầu bảo vệ dữ liệu cá nhân | Nghị định 13 năm 2023 | **Đúng về nội dung, chưa đối chiếu số điều** |
| Yêu cầu hóa đơn điện tử | Nghị định 123 năm 2020 | Đúng về nội dung, chưa đối chiếu số điều |

**Việc cần làm trước khi bảo vệ:** đối chiếu bảng phí hủy với điều khoản hủy tour công bố công
khai của một đến hai doanh nghiệp lữ hành lớn, và tra số điều khoản của ba văn bản pháp luật
nêu trên. Không đọc số điều trước hội đồng khi chưa tra lại.

## 6. Giới hạn kỹ thuật đã biết

| Giới hạn | Ảnh hưởng | Xử lý |
| --- | --- | --- |
| Máy phát triển dùng SQLite, máy chạy thật dùng MySQL | Một số phép tính tổng hợp cho kết quả khác nhau | Tính tổng hợp ở tầng ứng dụng thay vì trong câu truy vấn |
| Chưa có hàng đợi công việc chạy nền thường trực | Gửi thư đồng bộ, làm chậm phản hồi | Chuyển sang hàng đợi khi triển khai thật |
| Chưa có lệnh gọi lại tự động từ cổng thanh toán | Phụ thuộc việc khách quay lại trang kết quả | Đã có tác vụ nền và cơ chế khôi phục bù lại |
| Ảnh lưu trên dịch vụ bên thứ ba | Phụ thuộc dịch vụ ngoài | Chấp nhận, có phương án lưu cục bộ |
| Chưa có chữ ký số cho hợp đồng | Ký tay rồi tải bản chụp lên | Nêu rõ là hướng phát triển |

## 7. Mức độ hoàn thiện tự đánh giá

| Trụ nghiệp vụ | Mức độ | Ghi chú |
| --- | --- | --- |
| Bán hàng | Cao | Đã xử lý sâu phần tương tranh và thanh toán |
| Điều hành đoàn | Cao | Vòng đời chuyến, ghép, hủy, phân công theo năng lực, bàn giao, điểm danh, sự cố đều đã chạy |
| Cung ứng | Trung bình | Có mô hình, chưa triển khai |
| Tài chính | Trung bình | Có mô hình, chưa triển khai |
| Phân phối | Không có | Cố ý ngoài phạm vi |

Đây là đánh giá trung thực. Một hệ thống lữ hành thương mại đầy đủ còn cần cả trụ phân phối và
phần tích hợp bên ngoài mà đồ án không đặt mục tiêu bao phủ.
