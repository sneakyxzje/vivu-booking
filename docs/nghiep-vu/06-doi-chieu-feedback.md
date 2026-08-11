# 06 - Đối chiếu feedback hội đồng bảo vệ thử

Tài liệu này ánh xạ từng điểm hội đồng nêu sang hiện trạng mã nguồn, phương án xử lý,
tài liệu mô tả chi tiết và mức ưu tiên.

Ký hiệu hiện trạng:

- `Chưa có`: chưa có gì trong mã nguồn.
- `Một phần`: có nền tảng nhưng chưa đủ nghiệp vụ.
- `Đã có`: đáp ứng được yêu cầu.

## 1. Bảng đối chiếu tổng hợp

| STT | Hội đồng nêu | Hiện trạng | Phương án | Chi tiết tại | Mốc | Khối lượng |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Hỗ trợ chuyển tour | Chưa có | Chuyển chuyến và chuyển tour, khóa hai chuyến, xử lý chênh giá | [02 §4](02-luong-dat-tour.md) | 2 | Lớn |
| 2 | Cập nhật lại booking, thực tế và trên web | Chưa có | Sửa số khách có kiểm soát, tạo phụ thu hoặc hoàn, nhật ký thay đổi | [02 §3.2](02-luong-dat-tour.md) | 2 | Trung bình |
| 3 | Cập nhật thông tin khách hàng | Một phần | Bổ sung trường hành khách, phân quyền sửa theo mốc thời gian | [02 §3.1](02-luong-dat-tour.md) | 1 | Nhỏ |
| 4 | Validate điểm danh | Một phần | Chín quy tắc kiểm tra, chặn ghi sai chuyến, sai thời điểm, thiếu ảnh | [04 §5.3](04-luong-dieu-hanh.md) | 1 | Trung bình |
| 5 | Điểm danh từng điểm đến từng ngày | Một phần | Thêm bảng điểm dừng, điểm danh tới từng hành khách | [04 §5.2](04-luong-dieu-hanh.md) | 1 | Trung bình |
| 6 | Ghi chú khi khách vắng mặt | Chưa có | Năm trạng thái điểm danh, ghi chú bắt buộc, hệ quả theo lý do | [04 §5.4](04-luong-dieu-hanh.md) | 1 | Nhỏ |
| 7 | Thời gian hủy tour phải trước bao lâu | Chưa có | Bảng phí hủy sáu mốc, mô hình hóa thành bảng dữ liệu | [03 §2](03-luong-huy-va-hoan-tien.md) | 1 | Trung bình |
| 8 | Hủy sát giờ có cộng lại slot không | Chưa có | Có điều kiện theo hạn chốt danh sách, cột `seats_released`, mở lại thủ công | [03 §3](03-luong-huy-va-hoan-tien.md) | 1 | Nhỏ |
| 9 | Tour đang chạy không được hủy | Chưa có | Chặn ở tầng dịch vụ cho cả bốn lối vào, thay bằng ghi nhận vắng mặt hoặc rời đoàn | [03 §4](03-luong-huy-va-hoan-tien.md) | 1 | Nhỏ |
| 10 | Ai được hủy, ai xác nhận | Một phần | Ma trận phân quyền, luồng yêu cầu hủy cho đơn đã thanh toán | [03 §5](03-luong-huy-va-hoan-tien.md) | 1 | Trung bình |
| 11 | Thay hướng dẫn viên giữa chừng | Chưa có | Bảng phân công theo giai đoạn, bàn giao, chuyển quyền điểm danh | [04 §4.4](04-luong-dieu-hanh.md) | 2 | Trung bình |
| 12 | Chính sách hoàn tiền hoặc đặt cọc | Chưa có | Đặt cọc theo phần trăm, sổ giao dịch đơn hàng, hoàn tiền có đối soát | [02 §2.2](02-luong-dat-tour.md), [03 §6](03-luong-huy-va-hoan-tien.md) | 3 | Lớn |
| 13 | Chi phí phát sinh khi tour đã chạy | Chưa có | Bảng sự cố, bảng phụ thu, nguyên tắc phân bổ chi phí bất khả kháng | [04 §6](04-luong-dieu-hanh.md) | 3 | Lớn |
| 14 | Booking theo đoàn | Chưa có | Loại đơn đoàn, báo giá, bậc giá theo số lượng, nhập danh sách từ tệp | [05 §1](05-doan-hop-dong-ho-so.md) | 3 | Lớn |
| 15 | Xóa tour khi đã có người thanh toán | Một phần | Không xóa cứng, hủy chuyến ba bước bắt buộc có phương án cho từng đơn | [04 §3](04-luong-dieu-hanh.md) | 2 | Trung bình |
| 16 | Ghép tour | Chưa có | Ghép chuyến cùng tour, phân biệt với mô hình tour ghép và tour riêng | [04 §2](04-luong-dieu-hanh.md) | 2 | Trung bình |
| 17 | Hướng dẫn viên phù hợp cho từng tour | Chưa có | Hồ sơ năng lực, bộ tiêu chí gợi ý, kiểm tra trùng lịch | [04 §4.1-4.3](04-luong-dieu-hanh.md) | 2 | Trung bình |
| 18 | Hợp đồng, danh sách khách hàng | Chưa có | Hợp đồng PDF, danh sách đoàn, danh sách phòng, hồ sơ bàn giao | [05 §2-3](05-doan-hop-dong-ho-so.md) | 3 | Lớn |

Tổng kết: 13 điểm chưa có, 5 điểm mới ở mức nền tảng. Không có điểm nào đã hoàn chỉnh.
Điều này bình thường, vì toàn bộ danh sách thuộc tầng điều hành mà đồ án chưa chạm tới.

## 2. Nguyên nhân gốc

Mười ba trên mười tám điểm đều bắt nguồn từ **một thiếu sót duy nhất**: chuyến khởi hành
chưa có vòng đời và chưa có mốc thời gian nghiệp vụ.

Cụ thể, thiếu bốn thứ:

| Thiếu | Kéo theo các điểm |
| --- | --- |
| `status` của chuyến | 9, 15, 16, 11, 13 |
| `end_date` của chuyến | 9, 11, 17 |
| `booking_deadline` | 3, 7, 8, 14 |
| `min_people` | 15, 16 |

Vì vậy công việc đầu tiên phải là bổ sung vòng đời chuyến khởi hành. Làm xong bước này thì
các điểm còn lại chỉ là xây tiếp lên trên, không phải sửa lại nền.

Khi trình bày, nên nói đúng như vậy: nhận diện được nguyên nhân chung thay vì liệt kê
mười tám việc rời rạc cho thấy đã hiểu bài toán.

## 3. Câu trả lời ngắn cho từng câu hỏi

Phần này để dùng trực tiếp khi bảo vệ. Mỗi câu trả lời gói trong vài câu, có lý do,
không lan man.

**1. Hỗ trợ chuyển tour.**
Hệ thống phân biệt ba tình huống: đổi ngày trong cùng tour, đổi sang tour khác, và chuyển do
hãng khi chuyến bị hủy hoặc bị ghép. Khách khởi xướng thì phải trước khởi hành ít nhất bảy ngày,
miễn phí một lần, chênh giá thì bù thêm, rẻ hơn thì ghi công nợ. Hãng khởi xướng thì miễn phí
hoàn toàn và hoàn chênh nếu tour đích rẻ hơn. Về kỹ thuật, thao tác này khóa hai chuyến cùng lúc
nên phải khóa theo thứ tự khóa chính tăng dần để tránh khóa chết.

**2. Cập nhật lại booking theo thực tế.**
Tách thành hai luồng theo mức rủi ro. Sửa thông tin hành khách thì khách tự làm được trước hạn
chốt danh sách. Sửa số lượng khách thì bắt buộc qua điều hành vì chạm tới chỗ và tiền: tăng thì
khóa chuyến kiểm tra còn chỗ rồi tạo khoản thu thêm, giảm thì áp chính sách hủy một phần.
Mọi thay đổi đều ghi nhật ký gồm người thao tác, giá trị trước và sau, lý do.

**3. Cập nhật thông tin khách hàng.**
Bổ sung đủ trường cần cho vận hành thực tế: giới tính, ngày sinh, số căn cước hoặc hộ chiếu,
yêu cầu đặc biệt. Lý do là mua bảo hiểm cần đúng ngày sinh và số giấy tờ, xuất vé sai tên là mất vé.
Trước hạn chốt danh sách khách tự sửa, sau đó chỉ điều hành sửa vì danh sách đã gửi nhà cung cấp,
sau khi khởi hành thì khóa hoàn toàn.

**4. Validate điểm danh.**
Có chín quy tắc: chỉ hướng dẫn viên đang được phân công mới ghi được, chuyến phải đang chạy,
điểm dừng phải thuộc lịch trình của chuyến đó, không tick trước cho ngày tương lai, ghi bù quá
hai mươi tư giờ thì đánh dấu và cảnh báo, chỉ điểm danh hành khách của đơn còn hiệu lực,
trạng thái khác có mặt thì bắt buộc ghi chú, điểm dừng yêu cầu ảnh thì phải có ảnh mới chốt được,
và sửa điểm danh thì lưu lịch sử chứ không ghi đè.

**5. Điểm danh từng điểm đến từng ngày.**
Hiện điểm danh theo đơn hàng và theo ngày. Đổi thành theo từng hành khách và theo từng điểm dừng
trong ngày, gồm điểm đón, điểm tham quan, bữa ăn, khách sạn. Việc chuyển sang từng hành khách
giải quyết luôn tình huống đơn bốn người nhưng chỉ ba người có mặt, điều mà mô hình cũ không
mô tả được.

**6. Ghi chú khi khách vắng mặt.**
Trạng thái điểm danh có năm giá trị thay vì có mặt hoặc vắng: có mặt, vắng, đến muộn, rời đoàn sớm,
vắng có phép. Khác có mặt thì bắt buộc nhập ghi chú tối thiểu mười ký tự, có gợi ý các lý do
thường gặp. Mỗi lý do dẫn tới hệ quả khác nhau: vắng ở điểm đón đầu tiên thì cảnh báo mức cao vì
đơn có nguy cơ thành không có mặt, rời đoàn sớm thì mở luồng xét hoàn phần dịch vụ chưa dùng.

**7. Thời gian hủy phải trước bao lâu.**
Áp bảng phí sáu mốc: từ mười lăm ngày hoàn chín mươi phần trăm, tám đến mười bốn ngày hoàn bảy mươi,
bốn đến bảy ngày hoàn năm mươi, hai đến ba ngày hoàn ba mươi, dưới bốn mươi tám giờ và không có mặt
thì không hoàn. Cơ sở là chi phí không phát sinh đều mà nhảy bậc tại các mốc chốt với nhà cung cấp:
khách sạn chốt trước bảy ngày, nhà xe trước ba ngày, suất ăn trước một đến hai ngày. Bảng phí
được lưu thành dữ liệu chứ không viết cứng, và đơn hàng sao chép chính sách tại thời điểm đặt để
việc sửa chính sách về sau không hồi tố.

**8. Hủy sát giờ có cộng lại slot không.**
Có điều kiện. Hủy trước hạn chốt danh sách thì trả chỗ ngay vì còn bán lại được. Hủy sau hạn chốt
thì không trả tự động, vì phòng và suất ăn đã đặt theo danh sách đã chốt, trả chỗ về kho sẽ khiến
hệ thống bán ra một chỗ mà thực tế không có dịch vụ đi kèm. Chỗ đó là ghế chết, hãng đã trả tiền
mà không có khách. Vẫn cho điều hành mở lại chỗ thủ công vì có khi xin thêm suất được, nhưng đó
là quyết định của con người chứ không phải mặc định của hệ thống.

**9. Tour đang chạy có được hủy không.**
Không, và chặn ở tầng dịch vụ chứ không chỉ ở giao diện, vì có bốn lối vào khác nhau. Trong máy
trạng thái của chuyến không tồn tại đường từ đang chạy về đã hủy: chi phí đã phát sinh, nhà cung cấp
đã phục vụ, không thể coi như chưa từng xảy ra. Thay vào đó có hai nghiệp vụ khác là ghi nhận
không có mặt và ghi nhận rời đoàn giữa chừng.

**10. Ai được hủy, ai xác nhận.**
Đơn chưa thanh toán thì khách tự hủy hoặc hệ thống tự hủy khi hết mười phút giữ chỗ. Đơn đã thanh toán
thì khách chỉ tạo yêu cầu hủy, điều hành duyệt, kế toán chi tiền. Hướng dẫn viên không có quyền hủy đơn,
chỉ được báo cáo. Điều hành hủy được mọi đơn của chuyến chưa khởi hành nhưng bắt buộc nhập lý do.
Nguyên tắc là tách người duyệt, người chi và người đối soát, vì đây là kiểm soát nội bộ cơ bản
khi có dòng tiền.

**11. Thay hướng dẫn viên giữa chừng.**
Chuyển từ một cột hướng dẫn viên trên chuyến sang bảng phân công theo giai đoạn, mỗi bản ghi có
thời điểm bắt đầu và kết thúc phụ trách. Khi bàn giao, hệ thống kiểm tra người mới không trùng lịch
trong khoảng còn lại, đóng bản ghi cũ, mở bản ghi mới, người cũ mất quyền ghi nhưng vẫn xem được
dữ liệu đã nhập. Người mới nhận được toàn bộ tình trạng đoàn tính tới thời điểm đó cùng ghi chú
bàn giao. Khách trong đoàn nhận thông báo kèm số điện thoại người mới.

**12. Chính sách hoàn tiền hoặc chuyển sang đặt cọc.**
Chuyển sang mô hình đặt cọc ba mươi phần trăm, phần còn lại thanh toán trước khởi hành bảy ngày,
có tác vụ nhắc tự động. Đơn hàng có sổ giao dịch riêng ghi từng khoản cọc, thanh toán nốt, phụ thu,
hoàn tiền, thay cho việc chỉ lưu một cột tổng tiền. Về hoàn tiền, hoàn tự động qua cổng thanh toán
cần hợp đồng thương mại thật với đơn vị thanh toán nên nằm ngoài phạm vi đồ án; hệ thống dùng luồng
hoàn thủ công có kiểm soát: điều hành duyệt, kế toán chuyển khoản và tải chứng từ, hệ thống gửi thư
xác nhận và đối soát với nhật ký giao dịch.

**13. Chi phí phát sinh khi tour đã chạy.**
Có bảng ghi nhận sự cố và bảng phụ thu gắn với sự cố. Hướng dẫn viên chỉ được báo cáo kèm ảnh hiện
trường, không được tự quyết mức phí và không được tự thu tiền. Điều hành duyệt phương án và phân bổ
chi phí, kế toán thu. Nguyên tắc phân bổ với sự kiện bất khả kháng: hãng chịu chi phí thuộc nghĩa vụ
tổ chức như điều hành, hướng dẫn viên, phương tiện thay thế; khách chịu chi phí tiêu dùng cá nhân
thực tế phát sinh như phòng nghỉ thêm đêm và bữa ăn thêm. Phần chương trình bị cắt thì hoàn theo
giá vốn dịch vụ chưa sử dụng. Mọi khoản khách phải chịu đều cần biên bản có xác nhận của khách
hoặc trưởng đoàn, chụp ảnh tải lên hệ thống.

**14. Booking theo đoàn.**
Đoàn khác khách lẻ ở toàn bộ quy trình chứ không chỉ ở số lượng: có báo giá riêng trước khi chốt,
bậc giá theo số lượng kèm suất miễn phí cho trưởng đoàn, thanh toán nhiều đợt, nộp danh sách khách
sau bằng tệp Excel có kiểm tra từng dòng, và thường cần hóa đơn giá trị gia tăng nên phải lưu mã số
thuế và địa chỉ xuất hóa đơn. Đoàn cũng được hủy một phần số khách chứ không chỉ hủy cả đơn.

**15. Xóa tour khi đã có người thanh toán.**
Không bao giờ xóa cứng khi đã phát sinh giao dịch, chỉ chuyển sang ngừng bán. Với việc hủy chuyến,
hệ thống bắt buộc ba bước: xem tác động gồm số đơn, số khách, tổng tiền đã thu và số ngày còn lại;
gán phương án cho từng đơn đã thanh toán trong bốn lựa chọn là hoàn đủ, chuyển chuyến, chuyển tour,
hoặc ghi công nợ; rồi mới xác nhận. Hệ thống chặn ở tầng dịch vụ nếu còn đơn chưa có phương án,
kèm số lượng cụ thể chứ không chặn mù.

**16. Ghép tour.**
Phân biệt hai nghĩa. Ghép chuyến là dồn hai chuyến cùng tour ngày gần nhau khi mỗi chuyến ít khách,
điều kiện là chuyến đích còn đủ chỗ và lệch ngày không quá hai ngày, khách được báo trước và giá
giữ nguyên, ai không đồng ý thì hoàn đủ vì thay đổi do hãng. Ghép đoàn là mô hình kinh doanh:
tour ghép nhiều khách lẻ đi chung có giá thấp và có số khách tối thiểu, tour riêng độc quyền chuyến
có giá cao hơn.

**17. Hướng dẫn viên phù hợp cho từng tour.**
Thêm hồ sơ năng lực gồm số thẻ và ngày hết hạn, ngôn ngữ, vùng chuyên tuyến, loại hình chuyên,
sức dẫn tối đa, giới hạn ngày công. Khi phân công, hệ thống lọc theo bốn tiêu chí bắt buộc là
không trùng lịch, thẻ còn hiệu lực tới hết chuyến, đang nhận tour, đủ sức dẫn quy mô đoàn; rồi
xếp hạng theo ngôn ngữ, vùng, chuyên môn, tải công việc và điểm đánh giá. Kiểm tra trùng lịch dùng
điều kiện giao nhau của hai khoảng thời gian, cộng thêm khoảng nghỉ tối thiểu mười hai giờ giữa
hai chuyến.

**18. Hợp đồng và danh sách khách hàng.**
Theo Luật Du lịch năm 2017, kinh doanh lữ hành phải có hợp đồng bằng văn bản, nên hệ thống sinh
hợp đồng PDF từ mẫu với đủ mười một mục bắt buộc, đánh số theo năm, lưu kèm bản ký. Danh sách đoàn
xuất Excel và PDF cho hướng dẫn viên và nhà cung cấp, kèm danh sách phòng vì việc ghép phòng ảnh
hưởng trực tiếp tới chi phí. Danh sách chứa số căn cước nên thuộc dữ liệu cá nhân được bảo vệ theo
Nghị định 13 năm 2023: chỉ người được phân công xem được, hiển thị che một phần trên giao diện,
và ghi nhật ký mỗi lần xuất tệp.

## 4. Những gì hệ thống đã làm được

Khi bảo vệ, sau khi trả lời phần thiếu, nên chủ động nêu phần đã có, vì đây là những chỗ đã xử lý
sâu hơn mức thường thấy ở đồ án.

| Nội dung | Mức độ |
| --- | --- |
| Chống bán vượt chỗ bằng khóa dòng bi quan trong giao dịch | Đã xử lý ở cả năm đường thay đổi số chỗ |
| Giữ chỗ mười phút với bốn lớp nhả chỗ: lười, tác vụ nền, hạn cổng thanh toán, khôi phục khi tiền về muộn | Đã triển khai |
| Kiểm tra chữ ký HMAC SHA512 của cổng thanh toán trước khi tin dữ liệu trả về | Đã triển khai |
| Nhật ký giao dịch thanh toán phục vụ đối soát | Đã triển khai |
| Xử lý múi giờ Việt Nam trong khi ứng dụng chạy UTC | Đã triển khai |
| Tra cứu đơn cho khách vãng lai bằng mã ngẫu nhiên thay vì số thứ tự | Đã triển khai |
| Bốn mươi mốt kiểm thử tự động, chạy trong quy trình tích hợp liên tục | Đã triển khai |

Cách trình bày hiệu quả: thừa nhận thẳng phần thiếu, chỉ ra nguyên nhân chung là chưa mô hình hóa
vòng đời chuyến khởi hành, trình bày lộ trình ba mốc, rồi nêu những phần đã xử lý sâu như khóa dòng
và giữ chỗ nhiều lớp. Hội đồng đánh giá cao việc hiểu vì sao thiếu hơn là việc liệt kê đủ tính năng.
