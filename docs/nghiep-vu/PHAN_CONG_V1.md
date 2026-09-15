**Vivu — task triển khai V1 cho nhóm bốn người**

Căn cứ duy nhất về quy tắc trong đợt này: [Quy tắc nghiệp vụ V1](QUY_TAC_NGHIEP_VU_V1.md). Đây là bảng giao việc đã chốt về phạm vi; các task bên dưới chưa được thực hiện bởi việc tạo tài liệu.

**Gửi phiếu riêng cho từng thành viên**

Đây là phân công LẬP TRÌNH. Mỗi thành viên phải sửa code, kết nối xử lý thật trong phạm vi được giao và bàn giao chức năng chạy được. Chỉ khảo sát, ghi lỗi, chụp ảnh hoặc viết tài liệu thì chưa hoàn thành. Mỗi phiếu đã có bảng “Bạn phải code gì?” và đầu ra cụ thể ngay đầu file.

Mỗi phiếu tự giải thích website và vai trò, rồi ghi màn hình/file, từng việc phải làm, lý do, dữ liệu đầu vào và bài nghiệm thu. Người nhận có thể bắt đầu từ phiếu của mình, không phải đọc hết dự án trước. Nếu chức năng đã đáp ứng thì kiểm chứng và giữ lại; không viết lại chỉ để đánh dấu đã làm task.

| Người | Phiếu giao việc chi tiết | Việc đầu tiên |
|---|---|---|
| Thành viên 1 | [Khách hàng và khai hành khách](tasks/THANH_VIEN_1_KHACH_HANG.md) | Code phần chọn chuyến và hiển thị tình trạng nhận khách ở KH-01A |
| Thành viên 2 | [Quản trị booking và tiền](tasks/THANH_VIEN_2_QUAN_TRI_DON_TIEN.md) | Code lại cách hiển thị booking, số tiền và hạn ở QL-01A |
| Thành viên 3 | [Điều hành và HDV](tasks/THANH_VIEN_3_DIEU_HANH_HDV.md) | Code phần danh sách đoàn theo nhóm/người và tình trạng khai đủ ở DH-01A |

Các phiếu là phần chi tiết hóa của các task bên dưới, không phải ba bộ quy tắc mới. Trưởng nhóm giữ các ngoại lệ và quyết định tiền/chỗ. Thành viên phụ trách một màn hoàn thiện cả phần xử lý thông thường trong phạm vi được ghi, không tự sửa quy tắc CORE.

**Đầu vào trưởng nhóm cần bàn giao để họ bắt đầu được**

1. Bản dự án cùng phiên bản, cách chạy đã thống nhất, URL môi trường thử và tài khoản theo vai trò. Không ghi mật khẩu thật vào tài liệu chia sẻ.
2. Mã booking/chuyến và đường dẫn mở các bộ KH-A…KH-G, QL-A…QL-H, DH-A…DH-F được mô tả trong từng phiếu. Đây là tên bộ cần chuẩn bị, chưa phải dữ liệu chắc chắn đang tồn tại.
3. Kết quả trạng thái, số tiền, hạn và quyền thao tác từ CORE; cách báo thiếu dữ liệu/thao tác. Không giao cho từng thành viên tự suy ra API hoặc tự viết một công thức khác.
4. Cách tạo lại dữ liệu trước/sau hạn và chạy xử lý thời gian trong môi trường thử. Nếu chưa có, phần bố cục dùng mẫu rõ nhãn; phần nghiệm thu thực tế ghi chờ CORE.

Mẫu ghi kết quả dùng chung: **Mã bài | Mã dữ liệu | Thao tác đã làm | Kỳ vọng theo phiếu | Kết quả thực tế | Đạt/Chưa đạt/Chờ CORE | Ảnh hoặc lỗi liên quan**. Đừng ghi đạt cho bài chưa chạy.

**Ranh giới phối hợp để tránh bỏ sót hoặc sửa trùng**

| Phần | Người làm | Người cung cấp xử lý/đối chiếu |
|---|---|---|
| Khách gửi và xem yêu cầu hủy | Thành viên 1 | CORE; thành viên 2 mở cùng yêu cầu để xử lý |
| Quản trị duyệt và ghi nhận hoàn | Thành viên 2 | CORE; thành viên 1 xem kết quả phía khách |
| Trạng thái chuyến/điều kiện chạy | Bạn | Thành viên 1 và 3 hiển thị kết quả |
| Danh sách đoàn, giao HDV, điểm danh | Thành viên 3 | CORE cấp quyền/dữ liệu; thành viên 2 đối chiếu tiền không đổi |
| Nội dung chính sách phía khách | Thành viên 1 | Bạn kiểm tra phù hợp quy tắc và chính sách gắn với booking |
| Email/hợp đồng và quyết định tài chính ở màn sự cố | Bạn | Thành viên 1/3 ghi yêu cầu nội dung hoặc vấn đề quan sát được |

Không coi màn “Bàn giao HDV giữa chừng” là màn bắt buộc để HDV nhận nhiệm vụ ban đầu. Việc nhận nhiệm vụ đã có ở “Chuyến được giao”; thành viên 3 dùng đúng hai khái niệm đó.

**Phạm vi chung**

Một công ty bán chỗ trên chuyến tour nội địa có sẵn. Giữ 10 phút, cọc 50%; quyết định khởi hành D−7, thu đủ D−5, đóng nhận và chốt danh sách D−3. Hủy theo ba bậc phí trong V1; công ty hủy trước khi thực hiện thì hoàn đủ tiền đã nhận. Khách được đặt nhiều người trong một booking thông thường.

Không phát triển trong đợt này: chuyển/ghép chuyến, báo giá đoàn riêng, HDV thu hộ. Tìm kiếm, tài khoản, mã giảm giá và các chức năng đang hoạt động khác được giữ trong phạm vi tương thích; không mở thêm task nâng cấp chúng. Các nhánh ngoài V1 phải được tách khỏi luồng đang nghiệm thu, gồm cả tác vụ có thể thay đổi tiền/chỗ.

**Bạn — phần khó và trách nhiệm tích hợp**

| Task | Công việc | Điều kiện hoàn thành |
|---|---|---|
| CORE-01 | Áp lịch 7–5–3; tách xác nhận chuyến với đóng nhận khách; thống nhất tên và điều kiện của các trạng thái | Chuyến xác nhận tại D−7 vẫn nhận khách đến D−3 nếu còn chỗ; từng hạn thể hiện cùng một thời điểm ở các vai trò |
| CORE-02 | Giữ chỗ, cọc/trả nốt, quá hạn và gia hạn; phí hủy, duyệt hủy/hoàn; thời điểm giao dịch và chỗ còn lại | Số tiền theo bảng V1; không hủy hai lần/ghi thu hai lần; yêu cầu hợp lệ không mất quyền vì nhân viên xử lý chậm |
| CORE-03 | Chuẩn bị dữ liệu thử tạo lại được, thao tác đưa tới mốc và chạy tác vụ liên quan; cập nhật kỳ vọng sân thử | Mỗi thành viên có bộ dữ liệu ở đúng giai đoạn, không phải tự sửa ngày hoặc chờ nhiều ngày |
| CORE-04 | Tách các nhánh ngoài V1, tích hợp phần của ba thành viên và kiểm tra ba câu chuyện cuối | Không còn đường thao tác/lệnh nền dùng quy tắc cũ gây thay đổi tiền/chỗ trái V1 |

Bạn cung cấp cho các thành viên: tên trạng thái và ý nghĩa; số tiền/hạn cần hiển thị; thao tác được phép và lý do bị chặn; danh tính người có quyền thực hiện. Nếu xử lý phía máy chủ chưa xong, họ có thể làm bố cục từ dữ liệu mẫu rõ nhãn; không công bố mẫu đó là kết quả chạy thật.

**Thành viên 1 — khách hàng, mức dễ đến trung bình**

| Task | Công việc | Điều kiện hoàn thành |
|---|---|---|
| KH-01 | Chuẩn hóa trang tour, biểu mẫu đặt và trang kết quả/tra cứu: giá, số tiền phải trả, hạn, chờ xác nhận chuyến/đã xác nhận | Khách mới cọc không được báo là đã trả đủ; còn chỗ không bị hiểu thành chắc chắn khởi hành |
| KH-02 | Hoàn thiện khai hành khách: số người, thông tin thiếu, hạn sửa và hướng dẫn sau hạn; đồng bộ nội dung chính sách/thông báo theo V1 | Booking hai người có đủ hai dòng; biết ai chưa khai; trước/sau hạn có hướng dẫn đúng; không tự đổi số tiền qua sửa tên |

Không tự tính phí, hạn hoặc quyết định booking có được nhận hay không. Hiển thị và sử dụng kết quả xử lý do CORE cung cấp. Chuẩn hóa mô tả tiêu chuẩn tour trên form hiện có; không tự bổ sung hệ thống phòng/phụ thu mới.

Dữ liệu nhận từ bạn: chuyến đang bán; booking mới cọc; booking đã trả đủ; danh sách còn thiếu; danh sách đã khóa quyền tự sửa.

**Thành viên 2 — màn quản trị booking và hoàn tiền, mức trung bình**

| Task | Công việc | Điều kiện hoàn thành |
|---|---|---|
| QL-01 | Hiển thị booking, phải thu và quá hạn: tổng đơn, đã thu, còn thiếu, hạn thu, quyết định gia hạn/hủy theo quyền CORE | Điều hành nhìn thấy khoản cần xử lý và lý do; không dùng hạn danh sách để giải thích hạn thu |
| QL-02 | Hoàn thiện màn nhận yêu cầu hủy, duyệt và ghi nhận tiền hoàn; thể hiện thời điểm gửi, phí, nghĩa vụ, đã hoàn và còn phải hoàn | Ví dụ trả đủ 10 triệu, hủy tại D−5: phải hoàn 5 triệu; duyệt xong vẫn còn phải trả, ghi hoàn đủ mới hết nghĩa vụ |

Sở hữu biểu mẫu, thao tác gọi xử lý và cách trình bày kết quả. Công thức, quyết định được phép và bảo đảm tiền/chỗ thuộc CORE. Không tự đặt thêm ngoại lệ hoặc sửa kỳ vọng để kết quả trông đúng.

Dữ liệu nhận từ bạn: booking còn thiếu; booking được gia hạn; yêu cầu hủy đã cọc; yêu cầu hủy trả đủ; booking đã hủy còn phải hoàn.

**Thành viên 3 — điều hành/HDV, mức trung bình**

| Task | Công việc | Điều kiện hoàn thành |
|---|---|---|
| DH-01 | Hoàn thiện danh sách đoàn, phân công/bàn giao và bảng chuẩn bị dịch vụ tối giản trên chuyến | Biết HDV nào nhận nhiệm vụ; danh sách khách thiếu ai; xe/lưu trú nếu có đã xác nhận hay đang chờ; mỗi cập nhật có người và thời điểm |
| DH-02 | Hoàn thiện nhận bàn giao, điểm danh, lý do vắng, ghi nhận phát sinh và báo cáo kết thúc | HDV thao tác trên chuyến được giao; điểm danh không đổi tiền; điều hành xem lại được thông tin |

Được phụ trách lưu/đọc thông tin phục vụ và các kiểm tra quyền tương ứng ở phần này. Quyết định xác nhận khởi hành, đóng bán và ảnh hưởng tài chính vẫn thuộc CORE. Bảng dịch vụ chỉ ghi nhận tình trạng và căn cứ, không xây thêm hệ thống đặt xe/khách sạn hay tính quyết toán.

Dữ liệu nhận từ bạn: chuyến chuẩn bị đi; chuyến đang thực hiện; chuyến đã kết thúc, có hành khách và HDV được phân công.

**Thứ tự thực hiện**

1. Bạn bắt đầu CORE-01 và CORE-03, cung cấp trạng thái, dữ liệu và cách gọi thao tác. Ba thành viên bắt đầu chỉnh phần trình bày và các biểu mẫu trong phạm vi của mình.
2. Bạn triển khai CORE-02; thành viên 1, 2 nối luồng với xử lý thật. Thành viên 3 hoàn thiện phần điều hành/HDV độc lập với công thức tiền.
3. Bạn hoàn tất CORE-04. Mỗi thành viên tự demo phần được giao và giải thích một trường hợp không được thực hiện thao tác.
4. Cả nhóm kiểm tra: đi bình thường; khách hủy; công ty hủy. Bổ sung trường hợp sát hạn và giao dịch/yêu cầu xử lý muộn do bạn phụ trách.

Mỗi người sử dụng môi trường thử riêng hoặc bộ dữ liệu được cô lập thực sự; không cùng chạy lệnh nền trên một database đang được người khác dùng. Có nút/kịch bản báo đạt chưa thay thế việc thử biểu mẫu và quyền của người dùng.

**Bàn giao chung cho mỗi task**

- Phần hoạt động đã hoàn thiện, kèm dữ liệu và cách mở lại để kiểm tra.
- Kết quả mong đợi theo V1 và kết quả quan sát; ảnh màn hình cần thiết.
- Một đoạn giải thích: ai dùng, khi nào dùng, làm xong thay đổi gì.
- Vấn đề còn tồn hoặc phụ thuộc CORE, ghi rõ để tránh báo hoàn thành khi còn bị chặn.

Các file nhiều người cùng cần sửa phải có một người giữ lượt chỉnh tại một thời điểm. Trang quản trị chuyến do thành viên 3 phụ trách phần thông tin phục vụ; bạn tích hợp điều kiện chuyển trạng thái. Trang chính sách do thành viên 1 cập nhật theo V1, bạn đối chiếu lại trước khi áp dụng.
