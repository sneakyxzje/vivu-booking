**Vivu Booking — bộ quy tắc nghiệp vụ V1 đã chốt để triển khai**

Ngày soạn: 13/09/2026. Dành cho nhóm đồ án thống nhất cách hoạt động, đối chiếu website và chuẩn bị bảo vệ.

Trạng thái: chốt làm căn cứ triển khai và nghiệm thu theo yêu cầu của người phụ trách dự án. Khi tài liệu cũ, lời hướng dẫn hoặc kịch bản thử khác V1, ghi nhận phần cần sửa theo V1; không yêu cầu từng thành viên tự chọn lại quy tắc. Mọi thay đổi V1 về sau phải ghi rõ lý do và các phần bị ảnh hưởng.

Đây là thiết kế nghiệp vụ có chủ đích cho phạm vi đồ án. Các mức tiền và thời hạn dưới đây là lựa chọn của thiết kế này, không phải quy định bắt buộc chung của ngành. Chốt thiết kế không đồng nghĩa website đã được sửa hoặc nghiệm thu. Phân công thực hiện nằm trong [bảng task V1](PHAN_CONG_V1.md).

**1. Website phục vụ việc gì?**

Vivu là website của một công ty tổ chức tour nội địa theo lịch có sẵn. Công ty tạo chương trình, mở chuyến, bán chỗ, thu tiền và tổ chức phục vụ khách. Quản trị viên kiêm công việc bán hàng, điều hành và ghi nhận thu/hoàn tiền; hướng dẫn viên thực hiện nhiệm vụ trên chuyến.

Khách có thể đặt cho nhiều người trong một booking. Người đặt là người đại diện liên hệ, có thể không tham gia chuyến đi.

Phạm vi cốt lõi V1: bán chỗ trên chuyến có sẵn, đặt cọc, thanh toán còn lại, khai hành khách, hủy/hoàn, chuẩn bị và thực hiện chuyến. Đổi/ghép chuyến, báo giá đoàn riêng và thu hộ của HDV nằm ngoài đợt triển khai và nghiệm thu V1. Nhóm vẫn giữ mã hiện có; người phụ trách chính cần tách các lối vào/tác vụ đó khỏi luồng V1 để không còn đường áp quy tắc khác. Tài liệu này chưa thực hiện việc tách đó.

**2. Năm khái niệm cả nhóm phải dùng cùng nghĩa**

| Từ | Nghĩa | Ví dụ |
|---|---|---|
| Tour | Chương trình có thể tổ chức nhiều lần | Đà Nẵng – Hội An 3 ngày |
| Chuyến | Một lần tổ chức, có giờ đi/về và sức chứa | Chuyến khởi hành 20/10/2026 lúc 08:00 |
| Booking | Một đơn đăng ký một nhóm người trên một chuyến | Chị An đặt hai người lớn |
| Hành khách | Người thực sự đi tour | Hai người có tên trong danh sách |
| Giao dịch | Một lần thu hoặc hoàn tiền | Cọc 5 triệu, trả tiếp 5 triệu |

Nhận booking không đồng nghĩa đã thu đủ. Nhận booking cũng không đồng nghĩa chuyến chắc chắn chạy. Hủy booking không đồng nghĩa đã trả tiền hoàn.

**3. Luồng chính và lịch 7–5–3 ngày**

Gọi D là ngày giờ khởi hành. Một ngày ở đây bằng 24 giờ, tính theo giờ Việt Nam. Mỗi hạn phải hiển thị cả ngày và giờ; không dùng cách hiểu lúc đầu ngày ở màn này và cuối ngày ở màn khác.

| Thời điểm | Công việc | Quy tắc |
|---|---|---|
| Trước D−7 | Mở bán, nhận booking và cọc | Khách được thông báo chuyến đang chờ xác nhận khởi hành |
| Chậm nhất D−7 | Công ty quyết định tổ chức | Đủ điều kiện thì xác nhận; không đủ điều kiện thì hủy và hoàn tiền |
| Chậm nhất D−5 | Thu đủ những booking đã cọc | Khách thấy số còn thiếu và hạn cụ thể |
| Từ D−5 đến trước D−3 | Tiếp tục bán chỗ còn lại của chuyến đã xác nhận | Booking mới thanh toán đủ ngay, không chia cọc |
| Tại D−3 | Đóng nhận booking và khóa khách tự sửa danh sách | Điều hành hoàn tất danh sách, dịch vụ và bàn giao |
| Tại D | Thực hiện chuyến | HDV nhận khách, điểm danh và báo cáo |
| Sau kết thúc thực tế | Hoàn thành chuyến | Điều hành ghi nhận kết quả; nghĩa vụ hoàn tiền còn tồn vẫn phải xử lý |

Ví dụ D = 20/10/2026 08:00: quyết định chạy trước hoặc tại 13/10 08:00; hạn thu đủ 15/10 08:00; đóng nhận khách và chốt danh sách 17/10 08:00.

Ba hạn có ý nghĩa riêng. Chốt danh sách muộn không tự gia hạn trả tiền. Xác nhận khởi hành sớm không tự đóng bán. Hết chỗ có thể tạm ngừng bán trước D−3; nếu chỗ được trả lại trước hạn thì có thể nhận khách tiếp.

**4. Khi nào công ty xác nhận chuyến sẽ chạy?**

Công ty chỉ mở bán sau khi đã đánh giá khả năng bố trí dịch vụ. Trước khi xác nhận khởi hành, điều hành kiểm tra số khách đã được nhận booking và tình trạng dịch vụ thiết yếu như xe, lưu trú nếu có, khả năng bố trí HDV.

Số khách tối thiểu tính trên người lớn và trẻ em có ghế thuộc booking đã được nhận, không tính booking chỉ giữ chỗ tạm và không tính em bé không có ghế. Đây là ngưỡng tổ chức của đồ án, không phải phép tính chứng minh chuyến có lãi.

Tại D−7, nếu đủ số khách tối thiểu và có khả năng thực hiện dịch vụ, công ty xác nhận khởi hành và thông báo khách. Nếu không đáp ứng thì công ty hủy chuyến, đóng bán và xử lý hoàn theo mục 9. Phiên bản V1 không có ngoại lệ tự kéo dài mốc này hoặc chạy dưới số khách tối thiểu.

Nếu quá mốc mà quyết định chưa được ghi nhận, hệ thống phải dừng nhận booking và dừng yêu cầu trả tiếp, cảnh báo điều hành xử lý. Việc nhân viên chưa xử lý không được làm phát sinh lỗi thanh toán của khách. Khi chưa có căn cứ xác nhận đủ điều kiện tại hạn, xử lý theo nhánh công ty không tổ chức được.

Sau khi đã xác nhận khởi hành, khách hủy làm số người giảm không tự rút lại cam kết chạy. Công ty vẫn tổ chức hoặc chịu trách nhiệm hủy theo mục 9.

**5. Đặt chỗ, giá và danh sách người đi**

- Booking mới phải còn trước D−3 và còn đủ chỗ. Chuyến đã hủy, đang thực hiện hoặc đã kết thúc không nhận booking mới.
- Người lớn từ 12 tuổi; trẻ em từ 2 đến dưới 12; em bé dưới 2. Tính tuổi tại ngày khởi hành. Giá từng nhóm được công bố trên tour, không áp một tỷ lệ giá trẻ em chung cho mọi tour.
- Trong phạm vi sản phẩm V1, người lớn/trẻ em dùng một ghế; em bé không có ghế riêng. Các sản phẩm cần quy tắc phương tiện khác phải được xác định riêng trước khi mở bán.
- Có thể mua nhiều người trong một booking, nhưng phải có người lớn đi kèm trẻ em/em bé. Số người phải khớp danh sách; phân loại tuổi phải khớp ngày sinh trước khi chốt danh sách.
- Tổng giá chốt lúc đặt gồm các khoản đã công bố và giảm giá hợp lệ. Điều chỉnh giá tour sau đó không làm thay đổi giá booking đã nhận.
- Tour có lưu trú phải công bố tiêu chuẩn phòng, cách bố trí người đi lẻ và quyền lợi trẻ em. Yêu cầu chưa xác nhận không được trình bày như dịch vụ đã bán. V1 chỉ chốt tự động gói có giá và điều kiện rõ; yêu cầu ngoài gói cần tư vấn trước khi đặt.
- Khách có thể khai hành khách sau khi trả cọc. Trước D−3, người đại diện được sửa thông tin đúng quyền truy cập. Sau mốc đó, điều hành tiếp nhận sửa thông tin và phải đồng bộ lại bên nhận danh sách.
- Đổi số người, nhóm tuổi hoặc thay người có ảnh hưởng chi phí phải được điều hành xem xét; không tự thay đổi giá/chỗ bằng thao tác sửa tên. Hủy một phần nhóm chưa thuộc luồng tự phục vụ V1.

**6. Nhận tiền và quá hạn**

Booking mới giữ chỗ 10 phút, tính từ lúc tạo. Nếu thời gian giữ vượt D−3 thì hạn giữ kết thúc tại D−3. Khách phải thấy thời hạn thực tế trước khi thanh toán.

Trước D−5, số phải trả ban đầu là 50% tổng booking. Nếu khoảng giữ chỗ dự kiến chạm hoặc vượt D−5, thu đủ ngay để không tạo một đơn mới cọc với hạn trả nốt đã tới. Từ D−5 trở đi, số phải trả ban đầu là 100%. Số phải trả được hiển thị trước khi khách xác nhận. Đủ khoản phải trả ban đầu trong hạn thì booking được nhận. Chỗ của booking đó tiếp tục được giữ và không trừ thêm lần thứ hai khi trả nốt.

Khoản chuyển thiếu chưa đủ số phải trả ban đầu vẫn được ghi nhận, nhưng chưa làm booking được nhận. Hết hạn giữ mà chưa đủ thì kết thúc giữ chỗ và hoàn khoản đã nhận, không áp phí khách hủy của booking đã được nhận. Khách có thể đặt lại khi còn chỗ.

Booking đã cọc phải trả đủ chậm nhất D−5, với điều kiện chuyến đã xác nhận khởi hành. Thanh toán có thời điểm thành công đúng hạn vẫn được coi là đúng hạn dù người vận hành xem sau đó.

Mỗi khoản thu phải ghi số tiền, thời điểm, phương thức, mã đối chiếu/chứng từ và người ghi nếu nhập thủ công. Thanh toán trực tuyến có kết quả được xác minh; chuyển khoản/tiền mặt chỉ ghi nhận khi công ty thực nhận. Cùng một giao dịch không được tính tiền hai lần.

Nhắc khoản còn lại ngay khi xác nhận khởi hành và nhắc cuối trước hạn thu một ngày nếu vẫn còn thiếu. Nếu khách đã trả đủ thì không nhắc nợ tiếp.

Quá hạn trả nốt đưa booking vào danh sách cần xử lý, không lập tức kết luận mất cọc. Điều hành kiểm tra đã nhận tiền chưa, có chờ đối soát hay có yêu cầu hủy trước đó không. Chậm nhất 24 giờ sau hạn thu, phải ghi một trong ba quyết định:

1. Đã có tiền đúng hạn: ghi khoản thu với thời điểm thực, tiếp tục booking.
2. Gia hạn: lưu lý do, hạn mới và thông báo khách. Mỗi booking được gia hạn một lần, hạn mới không muộn hơn D−3. Trong lúc gia hạn còn hiệu lực vẫn giữ chỗ.
3. Không thanh toán và không được gia hạn: hủy vì vi phạm hạn thanh toán, áp phí bằng 50% giá trị booking; tiền thực hoàn tính theo mục 8. Nếu đã trả đúng cọc 50% thì không có tiền hoàn. V1 không thu thêm nợ phí hủy.

Hết hạn gia hạn mà vẫn chưa đủ tiền thì xử lý như trường hợp 3. Nếu chính công ty chưa xác nhận được khả năng tổ chức hoặc đang có hồ sơ hủy cần giải quyết thì không gán lỗi chậm trả cho khách; điều hành xử lý nguyên nhân đó trước.

Giao dịch phát sinh sau khi booking đã hết giữ chỗ hoặc đã hủy không tự khôi phục booking. Công ty ghi nhận khoản cần đối soát; khoản thu nhầm/phát sinh sau khi đơn hết hiệu lực được hoàn riêng, không tự tính vào tiền phạt. Nếu khách vẫn muốn đi thì đặt booking mới khi còn đủ điều kiện.

Giao dịch thành công đúng hạn nhưng thông báo từ cổng về muộn là trường hợp khác: đối chiếu thời điểm giao dịch và tình trạng chỗ. Nếu giữ được booking thì ghi nhận đúng khoản thu; nếu chỗ đã giải phóng và không còn khả năng phục vụ thì hoàn đầy đủ khoản đó, không phạt khách vì phản hồi chậm của hệ thống. Không tự phục hồi booking vượt sức chứa.

**7. Ai làm gì?**

| Người | Trách nhiệm chính | Ranh giới |
|---|---|---|
| Khách/người đại diện | Đặt, trả tiền, khai người đi, gửi yêu cầu hủy | Không tự quyết định chuyến chạy hay đánh dấu đã thanh toán |
| Quản trị/điều hành | Mở bán, xác nhận khởi hành, xử lý nợ/hủy/hoàn, chuẩn bị dịch vụ và phân công | Mọi quyết định tiền và ngoại lệ có lý do, dấu thời gian |
| HDV | Nhận nhiệm vụ, nhận danh sách, điểm danh, báo cáo phát sinh | Trong V1 không xác nhận thanh toán hay tự đổi/hủy booking |

HDV xác nhận nhận đoàn nghĩa là đã tiếp nhận nhiệm vụ và thông tin phục vụ. Điểm danh nghĩa là ghi nhận sự có mặt. Cả hai không thay đổi số tiền đã thu.

**8. Khách chủ động hủy: bảng phí V1**

Chưa trả tiền thì khách hủy miễn phí. Đã trả tiền thì gửi yêu cầu có xác nhận tiếp nhận; mức phí được giữ theo thời điểm hệ thống nhận yêu cầu hợp lệ, không theo thời điểm nhân viên mở màn hình.

| Thời điểm nhận yêu cầu | Phí hủy tính trên tổng giá trị booking |
|---|---|
| Trước D−7 | 0% |
| Từ D−7 đến hết thời điểm D−3 | 50% |
| Sau D−3 đến trước D | 100% |
| Vắng mặt không có yêu cầu hợp lệ trước D | Không hoàn tiền |

Gọi T là tổng giá trị booking đã chốt, P là số tiền hợp lệ đã thu cho booking và F là phí theo bảng. Tiền cần hoàn = max(0, P − F). V1 giới hạn khoản giữ lại trong số đã thu, không tạo thêm khoản phải đòi khi phí lớn hơn P. Khoản trả thừa hoặc thanh toán trùng được hoàn riêng đầy đủ.

| Tổng booking | Đã trả | Lý do/thời điểm | Phí | Thực hoàn |
|---|---|---|---|---|
| 10 triệu | 5 triệu | Khách hủy trước D−7 | 0 | 5 triệu |
| 10 triệu | 5 triệu | Khách hủy tại D−5 | 5 triệu | 0 |
| 10 triệu | 10 triệu | Khách hủy tại D−5 | 5 triệu | 5 triệu |
| 10 triệu | 10 triệu | Khách hủy tại D−2 | 10 triệu | 0 |
| 10 triệu | 5 triệu | Công ty hủy trước khi thực hiện | 0 | 5 triệu |

Trang khách và quản trị đều hiển thị tổng giá, đã thu, phí và thực hoàn. Không dùng riêng câu “hoàn 50%” để giải thích tiền của đơn mới cọc.

Điều hành xử lý yêu cầu trong 24 giờ; yêu cầu gần khởi hành được ưu tiên. Yêu cầu hợp lệ theo điều khoản phải được giải quyết theo điều khoản; từ chối phải có căn cứ như yêu cầu trùng, sai người yêu cầu hoặc ngoài phạm vi, không tùy ý giữ khách bằng nút từ chối. Khi đang chờ, không tiếp tục đòi khoản còn lại như đơn không có yêu cầu hủy.

Nếu khách gửi trước giờ đi nhưng nhân viên xử lý sau giờ đi, hồ sơ vẫn được xử lý theo mốc gửi và hoàn cảnh thực tế; không tự coi khách là vắng mặt mất tiền chỉ vì xử lý chậm. Đơn đã hủy không được dùng để đi tour. Nếu yêu cầu đang chờ nhưng khách đổi ý muốn đi, phải rút yêu cầu và được điều hành xác nhận trước khi phục vụ để tránh vừa đi vừa nhận hoàn.

Khi hủy có hiệu lực, chỗ không còn thuộc booking. Chỗ chỉ được bán lại nếu chuyến vẫn nhận khách, còn nguồn lực phục vụ và chưa tới D−3. Sau D−3, chỗ trống không có nghĩa được tự mở bán lại.

**9. Công ty hủy và thực hiện hoàn tiền**

Công ty hủy trước khi chuyến bắt đầu: thông báo lý do, ngừng bán, hủy các booking liên quan và hoàn 100% số tiền hợp lệ đã nhận. Không dùng bảng phí khách hủy. Khách chưa trả tiền không có khoản hoàn.

Trong V1, khi không chạy được chuyến, phương án chuẩn là hủy và hoàn. Không tự chuyển khách sang ngày khác. Nếu sau này giữ tính năng chuyển/ghép đổi ngày, phải bổ sung quy trình khách chọn phương án, hạn phản hồi và quyền từ chối trước khi sử dụng chung với bộ quy tắc này.

Hoàn tiền gồm hai việc: xác định nghĩa vụ và thực chuyển tiền. Sau khi duyệt, số còn phải hoàn = nghĩa vụ hoàn trừ tổng tiền đã hoàn hợp lệ. Chỉ đánh dấu đã hoàn đủ khi có giao dịch/chứng từ đủ số tiền; bấm duyệt không tự tạo bằng chứng đã chuyển.

Mục tiêu phục vụ của V1: gửi xác nhận tiếp nhận yêu cầu ngay; xử lý yêu cầu trong 24 giờ; thực hiện khoản hoàn trong 5 ngày làm việc sau khi xác định nghĩa vụ và có đủ thông tin nhận hoàn. Ngày làm việc tính thứ Hai đến thứ Sáu, trừ ngày nghỉ lễ được cấu hình. Thời điểm tiền về có thể phụ thuộc đơn vị thanh toán; nếu chưa xong phải hiển thị tiến độ và lý do. Thời gian này là cam kết đề xuất của đồ án, chưa khẳng định hệ thống hiện có theo dõi được.

Ưu tiên hoàn về phương thức/tài khoản đã thanh toán. Nếu không thực hiện được qua cổng, điều hành cần xác minh người nhận và ghi lý do, chứng từ phương án hoàn khác. Không dùng thông tin tài khoản bất kỳ mà chưa xác minh.

Sự cố sau khi chuyến đã thực hiện một phần được ghi nhận là sự cố/dừng sớm, không đổi thành “chưa từng tổ chức”. Điều hành ghi dịch vụ đã thực hiện, phần chưa cung cấp và phương án bồi hoàn được thống nhất. Không tự áp hoàn 100% toàn tour hoặc phí khách hủy cho mọi sự cố giữa hành trình.

**10. Trạng thái phải trả lời đúng câu hỏi**

| Nhóm thông tin | Các ý nghĩa cần phân biệt |
|---|---|
| Booking | Giữ chỗ tạm; đã nhận booking; đã hủy; đã hoàn thành sử dụng |
| Tiền thu | Chưa thu; đã cọc/còn thiếu; đã thu đủ; đang đối soát |
| Tiền hoàn | Không có nghĩa vụ; còn phải hoàn; đã hoàn một phần; đã hoàn đủ |
| Chuyến | Chờ quyết định; đã xác nhận khởi hành; đang thực hiện; hoàn thành; đã hủy |
| Nhận khách | Đang nhận; tạm hết chỗ; đã đóng nhận theo hạn |

Đây là các ý nghĩa phải thể hiện, không bắt buộc tạo đúng từng ấy cột dữ liệu hay màn hình. Ví dụ hoàn toàn hợp lệ: booking đã nhận + đã cọc + chuyến chờ quyết định; hoặc booking đã hủy + còn phải hoàn.

**11. Chỗ cần đối chiếu với website hiện tại**

Các quan sát dưới đây dựa trên tệp cấu hình, quy tắc trạng thái và nội dung giao diện được đọc; chưa phải kết quả chạy website thật. Cấu hình mặc định có thể bị ghi đè khi triển khai.

| Điểm hiện có | Theo V1 đã chốt | Ý nghĩa công việc |
|---|---|---|
| Mặc định cọc 50%, giữ 10 phút | Giữ hai giá trị này | Kiểm chứng mốc hết hạn và số tiền thực tế |
| Mặc định hạn trả nốt trước 10 ngày | Chuyển thành D−5 | Cần cập nhật xử lý, thông báo và dữ liệu thử cùng nhau |
| Hạn danh sách mặc định D−3 | Giữ D−3 | Tách ý nghĩa với hạn thu và quyết định chạy |
| Quy tắc chuyến hiện chỉ nhận booking khi trạng thái đang mở bán | Chuyến đã xác nhận vẫn bán đến D−3 nếu còn chỗ | Đây là thay đổi nghiệp vụ cần thực hiện; tài liệu chưa làm nó hoạt động |
| Có bảng hoàn theo nhiều bậc | V1 dùng ba bậc phí tại mục 8 | Chính sách mới phải được lưu theo phiên bản; không áp hồi tố cho đơn cũ |
| Có xử lý tự động quá hạn | V1 đưa qua quyết định điều hành/gia hạn có ghi nhận | Cần xem lại tác vụ nền và tránh hai cách xử lý cùng tồn tại |
| HDV có xác nhận đơn kèm khoản thu | V1 HDV nhận nhiệm vụ và điểm danh | Làm rõ quyền và tên thao tác |
| Ghép có thể đổi ngày trước khi hỏi từng khách | Ngoài luồng cốt lõi V1 | Nếu giữ phải bổ sung quy tắc chấp thuận trước khi tích hợp |
| Đã có sân thử nghiệm theo kịch bản | Dùng lại sau khi cập nhật kỳ vọng theo V1 | Kịch bản cũ báo đạt không chứng minh phù hợp V1 |

Không chỉ sửa phần chữ để công bố V1 trong khi xử lý còn theo quy tắc cũ. Khi áp dụng, phải kiểm tra cả thông báo, điều khoản gắn với đơn, công nợ, lịch nền và các màn hình liên quan. Với dữ liệu demo nên tạo bộ mới; đơn cũ giữ giá và phiên bản chính sách đã đồng ý.

Bằng chứng để nhóm tìm lại: [cấu hình booking](../../server/config/booking.php), [trạng thái chuyến](../../server/app/Enums/ScheduleStatus.php), [chính sách hiện tại](../../client/src/pages/PolicyPage.tsx), [sân thử](../../client/src/pages/admin/SandboxLab.tsx).

**12. Ba câu chuyện để giải thích với hội đồng**

1. Bình thường: booking 10 triệu, cọc 5 triệu. Đến D−7 công ty xác nhận chạy; khách trả nốt trước D−5; khai đủ trước D−3; HDV phục vụ. Cần chỉ được booking, danh sách và hai khoản thu.
2. Khách hủy: booking đã trả đủ 10 triệu, yêu cầu tại D−5. Phí 5 triệu, nghĩa vụ hoàn 5 triệu. Duyệt xong chưa phải hoàn xong; khi ghi giao dịch hoàn đủ thì nghĩa vụ mới hết.
3. Công ty không chạy: đến D−7 thiếu khách hoặc chưa bảo đảm dịch vụ. Công ty hủy và hoàn đúng tiền đã nhận; không áp phí lỗi của khách. Nếu đã nhận 5 triệu thì hoàn 5 triệu.

Câu trả lời ngắn cho “vì sao 7–5–3?”: nhóm chọn thời gian theo thứ tự cam kết tổ chức → khách hoàn tất tiền → công ty chốt danh sách. Các mốc là chính sách cấu hình cho phạm vi đồ án, không tự suy ra từ yêu cầu của mọi nhà cung cấp.

**13. Việc giao cho nhóm sau khi dùng V1 làm căn cứ**

Bạn phụ trách quy tắc tiền/chỗ, các hạn và tích hợp. Thành viên 1 phụ trách khách hiểu đúng giá, trạng thái và khai người đi. Thành viên 2 thao tác hủy/hoàn và đối chiếu kết quả với bảng tiền ở mục 8. Thành viên 3 thao tác điều hành/HDV và giải thích từng bước nhận đoàn, điểm danh, báo cáo.

Mỗi người nhận dữ liệu mẫu tại giai đoạn cần thử, không phải chờ ngày thật từ lúc đặt. Người phụ trách chính chuẩn bị các bộ: đang bán; đã cọc; có yêu cầu hủy; chuẩn bị đi; đang đi; đã kết thúc. Mốc cần thử gồm trước hạn, đúng hạn và sau hạn; tác vụ nền liên quan cũng phải được chạy. Mỗi lần thử dùng dữ liệu có thể tạo lại và môi trường riêng để tránh tác động chéo.

Mỗi task ghi bốn điều: dữ liệu đầu vào; thao tác; kết quả theo V1; kết quả quan sát. Kết quả khác V1 cần được xác định là lỗi thực hiện hay quy tắc V1 cần điều chỉnh. Không sửa kỳ vọng chỉ để kịch bản báo đạt.
