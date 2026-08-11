# 08 - Danh mục tình huống ngoại lệ

Bảng tổng hợp toàn bộ edge case của hệ thống, dùng làm danh sách kiểm tra khi phát triển và
khi trả lời hội đồng.

Cột trạng thái: `Đã xử lý` là đã có trong mã nguồn, `Cần bổ sung` là nằm trong lộ trình,
`Chấp nhận` là biết nhưng cố ý không xử lý, có nêu lý do.

## A. Đặt tour và giữ chỗ

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| A01 | Hai khách đặt đồng thời khi còn đúng một chỗ | Khóa dòng bi quan trên chuyến trong giao dịch, người sau đọc lại số chỗ đã cập nhật và bị từ chối | Đã xử lý |
| A02 | Đơn quá hạn giữ chỗ vẫn chiếm chỗ trên giao diện | Nhả chỗ lười khi xem chi tiết tour và khi tạo đơn | Đã xử lý |
| A03 | Đơn quá hạn không ai chạm tới trong thời gian dài | Tác vụ nền quét định kỳ | Đã xử lý |
| A04 | Khách bấm đặt hai lần liên tiếp | Khóa idempotency theo email, chuyến và tổng tiền trong 60 giây | Cần bổ sung |
| A05 | Khách đặt vượt số chỗ còn lại | Từ chối kèm số chỗ thực còn | Đã xử lý |
| A06 | Đặt cho chuyến đã qua ngày khởi hành | Không hiển thị chuyến quá khứ, kiểm tra lại phía máy chủ | Đã xử lý |
| A07 | Đặt sau hạn chốt danh sách | Từ chối, chuyến đã ở trạng thái đóng bán | Cần bổ sung |
| A08 | Đặt khi tour vừa bị chuyển sang ngừng bán | Kiểm tra lại trạng thái tour trong giao dịch tạo đơn | Cần bổ sung |
| A09 | Đơn chỉ có em bé, không có người lớn | Từ chối, phải có ít nhất một người lớn | Cần bổ sung |
| A10 | Số khách bằng 0 hoặc âm | Ràng buộc kiểm tra đầu vào | Đã xử lý |
| A11 | Mã giảm giá hết lượt trong lúc khách điền biểu mẫu | Kiểm tra lại lần cuối trong giao dịch, tạo đơn giá gốc và thông báo | Cần bổ sung |
| A12 | Mã giảm giá của đơn bị hủy | Trả lượt sử dụng về cho mã | Đã xử lý |
| A13 | Mã giảm giá hết hạn giữa lúc thanh toán | Giá đã chốt tại thời điểm tạo đơn, không tính lại | Đã xử lý |
| A14 | Em bé không chiếm ghế nhưng vẫn bị trừ chỗ | Tách `seat_count` khỏi `guests` | Cần bổ sung |
| A15 | Khách nhập email sai, không nhận được thư | Ghi nhật ký gửi thư hỏng, hiện cảnh báo trên trang quản trị | Cần bổ sung |
| A16 | Khách vãng lai mất mã tra cứu | Gửi lại mã qua email đã dùng khi đặt | Cần bổ sung |
| A17 | Mã tra cứu bị đoán để xem đơn người khác | Dùng UUID ngẫu nhiên thay cho số thứ tự | Đã xử lý |

## B. Thanh toán

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| B01 | Dữ liệu trả về từ cổng thanh toán bị giả mạo | Tính lại chữ ký HMAC SHA512 phía máy chủ trước khi tin | Đã xử lý |
| B02 | Cổng thanh toán gọi lại nhiều lần cho cùng giao dịch | Xử lý lũy đẳng, đơn đã `paid` thì bỏ qua | Đã xử lý |
| B03 | Tiền về sau khi đơn đã tự hủy vì quá hạn | Thử khôi phục nếu chuyến còn chỗ, ngược lại ghi cảnh báo đưa vào hàng chờ hoàn thủ công | Đã xử lý |
| B04 | Tiền về cho đơn đã bị quản trị hủy | Ghi cảnh báo hoàn tiền thủ công | Đã xử lý |
| B05 | Múi giờ lệch làm liên kết thanh toán hết hạn sai thời điểm | Quy đổi về giờ Việt Nam cho `vnp_CreateDate` và `vnp_ExpireDate` | Đã xử lý |
| B06 | Khách đóng cọc rồi không đóng nốt | Không tự hủy vì đã thu tiền, đưa vào cảnh báo cho điều hành xử lý | Cần bổ sung |
| B07 | Khách đóng thừa so với số phải trả | Ghi nhận đúng số nhận, phần vượt trừ vào khoản còn lại | Cần bổ sung |
| B08 | Tổng các khoản hoàn vượt tổng đã thu | Kiểm tra ở tầng dịch vụ, từ chối | Cần bổ sung |
| B09 | Mất kết nối giữa lúc chuyển hướng sang cổng thanh toán | Đơn vẫn ở `pending` và giữ chỗ tới hết hạn, khách vào lại đơn để thanh toán lại | Đã xử lý |
| B10 | Hoàn tiền tự động qua cổng | Cần hợp đồng thương mại thật, dùng luồng hoàn thủ công có đối soát | Chấp nhận |

## C. Hủy đơn

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| C01 | Khách hủy đơn chưa thanh toán | Tự hủy ngay, trả chỗ | Đã xử lý |
| C02 | Khách hủy đơn đã thanh toán | Tạo yêu cầu hủy, điều hành duyệt | Cần bổ sung |
| C03 | Hủy sau hạn chốt danh sách | Không trả chỗ tự động, đánh dấu chờ mở lại thủ công | Cần bổ sung |
| C04 | Hủy khi chuyến đang chạy | Chặn ở tầng dịch vụ cho cả bốn lối vào | Cần bổ sung |
| C05 | Hủy khi chuyến đã kết thúc | Chặn, chuyển sang luồng khiếu nại | Cần bổ sung |
| C06 | Quản trị hủy nhầm đơn | Cho mở lại trong 24 giờ kèm lý do, nếu chuyến còn chỗ | Cần bổ sung |
| C07 | Hủy một phần số khách trong đơn | Coi là giảm số khách, tính hoàn theo mốc thời gian | Cần bổ sung |
| C08 | Hủy đơn đã hủy | Kiểm tra trạng thái trong giao dịch có khóa dòng, bỏ qua nếu đã hủy | Đã xử lý |
| C09 | Hai luồng cùng hủy một đơn | Khóa dòng đơn, luồng sau thấy trạng thái đã đổi và dừng | Đã xử lý |
| C10 | Số chỗ bị trừ âm khi hủy nhiều lần | Trừ theo `min(guests, booked_people)` | Đã xử lý |
| C11 | Khách yêu cầu hoàn về tài khoản người khác | Yêu cầu xác nhận bằng văn bản, ghi vào ghi chú | Cần bổ sung |
| C12 | Khách không có mặt lúc khởi hành | Đánh dấu `no_show`, không hoàn tiền, có ghi chú của hướng dẫn viên | Cần bổ sung |

## D. Sửa đơn và chuyển chuyến

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| D01 | Tăng số khách vượt sức chứa còn lại | Khóa chuyến, kiểm tra, từ chối kèm gợi ý tách sang chuyến khác | Cần bổ sung |
| D02 | Giảm số khách về 0 | Từ chối, phải dùng chức năng hủy đơn | Cần bổ sung |
| D03 | Sửa số khách sau hạn chốt | Cho phép nhưng cảnh báo và ghi người duyệt | Cần bổ sung |
| D04 | Khách đi thực tế nhiều hơn số đã đặt | Hướng dẫn viên báo cáo, điều hành tạo phụ thu, kế toán thu, không thu tiền mặt tại chỗ | Cần bổ sung |
| D05 | Số hành khách khai báo ít hơn số đã đặt | Cảnh báo và chặn xuất danh sách đoàn | Cần bổ sung |
| D06 | Trùng số giấy tờ giữa hai hành khách cùng đơn | Từ chối | Cần bổ sung |
| D07 | Hộ chiếu hết hạn trước ngày về | Cảnh báo, yêu cầu điều hành xác nhận | Cần bổ sung |
| D08 | Đổi hoàn toàn người đi | Coi là chuyển nhượng suất, cần duyệt, có thể thu phí đổi tên | Cần bổ sung |
| D09 | Chuyến đích hết chỗ đúng lúc bấm chuyển | Khóa dòng phát hiện, giao dịch quay lui | Cần bổ sung |
| D10 | Hai luồng chuyển chéo nhau giữa hai chuyến | Khóa theo thứ tự khóa chính tăng dần để tránh khóa chết | Cần bổ sung |
| D11 | Chuyển sang chính chuyến đang ở | Từ chối | Cần bổ sung |
| D12 | Chuyển đi chuyển lại nhiều lần | Đếm số lần, từ lần thứ hai thu phí đổi lịch | Cần bổ sung |
| D13 | Chuyển đơn đã có dữ liệu điểm danh | Từ chối vì chuyến gốc đã khởi hành | Cần bổ sung |
| D14 | Chuyển một phần số khách | Tách đơn, ghi liên kết đơn gốc | Cần bổ sung |
| D15 | Chuyến đích rẻ hơn, khách khởi xướng | Ghi công nợ dùng cho lần sau, không hoàn tiền mặt | Cần bổ sung |

## E. Vòng đời chuyến

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| E01 | Chuyến không đủ khách tối thiểu tại hạn chốt | Cảnh báo cho điều hành với ba lựa chọn: vẫn chạy, ghép, hủy | Cần bổ sung |
| E02 | Có đơn mới thanh toán đúng lúc đang chốt chuyến | Tính lại trong giao dịch có khóa dòng | Cần bổ sung |
| E03 | Chuyến đủ khách rồi tụt xuống dưới mức tối thiểu | Cảnh báo lại, cho phép quay về trạng thái đóng bán để cân nhắc | Cần bổ sung |
| E04 | Hủy chuyến khi còn đơn đã thu tiền chưa có phương án | Chặn ở tầng dịch vụ kèm số lượng cụ thể | Cần bổ sung |
| E05 | Hủy chuyến muộn hơn quy định báo trước | Vẫn cho hủy nhưng cảnh báo và ghi vào nhật ký để chịu trách nhiệm | Cần bổ sung |
| E06 | Ghép chuyến mà chuyến đích không đủ chỗ | Từ chối ghép toàn phần, gợi ý ghép một phần | Cần bổ sung |
| E07 | Ghép dây chuyền qua nhiều chuyến | Cập nhật chuỗi tham chiếu, hiển thị chuyến cuối cùng cho khách | Cần bổ sung |
| E08 | Khách không đồng ý đổi ngày do ghép chuyến | Cho phép hoàn 100 phần trăm vì thay đổi do hãng | Cần bổ sung |
| E09 | Xóa tour còn đơn hiệu lực | Chỉ cho chuyển ngừng bán, nêu rõ số đơn đang chặn | Cần bổ sung |
| E10 | Ngừng bán tour có chuyến đã chốt | Chuyến đã chốt vẫn chạy đúng cam kết, chỉ ngừng nhận khách mới | Cần bổ sung |
| E11 | Sửa giá tour sau khi đã có đơn | Không hồi tố, đơn giữ giá tại thời điểm đặt | Đã xử lý |
| E12 | Chuyến đã chốt nhưng không còn hướng dẫn viên | Không hạ trạng thái, buộc tìm người thay hoặc thuê ngoài | Cần bổ sung |

## F. Hướng dẫn viên

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| F01 | Gán hướng dẫn viên trùng lịch hai chuyến | Kiểm tra giao nhau khoảng thời gian, từ chối | Cần bổ sung |
| F02 | Hai chuyến sát nhau không đủ thời gian nghỉ | Yêu cầu khoảng nghỉ tối thiểu 12 giờ | Cần bổ sung |
| F03 | Thẻ hướng dẫn viên hết hạn trước khi chuyến kết thúc | Chặn phân công | Cần bổ sung |
| F04 | Đoàn vượt sức dẫn tối đa của hướng dẫn viên | Chặn, gợi ý bổ sung người phụ | Cần bổ sung |
| F05 | Thay hướng dẫn viên giữa chuyến | Đóng bản ghi phân công cũ, mở bản ghi mới, chuyển quyền ghi | Cần bổ sung |
| F06 | Không tìm được người thay | Gán tạm cho điều hành, đánh dấu cần xử lý | Cần bổ sung |
| F07 | Người cũ vẫn cố ghi điểm danh sau khi bàn giao | Kiểm tra phân công có hiệu lực tại thời điểm thao tác, từ chối | Cần bổ sung |
| F08 | Người cũ cần xem lại dữ liệu đã ghi | Cho phép đọc, không cho ghi | Cần bổ sung |
| F09 | Hai hướng dẫn viên cùng phụ trách một đoàn lớn | Nhiều bản ghi hiệu lực đồng thời với vai trò khác nhau | Cần bổ sung |
| F10 | Thay người sau khi chuyến đã kết thúc | Từ chối | Cần bổ sung |

## G. Điểm danh

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| G01 | Hướng dẫn viên ghi điểm danh cho chuyến không thuộc phân công | Từ chối | Đã xử lý |
| G02 | Truyền chặng không thuộc tour của chuyến | Từ chối | Đã xử lý |
| G03 | Điểm danh chuyến chưa khởi hành | Từ chối | Cần bổ sung |
| G04 | Điểm danh chuyến đã kết thúc | Chỉ cho xem | Cần bổ sung |
| G05 | Tick trước cho ngày trong tương lai | Từ chối | Cần bổ sung |
| G06 | Ghi bù sau khi mất sóng | Cho phép nhưng đánh dấu ghi bù muộn và cảnh báo nếu quá 24 giờ | Cần bổ sung |
| G07 | Đơn bốn người nhưng chỉ ba người có mặt | Điểm danh tới từng hành khách thay vì từng đơn | Cần bổ sung |
| G08 | Điểm danh đơn đã hủy | Chỉ lấy hành khách của đơn còn hiệu lực | Đã xử lý |
| G09 | Đánh vắng mà không ghi lý do | Bắt buộc ghi chú tối thiểu 10 ký tự | Cần bổ sung |
| G10 | Chốt điểm dừng bắt buộc chụp ảnh mà chưa có ảnh | Chặn chốt | Cần bổ sung |
| G11 | Ảnh check-in chụp cách điểm dừng quá xa | So sánh tọa độ, cảnh báo | Cần bổ sung |
| G12 | Sửa điểm danh đã ghi | Lưu lịch sử thay đổi, không ghi đè lặng lẽ | Cần bổ sung |
| G13 | Còn khách chưa điểm danh mà chuyển điểm dừng tiếp | Cảnh báo mềm, cho phép bỏ qua nhưng phải xác nhận | Cần bổ sung |
| G14 | Vắng mặt tại điểm cuối, không về cùng đoàn | Cảnh báo mức cao cho điều hành | Cần bổ sung |
| G15 | Khách rời đoàn giữa chừng | Đánh dấu các điểm dừng còn lại, mở luồng xét hoàn phần chưa dùng | Cần bổ sung |

## H. Sự cố và chi phí phát sinh

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| H01 | Bão làm không ra biển được, phải đổi chương trình | Ghi sự cố, điều hành duyệt phương án và phân bổ chi phí | Cần bổ sung |
| H02 | Hướng dẫn viên tự thu tiền của khách | Không cho phép, mọi khoản thu qua kế toán và có chứng từ | Cần bổ sung |
| H03 | Khách không đồng ý đóng phụ thu | Ghi nhận từ chối, điều hành quyết định miễn hoặc xử lý theo hợp đồng, không được bỏ khách lại | Cần bổ sung |
| H04 | Chương trình bị rút ngắn | Hoàn phần dịch vụ chưa sử dụng theo giá vốn, không theo giá bán | Cần bổ sung |
| H05 | Chuyến dừng hoàn toàn giữa chừng | Vẫn kết thúc ở trạng thái hoàn thành, tạo hoàn theo phần chưa dùng | Cần bổ sung |
| H06 | Một phần đoàn tiếp tục, một phần về sớm | Ghi nhận riêng từng hành khách | Cần bổ sung |
| H07 | Khách gặp vấn đề sức khỏe phải về sớm | Ghi nhận đầy đủ: ai đưa về, đã báo người nhà chưa, chi phí y tế, phục vụ hồ sơ bảo hiểm | Cần bổ sung |
| H08 | Sự cố do lỗi nhà cung cấp | Với khách thì hãng chịu, đồng thời mở phiếu đòi bồi thường nhà cung cấp | Cần bổ sung |
| H09 | Báo cáo sự cố muộn do mất sóng | Cho nhập thời điểm trong quá khứ, đánh dấu ghi bù | Cần bổ sung |

## I. Đoàn, hợp đồng và dữ liệu cá nhân

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| I01 | Đoàn giảm số khách sau khi ký hợp đồng | Áp bậc giá mới nếu tụt bậc, tính phí hủy cho phần giảm | Cần bổ sung |
| I02 | Đoàn tăng số khách | Kiểm tra chỗ, có thể lên bậc giá tốt hơn, tính lại toàn đơn | Cần bổ sung |
| I03 | Đoàn đặt trọn chuyến | Chuyển chuyến sang đóng bán lẻ, đánh dấu chuyến riêng | Cần bổ sung |
| I04 | Nộp danh sách khách muộn hơn hạn chốt | Cảnh báo, điều hành quyết định, ghi rủi ro không kịp mua bảo hiểm | Cần bổ sung |
| I05 | Tệp danh sách sai định dạng hoặc thiếu cột | Báo lỗi theo từng dòng, cho sửa và nhập lại phần lỗi | Cần bổ sung |
| I06 | Số hợp đồng bị trùng khi nhiều đơn cùng lúc | Bảng đếm riêng có khóa dòng, không dùng đếm bản ghi cộng một | Cần bổ sung |
| I07 | Xếp phòng vượt sức chứa loại phòng | Kiểm tra khi lưu danh sách phòng | Cần bổ sung |
| I08 | Khách lẻ không ghép được phòng | Cảnh báo phụ thu phòng đơn chưa được tính | Cần bổ sung |
| I09 | Xếp chung phòng khách khác giới không cùng đơn | Cảnh báo, yêu cầu xác nhận | Cần bổ sung |
| I10 | Danh sách chứa số căn cước bị xem hoặc tải bởi người không phận sự | Giới hạn theo phân công, che một phần trên giao diện, ghi nhật ký mỗi lần xuất tệp | Cần bổ sung |

## J. Tính nhất quán dữ liệu

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| J01 | `booked_people` lệch so với tổng đơn thực tế | Lệnh kiểm tra định kỳ đối chiếu và báo cáo chênh lệch | Cần bổ sung |
| J02 | `booked_people` bị âm | Trừ theo `min(guests, booked_people)` | Đã xử lý |
| J03 | Đơn thuộc chuyến đã bị ghép | Cập nhật đơn sang chuyến đích trong cùng giao dịch với thao tác ghép | Cần bổ sung |
| J04 | Tổng đã thu lệch giữa nhật ký cổng thanh toán và sổ giao dịch đơn hàng | Báo cáo đối soát định kỳ | Cần bổ sung |
| J05 | Chuyến quá ngày khởi hành nhưng trạng thái chưa chuyển | Tác vụ nền cập nhật trạng thái theo thời gian | Cần bổ sung |
| J06 | Đơn `pending` tồn đọng của chuyến đã kết thúc | Tác vụ nền dọn, đánh dấu hết hiệu lực | Cần bổ sung |

## K. Thống kê

| Nhóm | Tổng | Đã xử lý | Cần bổ sung | Chấp nhận |
| --- | --- | --- | --- | --- |
| A - Đặt tour và giữ chỗ | 17 | 8 | 9 | 0 |
| B - Thanh toán | 10 | 6 | 3 | 1 |
| C - Hủy đơn | 12 | 4 | 8 | 0 |
| D - Sửa đơn và chuyển chuyến | 15 | 0 | 15 | 0 |
| E - Vòng đời chuyến | 12 | 1 | 11 | 0 |
| F - Hướng dẫn viên | 10 | 0 | 10 | 0 |
| G - Điểm danh | 15 | 3 | 12 | 0 |
| H - Sự cố và chi phí | 9 | 0 | 9 | 0 |
| I - Đoàn và hợp đồng | 10 | 0 | 10 | 0 |
| J - Nhất quán dữ liệu | 6 | 1 | 5 | 0 |
| Tổng | 116 | 23 | 92 | 1 |

Con số này không phải để bi quan mà để cho thấy phạm vi thực của bài toán. Hai mươi ba tình
huống đã xử lý đều nằm ở phần khó nhất về kỹ thuật là tương tranh và thanh toán. Phần còn lại
chủ yếu là nghiệp vụ, khối lượng lớn nhưng độ khó kỹ thuật thấp hơn nhiều.
