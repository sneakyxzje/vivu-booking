**Phiếu giao việc — Thành viên 1: khách đặt tour và khai người đi**

Trạng thái: task để thực hiện, chưa phải báo cáo đã hoàn thành. Mức độ: dễ đến trung bình. Người chịu trách nhiệm quy tắc tiền/chỗ là trưởng nhóm. Căn cứ: [Quy tắc V1](../QUY_TAC_NGHIEP_VU_V1.md).

**Đây là task lập trình: bạn phải sửa code và bàn giao chức năng chạy được**

Các mục giải thích nghiệp vụ phía dưới giúp bạn hiểu thứ mình đang lập trình. Ảnh màn hình, bảng kiểm tra và phần giải thích là bằng chứng đi kèm; chỉ nộp tài liệu hoặc danh sách lỗi thì chưa hoàn thành task.

| Mã | Bạn phải code gì? | Đầu ra phải bàn giao |
|---|---|---|
| KH-01A | Sửa phần chọn chuyến trong TourDepartures/TourRightSidebar và dữ liệu đưa sang BookingTour: hiện trạng thái khởi hành riêng với tình trạng còn nhận khách; nối lựa chọn chuyến và lý do không đặt được | Khách chọn đúng chuyến; chuyến đã xác nhận vẫn đặt được khi CORE cho phép; có lý do rõ khi bị chặn |
| KH-01B | Sửa khối tổng tiền, nút thanh toán, hạn giữ và thông báo tại BookingTour, BookingSuccess, PaymentResult; nối số tiền/hạn/trạng thái từ xử lý chung, xử lý đang gửi và lỗi | Đơn cọc 50% hiện đúng khoản trả ngay và khoản thiếu; đơn thu đủ hiện đúng tổng tiền; không tự báo trả đủ khi mới cọc |
| KH-01C | Sửa phần đơn của khách và biểu mẫu yêu cầu hủy tại MyBookingsTab/BookingLookup theo cách các trang hiện có chia sẻ dữ liệu; sửa RefundPolicyCard; nối thao tác gửi/rút yêu cầu và tải lại kết quả từ CORE | Khách gửi được yêu cầu thật, thấy mức phí/tiền hoàn và trạng thái xử lý; không có trạng thái thành công giả chỉ lưu trong trình duyệt |
| KH-02A | Sửa PassengerDeclaration và phần khai người đi dùng chung: các dòng theo người đã đặt, kiểm tra nhập liệu, tiến độ khai đủ, thông báo lỗi và quyền lưu; nối lưu/đọc dữ liệu qua API hiện có hoặc hợp đồng CORE bàn giao | Khai và lưu được danh sách; tải lại vẫn còn; lỗi và việc hết quyền tự sửa có hướng dẫn đúng |
| KH-02B | Sửa nội dung và cách lấy dữ liệu chính sách trong PolicyPage cùng các đoạn hướng dẫn liên quan; không gõ đè kết quả CORE hoặc chính sách của booking cũ | Nội dung cho đơn V1 khớp xử lý V1, hạn và phí được trình bày thống nhất |

Bạn phụ trách React/TypeScript, phần gọi dịch vụ và kiểu dữ liệu cần thiết trong phạm vi trên. Công thức tiền, OTP/cổng thanh toán, giữ chỗ và điều kiện nghiệp vụ phía máy chủ thuộc trưởng nhóm. Nếu một phần hiện có đã đáp ứng thì giữ lại và nối vào luồng hoàn chỉnh, không viết lại để tăng số dòng code.

**Đọc đoạn này trước khi mở dự án**

Vivu là website của một công ty tổ chức tour. Khách chọn một chuyến có ngày đi và giá có sẵn, đặt cho một hoặc nhiều người, thanh toán rồi khai người sẽ đi. Bạn phụ trách những gì khách nhìn thấy và thao tác trong quá trình đó.

Ví dụ xuyên suốt: chị Lan đặt cho hai người lớn là Hoa và Minh, tổng 10 triệu đồng. Lan là người liên hệ nhưng không đi. Lan trả cọc 5 triệu, còn phải trả 5 triệu. Bạn phải giúp Lan biết đơn đã được nhận chưa, chuyến có chắc chạy chưa, còn tiền và thông tin nào phải bổ sung.

Tour là chương trình; chuyến là một lần tổ chức chương trình; booking là đơn của Lan; hành khách là Hoa và Minh. Đừng điền mặc định Lan thành hành khách bắt buộc.

**Mục tiêu cuối cùng**

Khách mở đơn là trả lời được: tôi đặt chuyến nào, cho mấy người, đã trả bao nhiêu, còn phải trả bao nhiêu, trước lúc nào và còn việc gì chưa làm. Bạn sửa và hoàn thiện các màn hiện có, không cần thiết kế lại toàn bộ website.

**Quy tắc bạn cần nhớ**

- Giữ chỗ tạm tối đa 10 phút. Cọc thông thường 50%; đặt sát hạn trả nốt phải trả đủ. Số phải trả và hạn thực tế lấy từ xử lý chung của trưởng nhóm.
- Công ty quyết định chạy trước 7 ngày, khách trả đủ trước 5 ngày, đóng nhận và chốt danh sách trước 3 ngày. Mọi hạn hiển thị ngày và giờ Việt Nam.
- Đã cọc chưa phải đã trả đủ. Đã nhận booking chưa phải chuyến chắc chắn chạy.
- Chuyến đã xác nhận khởi hành vẫn có thể nhận khách nếu còn chỗ và còn hạn.
- Khách hủy và công ty hủy có quy tắc tiền khác nhau. Bạn hiển thị kết quả tính sẵn, không tự dựng công thức khác trong giao diện.
- Khách có quyền xem/sửa đơn của mình; biết một mã hoặc đoán đường dẫn không tự động có mọi quyền.

**Màn hình cần mở và nơi bắt đầu tìm**

Các đường dẫn dưới đây đã có trong dự án. Phần `:slug`, `:id`, `:publicToken` là thông tin của dữ liệu mẫu do trưởng nhóm cấp, không gõ nguyên dấu hai chấm vào địa chỉ.

| Màn | Đường dẫn hiện có | File bắt đầu đọc/sửa |
|---|---|---|
| Chi tiết tour và chọn chuyến | `/tours/:slug` | [TourDepartures](../../../client/src/components/TourDepartures.tsx), [TourRightSidebar](../../../client/src/components/TourRightSidebar.tsx), [TourLeftDetails](../../../client/src/components/TourLeftDetails.tsx) |
| Biểu mẫu đặt | `/tours/:slug/booking` | [BookingTour](../../../client/src/pages/BookingTour.tsx) |
| Kết quả đặt và thanh toán | `/booking-success/:id`, `/payment-result` | [BookingSuccess](../../../client/src/pages/BookingSuccess.tsx), [PaymentResult](../../../client/src/pages/PaymentResult.tsx) |
| Tra cứu / đơn của tôi | `/booking-lookup`, `/my-bookings` | [BookingLookup](../../../client/src/pages/BookingLookup.tsx), [MyBookingsTab](../../../client/src/components/profile/MyBookingsTab.tsx) |
| Khai người đi | `/bookings/:publicToken/passengers` | [PassengerDeclaration](../../../client/src/pages/PassengerDeclaration.tsx) |
| Chính sách và mức hoàn | `/chinh-sach`, phần hủy trong đơn | [PolicyPage](../../../client/src/pages/PolicyPage.tsx), [RefundPolicyCard](../../../client/src/components/RefundPolicyCard.tsx) |

Đây là điểm tìm phần liên quan; không phải yêu cầu sửa mọi dòng của tất cả các file.

**KH-01A — Làm rõ chuyến và giá trước khi đặt**

Vì sao: khách quyết định mua dựa trên thời gian, dịch vụ và giá. Chỉ hiện “còn chỗ” khiến khách dễ nghĩ chuyến đã chắc chắn tổ chức.

Việc cần làm:

1. Mở chi tiết một tour mẫu. Kiểm tra khách chọn được đúng ngày/giờ chuyến và biết ngày/giờ về, điểm đón, số chỗ còn.
2. Đặt thông tin “Chuyến đang chờ xác nhận khởi hành” hoặc “Chuyến đã xác nhận khởi hành” cạnh lựa chọn chuyến, theo dữ liệu trưởng nhóm trả về.
3. Khi không đặt được, nêu đúng nguyên nhân: hết chỗ, đã đóng nhận khách hoặc chuyến đã hủy. Không dùng một câu “Có lỗi” cho tất cả.
4. Hiển thị giá người lớn/trẻ em/em bé và tiêu chuẩn đi kèm theo sản phẩm. Với lưu trú, trình bày cách bố trí phòng đã được công ty xác định; không tự hứa phòng đơn, giường riêng hay dịch vụ chưa xác nhận.
5. Kiểm tra nút đặt đưa đúng chuyến vừa chọn sang biểu mẫu. Khi đổi chuyến, giá/hạn phải được nạp lại theo chuyến mới.

Xong khi: khách xem và chọn chuyến không phải đoán công ty đã cam kết chạy hay chưa; dữ liệu chuyến ở trang đặt khớp lựa chọn.

**KH-01B — Làm rõ tiền phải trả và kết quả thanh toán**

Vì sao: “tổng đơn”, “trả hôm nay” và “còn phải trả” là ba số có thể khác nhau.

1. Trong phần tóm tắt đơn, tách số người theo nhóm giá, tổng tiền trước/sau giảm giá nếu có, khoản phải trả lần này và khoản còn lại.
2. Với ví dụ tổng 10 triệu được cọc: ghi “Thanh toán cọc 5.000.000đ”; phần còn lại ghi 5.000.000đ kèm hạn cụ thể.
3. Với chuyến phải thu đủ: ghi “Thanh toán 10.000.000đ”, không ghi “Cọc 100%”. Không tự suy từ lịch máy người dùng; sử dụng quyết định từ xử lý chung.
4. Khi thanh toán thất bại hoặc đang xác minh, giữ thông báo đúng tình trạng, có cách tải lại/tra cứu. Không đánh dấu đã trả chỉ vì khách quay về từ cổng.
5. Sau cọc, hiển thị “Đã nhận cọc”, số còn thiếu và bước tiếp theo. Sau thu đủ, hiển thị “Đã thanh toán đủ”; vẫn trình bày trạng thái chuyến riêng.
6. Nút bấm đang gửi phải chống bấm lặp ở giao diện; lỗi gửi không xóa thông tin khách vừa nhập một cách không cần thiết. Việc chống ghi tiền/booking trùng ở máy chủ do trưởng nhóm làm.

Không thay đổi tích hợp cổng, xác minh giao dịch, OTP hoặc quy tắc giữ chỗ. Nếu các phần đó trả sai thì ghi tình huống và chuyển trưởng nhóm.

**KH-01C — Thống nhất tra cứu và phần khách gửi yêu cầu hủy**

Vì sao: cùng một đơn mở từ email, mã tra cứu hoặc tài khoản phải cho cùng câu trả lời.

1. Kiểm tra ở trang kết quả, tra cứu và đơn của tôi đều thấy cùng chuyến, người đi, tổng tiền, đã trả, còn thiếu và hạn.
2. Ở đơn chưa trả tiền, dùng thao tác hủy miễn phí khi xử lý chung cho phép. Ở đơn đã trả, dùng “Gửi yêu cầu hủy”.
3. Trước khi gửi, hiện bốn số do máy chủ cung cấp: tổng đơn, đã trả, phí hủy, thực hoàn. Ví dụ đã trả đủ 10 triệu, hủy tại D−5: phí 5 triệu, thực hoàn 5 triệu.
4. Khi gửi thành công, hiện đã tiếp nhận và thời điểm gửi. Không đổi ngay thành “Đã hoàn tiền”. Nếu yêu cầu đang chờ, nêu đang chờ xử lý và không mời trả tiếp như đơn bình thường.
5. Hiện kết quả xử lý, lý do từ chối nếu có và trạng thái hoàn tiền. Nếu được phép rút yêu cầu, dùng đúng quyền và thông báo từ xử lý chung.
6. Trường hợp sau giờ đi không được tạo yêu cầu hủy mới thông thường thì giải thích cách liên hệ. Hồ sơ gửi hợp lệ trước giờ đi vẫn phải xem được; không tự che vì đồng hồ đã qua giờ đi.

Bạn sở hữu phần KHÁCH gửi và xem yêu cầu. Thành viên 2 sở hữu phần QUẢN TRỊ xử lý. Hai bên cùng dùng một hồ sơ do trưởng nhóm cung cấp.

**KH-02A — Hoàn thiện khai hành khách**

Vì sao: người đặt có thể không đi và thường chưa có đủ thông tin của cả nhóm ngay lúc đặt.

1. Dựng số dòng đúng cơ cấu người đã đặt; không bắt người đại diện xuất hiện trong danh sách.
2. Hiển thị tên, ngày sinh, nhóm khách và các thông tin/giấy tờ cần thiết mà sản phẩm và xử lý chung yêu cầu. Nêu lỗi cạnh người đang thiếu hoặc sai thông tin.
3. Hiển thị tiến độ, ví dụ “Đã khai đủ 1/2 hành khách”. “Đã khai đủ” phải theo kết quả kiểm tra hợp lệ, không chỉ đếm ô tên có chữ.
4. Khi lưu thành công, mở lại trang phải còn dữ liệu vừa lưu. Khi lưu thất bại, giữ thông tin đang nhập và nêu rõ lỗi.
5. Trước hạn cho phép sửa, hiện nút lưu. Sau hạn, thông báo danh sách cần điều hành hỗ trợ sửa. Không tự đổi số người/nhóm giá để né lỗi ngày sinh.
6. Giữ quy trình xác thực hiện có; thử cả khách có tài khoản và khách đặt không có tài khoản. Không bỏ bước xác minh để việc demo dễ hơn.

Trưởng nhóm chịu trách nhiệm kiểm tra quyền, nhóm tuổi và ảnh hưởng giá/chỗ ở máy chủ. Bạn bảo đảm người dùng nhận và hiểu kết quả đó.

**KH-02B — Đồng bộ chính sách và câu chữ**

1. Sửa mô tả thanh toán sang lịch 7–5–3 và bảng phí ba bậc của V1 khi xử lý V1 sẵn sàng tích hợp.
2. Ghi rõ quá hạn thanh toán cần được điều hành xử lý theo V1; bỏ lời khẳng định tự mất cọc ngay nếu không còn đúng.
3. Phân biệt điều khoản chung đang áp dụng cho đơn mới với chính sách đã gắn vào booking cũ. Không lấy chính sách mới thay quyền lợi đơn cũ.
4. Rà những lời hứa liên quan đổi/ghép, báo giá đoàn riêng, thu hộ HDV. Đánh dấu phần cần tách khỏi luồng V1 cho trưởng nhóm; không tự xóa route hoặc sửa quyền hệ thống.
5. Với email/hợp đồng cần sửa câu chữ, liệt kê mẫu và nội dung mong muốn cho trưởng nhóm tích hợp cùng dữ liệu máy chủ; không đổi cách tính hoặc lịch gửi thư.

**Bạn cần trưởng nhóm bàn giao gì?**

Các bộ sau là yêu cầu chuẩn bị, chưa khẳng định đang có sẵn. Nhận mã/đường dẫn thật trong database thử của bạn.

| Mã mẫu | Tình huống cần nhận |
|---|---|
| KH-A | Chuyến đang bán, chờ xác nhận, còn chỗ |
| KH-B | Chuyến đã xác nhận, còn trước hạn đóng bán, còn chỗ |
| KH-C | Booking 10 triệu đã cọc 5 triệu, còn 5 triệu và hạn cụ thể |
| KH-D | Booking trả đủ; một người đã khai, một người còn thiếu |
| KH-E | Booking sau hạn tự sửa danh sách |
| KH-F | Booking có yêu cầu hủy đang chờ, một bản khác đã duyệt còn phải hoàn |
| KH-G | Chuyến đã đóng bán, booking hết giữ chỗ, và kết quả thanh toán đang đối soát |

Trưởng nhóm phải trả được số tiền, hạn thực tế, trạng thái và quyền thao tác. Tên trường kỹ thuật do trưởng nhóm thống nhất; các nhãn trong phiếu này không phải API đã được triển khai.

**Các bài tự kiểm tra bắt buộc**

| Mã | Làm gì | Kết quả cần thấy |
|---|---|---|
| KH-T01 | Chọn chuyến KH-A rồi mở biểu mẫu đặt | Đúng ngày/giờ và trạng thái chờ xác nhận |
| KH-T02 | Mở KH-B khi còn chỗ | Có thể đặt dù chuyến đã xác nhận |
| KH-T03 | Mở KH-C qua tra cứu và qua tài khoản | Cùng tổng 10 triệu, đã trả 5 triệu, còn 5 triệu; không báo trả đủ |
| KH-T04 | Dùng bộ phải trả đủ ngay | Hiển thị toàn bộ khoản phải trả, không mời cọc 50% |
| KH-T05 | Dùng kết quả thanh toán thất bại/đang xác minh | Không tự báo thành công; biết cách kiểm tra tiếp |
| KH-T06 | Lan đặt cho Hoa và Minh | Khai được hai người đó, không bắt Lan phải đi |
| KH-T07 | KH-D khai thiếu người rồi bổ sung và tải lại | Tiến độ thay đổi đúng và dữ liệu còn sau tải lại |
| KH-T08 | KH-E mở sửa danh sách | Nêu đúng giới hạn và hướng dẫn liên hệ |
| KH-T09 | Đơn trả đủ 10 triệu gửi hủy tại D−5 | Hiện phí 5 triệu, thực hoàn 5 triệu, gửi xong là chờ xử lý |
| KH-T10 | Mở đơn đã duyệt nhưng chưa chuyển tiền | Hiện còn phải hoàn, không báo đã nhận tiền |
| KH-T11 | Dùng tài khoản khác mở đơn thử không thuộc mình | Không đọc/sửa dữ liệu trái quyền; nếu máy chủ cho phép sai phải báo trưởng nhóm |
| KH-T12 | Đổi chuyến lựa chọn sang KH-G đã đóng bán | Không tạo mới được; nguyên nhân rõ; giữ thông tin cần thiết để chọn lại |

Các mốc thử do trưởng nhóm chuẩn bị. Không chỉnh ngày máy tính, rút ngắn chính sách hoặc tự cập nhật database để làm bài đạt. Dữ liệu mẫu dùng riêng để dựng giao diện phải có nhãn và không được tính là nghiệm thu luồng thật.

**Thứ tự làm và cách bàn giao**

Làm KH-01A → KH-01B → KH-02A → KH-01C → KH-02B. Có thể hoàn thiện bố cục/câu chữ khi chờ xử lý chung, nhưng phần thiếu kết nối phải ghi “chờ CORE”.

Bàn giao: danh sách file đã sửa; đường dẫn/mã dữ liệu từng bài KH-T; kết quả mong đợi và quan sát; ảnh các tình huống cọc, thiếu hành khách, chờ hủy; lỗi hoặc phần còn chờ. Nếu sửa TypeScript/React thì chạy `npm run build` trong `client` và ghi kết quả, cùng các bài thao tác ở trên.

Tự giải thích được ba câu: “Vì sao nhận cọc chưa chắc chuyến chạy?”, “Người đặt khác người đi thế nào?”, “Vì sao gửi yêu cầu hủy chưa phải đã hoàn tiền?”. Trả lời bằng ví dụ 10 triệu ở đầu phiếu.
