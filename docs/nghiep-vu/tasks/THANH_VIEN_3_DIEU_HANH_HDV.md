**Phiếu giao việc — Thành viên 3: chuẩn bị chuyến, giao HDV và điểm danh**

Trạng thái: task để thực hiện, chưa phải báo cáo hoàn thành. Mức độ: trung bình. Căn cứ: [Quy tắc V1](../QUY_TAC_NGHIEP_VU_V1.md). Bạn phụ trách thông tin phục vụ chuyến, không quyết định tiền hay chính sách nhận/hủy booking.

**Đây là task lập trình: bạn phải sửa code và bàn giao chức năng chạy được**

Bạn hoàn thiện các màn điều hành/HDV và phần lưu thông tin phục vụ trong phạm vi được giao. Bảng kiểm tra phía dưới dùng để nghiệm thu code; chỉ nộp ảnh hoặc nhận xét thì chưa hoàn thành.

| Mã | Bạn phải code gì? | Đầu ra phải bàn giao |
|---|---|---|
| DH-01A | Sửa phần danh sách đoàn trong ScheduleManagement và cách mở/xuất danh sách hiện có: tách booking với hành khách, tình trạng khai đủ và ghi chú phục vụ | Danh sách hiển thị đúng nhóm/người, người còn thiếu thông tin; xuất đúng dữ liệu đủ điều kiện |
| DH-01B | Bổ sung phần bảng chuẩn bị còn thiếu: giao diện, đọc/lưu trạng thái và căn cứ xe/lưu trú, người và thời điểm cập nhật. Nếu chưa có chỗ lưu, bổ sung cấu trúc dữ liệu, xử lý lưu/đọc và kiểm tra quyền sau khi thống nhất với trưởng nhóm | Điều hành cập nhật được thông tin phục vụ qua máy chủ; tải lại vẫn có lịch sử/căn cứ cần thiết, không chỉ là ô tích lưu trong trình duyệt |
| DH-01C | Hoàn thiện phần phân công của ScheduleManagement và nhận nhiệm vụ trong GuideAssignments: tên thao tác, trạng thái từng HDV, gọi lưu/nhận/từ chối hiện có, nạp lại dữ liệu và báo lỗi | Điều hành giao được chuyến, HDV nhận được nhiệm vụ, điều hành nhìn thấy kết quả; không tác động thanh toán |
| DH-02A | Sửa GuideAttendance và phần xem lại của quản trị: chọn chặng, trạng thái từng người, lý do, các tổng, gọi lưu/đọc và xử lý lỗi; hoàn thiện kiểm tra quyền ghi nhận trong phạm vi điểm danh khi cần | Lưu được đúng người/đúng chặng, tải lại còn dữ liệu, tài khoản không có quyền không sửa được |
| DH-02B | Hoàn thiện phần ghi và xem báo cáo phát sinh trong GuideIncidents/IncidentManagement, cùng liên kết tới báo cáo điểm danh và chuyến đã kết thúc | HDV ghi được sự việc, điều hành xem và ghi phương án xử lý; phần này không tự sinh khoản thu/hoàn |

Bạn được sửa giao diện, lời gọi dịch vụ và xử lý máy chủ về dữ liệu phục vụ trong phạm vi trên. Điều kiện xác nhận chuyến, thời gian, số chỗ và tài chính thuộc trưởng nhóm. Các luồng đã hoạt động thì giữ lại và sửa phần chưa đáp ứng; không xây lại toàn bộ hệ thống điều hành.

**Website đang làm gì và bạn giúp ai?**

Vivu là công ty tổ chức tour. Sau khi khách đặt và trả tiền, công ty vẫn phải chuẩn bị dịch vụ, giao người dẫn đoàn, đón đúng khách và biết tình hình khi đoàn đi. Bạn làm phần để điều hành văn phòng và HDV thực hiện những việc đó.

Ví dụ: chuyến Đà Nẵng 20/10 có hai booking, một nhóm ba người và một nhóm hai người. Tổng cộng năm người thực sự đi. Điều hành giao HDV Hùng. Hùng nhận nhiệm vụ, xem danh sách, điểm danh năm người ở điểm tập trung. Một người đến muộn thì ghi đúng người và lý do; không sửa số tiền của cả booking.

**Các việc khác nhau cần hiểu trước**

| Việc | Nghĩa | Ai làm |
|---|---|---|
| Xác nhận khởi hành | Công ty cam kết tổ chức chuyến | Điều hành theo điều kiện CORE |
| Phân công HDV | Giao người chịu trách nhiệm dẫn đoàn | Điều hành |
| HDV nhận nhiệm vụ | HDV xác nhận đã biết và nhận công việc được giao | HDV đó |
| Bàn giao thông tin trước chuyến | Cung cấp danh sách, điểm đón, ghi chú và dịch vụ | Điều hành cho HDV |
| Thay HDV giữa chuyến | Chuyển trách nhiệm từ HDV đang dẫn sang người khác | Luồng bàn giao hiện có, khác nhận nhiệm vụ ban đầu |
| Điểm danh | Ghi nhận một hành khách có mặt/vắng tại một chặng | HDV có quyền |

Điểm danh “vắng ở chặng tham quan buổi chiều” không tự đồng nghĩa “bỏ toàn bộ tour”; tuyệt đối không dùng thao tác đó để tự hủy booking hoặc kết luận tiền hoàn.

**Màn hình và file bắt đầu**

| Màn | Đường dẫn hiện có | File |
|---|---|---|
| Điều hành chuyến | `/admin/schedules` | [ScheduleManagement](../../../client/src/pages/admin/ScheduleManagement.tsx) |
| Chuyến được giao cho HDV | `/guide/assignments` | [GuideAssignments](../../../client/src/pages/guide/GuideAssignments.tsx) |
| Tour của HDV | `/guide/tours` | [GuideTours](../../../client/src/pages/guide/GuideTours.tsx) |
| Điểm danh | `/guide/attendance/:scheduleId` | [GuideAttendance](../../../client/src/pages/guide/GuideAttendance.tsx), [quy ước điểm danh](../../../client/src/utils/attendance.ts) |
| Điều hành xem điểm danh | `/admin/tour-schedules/:scheduleId/attendance`, `/admin/attendance-reports` | [ScheduleAttendance](../../../client/src/pages/admin/ScheduleAttendance.tsx), [AttendanceReport](../../../client/src/pages/admin/AttendanceReport.tsx) |
| Báo cáo phát sinh | `/guide/incidents`, `/admin/incidents` | [GuideIncidents](../../../client/src/pages/guide/GuideIncidents.tsx), [IncidentManagement](../../../client/src/pages/admin/IncidentManagement.tsx) |

`:scheduleId` phải thay bằng chuyến thử được giao. Trang `/guide/handovers` và `/admin/handovers` hiện chủ yếu phục vụ thay người/bàn giao giữa chừng; không dùng chúng làm lý do bắt mọi chuyến phải đổi HDV trước khi đi. Đợt này không giao xây lại luồng thay HDV giữa chuyến.

**DH-01A — Làm rõ danh sách đoàn**

Vì sao: số booking là số nhóm đặt, không phải số người phải đón.

1. Trong chi tiết chuyến, hiện tour, mã chuyến, ngày giờ đi/về, điểm tập trung và trạng thái chuyến do CORE trả về.
2. Danh sách nhóm phải ghi mã booking, người liên hệ và số hành khách. Mở nhóm thấy từng người, nhóm tuổi và ghi chú phục vụ cần thiết.
3. Hiện tổng số người thực sự đi và tình trạng khai thông tin. Ví dụ có hai booking tổng năm hành khách, đã khai đủ bốn thì ghi “2 booking / 5 hành khách / còn thiếu thông tin 1 người”.
4. Danh sách phục vụ chỉ lấy booking đủ điều kiện theo xử lý chung. Không tự đưa đơn giữ tạm hoặc đã hủy vào đoàn chỉ vì chúng cùng chuyến.
5. Tận dụng xuất danh sách hiện có. Khi chưa đủ thông tin, nêu ai thiếu; nếu có xuất bản nháp thì ghi rõ bản nháp chưa đầy đủ. Không báo “đã gửi nhà xe” chỉ vì đã tải file xuống.
6. Khi điều hành sửa thông tin sau hạn khai, phải thể hiện bản cập nhật/ghi chú cần chuyển lại cho người nhận. Không coi HDV nhớ dữ liệu cũ là đã được cập nhật tự động.

Xong khi: điều hành phân biệt được nhóm đặt, người liên hệ và từng người đi, đồng thời biết danh sách còn thiếu gì.

**DH-01B — Bảng chuẩn bị dịch vụ tối giản**

Vì sao: bán đủ khách chưa chứng minh xe, phòng và người phục vụ đã được chuẩn bị.

1. Kiểm tra phần ghi nhận hiện có. Tận dụng nơi phù hợp trên chi tiết chuyến; chỉ bổ sung phần còn thiếu để có bảng công việc chuẩn bị, không làm lại toàn bộ màn.
2. Bảng gồm: phương tiện; lưu trú nếu tour có; HDV được giao và tình trạng nhận nhiệm vụ; danh sách khách; điểm/giờ tập trung.
3. Mỗi mục cần trạng thái, người cập nhật, thời điểm và ghi chú/căn cứ. Các trạng thái ghi nhận đơn giản: chưa xác nhận, đã xác nhận, không áp dụng nếu sản phẩm không có mục đó.
4. Không đặt sẵn mọi mục thành đã xác nhận. “Không áp dụng lưu trú” dùng cho tour không lưu trú, không dùng để che việc chưa đặt được phòng.
5. Phần HDV và danh sách khách lấy theo dữ liệu thật đã có, không tạo thêm ô tích thủ công mâu thuẫn với danh sách người được giao/số khách còn thiếu.
6. Sau khi lưu thông tin phải tải lại còn dữ liệu, biết ai cập nhật và khi nào. Nếu phải bổ sung nơi lưu dữ liệu, trao đổi cấu trúc với trưởng nhóm rồi triển khai phần ghi nhận này.
7. Hiển thị mục còn thiếu cho điều hành. Quyết định chuyển chuyến sang đã xác nhận thuộc CORE; bạn không tự đặt quy tắc “tích đủ ô thì tự chạy”.

Số điện thoại đơn vị, ghi chú xác nhận và chứng từ hiện có có thể làm căn cứ. Không xây chức năng đặt phòng/thuê xe trực tuyến, hợp đồng nhà cung cấp hoặc tính chi phí/lợi nhuận mới.

**DH-01C — Phân công và nhận nhiệm vụ**

Vì sao: điều hành chọn một HDV chưa chứng minh HDV đã biết hoặc đã nhận công việc.

1. Hoàn thiện phần chọn và lưu HDV trong quản lý chuyến. Tận dụng kiểm tra lịch và quyền sẵn có; nếu máy chủ báo trùng lịch, hiện rõ lỗi thay vì bỏ kiểm tra.
2. Hiện riêng “Đã phân công” và “HDV đã nhận nhiệm vụ”. Sửa ghi chú hoặc tải lại không được tự làm mất trạng thái đã nhận.
3. HDV đăng nhập vào “Chuyến được giao”, nhìn thấy đúng chuyến và các thông tin cần để nhận việc.
4. Nút nhận phải có tên rõ như “Nhận nhiệm vụ”; thao tác không ghi nhận khoản thu hay xác nhận thanh toán booking.
5. Nếu có từ chối theo chức năng hiện có, yêu cầu lý do và để điều hành thấy cần bố trí lại. Không tự làm cả chuyến bị hủy khi một HDV từ chối.
6. Ghi chú bàn giao trước chuyến cần đủ điểm/giờ tập trung, người liên hệ và lưu ý hành khách. Khi không có thông tin phải báo thiếu, không chèn thông tin giả.

Nếu có nhiều HDV, giữ cách phân công hiện có và hiển thị từng người; không tự đổi cấu trúc thành chỉ một HDV. Ví dụ Hùng trong phiếu chỉ là dữ liệu để dễ thử.

**DH-02A — Điểm danh đúng người, đúng chặng**

Vì sao: công ty cần biết người nào có mặt ở đâu; một đơn đặt ba người có thể chỉ hai người đang có mặt.

1. Mở điểm danh từ chuyến đã giao. Phần đầu phải cho thấy đúng chuyến, ngày và chặng/điểm tập trung đang chọn.
2. Danh sách là từng hành khách, có nhóm booking/người liên hệ để tra cứu. Không làm một nút “cả booking có mặt” rồi mất dấu từng người.
3. Giữ các trạng thái hiện có phù hợp như có mặt, vắng mặt, đến muộn, rời đoàn sớm, vắng có phép. Chưa có bản ghi phải hiểu là chưa điểm danh, không mặc định vắng hoặc có mặt.
4. Khi trạng thái yêu cầu lý do, mở ô ghi chú và nêu lỗi nếu thiếu theo quy tắc hiện có. Lý do cần giải thích tình huống, không điền sẵn cho qua.
5. Lưu xong, tải lại phải giữ đúng người, đúng chặng, trạng thái và lý do. Đổi chặng không sao chép nhầm kết quả của chặng trước.
6. Nếu đã có ảnh điểm danh, giữ cách gắn ảnh đúng chặng và hiển thị lỗi tải ảnh. Không tạo thêm yêu cầu bắt buộc ảnh cho mọi chặng nếu chương trình không yêu cầu.
7. Hiện tổng theo từng trạng thái và số chưa điểm danh; tổng các nhóm phải bằng số hành khách trong danh sách chặng đang xét.
8. Thao tác ngoài quyền hoặc ngoài giai đoạn được phép phải báo rõ theo kết quả máy chủ. Bạn được hoàn thiện kiểm tra quyền trong phần ghi nhận phục vụ sau khi thống nhất với trưởng nhóm; không sửa tiền/chỗ để cho điểm danh qua.

**DH-02B — Báo cáo phát sinh và xem lại sau chuyến**

Vì sao: điều hành cần biết sự việc và cách đã xử lý, không chỉ thấy chuyến đổi thành hoàn thành.

1. Hoàn thiện báo cáo HDV trên màn hiện có: chuyến, thời điểm xảy ra, nội dung, tình trạng đoàn/ảnh hưởng và căn cứ nếu có.
2. Giữ quan hệ báo cáo với chuyến cụ thể và người báo. Dùng ngày giờ có nghĩa, không gán mọi báo cáo vào thời điểm tạo chuyến.
3. Điều hành xem được báo cáo, thêm phương án/ghi chú xử lý và trạng thái hồ sơ theo chức năng có sẵn. Báo cáo đổi trạng thái không tự kết thúc chuyến.
4. Nếu có trường ước tính chi phí, ghi rõ ước tính. Quyết định thu thêm/hoàn thêm và các nút ghi tiền trên màn sự cố thuộc trưởng nhóm; không tự coi số ước tính là khoản khách phải trả.
5. Ở chuyến đã kết thúc, điều hành xem lại danh sách, người dẫn, điểm danh từng chặng và phát sinh. Tận dụng báo cáo hiện có, không tạo thêm dashboard toàn hệ thống.
6. Kết thúc chuyến không tự xóa các yêu cầu hủy/tiền hoàn tồn đọng. Phần tiền do thành viên 2 và CORE xử lý.

**Bạn cần trưởng nhóm cung cấp gì?**

| Mã mẫu | Dữ liệu cần nhận |
|---|---|
| DH-A | Chuyến chuẩn bị đi, hai booking có tổng năm người, một người chưa khai đủ |
| DH-B | Cùng kiểu chuyến nhưng danh sách đã đầy đủ, chưa phân công HDV |
| DH-C | Chuyến đã giao HDV Hùng, Hùng chưa nhận; có tài khoản HDV khác không được giao |
| DH-D | Chuyến đang chạy, Hùng đã nhận, năm hành khách, ít nhất hai chặng điểm danh |
| DH-E | Chuyến kết thúc, có kết quả điểm danh và một báo cáo phát sinh |
| DH-F | Chuyến có một booking đã hủy và một booking còn giữ tạm để kiểm tra danh sách phục vụ |

Đây là bộ dữ liệu phải được chuẩn bị, chưa khẳng định đã tồn tại. Trưởng nhóm cung cấp mã/đường dẫn thật, quyền thao tác và công cụ tạo lại. Bạn không phải tự đợi chuyến từ tương lai tới ngày đi hay tự sửa ngày máy tính.

**Các bài tự kiểm tra bắt buộc**

| Mã | Thao tác | Kết quả cần thấy |
|---|---|---|
| DH-T01 | Mở DH-A | Phân biệt 2 booking, 5 người; chỉ ra người còn thiếu |
| DH-T02 | Mở DH-F | Không đưa booking đã hủy/giữ tạm không đủ điều kiện vào danh sách phục vụ |
| DH-T03 | DH-B ghi đã xác nhận xe rồi tải lại | Trạng thái, căn cứ, người và thời điểm cập nhật còn đúng |
| DH-T04 | Tour không lưu trú và tour có lưu trú chưa có phòng | Trường hợp đầu không áp dụng; trường hợp sau vẫn báo chưa xác nhận |
| DH-T05 | Phân công Hùng rồi để Hùng nhận DH-C | Điều hành thấy hai giai đoạn phân công/đã nhận; tiền booking không đổi |
| DH-T06 | Dùng tài khoản HDV khác mở DH-D | Không xem hoặc sửa ngoài quyền; nếu sai ghi lỗi để xử lý |
| DH-T07 | DH-D chưa ghi gì, sau đó đánh 4 có mặt và 1 đến muộn có lý do | Ban đầu 5 chưa điểm danh; sau đó 4 có mặt, 1 đến muộn; tổng 5 |
| DH-T08 | Chuyển sang chặng thứ hai của DH-D | Không tự thành 4 có mặt/1 đến muộn như chặng đầu |
| DH-T09 | Ghi vắng nhưng thiếu lý do bắt buộc | Không báo đã lưu thành công; chỉ rõ người cần bổ sung |
| DH-T10 | Lưu điểm danh và tải lại; điều hành mở cùng chuyến/chặng | Hai phía cùng trạng thái và lý do |
| DH-T11 | HDV báo sự cố, điều hành ghi phương án | Có lịch sử/nội dung đủ hiểu ai báo và xử lý gì; không tự phát sinh tiền |
| DH-T12 | Mở DH-E | Xem lại được thông tin chuyến, điểm danh và phát sinh; không sửa thành chuyến chưa từng chạy |

Những kiểm tra số tiền không đổi ở DH-T05/DH-T11 chỉ cần đối chiếu booking trước/sau cùng thành viên 2; không cần bạn hiểu cách tính phí hủy.

**Thứ tự làm và bàn giao**

Làm DH-01A → DH-01C → DH-02A → DH-02B → DH-01B. Bảng chuẩn bị cần thống nhất chỗ lưu nên có thể làm sau phần đã có sẵn.

Trước khi sửa [ScheduleManagement](../../../client/src/pages/admin/ScheduleManagement.tsx), báo trưởng nhóm đoạn/phần bạn sẽ sửa vì CORE cũng dùng file này. Phần thông tin phục vụ thuộc bạn; điều kiện chuyển trạng thái thuộc trưởng nhóm. Các file dịch vụ dùng chung phải thống nhất lượt sửa để không ghi đè nhau.

Bàn giao: file đã sửa; mã dữ liệu và tài khoản thử theo vai trò được cấp; bảng DH-T đã chạy/chưa chạy; ảnh minh họa phân công/nhận nhiệm vụ, điểm danh hai chặng và sự cố; phần còn chờ CORE. Chạy `npm run build` trong `client` nếu sửa React/TypeScript. Nếu bổ sung xử lý máy chủ về quyền hoặc ghi nhận, cùng trưởng nhóm kiểm tra đúng phần thay đổi.

Tự giải thích được: “Hai booking có phải hai khách không?”, “HDV nhận nhiệm vụ có phải xác nhận khách trả tiền không?”, “Một người vắng ở một chặng có phải hủy cả booking không?”.
