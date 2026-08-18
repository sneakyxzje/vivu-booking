# 08 - Danh mục tình huống ngoại lệ

Bảng tổng hợp toàn bộ edge case của hệ thống, dùng làm danh sách kiểm tra khi phát triển và
khi trả lời hội đồng.

Cột trạng thái: `Đã xử lý` là đã có trong mã nguồn, `Cần bổ sung` là nằm trong lộ trình,
`Chấp nhận` là biết nhưng cố ý không xử lý, có nêu lý do.

> **Đã đối chiếu với mã nguồn ngày 18/08/2026.** Bảng này viết từ lúc còn là thiết kế và cột
> trạng thái không được cập nhật theo mã trong suốt quá trình làm, nên có lúc ghi 26/116 đã xử lý
> trong khi thực tế là 78. Lần rà này đọc thẳng mã cho từng dòng; chỗ nào không tìm được mã thì
> giữ nguyên `Cần bổ sung` chứ không suy đoán.

## A. Đặt tour và giữ chỗ

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| A01 | Hai khách đặt đồng thời khi còn đúng một chỗ | Khóa dòng bi quan trên chuyến trong giao dịch, người sau đọc lại số chỗ đã cập nhật và bị từ chối | Đã xử lý |
| A02 | Đơn quá hạn giữ chỗ vẫn chiếm chỗ trên giao diện | Nhả chỗ lười khi xem chi tiết tour và khi tạo đơn | Đã xử lý |
| A03 | Đơn quá hạn không ai chạm tới trong thời gian dài | Tác vụ nền quét định kỳ | Đã xử lý |
| A04 | Khách bấm đặt hai lần liên tiếp | Khóa idempotency theo email, chuyến và tổng tiền trong 60 giây (`DUPLICATE_WINDOW_SECONDS`) | Đã xử lý |
| A05 | Khách đặt vượt số chỗ còn lại | Từ chối kèm số chỗ thực còn | Đã xử lý |
| A06 | Đặt cho chuyến đã qua ngày khởi hành | Không hiển thị chuyến quá khứ, kiểm tra lại phía máy chủ | Đã xử lý |
| A07 | Đặt sau hạn chốt danh sách | `TourSchedule::isBookable()` xét `booking_deadline`, từ chối trong giao dịch | Đã xử lý |
| A08 | Đặt khi tour vừa bị chuyển sang ngừng bán | Kiểm tra lại `tour->status` bên trong giao dịch đã khóa chuyến | Đã xử lý |
| A09 | Đơn chỉ có em bé, không có người lớn | `adult_count` bắt buộc tối thiểu 1 | Đã xử lý |
| A10 | Số khách bằng 0 hoặc âm | Ràng buộc kiểm tra đầu vào | Đã xử lý |
| A11 | Mã giảm giá hết lượt trong lúc khách điền biểu mẫu | Kiểm tra lại lần cuối trong giao dịch, tạo đơn giá gốc và thông báo cho khách | Đã xử lý |
| A12 | Mã giảm giá của đơn bị hủy | Trả lượt sử dụng về cho mã | Đã xử lý |
| A13 | Mã giảm giá hết hạn giữa lúc thanh toán | Giá đã chốt tại thời điểm tạo đơn, không tính lại | Đã xử lý |
| A14 | Em bé không chiếm ghế nhưng vẫn bị trừ chỗ | Tách `seat_count` khỏi `guests` | Cần bổ sung |
| A15 | Khách nhập email sai, không nhận được thư | Ghi nhật ký gửi thư hỏng, hiện cảnh báo trên trang quản trị | Cần bổ sung |
| A16 | Khách vãng lai mất mã tra cứu | Gửi lại mã qua email đã dùng khi đặt | Đã xử lý |
| A17 | Mã tra cứu bị đoán để xem đơn người khác | Dùng UUID ngẫu nhiên thay cho số thứ tự | Đã xử lý |

## B. Thanh toán

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| B01 | Dữ liệu trả về từ cổng thanh toán bị giả mạo | Tính lại chữ ký HMAC SHA512 phía máy chủ trước khi tin | Đã xử lý |
| B02 | Cổng thanh toán gọi lại nhiều lần cho cùng giao dịch | Xử lý lũy đẳng, đơn đã `paid` thì bỏ qua | Đã xử lý |
| B03 | Tiền về sau khi đơn đã tự hủy vì quá hạn | Thử khôi phục nếu chuyến còn chỗ, ngược lại ghi cảnh báo đưa vào hàng chờ hoàn thủ công | Đã xử lý |
| B04 | Tiền về cho đơn đã bị quản trị hủy | Ghi cảnh báo hoàn tiền thủ công | Đã xử lý |
| B05 | Múi giờ lệch làm liên kết thanh toán hết hạn sai thời điểm | Toàn hệ thống chạy `Asia/Ho_Chi_Minh`, cột nghiệp vụ lưu giờ treo tường | Đã xử lý |
| B06 | Khách đóng cọc rồi không đóng nốt | Không tự hủy vì đã thu tiền, đưa vào cảnh báo cho điều hành xử lý. Sổ giao dịch đã có, còn thiếu tác vụ nhắc | Cần bổ sung |
| B07 | Khách đóng thừa so với số phải trả | Sổ giao dịch ghi đúng số nhận; màn đơn đoàn nêu rõ phần vượt để điều hành ghi khoản hoàn | Đã xử lý |
| B08 | Tổng các khoản hoàn vượt tổng đã thu | `GroupBookingService::recordPayment` từ chối kèm số đã thu thực | Đã xử lý |
| B09 | Mất kết nối giữa lúc chuyển hướng sang cổng thanh toán | Đơn vẫn ở `pending` và giữ chỗ tới hết hạn, khách vào lại đơn để thanh toán lại | Đã xử lý |
| B10 | Hoàn tiền tự động qua cổng | Cần hợp đồng thương mại thật, dùng luồng hoàn thủ công có đối soát | Chấp nhận |

## C. Hủy đơn

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| C01 | Khách hủy đơn chưa thanh toán | Tự hủy ngay, trả chỗ | Đã xử lý |
| C02 | Khách hủy đơn đã thanh toán | Tạo yêu cầu hủy, điều hành duyệt (`BookingChangeRequestService`) | Đã xử lý |
| C03 | Hủy sau hạn chốt danh sách | Không trả chỗ, `seats_released = false`. Ghế chết giữ tới hết chuyến — xem [06](06-doi-chieu-feedback.md) vì sao không có nút mở lại | Đã xử lý |
| C04 | Hủy khi chuyến đang chạy | `BookingPolicyService::assertCancellable` chặn ở tầng dịch vụ cho cả bốn lối vào | Đã xử lý |
| C05 | Hủy khi chuyến đã kết thúc | Cùng lớp trên, chặn | Đã xử lý |
| C06 | Quản trị hủy nhầm đơn | Cho mở lại trong 24 giờ kèm lý do, nếu chuyến còn chỗ | Đã xử lý |
| C07 | Hủy một phần số khách trong đơn | Chỉ đơn đoàn giảm được số khách, trước hạn chốt. Đơn lẻ cố ý không cho — xem D01 | Cần bổ sung |
| C08 | Hủy đơn đã hủy | Kiểm tra trạng thái trong giao dịch có khóa dòng, bỏ qua nếu đã hủy | Đã xử lý |
| C09 | Hai luồng cùng hủy một đơn | Khóa dòng đơn, luồng sau thấy trạng thái đã đổi và dừng | Đã xử lý |
| C10 | Số chỗ bị trừ âm khi hủy nhiều lần | Trừ theo `min(guests, booked_people)` | Đã xử lý |
| C11 | Khách yêu cầu hoàn về tài khoản người khác | Yêu cầu xác nhận bằng văn bản, ghi vào ghi chú | Cần bổ sung |
| C12 | Khách không có mặt lúc khởi hành | `BookingFinalizationService` đánh dấu `no_show`, không hoàn tiền | Đã xử lý |

## D. Sửa đơn và chuyển chuyến

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| D01 | Tăng số khách vượt sức chứa còn lại | **Đơn lẻ cố ý không cho sửa số khách**: đổi số người là đổi chỗ và tiền, phải hủy đặt lại theo chính sách. Xem ghi chú điểm 2 ở [06](06-doi-chieu-feedback.md) | Chấp nhận |
| D02 | Giảm số khách về 0 | Đơn đoàn giảm được nhưng phải còn nhiều hơn số suất miễn phí; về 0 bị từ chối | Đã xử lý |
| D03 | Sửa số khách sau hạn chốt | Chặn: phòng và suất ăn đã đặt theo danh sách đã gửi nhà cung cấp | Đã xử lý |
| D04 | Khách đi thực tế nhiều hơn số đã đặt | Hướng dẫn viên báo sự cố, điều hành tạo phụ thu, hướng dẫn viên không nhập được tiền | Đã xử lý |
| D05 | Số hành khách khai báo ít hơn số đã đặt | `PassengerPolicyService::manifestWarnings` cảnh báo trên màn danh sách đoàn | Đã xử lý |
| D06 | Trùng số giấy tờ giữa hai hành khách cùng đơn | Từ chối, kiểm theo cả đơn | Đã xử lý |
| D07 | Hộ chiếu hết hạn trước ngày về | Cột `passport_expiry` đã có, còn thiếu luật đối chiếu với ngày kết thúc chuyến | Cần bổ sung |
| D08 | Đổi hoàn toàn người đi | Coi là chuyển nhượng suất, cần duyệt, có thể thu phí đổi tên | Cần bổ sung |
| D09 | Chuyến đích hết chỗ đúng lúc bấm chuyển | Khóa dòng phát hiện, giao dịch quay lui | Đã xử lý |
| D10 | Hai luồng chuyển chéo nhau giữa hai chuyến | Khóa theo thứ tự khóa chính tăng dần để tránh khóa chết | Đã xử lý |
| D11 | Chuyển sang chính chuyến đang ở | Từ chối kèm thông báo rõ | Đã xử lý |
| D12 | Chuyển đi chuyển lại nhiều lần | `transfer_count`, từ lần thứ hai thu phí đổi lịch | Đã xử lý |
| D13 | Chuyển đơn đã có dữ liệu điểm danh | Chặn qua trạng thái chuyến gốc: đang chạy hoặc đã kết thúc thì không chuyển | Đã xử lý |
| D14 | Chuyển một phần số khách | Tách đơn, ghi liên kết đơn gốc | Cần bổ sung |
| D15 | Chuyến đích rẻ hơn, khách khởi xướng | Ghi công nợ dùng cho lần sau, không hoàn tiền mặt | Cần bổ sung |

## E. Vòng đời chuyến

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| E01 | Chuyến không đủ khách tối thiểu tại hạn chốt | `ConfirmReadySchedules` không chốt và cảnh báo kèm số còn thiếu | Đã xử lý |
| E02 | Có đơn mới thanh toán đúng lúc đang chốt chuyến | Tính lại trong giao dịch có khóa dòng | Đã xử lý |
| E03 | Chuyến đủ khách rồi tụt xuống dưới mức tối thiểu | Cảnh báo lại, cho phép quay về trạng thái đóng bán để cân nhắc | Cần bổ sung |
| E04 | Hủy chuyến khi còn đơn đã thu tiền chưa có phương án | `ScheduleCancellationService` chặn kèm số lượng cụ thể | Đã xử lý |
| E05 | Hủy chuyến muộn hơn quy định báo trước | Vẫn cho hủy nhưng cảnh báo và ghi vào nhật ký để chịu trách nhiệm | Cần bổ sung |
| E06 | Ghép chuyến mà chuyến đích không đủ chỗ | `ScheduleMergeService` tính chỗ trống và từ chối | Đã xử lý |
| E07 | Ghép dây chuyền qua nhiều chuyến | Cập nhật chuỗi tham chiếu, hiển thị chuyến cuối cùng cho khách | Cần bổ sung |
| E08 | Khách không đồng ý đổi ngày do ghép chuyến | Hoàn 100 phần trăm vì thay đổi do hãng (`nguonBiHuy`) | Đã xử lý |
| E09 | Xóa tour còn đơn hiệu lực | `TourDeletionService` xóa mềm, chứng từ giữ nguyên; khóa ngoại đổi sang `restrict` | Đã xử lý |
| E10 | Ngừng bán tour có chuyến đã chốt | Ngừng bán không đụng tới chuyến nào, chuyến đã chốt vẫn chạy | Đã xử lý |
| E11 | Sửa giá tour sau khi đã có đơn | Không hồi tố, đơn giữ giá tại thời điểm đặt | Đã xử lý |
| E12 | Chuyến đã chốt nhưng không còn hướng dẫn viên | Không hạ trạng thái, buộc tìm người thay hoặc thuê ngoài. Còn thiếu tác vụ cảnh báo định kỳ | Cần bổ sung |

## F. Hướng dẫn viên

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| F01 | Gán hướng dẫn viên trùng lịch hai chuyến | `ScheduleGuideService::lyDoChan` kiểm giao nhau khoảng thời gian, từ chối | Đã xử lý |
| F02 | Hai chuyến sát nhau không đủ thời gian nghỉ | **Không cài riêng**: luật trùng lịch so theo *ngày*, đoàn về 22h thì hôm đó đã coi là bận cả ngày — chặt hơn mốc 12 giờ | Chấp nhận |
| F03 | Thẻ hướng dẫn viên hết hạn trước khi chuyến kết thúc | **Đã cài rồi gỡ đi**: hội đồng hỏi về chuyên môn, còn hiệu lực thẻ là việc của quản lý nhân sự. Lý do đầy đủ ở [06](06-doi-chieu-feedback.md) | Chấp nhận |
| F04 | Đoàn vượt sức dẫn tối đa của hướng dẫn viên | **Cảnh báo chứ không chặn**: đoàn đông cần mấy người dẫn là quyết định của điều hành, họ xếp thêm người mà hệ thống không biết trước | Chấp nhận |
| F05 | Thay hướng dẫn viên giữa chuyến | `GuideHandoverService` — hai lối vào, bắt buộc lý do và tình trạng đoàn | Đã xử lý |
| F06 | Không tìm được người thay | Gán tạm cho điều hành, đánh dấu cần xử lý | Cần bổ sung |
| F07 | Người cũ vẫn cố ghi điểm danh sau khi bàn giao | `assertCanRecord` tra phân công hiện tại, người đã bàn giao không còn trong danh sách | Đã xử lý |
| F08 | Người cũ cần xem lại dữ liệu đã ghi | Dữ liệu đã ghi giữ nguyên, biên bản bàn giao đọc được | Đã xử lý |
| F09 | Hai hướng dẫn viên cùng phụ trách một đoàn lớn | Bảng `tour_schedule_guides` nhận nhiều người cho một chuyến | Đã xử lý |
| F10 | Thay người sau khi chuyến đã kết thúc | Từ chối | Cần bổ sung |

## G. Điểm danh

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| G01 | Hướng dẫn viên ghi điểm danh cho chuyến không thuộc phân công | Từ chối | Đã xử lý |
| G02 | Truyền chặng không thuộc tour của chuyến | Từ chối | Đã xử lý |
| G03 | Điểm danh chuyến chưa khởi hành | Từ chối, chuyến phải đang chạy | Đã xử lý |
| G04 | Điểm danh chuyến đã kết thúc | Từ chối ghi, chỉ còn xem | Đã xử lý |
| G05 | Tick trước cho ngày trong tương lai | So ngày của điểm dừng với hôm nay, từ chối | Đã xử lý |
| G06 | Ghi bù sau khi mất sóng | Cho phép, đánh dấu `is_late_entry` | Đã xử lý |
| G07 | Đơn bốn người nhưng chỉ ba người có mặt | `passenger_checkins` theo từng hành khách, từng điểm dừng | Đã xử lý |
| G08 | Điểm danh đơn đã hủy | Chỉ lấy hành khách của đơn còn hiệu lực | Đã xử lý |
| G09 | Đánh vắng mà không ghi lý do | `PassengerCheckinStatus::requiresNote`, bắt buộc ghi chú | Đã xử lý |
| G10 | Chốt điểm dừng bắt buộc chụp ảnh mà chưa có ảnh | `assertCheckpointCompletable` đã viết và có kiểm thử, **chưa có thao tác nào gọi tới** vì chưa có chức năng chốt điểm dừng | Cần bổ sung |
| G11 | Ảnh check-in chụp cách điểm dừng quá xa | Tính khoảng cách bằng công thức Haversine, vượt 200m thì vẫn lưu nhưng cảnh báo | Đã xử lý |
| G12 | Sửa điểm danh đã ghi | `PassengerCheckinHistory` lưu bản cũ, không ghi đè lặng lẽ | Đã xử lý |
| G13 | Còn khách chưa điểm danh mà chuyển điểm dừng tiếp | Cảnh báo mềm, cho phép bỏ qua nhưng phải xác nhận | Cần bổ sung |
| G14 | Vắng mặt tại điểm cuối, không về cùng đoàn | `PassengerAbsentAtBoundaryNotification` báo mức cao cho điều hành | Đã xử lý |
| G15 | Khách rời đoàn giữa chừng | Trạng thái điểm danh đã có; luồng xét hoàn phần chưa dùng thì chưa | Cần bổ sung |

## H. Sự cố và chi phí phát sinh

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| H01 | Bão làm không ra biển được, phải đổi chương trình | `IncidentService` — hướng dẫn viên báo, điều hành duyệt phương án và phân bổ chi phí | Đã xử lý |
| H02 | Hướng dẫn viên tự thu tiền của khách | Biểu mẫu báo sự cố **không có ô nhập tiền**; phụ thu chỉ sinh ra từ phía điều hành | Đã xử lý |
| H03 | Khách không đồng ý đóng phụ thu | Phụ thu có trạng thái miễn, điều hành quyết; khoản chưa duyệt chưa có hiệu lực | Đã xử lý |
| H04 | Chương trình bị rút ngắn | Hoàn phần dịch vụ chưa sử dụng theo giá vốn — cần tầng cung ứng, ngoài phạm vi | Cần bổ sung |
| H05 | Chuyến dừng hoàn toàn giữa chừng | Vẫn kết thúc ở trạng thái hoàn thành, tạo hoàn theo phần chưa dùng | Cần bổ sung |
| H06 | Một phần đoàn tiếp tục, một phần về sớm | Ghi nhận riêng từng hành khách | Cần bổ sung |
| H07 | Khách gặp vấn đề sức khỏe phải về sớm | Ghi nhận đầy đủ phục vụ hồ sơ bảo hiểm: ai đưa về, đã báo người nhà chưa, chi phí y tế | Cần bổ sung |
| H08 | Sự cố do lỗi nhà cung cấp | Với khách thì hãng chịu, đồng thời mở phiếu đòi bồi thường nhà cung cấp | Cần bổ sung |
| H09 | Báo cáo sự cố muộn do mất sóng | Cho nhập thời điểm trong quá khứ | Đã xử lý |

## I. Đoàn, hợp đồng và dữ liệu cá nhân

| Mã | Tình huống | Xử lý | Trạng thái |
| --- | --- | --- | --- |
| I01 | Đoàn giảm số khách sau khi ký hợp đồng | `GroupBookingService::reduceGuests` tính lại tổng theo giá đã báo, trả chỗ về kho, chỉ trước hạn chốt | Đã xử lý |
| I02 | Đoàn tăng số khách | Kiểm tra chỗ, báo giá lại phần thêm, tính lại toàn đơn | Cần bổ sung |
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
| J01 | `booked_people` lệch so với tổng đơn thực tế | Lệnh `bookings:check-seat-consistency` đối chiếu định kỳ | Đã xử lý |
| J02 | `booked_people` bị âm | Trừ theo `min(guests, booked_people)` | Đã xử lý |
| J03 | Đơn thuộc chuyến đã bị ghép | Cập nhật đơn sang chuyến đích trong cùng giao dịch với thao tác ghép | Đã xử lý |
| J04 | Tổng đã thu lệch giữa nhật ký cổng thanh toán và sổ giao dịch đơn hàng | Báo cáo đối soát định kỳ | Cần bổ sung |
| J05 | Chuyến quá ngày khởi hành nhưng trạng thái chưa chuyển | Lệnh `schedules:advance-status` cập nhật theo thời gian | Đã xử lý |
| J06 | Đơn `pending` tồn đọng của chuyến đã kết thúc | Tác vụ nền dọn, đánh dấu hết hiệu lực | Đã xử lý |

## K. Thống kê

| Nhóm | Tổng | Đã xử lý | Cần bổ sung | Chấp nhận |
| --- | --- | --- | --- | --- |
| A - Đặt tour và giữ chỗ | 17 | 15 | 2 | 0 |
| B - Thanh toán | 10 | 8 | 1 | 1 |
| C - Hủy đơn | 12 | 10 | 2 | 0 |
| D - Sửa đơn và chuyển chuyến | 15 | 10 | 4 | 1 |
| E - Vòng đời chuyến | 12 | 8 | 4 | 0 |
| F - Hướng dẫn viên | 10 | 5 | 2 | 3 |
| G - Điểm danh | 15 | 12 | 3 | 0 |
| H - Sự cố và chi phí | 9 | 4 | 5 | 0 |
| I - Đoàn và hợp đồng | 10 | 1 | 9 | 0 |
| J - Nhất quán dữ liệu | 6 | 5 | 1 | 0 |
| **Tổng** | **116** | **78** | **33** | **5** |

### Đọc bảng này thế nào

**78 trên 116 đã có mã chạy.** Phần còn thiếu không rải đều mà **dồn vào hai nhóm**: nhóm I (đoàn,
hợp đồng, xếp phòng — 9/10 chưa làm) và nhóm H (sự cố — 5/9 chưa làm, hầu hết cần giá vốn dịch vụ
mà tầng cung ứng nằm ngoài phạm vi).

Năm dòng `Chấp nhận` đều là **quyết định có chủ ý**, không phải việc bỏ dở: ba dòng ở nhóm F đến
từ cùng một nguyên tắc — hệ thống hỗ trợ quyết định chứ không quyết thay điều hành; D01 là ranh
giới "sửa thứ gõ nhầm thì được, đổi thứ đã mua thì không"; B10 là giới hạn của môi trường thử
nghiệm cổng thanh toán.
