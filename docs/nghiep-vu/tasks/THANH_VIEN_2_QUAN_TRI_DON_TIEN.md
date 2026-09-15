**Phiếu giao việc — Thành viên 2: quản trị booking, khoản còn thiếu và hoàn tiền**

Trạng thái: task để thực hiện, chưa phải báo cáo hoàn thành. Mức độ: trung bình. Căn cứ: [Quy tắc V1](../QUY_TAC_NGHIEP_VU_V1.md). Trưởng nhóm phụ trách mọi công thức và quyết định ảnh hưởng tiền/chỗ.

**Đây là task lập trình: bạn phải sửa code và bàn giao chức năng chạy được**

Bạn làm phần quản trị để nhân viên sử dụng được các xử lý do trưởng nhóm cung cấp. Các bài kiểm tra phía dưới là điều kiện nghiệm thu phần code, không phải toàn bộ công việc của bạn.

| Mã | Bạn phải code gì? | Đầu ra phải bàn giao |
|---|---|---|
| QL-01A | Sửa bảng/chi tiết BookingManagement, các tab FinanceHub và ReceivableManagement để hiển thị đúng các nhóm trạng thái, số tiền và hạn; nối liên kết tới đúng đơn/hồ sơ | Nhân viên mở một đơn thấy tình trạng booking, tiền và việc cần xử lý riêng biệt |
| QL-01B | Hoàn thiện biểu mẫu ghi nhận thu ở BookingManagement: trường nhập, kiểm tra định dạng, đang gửi/lỗi, gọi xử lý CORE và nạp lại số dư/lịch sử | Ghi khoản thu mẫu thành công qua máy chủ; sổ giao dịch và khoản thiếu cập nhật cùng nhau |
| QL-01C | Sửa danh sách quá hạn và thêm/hoàn thiện biểu mẫu gia hạn trong chi tiết booking: hạn mới, lý do, lịch sử hạn; nối hành động gia hạn hoặc hủy theo kết quả quyền từ CORE | Gia hạn một booking hoạt động qua máy chủ, không đổi ngày cả chuyến; trường hợp bị từ chối hiện đúng lý do |
| QL-02A | Sửa ChangeRequestManagement: danh sách/chi tiết, thời điểm gửi, các số tiền, biểu mẫu duyệt/từ chối và cập nhật sau thao tác | Xử lý được yêu cầu khách thật đã gửi; booking và nghĩa vụ hoàn phản ánh kết quả CORE, không đánh dấu đã hoàn ngay |
| QL-02B | Sửa RefundManagement và phần hiển thị sổ giao dịch liên quan: số phải hoàn/đã hoàn/còn lại, biểu mẫu ghi nhận hoàn, chứng từ, tải lại trạng thái | Ghi nhận được hoàn một phần hoặc đủ trên dữ liệu thử; lịch sử còn sau tải lại và hai màn không lệch số |

Bạn phụ trách React/TypeScript, phần gọi dịch vụ và kiểu dữ liệu cho các biểu mẫu trên. Trưởng nhóm làm công thức, quyền quyết định và bảo đảm ghi tiền/chỗ đúng ở máy chủ. Hợp đồng CORE chưa có thì ghi chờ kết nối, không dùng một phép tính hoặc dữ liệu lưu cục bộ thay cho chức năng thật khi bàn giao.

**Website đang làm gì và bạn giúp ai?**

Vivu bán chỗ trên chuyến tour của một công ty. Một booking có thể trả làm nhiều lần. Khi khách hủy, công ty có thể phải trả lại một phần tiền. Nhân viên văn phòng cần biết hôm nay phải thu tiền ai, xử lý yêu cầu nào và còn nợ khách nào.

Bạn làm màn hình cho nhân viên đó. Trong đồ án, tài khoản quản trị kiêm công việc bán hàng, điều hành và ghi nhận tiền; chưa cần thêm vai trò kế toán mới.

Ví dụ: booking của Lan tổng 10 triệu. Lan cọc 5 triệu thì công ty còn phải thu 5 triệu. Nếu Lan trả đủ rồi hủy tại mốc trước ngày đi 5 ngày, theo V1 phí là 5 triệu, công ty phải hoàn 5 triệu. Duyệt yêu cầu chỉ xác định nghĩa vụ; phải ghi nhận đã chuyển tiền thì mới biết nghĩa vụ đã hoàn tất.

**Các từ bạn phải phân biệt**

| Từ | Nghĩa |
|---|---|
| Booking/đơn | Đơn đăng ký một nhóm người trên một chuyến |
| Đã thu | Tiền hợp lệ công ty đã nhận cho booking |
| Phải thu/còn thiếu | Khoản khách còn phải trả |
| Phải hoàn | Khoản công ty có nghĩa vụ trả lại |
| Đã hoàn | Khoản công ty thực tế đã chuyển trả và có ghi nhận |
| Yêu cầu hủy | Hồ sơ khách gửi, có thời điểm và quá trình xử lý |
| Sổ giao dịch | Danh sách các lần thu và hoàn, có chứng từ/mã đối chiếu |

Một đơn đã hủy vẫn có thể còn phải hoàn. Một đơn được nhận vẫn có thể còn phải thu. Không gom mọi ý nghĩa vào một nhãn “đã xác nhận”.

**Màn hình và file bắt đầu**

| Màn | Đường dẫn hiện có | File |
|---|---|---|
| Quản lý đơn | `/admin/bookings` | [BookingManagement](../../../client/src/pages/admin/BookingManagement.tsx) |
| Yêu cầu hủy | `/admin/change-requests` | [ChangeRequestManagement](../../../client/src/pages/admin/ChangeRequestManagement.tsx) |
| Sổ giao dịch và các tab | `/admin/transactions` | [FinanceHub](../../../client/src/pages/admin/FinanceHub.tsx), [TransactionRegister](../../../client/src/pages/admin/TransactionRegister.tsx) |
| Khoản còn thiếu | `/admin/transactions?tab=receivables` | [ReceivableManagement](../../../client/src/pages/admin/ReceivableManagement.tsx) |
| Khoản còn phải hoàn | `/admin/transactions?tab=refunds` | [RefundManagement](../../../client/src/pages/admin/RefundManagement.tsx) |

Các đường `/admin/refunds` và `/admin/receivables` hiện chuyển về tab tương ứng. Sử dụng màn có sẵn; không tạo thêm một trang tài chính trùng chức năng.

**QL-01A — Làm rõ tình trạng một booking**

Vì sao: nhân viên cần quyết định xử lý mà không mở nhiều nơi và tự cộng tiền.

1. Trên danh sách/chi tiết, hiển thị mã booking, người liên hệ, tour, chuyến và số người.
2. Tách phần booking với phần tiền. Hiển thị tổng đơn, đã thu, còn thiếu; nếu có nghĩa vụ hoàn thì hiện phải hoàn, đã hoàn, còn phải hoàn.
3. Hiển thị hạn thu bằng ngày giờ thực tế từ máy chủ. Không lấy hạn chốt danh sách làm tên gọi thay thế.
4. Hiện trạng thái xử lý cần thiết: chờ đối soát, đang gia hạn, có yêu cầu hủy hoặc quá hạn cần xử lý. Khi không thể thao tác thì nêu lý do.
5. Từ đơn phải mở được lịch sử giao dịch và yêu cầu hủy liên quan; từ danh sách phải thu/phải hoàn phải đi về đúng đơn.

Ví dụ đạt: mở booking Lan là thấy “10 triệu / đã thu 5 triệu / còn thiếu 5 triệu / hạn ...”, không phải tự lấy tổng trừ các dòng giao dịch bằng máy tính.

**QL-01B — Thao tác ghi nhận khoản thu**

Vì sao: “xác nhận đơn” không đủ chứng minh công ty đã nhận tiền.

1. Sử dụng biểu mẫu thu hiện có. Ghi rõ đây là ghi khoản công ty đã thực nhận, không phải nhấn nút để giả thanh toán.
2. Biểu mẫu phải có số tiền, phương thức và mã đối chiếu/chứng từ; thời điểm thực nhận theo hợp đồng dữ liệu trưởng nhóm cung cấp. Người ghi lấy từ phiên đăng nhập, không cho gõ một người tùy ý.
3. Kiểm tra số tiền nhập hợp lệ ở giao diện; xử lý vượt số tiền, trùng giao dịch và tính tiền thuộc trưởng nhóm. Không âm thầm giảm số tiền người dùng nhập để khớp khoản thiếu.
4. Sau khi gửi thành công, tải lại số dư và sổ giao dịch. Đang gửi thì khóa nút gửi lặp; lỗi giữ thông tin cần sửa và không hiện thành công.
5. Khoản chờ đối soát phải được giải thích là đang kiểm tra; không trình bày như đã thu chắc chắn.

Chưa có trường/thao tác cần thiết thì báo trưởng nhóm bổ sung xử lý; không tự tạo thêm bút toán trong database từ giao diện.

**QL-01C — Làm rõ danh sách quá hạn và gia hạn**

Vì sao: V1 yêu cầu điều hành kiểm tra trước khi kết luận khách vi phạm hạn trả nốt.

1. Hiển thị danh sách đơn quá hạn cần xử lý và liên kết tới chi tiết. Ghi số tiền còn thiếu, hạn cũ và trạng thái xử lý.
2. Tại chi tiết, đưa ra các hành động xử lý chung cho phép: ghi nhận tiền đã có; ghi gia hạn; hủy vì không thanh toán. Đừng tự quyết định một đơn đủ điều kiện chỉ bằng cách kiểm tra ngày ở trình duyệt.
3. Gia hạn cần hạn mới và lý do; hiện thông báo sẽ gửi khách. V1 cho một lần, không muộn hơn D−3; trưởng nhóm kiểm tra điều kiện ở máy chủ.
4. Sau khi gia hạn, hiển thị hạn cũ, hạn mới và quyết định đã ghi. Không đổi mốc của cả chuyến để gia hạn một khách.
5. Đơn có yêu cầu hủy đang chờ hoặc vấn đề do công ty phải có hướng dẫn xử lý nguyên nhân, không hiện như khách cố tình không trả.
6. Không cho nhân viên nghĩ một nút hủy chỉ ẩn dòng khỏi bảng: trước khi gửi, tóm tắt booking bị hủy, khoản tiền bị giữ/được hoàn theo kết quả xử lý chung.

**QL-02A — Nhận và xử lý yêu cầu hủy**

Vì sao: cần phân biệt lúc khách gửi, lúc công ty duyệt và lúc tiền thực trả lại.

1. Danh sách yêu cầu hiện booking, người liên hệ, thời điểm gửi, lý do, tình trạng và số thực hoàn dự kiến.
2. Khi mở chi tiết, hiện tổng giá, đã thu, phí hủy, thực hoàn và mốc gửi làm căn cứ. Không tự tính lại phí theo hôm nay.
3. Theo V1: trước D−7 phí 0%; từ D−7 đến hết D−3 phí 50%; sau D−3 trước D phí 100% tổng đơn. Tiền hoàn không âm. Dùng bảng này để kiểm tra kết quả máy chủ, không chép công thức vào giao diện làm một nguồn tính khác.
4. Nút “Duyệt và hủy đơn” gửi quyết định thật. Sau thành công, làm mới đơn và nghĩa vụ hoàn; không tự ghi đã chuyển tiền.
5. Từ chối cần lý do có căn cứ và khách đọc được. Không dùng lý do mẫu cho mọi yêu cầu. Khi quyền bị chặn phải nêu lý do đúng từ máy chủ.
6. Yêu cầu gửi hợp lệ trước giờ đi nhưng được mở sau giờ đi vẫn cần xem và xử lý. Nếu máy chủ chặn sai, báo trưởng nhóm bằng dữ liệu mẫu; không tự bỏ kiểm tra trên giao diện để vượt chặn.
7. Khi cần ưu tiên, làm rõ yêu cầu gần giờ đi hoặc quá hạn xử lý 24 giờ. Trường hợp không có dữ liệu để xác định phải ghi phụ thuộc CORE, không tự ghi số giả.

Thành viên 1 làm phần khách gửi/xem. Bạn nhận cùng mã yêu cầu đó để xử lý, rồi nhờ thành viên 1 đối chiếu trạng thái khách sau mỗi quyết định.

**QL-02B — Ghi nhận khoản hoàn thực tế**

Vì sao: công ty có thể đã duyệt hoàn nhưng chưa chuyển, hoặc mới chuyển một phần.

1. Màn phải hoàn hiện người nhận đã được xác minh theo xử lý chung, booking, nghĩa vụ, đã hoàn và số còn phải hoàn.
2. Nút ghi nhận khoản hoàn mở biểu mẫu số tiền thực chuyển, phương thức và mã chứng từ/đối chiếu; dùng thời điểm giao dịch và người ghi theo xử lý chung.
3. Nếu chưa đủ thông tin nhận hoàn, hiển thị thiếu gì và hành động cần làm. Không điền một tài khoản mẫu vào hồ sơ thật.
4. Sau hoàn một phần, vẫn còn trong danh sách chưa trả đủ. Sau ghi đủ, chuyển sang nhóm đã trả xong và vẫn tra lại được lịch sử.
5. Số tiền vượt nghĩa vụ còn lại, trùng chứng từ hoặc gửi lặp phải nhận lỗi/được xử lý đúng từ máy chủ; không giả thành công bằng cách cập nhật mỗi bảng phía khách.
6. Hiển thị tiến độ xử lý và thời hạn hoàn theo dữ liệu CORE. Mốc 5 ngày làm việc phải do xử lý chung tính; không đếm 5 ngày lịch trong giao diện.
7. Khi thử, chỉ ghi tiền mẫu trên môi trường thử. Phiếu này không giao thực hiện chuyển tiền thật.

**Trưởng nhóm phải giao dữ liệu/thao tác nào?**

| Mã mẫu | Trạng thái bắt đầu |
|---|---|
| QL-A | Booking 10 triệu, đã cọc 5 triệu, chưa quá hạn |
| QL-B | Còn nợ và quá hạn; bản khác đang đối soát hoặc có yêu cầu hủy |
| QL-C | Booking đã được gia hạn một lần, kèm hạn cũ/mới |
| QL-D | Yêu cầu tại D−5, booking đã trả đủ 10 triệu; máy chủ tính hoàn 5 triệu |
| QL-E | Yêu cầu tại D−5, booking mới cọc 5 triệu; máy chủ tính hoàn 0 |
| QL-F | Công ty hủy, booking đã thu 5 triệu; phải hoàn 5 triệu |
| QL-G | Phải hoàn 5 triệu, đã hoàn 2 triệu; còn 3 triệu |
| QL-H | Hồ sơ gửi trước giờ đi, hiện đã qua giờ đi nhưng chưa xử lý |

Các mã là tên bộ cần chuẩn bị, không phải ID/seed chắc chắn đã tồn tại. Trưởng nhóm cấp mã thật, quyền được thao tác, kết quả tiền/hạn và các thao tác xử lý. Trong lúc thiếu CORE, dựng phần trình bày bằng dữ liệu ghi nhãn mẫu và báo chờ kết nối; không tự viết quy tắc mới.

**Các bài tự kiểm tra bắt buộc**

| Mã | Thao tác | Kỳ vọng |
|---|---|---|
| QL-T01 | Mở QL-A từ danh sách và tab phải thu | Cùng số còn thiếu 5 triệu, cùng hạn |
| QL-T02 | Ghi khoản trả nốt 5 triệu trên dữ liệu thử | Còn thiếu 0; có giao dịch mới; tải lại vẫn đúng |
| QL-T03 | Ghi khoản thu với số âm hoặc nhập sai | Không báo thành công; giữ thông tin và nêu lỗi |
| QL-T04 | Xử lý QL-B bằng gia hạn hợp lệ | Giữ lịch sử hạn cũ/mới, không hủy đơn |
| QL-T05 | Gia hạn QL-C lần nữa hoặc chọn hạn sau D−3 | Bị từ chối có lý do, không lách bằng đổi ngày chuyến |
| QL-T06 | Duyệt QL-D | Phí 5 triệu, nghĩa vụ hoàn 5 triệu, chưa báo đã hoàn |
| QL-T07 | Duyệt QL-E | Phí 5 triệu, thực hoàn 0; không tạo khoản hoàn âm |
| QL-T08 | Mở QL-F | Phải hoàn đủ 5 triệu; không áp phí khách hủy |
| QL-T09 | QL-G ghi hoàn thêm 3 triệu | Còn phải hoàn 0; lịch sử có các lần hoàn 2 và 3 triệu |
| QL-T10 | Thử ghi hoàn 4 triệu cho QL-G còn nợ 3 triệu | Nhận xử lý lỗi từ CORE, không đổi số dư sai |
| QL-T11 | Mở QL-H sau giờ đi | Hồ sơ còn truy cập; xử lý theo thời điểm gửi hợp lệ |
| QL-T12 | Thành viên 1 mở lại đơn sau bạn duyệt/ghi hoàn | Hai phía cùng kết quả và phân biệt đang hoàn/đã hoàn đủ |
| QL-T13 | Tải danh sách lỗi hoặc rỗng | Lỗi tải không được trình bày như “không còn khoản phải trả” |

Không chỉnh ngày máy tính hay bảng phí để thử. Nhận bộ trước/đúng/sau hạn từ trưởng nhóm. Sân thử tự báo đạt chưa thay thế các lần bấm biểu mẫu và kiểm tra quyền.

**Bàn giao và ranh giới**

Làm QL-01A → QL-01B → QL-02A → QL-02B → QL-01C theo độ sẵn sàng của CORE. Không sửa công thức, lịch nền hoặc điều kiện chỗ trong phần máy chủ; khi sai ghi mã dữ liệu, thao tác, kỳ vọng và kết quả để trưởng nhóm xử lý.

Bàn giao danh sách file, bảng QL-T đã chạy/chưa chạy, ảnh đủ chứng minh dòng tiền, phần chờ CORE. Chạy `npm run build` trong `client` khi có sửa TypeScript/React và ghi kết quả. Không coi build thành công là các tình huống nghiệp vụ đã đạt.

Tự giải thích: “Phải thu khác phải hoàn thế nào?”, “Duyệt hủy rồi vì sao vẫn còn việc?”, “Khách trả cọc 5 triệu và khách trả đủ 10 triệu có cùng số hoàn tại D−5 không?”.
