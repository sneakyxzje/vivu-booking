# Rà soát nghiệp vụ toàn website — 28/09/2026

## Kết luận

Chưa thể xác nhận toàn bộ luồng đã ăn khớp. Lượt rà soát ban đầu xác định 6 vấn đề ở các bước nối xác thực → tạo đơn, nhận tiền → hoàn tiền, hủy → chuyển chuyến, tự động hóa → kết thúc và điều khoản → hành vi hệ thống. Sau đó đã sửa nội dung điều khoản/FAQ (A6) theo yêu cầu; còn 5 vấn đề A1–A5 chưa xử lý.

Đây là lượt kiểm tra mã nguồn, API và kiểm thử trong môi trường cô lập. Lượt này không sửa nghiệp vụ chạy thật, không gửi thư hoặc gọi cổng thanh toán thật, không chạy migration trên cơ sở dữ liệu phát triển.

Nguồn nghiệp vụ ưu tiên: [Đặt cọc và cam kết khởi hành](COC_VA_CAM_KET_KHOI_HANH.md), cùng các yêu cầu đã chốt về OTP khai hành khách, điểm danh đúng ngày và bỏ GPS. Các đoạn V1 cũ về mốc 7–5–3 hoặc yêu cầu đủ khách không được dùng để thay thế quy tắc mới.

## Bằng chứng kiểm tra

- Bộ kiểm thử backend đầy đủ: **948 bài, 914 đạt, 34 thất bại**, 3.452 assertions, không có bài bỏ qua. SQLite trong bộ nhớ.
- Bộ kiểm thử frontend hiện có: **56/56 đạt**. Đây là kiểm thử hàm xử lý dữ liệu, chưa phải thao tác giao diện trên trình duyệt.
- 33 bài backend bị 403 ở bước tạo đơn vì chưa có cờ xác thực email. Gồm BookingGuardRules (6), BookingIntegrityFixes (1), DiscountCodeAtCheckout (10), DuplicateBookingGuard (8), GuideOperationsFixes (2), InfantPerAdultRule (4), SeatConsistencyCommand (1), SmallDefectFixes (1). Những bài bị chặn chưa kiểm tra được nghiệp vụ phía sau.
- 1 bài SchemaColumnReference thất bại do coi `schedules_max_start_date` là cột vật lý thiếu. Đây là alias do `withMax` tạo ra trong truy vấn HDV, cần cập nhật bộ kiểm tra alias; không phải bằng chứng truy vấn tour bị hỏng.
- Chạy thêm 5 phép tái hiện độc lập: **5/5 xác nhận được hành vi có vấn đề** ở mục A1–A5. Những phép này mô tả lỗi hiện hữu, kết quả “passed” của chúng KHÔNG có nghĩa nghiệp vụ đã đúng.
- Kết quả thô: `server/storage/logs/business-audit-junit.xml`, `server/storage/logs/business-audit-probes.xml`. Mã tái hiện nằm ngoài các suite chuẩn: `server/storage/logs/BusinessLogicAuditProbeTest.php`.

## Những điểm chưa khớp

### A1 — Ưu tiên cao: OTP tạo đơn không gắn với người/phiên đã xác thực

**Hiện tượng:** OTP đúng tạo `booking_verified_<email> = true` dùng chung trong cache. API tạo đơn chỉ cần thấy cờ này; không yêu cầu token chứng minh chính request đó đã xác thực.

**Tái hiện:** request A xác thực OTP; request B từ địa chỉ khác, chưa xác thực OTP, gửi đặt tour cùng email vẫn nhận 201 và tạo đơn. Không cần đoán hoặc gửi OTP trong request B.

**Tác động:** một lượt xác thực có thể bị người khác dùng để tạo đơn/tiêu thụ cờ trước chủ email. Đây là OTP lúc tạo đơn; cơ chế OTP khai hành khách đang dùng token gắn đơn/actor và là luồng khác.

**Căn cứ:** `server/app/Http/Controllers/Api/Customer/OtpController.php:105`; `server/app/Http/Controllers/Api/Customer/BookingController.php:111`.

**Hướng xử lý:** verify trả token ngẫu nhiên, gắn email + phiên/actor + thời hạn; tạo đơn phải đưa token và tiêu thụ nguyên tử. Đồng thời thống nhất cách xử lý chủ tài khoản đã đăng nhập, không dùng cờ email toàn cục.

### A2 — Ưu tiên cao: tiền VNPay về sau hạn chốt chưa đi hết luồng đối chiếu/hoàn

**Hiện tượng:** callback thành công sau hạn chốt được ghi vào `payment_logs` và log cảnh báo; đơn không được xác nhận lại (đúng), nhưng khoản tiền không được đưa vào sổ thu/tiền chờ xử lý và không tạo nghĩa vụ hoàn.

**Tái hiện:** callback có chữ ký hợp lệ, nhận 500.000 đồng sau hạn chốt. Có payment log, đơn bị hủy, `booking_payments` vẫn rỗng, `refundOutstanding()` bằng 0. Thử ghi hoàn 500.000 qua dịch vụ sổ giao dịch bị chặn “Không hoàn quá số đã thu”.

**Tác động:** hệ thống biết có callback nhưng màn/sổ hoàn tiền chưa xử lý được khoản thu thực này. Nhánh tiền về cho đơn đã đủ tiền/đã hủy cũng chỉ cảnh báo bằng log, cần rà cùng thiết kế.

**Căn cứ:** `server/app/Services/VNPayCallbackService.php:113`; `server/app/Services/BookingPaymentService.php:89`; `server/app/Http/Controllers/Api/Admin/AdminBookingPaymentController.php:117`.

**Hướng xử lý:** tách quyền tham gia tour khỏi việc ghi nhận tiền thực nhận; ghi nhận khoản thu muộn/thừa theo mã giao dịch, sinh nghĩa vụ hoàn và cho đối soát/hoàn đúng khoản đó, chống ghi trùng callback. Không mở lại đơn quá hạn để xử lý tiền.

### A3 — Ưu tiên cao: hủy chuyến rồi chuyển khách có thể bỏ qua sự đồng ý

**Hiện tượng:** luồng ghép chuyến đã chờ khách đồng ý, nhưng luồng công ty hủy chuyến có phương án `transfer` chuyển ngay. `nguonBiHuy: true` làm hàm kiểm tra căn cứ đồng ý trả về sớm.

**Tái hiện:** admin gửi hủy chuyến với phương án chuyển một đơn đã cọc sang chuyến khác. API trả 200, booking đổi chuyến; không có đề xuất được khách chấp thuận hoặc bản ghi liên hệ đồng ý.

**Tác động:** cùng việc đổi chuyến/lịch của khách nhưng hai cửa xử lý áp dụng hai quy tắc chấp thuận khác nhau. Email ở nhánh này là thông báo sau khi đã chuyển.

**Căn cứ:** `server/app/Services/ScheduleCancellationService.php:374`; `server/app/Services/BookingTransferService.php:312`.

**Hướng xử lý:** công ty hủy vẫn phải để khách chọn hoàn tiền hoặc đồng ý phương án mới. Hủy chuyến nguồn không tự thay thế sự đồng ý với chuyến đích; cần lưu phương án và bằng chứng chấp thuận trước khi chuyển.

### A4 — Ưu tiên vừa: tác vụ nền bỏ lỡ hạn chốt không tự xử lý bù chuyến còn open

**Hiện tượng:** `confirm-ready` chỉ lấy chuyến chưa tới giờ đi; `advance-status` chỉ lấy confirmed/in_progress; `finalize-completed` cũng bỏ qua open. Trạng thái hiển thị thực tế vẫn có thể được suy ra là đang đi/đã kết thúc.

**Tái hiện:** chuyến open có khách đã trả đủ, tác vụ không chạy qua hạn chốt đến sau khởi hành. Chạy lại các lệnh: trạng thái lưu vẫn open. Qua ngày kết thúc, chạy chốt đơn: đơn vẫn confirmed, chưa hoàn tất.

**Tác động:** giao diện có thể hiển thị chuyến đã kết thúc trong khi vòng đời dữ liệu, thông báo và các xử lý sau chuyến chưa hoàn tất.

**Căn cứ:** `server/app/Console/Commands/ConfirmReadySchedules.php:42`; `server/app/Console/Commands/AdvanceScheduleStatus.php:29`; `server/app/Console/Commands/FinalizeCompletedBookings.php:72`.

**Hướng xử lý:** bổ sung xử lý bù theo thứ tự hủy đơn thiếu tiền → chốt chuyến có khách đủ điều kiện → cập nhật trạng thái theo thời gian → chốt đơn. Phải kiểm tra lại điều kiện từng bước, không chỉ đổi nhãn hiển thị.

### A5 — Ưu tiên vừa: gửi lại cùng yêu cầu đặt chỗ bị OTP chặn trước bước trả lại đơn cũ

**Hiện tượng:** tạo đơn thành công xóa cờ OTP; request gửi lại bị 403 ngay tại cửa xác thực, chưa chạy tới kiểm tra đơn trùng bên trong transaction.

**Tái hiện:** xác thực OTP một lần, POST đặt tour lần đầu trả 201; gửi lại cùng payload ngay sau đó trả 403, mặc dù đơn đầu đã được tạo. Không tạo trùng đơn, nhưng luồng phục hồi khi phản hồi mạng bị mất không hoạt động như thiết kế.

**Tác động:** khách không nhận được phản hồi đầu có thể tưởng chưa đặt được và phải xác thực lại; cơ chế chống trùng chưa xử lý được yêu cầu lặp một cách liền mạch.

**Căn cứ:** `server/app/Http/Controllers/Api/Customer/BookingController.php:111`, `:240`, `:339`.

**Hướng xử lý:** mã yêu cầu đặt chỗ gắn cùng token xác thực; gửi lại đúng mã/người/nội dung phải trả lại kết quả đơn cũ, kể cả token đã được tiêu thụ bởi chính yêu cầu đó. Không bỏ qua xác thực chỉ dựa vào email để chữa lỗi này.

### A6 — Đã sửa nội dung: điều khoản công khai còn cho phép hủy vì thiếu khách

**Hiện tượng:** mục 6.1 và FAQ vẫn nêu không đạt số khách tối thiểu là lý do công ty hủy. Trong cùng trang, mục 7.2 nói không ghép được hoặc ghép vẫn dưới mục tiêu thì vẫn tổ chức chuyến.

**Tác động:** khách đọc được hai cam kết mâu thuẫn; điều hành khó giải thích đúng chính sách. Luồng chốt dưới mục tiêu đã có kiểm thử đạt nhưng nội dung công bố chưa cập nhật hết.

**Căn cứ:** `client/src/pages/PolicyPage.tsx:469`, `:552`, `:861`; [quy tắc đã chốt](COC_VA_CAM_KET_KHOI_HANH.md).

**Hướng xử lý:** bỏ lý do hủy chỉ vì không đạt mục tiêu khách, rà lại điều khoản, FAQ, email/hợp đồng và hướng dẫn vận hành. Vẫn phân biệt các trường hợp hủy do sự cố/bất khả kháng cần xử lý riêng.

**Cập nhật sau rà soát:** đã sửa `PolicyPage.tsx`: thống nhất số khách mục tiêu, bỏ lý do hủy vì thiếu khách ở mục 6.1 và FAQ, ghi rõ vẫn tổ chức cho khách trả đủ đúng hạn, chỉ chuyển sau khi khách đồng ý. Từ chối hoặc hết hạn phản hồi đề xuất ghép thì giữ chuyến ban đầu cùng hạn thanh toán của chuyến đó. Đồng thời làm rõ thời gian giữ chỗ không vượt hạn chốt, cọc 50% và hậu quả trả thiếu, sửa số mục bị trùng, cập nhật FAQ xác thực khi khai hành khách. Rà mẫu email/hợp đồng không thấy thêm nội dung hủy chỉ vì thiếu khách. Không thay đổi backend; A3 về cửa hủy/chuyển bỏ qua chấp thuận vẫn còn.

**Kiểm tra phần sửa:** ESLint trang điều khoản và build frontend đạt; `PublicPolicyTest` đạt 6/6 bài, 31 assertions. Chưa kiểm tra trực quan trên trình duyệt.

## Phạm vi đã đối chiếu

| Khâu | Bằng chứng hiện có | Đánh giá |
|---|---|---|
| Tài khoản, đăng nhập, phân quyền, hồ sơ | AuthSecurity, PasswordReset, Profile, RateLimit, AdminUserManagement | Các bài hiện có đạt; OTP tạo đơn còn A1/A5 |
| Tạo tour, lịch trình, lịch khởi hành | AdminScheduleManagement, TourItineraryValidation, ScheduleReturnTime, ItineraryCheckpointPersistence | Các bài hiện có đạt; trạng thái admin/HDV đã được đồng bộ ở lượt trước |
| Tìm tour, sức chứa, giữ chỗ, trẻ em/em bé | TourSearch, HeldSeats, BookingHoldExpiry, SeatReleaseRule | Luồng giữ chỗ có kiểm thử đạt; một số bài tạo đơn/kiểm tra em bé bị OTP chặn |
| Mã giảm giá, chống đặt trùng | DiscountCodeAtCheckout, DuplicateBookingGuard | Chưa thể xác nhận trọn luồng vì các bài cũ bị 403; A5 được tái hiện riêng |
| Cọc 50%, trả đủ trước hạn, mất cọc khi quá hạn | CommittedDeparturePolicy, BalanceDueFlow, PaymentLedgerAndRefund | Luồng thông thường đạt; callback muộn còn A2 |
| Khai hành khách, import, OTP hoặc chủ đơn đăng nhập | PassengerOtp, PassengerOwnerAccess, PublicPassengerDeclaration, các bài import XLSX/CSV/DOCX | Các bài hiện có đạt; tách biệt với OTP tạo đơn |
| Đề xuất ghép, đồng ý/từ chối, kiểm lại chỗ | ScheduleMerge, BookingProposal, CommittedDeparturePolicy | Luồng ghép có chấp thuận đạt; cửa hủy/chuyển còn A3 |
| Khách hủy, công ty hủy, hoàn tiền, đổi giá/chuyển chuyến | CancellationPolicy, BookingChangeRequest, ScheduleCancellation, BookingTransfer, TransferDepositCarryOver | Phần lớn bài đạt; còn A2/A3, nội dung A6 đã sửa |
| Đoàn riêng, báo giá, hợp đồng | GroupBooking, Contract | Bài hiện có đạt; vẫn là luồng hợp đồng riêng, không mặc nhiên áp mọi quy tắc của bán chỗ lẻ |
| Phân công/nhận chuyến, bàn giao, điểm danh, ảnh | ScheduleGuide, GuideAssignmentResponse, GuideHandover, AttendanceRules, GuideAttendance | Các bài hiện có đạt, ảnh không cần GPS |
| Sự cố, hoàn tất, no-show, đánh giá | Incident, BookingFinalization, ReviewRestriction | Luồng thông thường đạt; xử lý bù tác vụ nền còn A4 |
| Tài chính, công nợ, báo cáo, email/thông báo, chatbot | Receivables, AdminTransactionRegister, AdminDashboard, Notification, DepartureReminder, AiChatbot | Bài hiện có đạt nhưng không chứng minh email/cổng thanh toán ngoài đời đã vận hành; A2 ảnh hưởng đối soát |

## Những điều chưa được xác nhận trong lượt này

- Thao tác xuyên suốt các màn trên trình duyệt/điện thoại; trình duyệt tích hợp chưa khả dụng ở phiên trước.
- MySQL thực tế: khóa dòng, deadlock, hai giao dịch tranh chỗ/tiền. SQLite không chứng minh được các tình huống cạnh tranh này.
- VNPay sandbox/thật, email tới hộp thư thật, Cloudinary thật; các bài kiểm thử dùng fixture/mock hoặc gọi dịch vụ nội bộ.
- Scheduler, hàng đợi thư và migration đang hoạt động đầy đủ trên môi trường triển khai. Không chạy các tác vụ làm thay đổi dữ liệu thật để kiểm tra.

## Thứ tự khắc phục đề nghị

1. OTP tạo đơn và gửi lại yêu cầu: A1 + A5 trong cùng thiết kế.
2. Thu muộn/thừa và hoàn tiền: A2, bao gồm giao dịch lặp và đối soát.
3. Chấp thuận khi đổi chuyến: A3 cho mọi cửa điều hành có thể chuyển khách.
4. Xử lý bù tác vụ nền: A4, đối chiếu số chỗ và trạng thái sau nhiều lần chạy.
5. Điều khoản/FAQ: A6 đã sửa nội dung và rà mẫu email/hợp đồng; vẫn cần xử lý A3 để mọi cửa chuyển khách tuân thủ chấp thuận.
6. Cập nhật tiền điều kiện OTP của các bài kiểm thử cũ, sửa nhận diện alias trong bộ kiểm tra lược đồ, chạy lại toàn bộ và kiểm thử xuyên suốt trên môi trường tích hợp.
