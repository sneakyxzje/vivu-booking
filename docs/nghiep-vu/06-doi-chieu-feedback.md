# 06 - Đối chiếu feedback hội đồng bảo vệ thử

Tài liệu này ánh xạ từng điểm hội đồng nêu sang hiện trạng mã nguồn, phương án xử lý,
tài liệu mô tả chi tiết và mức ưu tiên.

Ký hiệu hiện trạng:

- `Chưa có`: chưa có gì trong mã nguồn.
- `Một phần`: chạy được nhưng còn thiếu một mảng nghiệp vụ, ghi rõ thiếu gì.
- `Đã có`: có mã chạy thật và có kiểm thử tự động giữ.

**Cập nhật ngày 17/08/2026.** Bảng dưới đây phản ánh mã nguồn tại thời điểm đó, không phải kế
hoạch. Cột cuối chỉ thẳng chỗ đặt luật để đối chiếu được ngay. Bộ kiểm thử: 488 bài, xanh.

## 1. Bảng đối chiếu tổng hợp

| STT | Hội đồng nêu | Hiện trạng | Luật nằm ở đâu |
| --- | --- | --- | --- |
| 1 | Hỗ trợ chuyển tour | **Đã có** | `BookingTransferService` — cùng tour và khác tour, khóa hai chuyến theo id tăng dần, lần hai thu phí, chặn sau hạn chốt |
| 2 | Cập nhật lại booking | **Đã có** | `BookingContactService` sửa thông tin người đặt; `PassengerPolicyService` sửa hành khách; chuyển chuyến, xác nhận, hủy, mở lại. **Số khách cố ý không cho sửa** — xem ghi chú dưới bảng |
| 3 | Cập nhật thông tin khách hàng | **Đã có** | `PassengerPolicyService` — quyền sửa theo thời điểm: trước hạn chốt khách tự sửa, sau đó chỉ điều hành, khởi hành rồi thì khóa |
| 4 | Validate điểm danh | **Đã có** | `AttendanceService` — chín quy tắc. Quy tắc 8 (điểm dừng bắt buộc ảnh) đã viết nhưng chưa có nút gọi |
| 5 | Điểm danh từng điểm đến từng ngày | **Đã có** | `passenger_checkins` theo từng hành khách, từng điểm dừng |
| 6 | Ghi chú khi khách vắng mặt | **Đã có** | Bắt buộc ghi chú tối thiểu 10 ký tự khi trạng thái khác có mặt |
| 7 | Thời gian hủy tour phải trước bao lâu | **Đã có** | `CancellationPolicy` + `CancellationPolicyService` — bậc thang lưu thành dữ liệu, admin sửa được, đơn chép chính sách lúc đặt nên không hồi tố |
| 8 | Hủy sát giờ có cộng lại slot không | **Đã có** | `BookingHoldService::shouldReleaseSeats` — ghế chết. Câu trả lời là **không**, và có nút mở lại thủ công |
| 9 | Tour đang chạy không được hủy | **Đã có** | `BookingPolicyService::assertCancellable` ở tầng dịch vụ, áp cho cả bốn lối vào |
| 10 | Ai được hủy, ai xác nhận | **Đã có** | `BookingChangeRequestService` — khách xin, điều hành duyệt; kèm nhật ký ghi ai làm gì |
| 11 | Thay hướng dẫn viên giữa chừng | **Đã có** | `GuideHandoverService` — hai lối vào: hướng dẫn viên xin rồi điều hành duyệt, hoặc điều hành tự bàn giao khi không liên lạc được. Bắt buộc kèm lý do và tình trạng đoàn; người cũ mất quyền ghi ngay, dữ liệu đã ghi giữ nguyên |
| 12 | Chính sách hoàn tiền hoặc đặt cọc | **Một phần** | Hoàn tiền xong. **Sổ giao dịch nhiều đợt đã có** (`booking_payments`, làm nền cho đơn đoàn): cọc, thanh toán nốt, hoàn — `paidAmount()` đọc tổng sổ khi sổ có dòng. **Còn thiếu:** đơn lẻ vẫn trả một lần qua cổng, chưa có nhắc nợ tự động |
| 13 | Chi phí phát sinh khi tour đã chạy | **Đã có** | `IncidentService` — hướng dẫn viên báo cáo kèm ảnh và **không nhập được tiền**; điều hành quyết phương án và phân bổ cho từng đơn; khoản chưa duyệt chưa có hiệu lực |
| 14 | Booking theo đoàn | **Một phần** | `GroupBookingService` — đường ống yêu cầu → báo giá → chốt; chỉ bước chốt mới chiếm chỗ và đi qua đúng luật giữ chỗ của khách lẻ; thu tiền nhiều đợt qua sổ giao dịch; đoàn được giảm số khách trước hạn chốt (khách lẻ vẫn không). **Còn thiếu:** nộp danh sách bằng Excel, hóa đơn VAT mới lưu thông tin chưa phát hành — xem ghi chú mục 6 |
| 15 | Xóa tour khi đã có người thanh toán | **Đã có** | `ScheduleCancellationService` — ba bước bắt buộc, mỗi đơn đã trả tiền phải có phương án, hoàn 100% hoặc chuyển miễn phí |
| 16 | Ghép tour | **Đã có** | `ScheduleMergeService` — cùng tour, lệch không quá 2 ngày, cả hai chuyến phải còn trước hạn chốt |
| 17 | Hướng dẫn viên phù hợp cho từng tour | **Đã có** | `GuideSuitabilityService` chấm và xếp hạng; `guide_profiles` + `guide_categories` giữ ngôn ngữ, tuyến quen, chuyên môn, sức dẫn. Hồ sơ **không chặn ai** — luật chặn duy nhất vẫn là trùng lịch. Xem ghi chú dưới bảng |
| 18 | Hợp đồng, danh sách khách hàng | **Một phần** | Danh sách đoàn chia theo nhóm đã có. **Hợp đồng chưa có gì** |

**Tổng kết: 15 điểm đã có mã chạy, 3 điểm còn một mảng thiếu (12, 14, 18), không còn điểm nào
ở con số không.**

### Ranh giới của điểm 17: cái gì chặn, cái gì chỉ gợi ý

Đây là chỗ dễ bị hỏi nhất, và câu trả lời phải nhất quán với nguyên tắc đã áp ở mọi nơi khác:
**hệ thống hỗ trợ quyết định, không quyết thay.**

Thiết kế ban đầu ở mục 3 dưới đây liệt kê **bốn tiêu chí lọc bắt buộc** — không trùng lịch, thẻ
hành nghề còn hiệu lực, đang nhận tour, đủ sức dẫn quy mô đoàn. Khi cài đặt đã **cố ý bỏ ba trong
bốn**, chỉ giữ lại một:

| Luật | Vì sao chặn | Nằm ở đâu |
| --- | --- | --- |
| Trùng lịch | Luật vật lý — một người không đứng ở hai đoàn cùng lúc. Hệ thống biết chắc, và người dùng không thể muốn khác | `ScheduleGuideService::lyDoChan` |

Chuyên môn, tuyến quen, sức dẫn, tải công việc **chỉ xếp thứ tự và nói ra lý do**. Lý do: tỷ lệ
người dẫn trên số khách khác nhau theo loại tour và cách từng công ty vận hành — chặn theo một con
số cứng là áp giá trị do lập trình viên nghĩ ra lên mọi tour. Điều hành biết những thứ hệ thống
không biết: người này vừa nhận lời đi tuyến đó, người kia đang muốn học tuyến mới, và họ có thể xếp
thêm người thứ hai mà hệ thống chưa thấy.

**Vì sao bỏ điều kiện thẻ hành nghề.** Thẻ hướng dẫn viên là giấy phép hành nghề do Sở Du lịch cấp,
có thời hạn — nên về lý thì thẻ hết hạn giữa chuyến là điều kiện pháp lý, không phải chuyện ưu tiên
cao thấp. Đã cài đặt xong rồi gỡ đi, vì hai lý do:

1. Hội đồng hỏi *"hướng dẫn viên phù hợp cho từng tour"* — ý là **hợp về chuyên môn**. Điều kiện
   hành nghề nằm cạnh câu hỏi đó chứ không nằm trong nó; trả lời bằng nó là trả lời lệch câu.
2. Theo dõi hiệu lực thẻ là việc của quản lý nhân sự. Phần mềm đặt tour biết ngày kết thúc chuyến
   nhưng không phải là nơi giữ hồ sơ pháp lý của nhân viên — kéo vào đây là mở rộng phạm vi sang
   một trụ nghiệp vụ khác.

Kết quả: điểm 17 nay chỉ có **đúng một luật cứng**, và đó là luật không ai cãi được. Toàn bộ phần
còn lại là hỗ trợ quyết định — nhất quán với mọi chỗ khác trong hệ thống.

Ba điểm nữa nên nói thẳng nếu bị hỏi sâu:

- **Chưa khai hồ sơ thì không bị trừ điểm, cũng không bị đánh dấu.** Người đó chỉ không được cộng
  điểm nào nên nằm giữa danh sách. Phạt họ là phạt nhầm đối tượng: lỗi ở chỗ chưa ai nhập dữ liệu.
- **Ngôn ngữ hiện ra nhưng không chấm điểm.** Tour không có ô khai đoàn cần tiếng gì nên không có
  vế cầu để so. Nói thẳng tốt hơn là bịa ra một tiêu chí trông có vẻ thông minh.
- **Người trùng lịch vẫn hiện trong danh sách**, kèm đúng câu máy chủ sẽ từ chối. Lọc bỏ thì điều
  hành đi tìm mãi một cái tên đáng lẽ phải có mà không hiểu vì sao mất.

Thiết kế ở mục 3 còn nêu *"khoảng nghỉ tối thiểu mười hai giờ giữa hai chuyến"*. Không cài riêng,
vì luật chống trùng lịch hiện có so theo **ngày** chứ không theo giờ — đoàn về lúc 22h thì hôm đó
người dẫn đã coi như bận cả ngày. Điều kiện ấy đã chặt hơn mười hai giờ, thêm nữa là thừa.

### Ranh giới của điểm 14: giá đoàn không có bảng bậc tự động

Thiết kế ở mục 3 nêu *"bậc giá theo số lượng kèm suất miễn phí cho trưởng đoàn"*. Khi cài đặt,
suất miễn phí giữ nguyên, còn **bậc giá tự động cố ý bỏ**: điều hành nhập thẳng một giá mỗi người
cho từng đoàn, kèm hạn hiệu lực. Giảm bao nhiêu cho đoàn 40 người phụ thuộc mùa, quan hệ với
khách, chỗ còn trống — đúng loại quyết định con người cân, cùng nguyên tắc đã áp ở điểm 17 và ở
tỷ lệ người dẫn trên số khách. Hệ thống giữ phần nó giữ được: chỗ và tiền nhất quán, báo giá có
hạn, chốt phải qua đúng luật giữ chỗ của khách lẻ.

Hai chỗ nữa cài khác thiết kế, cùng một lý do "ít trạng thái hơn là ít chỗ hỏng hơn":

- **Báo giá quá hạn không phải một trạng thái** mà suy ra từ `quote_expires_at` lúc đọc — thành
  trạng thái riêng thì phải có tác vụ nền canh giờ để chuyển, thêm một chỗ hỏng không thêm thông tin.
- **Đoàn được giảm số khách, khách lẻ vẫn không.** Không phải mâu thuẫn với quyết định ở điểm 2:
  đơn lẻ 2-4 người hủy đặt lại là thao tác nhỏ, đoàn 40 người bớt 3 người mà bắt hủy đặt lại là
  phá giấy tờ và sổ giao dịch của 37 người còn lại. Chỉ trước hạn chốt danh sách, và chênh lệch
  tiền thừa hệ thống chỉ hiển thị — hoàn hay không là thỏa thuận giữa người với người.

### Vì sao không cho sửa số khách (điểm 2)

Đây là **quyết định có chủ ý, không phải thiếu sót**, và nếu bị hỏi thì trả lời thẳng như vậy.

Hội đồng nêu *"cập nhật lại booking, thực tế và trên web"*. Tài liệu này trước đây tự diễn giải
thành hai luồng, trong đó có sửa số lượng khách, rồi backlog dựng hẳn nhóm J gần 6 ngày công. Đọc
lại nguyên văn thì hội đồng không nói tới số lượng.

Ranh giới đang áp: **sửa thứ gõ nhầm thì được, đổi thứ đã mua thì không.**

Tên, điện thoại, thư điện tử, thông tin hành khách là dữ liệu mô tả — gõ sai thì sửa, không ảnh
hưởng chỗ và tiền. Số lượng khách là thứ đã mua: đổi nó là đổi số chỗ giữ ở chuyến, tổng tiền đơn,
và nếu giảm thì phải tính phí hủy trên phần bớt đi. Khách cần đổi số người thì hủy và đặt lại theo
đúng chính sách hủy, chứ không đi cửa sau qua màn sửa đơn.

Đổi lại, việc sửa thông tin liên hệ **không bị hạn chốt danh sách khóa** — khác với sửa danh sách
hành khách. Danh sách hành khách gửi cho nhà cung cấp nên sau hạn chốt khách hết quyền sửa. Thông
tin liên hệ không đi đâu cả, nó là số hướng dẫn viên gọi khách, càng sát ngày càng cần đúng. Áp
cùng một mốc cho cả hai là khóa ngược.

Nguyên nhân gốc ở mục 2 đã được xử lý: chuyến khởi hành nay có vòng đời đầy đủ, và mười một điểm
đóng được đều nhờ nền đó.

Ba mảng còn thiếu (12 đặt cọc phía khách lẻ, 14 phần Excel và hóa đơn, 18 hợp đồng) ghi rõ ở
[mục 6](#6-ba-việc-còn-lại-và-thứ-tự-bắt-buộc) dưới đây, kèm lý do vì sao lõi của 12 đã được kéo
vào làm nền cho 14.

### Ba chỗ nên nói thẳng nếu bị hỏi sâu

**Điểm 11.** Bàn giao có biên bản đầy đủ, nhưng **cách cài đặt khác thiết kế ở tài liệu 04 mục
4.4**: không dùng bảng phân công có `effective_from` / `effective_to` mà tách làm hai bảng, một
bảng "ai đang phụ trách" và một bảng "lịch sử bàn giao". Lý do đầy đủ ghi ngay trong migration
`2026_08_17_000004`. Hệ quả cần biết: câu "lúc 14h ngày thứ hai ai đang dẫn" phải lần theo lịch
sử bàn giao chứ không tra được bằng một truy vấn.

**Điểm 4.** Quy tắc "điểm dừng yêu cầu ảnh thì phải có ảnh mới chốt được" đã viết trong
`AttendanceService::assertCheckpointCompletable` nhưng chưa có thao tác nào gọi tới, vì chưa có
chức năng chốt điểm dừng. Luật có, đường dẫn tới luật thì chưa.

**Điểm 13.** Phần "hoàn theo giá vốn dịch vụ chưa sử dụng" ở mục 3 dưới đây **không tính tự động**.
Muốn tính đúng thì phải có giá vốn từng dịch vụ, mà tầng cung ứng nằm ngoài phạm vi đồ án. Nên số
tiền do điều hành nhập kèm diễn giải; hệ thống bắt buộc có lý do và giữ vết, chứ không giả vờ tính
được. Nói thẳng như vậy tốt hơn là để hội đồng phát hiện ra con số ấy từ đâu mà có.

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

**Phần này đã làm xong.** Bốn thứ thiếu ở bảng trên nay đều có, và mười một điểm đóng được đều
dựa lên chúng. Dự đoán "làm xong nền thì phần còn lại chỉ là xây tiếp" đã đúng trên thực tế: không
có nhóm nào phải quay lại sửa lược đồ dữ liệu của chuyến.

Một hệ quả ngoài dự tính, đáng nói vì nó cho thấy nền đúng: `booking_deadline` ban đầu chỉ định
dùng cho việc ngừng bán, nhưng cuối cùng điều khiển **năm** quy tắc thuộc năm nhóm khác nhau — bán
chỗ, trả chỗ, sửa tên khách, chuyển chuyến, ghép chuyến. Chi tiết ở
[16 - Dời hạn chốt danh sách](16-sua-han-chot.md).

## 3. Câu trả lời ngắn cho từng câu hỏi

Phần này để dùng trực tiếp khi bảo vệ. Mỗi câu trả lời gói trong vài câu, có lý do,
không lan man.

**Đọc kèm bảng ở mục 1.** Các câu dưới đây viết từ lúc còn là thiết kế, nên đọc như mô tả nghiệp
vụ mong muốn — **không phải chỗ nào cũng khớp với mã đang chạy**. Với 15 điểm đánh dấu *Đã có*,
đối chiếu bảng trước khi nói: điểm 11, 14 và 17 đều cài khác thiết kế ban đầu, và mỗi lần lệch
đều có lý do ghi ngay dưới bảng. Với 3 điểm *Một phần*, phần thiếu đã ghi rõ — đừng trình bày như
đã có.

**1. Hỗ trợ chuyển tour.**
Hệ thống phân biệt ba tình huống: đổi ngày trong cùng tour, đổi sang tour khác, và chuyển do
hãng khi chuyến bị hủy hoặc bị ghép. Khách khởi xướng thì phải trước khởi hành ít nhất bảy ngày,
miễn phí một lần, chênh giá thì bù thêm, rẻ hơn thì ghi công nợ. Hãng khởi xướng thì miễn phí
hoàn toàn và hoàn chênh nếu tour đích rẻ hơn. Về kỹ thuật, thao tác này khóa hai chuyến cùng lúc
nên phải khóa theo thứ tự khóa chính tăng dần để tránh khóa chết.

**2. Cập nhật lại booking theo thực tế.**
Ranh giới là **sửa thứ gõ nhầm thì được, đổi thứ đã mua thì không**. Thông tin người đặt và thông
tin hành khách đều sửa được vì đó là dữ liệu mô tả, gõ sai thì sửa lại. Số lượng khách thì không,
vì đổi nó là đổi số chỗ giữ ở chuyến và tổng tiền đơn — khách cần đổi số người thì hủy và đặt lại
theo đúng chính sách hủy. Hai loại thông tin trên chịu hai mốc khóa khác nhau: danh sách hành khách
khóa với khách sau hạn chốt vì đã gửi nhà cung cấp, còn thông tin liên hệ sửa được tới tận lúc
đoàn đang đi vì đó là số hướng dẫn viên gọi khách. Mọi thay đổi đều ghi nhật ký gồm người thao tác,
giá trị trước và sau.

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
| Toàn hệ thống chạy giờ Việt Nam (`Asia/Ho_Chi_Minh`), cột thời gian nghiệp vụ lưu giờ treo tường | Đã triển khai |
| Tra cứu đơn cho khách vãng lai bằng mã ngẫu nhiên thay vì số thứ tự | Đã triển khai |
| **488 kiểm thử tự động**, chạy trong quy trình tích hợp liên tục | Đã triển khai |
| Khóa hai chuyến theo id tăng dần khi thao tác chạm hai chuyến, để tránh khóa chết | Chuyển chuyến và ghép chuyến |
| Nhật ký thay đổi cho cả đơn hàng lẫn chuyến, gộp thành một dòng thời gian tra cứu được | `/admin/audit-logs`, lọc riêng được các lần chạm tiền |
| Xem trước hậu quả trước khi bấm ở mọi thao tác nặng | Hủy đơn, hủy chuyến, ghép chuyến, chuyển chuyến, dời hạn chốt |

## 5. Cách trình bày

Thừa nhận thẳng ba phần còn thiếu — không còn điểm nào ở con số không — chỉ ra nguyên nhân chung
là chưa mô hình hóa vòng đời chuyến khởi hành, nói rõ nền đó nay đã dựng xong và mười lăm điểm
đóng được là nhờ nó.

Hai điều nên nói mà thường bị bỏ qua:

**Cùng một mốc điều khiển năm luật.** `booking_deadline` không phải năm chỗ kiểm tra riêng lẻ mà
là một sự thật nghiệp vụ — ngày gửi danh sách cho nhà cung cấp — được năm nhóm cùng đọc. Đó là
khác biệt giữa hiểu bài toán và ghép tính năng.

**Luật nằm ở tầng dịch vụ, không phải ở giao diện.** Mỗi nghiệp vụ chạm tiền hoặc chạm chỗ đều có
đúng một đường ghi. Vài lỗi trong quá trình làm đều cùng một khuôn: luật có ở một đường ghi mà
thiếu ở đường kia. Mỗi lần phát hiện đều khóa lại bằng một bài kiểm thử giữ cả hai đường.

Hội đồng đánh giá cao việc hiểu vì sao thiếu hơn là việc liệt kê đủ tính năng.

## 6. Ba việc còn lại và thứ tự bắt buộc

Ghi ở đây thay vì trỏ sang tài liệu 00, vì tài liệu 00 xếp cả ba vào **trong phạm vi** (mục 2, dòng
"Đoàn và hồ sơ") — trỏ sang một chỗ không giải thích thì đúng loại vênh hội đồng bắt được.

**Thứ tự phụ thuộc đã được tôn trọng khi cài đặt 14.** Đoàn thanh toán nhiều đợt, nên phần lõi
của 12 — sổ giao dịch `booking_payments`, mỗi khoản thu/hoàn một dòng, chỉ thêm không sửa — được
dựng TRƯỚC rồi luồng đoàn mới xây lên trên. Dựng luồng đoàn trên cột `paid_at` kiểu bật/tắt rồi
mới thêm sổ nghĩa là đập ra làm lại; `paidAmount()` nay đọc tổng sổ khi sổ có dòng, nhờ đó phép
"mất cọc" (đoàn cọc 30% hủy sát ngày) tính đúng và có kiểm thử giữ.

Phần còn thiếu của từng điểm:

| Điểm | Còn thiếu gì | Vì sao lùi được |
| --- | --- | --- |
| 12 | Đặt cọc cho **khách lẻ** (cổng thanh toán thu một phần), nhắc nợ tự động | Thu một phần qua VNPay cần hợp đồng thương mại thật (cùng lý do hoàn tự động, doc 00 §3.3); nhắc nợ cần hàng đợi nền |
| 14 | Nộp danh sách khách bằng **tệp Excel** có kiểm từng dòng; phát hành hóa đơn VAT (mới lưu MST, địa chỉ) | Danh sách vẫn nhập được từng người qua màn hành khách sẵn có — Excel là tiện, không phải nghiệp vụ mới. Hóa đơn điện tử ngoài phạm vi (doc 00 §3.2) |
| 18 | Hợp đồng PDF từ mẫu, đánh số theo năm, lưu bản ký | Làm cùng đợt với hóa đơn — cả hai đều là sinh giấy tờ từ dữ liệu đã có đủ |

Hai đầu thừa nhỏ hơn, nên nói nếu bị hỏi kỹ:

- `AttendanceService::assertCheckpointCompletable` (quy tắc 8) chưa có thao tác nào gọi tới.
- `IncidentType::thuongDoHangChiu()` đã viết nhưng chưa nối vào luồng quyết định phương án.
