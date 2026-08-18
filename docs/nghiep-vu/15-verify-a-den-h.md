# 15 - Verify nhóm A đến H: từ mã gốc đã thêm được gì

Tài liệu này để **kiểm chứng**, không phải để giới thiệu. [14 - Nhóm A, B, C, D đã giải quyết
bài toán gì](14-nhom-a-va-d.md) kể câu chuyện nghiệp vụ; tài liệu này chỉ ra chỗ đứng của từng
luật trong mã và cách tự chứng minh nó còn sống.

**Muốn thử tay ngay thì nhảy thẳng xuống mục 2.**

## 1. Mốc so sánh

Mã gốc là commit `bafa231` (`chore(server): rewrite booking emails in proper Vietnamese`), ngay
trước khi tài liệu nghiệp vụ được viết.

```
git diff --stat bafa231 HEAD -- server client
```

| Chỉ số | Gốc | Hiện tại |
| --- | --- | --- |
| Tệp thay đổi | | 123 |
| Dòng thêm / bớt | | +14.959 / −1.115 |
| Bài kiểm thử | 41 | 214 |
| Lớp dịch vụ | 5 | 9 |
| Lệnh chạy nền | 1 | 5 |
| Enum trạng thái | 0 | 3 |

Đếm lại số bài kiểm thử bất cứ lúc nào:

```
git grep -h "public function test" bafa231 -- server/tests | wc -l
git grep -h "public function test" HEAD -- server/tests | wc -l
```

## 2. Đọc tài liệu này thế nào

Chỉ có **một** quy trình thử tay, ở [mục 11](#11-một-buổi-test-đầy-đủ-khoảng-40-phút). Các mục 5
tới 9 không phải là các bài thử khác nhau; chúng giải thích **vì sao** từng bước ở mục 11 lại ra
kết quả đó.

Muốn bắt tay vào thử ngay thì bỏ qua tất cả, chạy:

```
cd server
php artisan migrate:fresh --seed
```

Lệnh in ra danh sách việc cần bấm, theo thứ tự, **kèm số hiệu đơn và chuyến thật của lần chạy
đó**. Cứ làm từ trên xuống. Quay lại tài liệu này khi muốn biết vì sao một bước ra kết quả như
vậy, hoặc khi màn hình không giống mô tả.

Nhãn `S1` tới `S9` trong tài liệu là quy ước riêng để gọi tên chín chuyến; **trên màn hình không
có chữ nào như thế**. Bảng cuối phần in ra của seeder ghi rõ chuyến `#mấy` ứng với `S` nào.

## 3. Ba cách verify

**Tầng 1 - chạy bộ kiểm thử.** Nhanh nhất, chứng minh luật còn hiệu lực chứ không chỉ tồn tại
trong mã chết.

```
cd server
php artisan test
```

Toàn bộ 214 bài phải xanh. Mỗi nhóm bên dưới có lệnh lọc riêng.

**Tầng 2 - đọc mã theo neo.** Mỗi nhóm có bảng "neo mã" chỉ đúng tệp và dòng. Đọc phần chú
thích trước, phần thân sau: chú thích nói **vì sao**, thân nói **thế nào**. Hội đồng hỏi vì sao
nhiều hơn hỏi thế nào.

**Tầng 3 - thử tay trên giao diện.** Chạy ở máy nhà vì cần MySQL. Một quy trình duy nhất ở mục
11, và danh sách việc cần bấm do seeder in ra kèm số hiệu thật.

Một điều cần phân biệt khi verify: **kiểm thử ở tầng dịch vụ chứng minh luật đúng, kiểm thử qua
HTTP chứng minh luật được áp**. Đã có lần bộ kiểm tầng dịch vụ xanh 21/21 trong khi đường API
thật thủng bốn quy tắc, vì bộ đó không đi qua controller. Nhóm H bên dưới có cả hai loại, và đó
là lý do.

## 4. Dữ liệu để thử tay

`BusinessScenarioSeeder` dựng sẵn một tour tên **Tour Thử Nghiệm Nghiệp Vụ 3N2Đ** với chín
chuyến phủ hết sáu trạng thái vòng đời và năm bậc phí hủy. Mọi tình huống của A, B, C, D, H đều
có sẵn dữ liệu, không phải tự tạo bằng tay.

```
cd server
php artisan migrate:fresh --seed        # dựng lại toàn bộ
php artisan db:seed --class=BusinessScenarioSeeder   # chỉ dựng lại phòng thí nghiệm
```

Lệnh in ra **danh sách việc cần bấm kèm số hiệu đơn và chuyến thật**, cộng bảng tra cứu chín
chuyến ở cuối. Bảng dưới đây chỉ để tra khi cần hiểu ý đồ của từng chuyến; lúc thao tác thì bám
theo phần seeder in ra, vì ở đó là số hiệu thật của lần chạy hiện tại.

Đăng nhập khách: `customer@gmail.com` / `customer123` — mọi đơn kịch bản đều gắn vào tài khoản
này để xem một lần là hết.

| Mã | Cách hiện tại | Trạng thái | Dùng để thử |
| --- | --- | --- | --- |
| S1 | còn 480h | Đang mở bán | Hủy → hoàn **90%**, chỗ trả về kho. Có sẵn một đơn vừa hủy 2 giờ trước để thử **mở lại đơn** |
| S2 | còn 240h | Đang mở bán | Hủy → hoàn **70%** |
| S3 | còn 120h | Đang mở bán | Hủy → hoàn **50%**, vẫn **trước** hạn chốt nên chỗ trả về |
| S4 | còn 60h | Đã đóng bán | Hủy → hoàn **30%** nhưng **sau** hạn chốt nên sinh **ghế chết**. Có sẵn một ghế chết dựng trước |
| S5 | còn 26h | Đã chốt chuyến | Hủy → hoàn **0%**. Có sẵn một đơn quá hạn thanh toán để chạy `bookings:release-expired` |
| S6 | đã qua 24h | Đang khởi hành | **Chặn hủy** ở cả hai trang. Điểm danh: một đơn đã ghi dở, một đơn chưa ghi gì |
| S7 | đã qua 120h | Đã kết thúc | Chạy `bookings:finalize-completed`: ba đơn ra ba kết quả khác nhau |
| S8 | còn 360h | Đã hủy chuyến | Trạng thái cuối, không đi đâu được nữa |
| S9 | còn 90h | Đang mở bán | Đủ khách và tới hạn chốt, `schedules:confirm-ready` sẽ **chốt** chuyến này |

S4 và S9 là cặp đối chứng cho lệnh chốt chuyến: cả hai đều tới hạn chốt, nhưng S4 thiếu khách nên
chỉ bị cảnh báo còn S9 đủ khách nên được chốt thật.

### Ba cặp đối chứng đáng thử nhất

Dữ liệu cố ý dựng thành từng cặp gần giống nhau nhưng ra kết quả khác nhau. Hiểu được vì sao
khác là hiểu được luật.

**S3 với S4 — hai cổng đặt ở hai mốc khác nhau.** Cả hai đều là đơn đã thanh toán, chỉ cách nhau
60 giờ. Hủy S3 thì chỗ về kho, hủy S4 thì thành ghế chết. Vì tiền theo bậc giờ còn chỗ theo hạn
chốt 72 giờ, và hai mốc đó độc lập.

**Ghế chết ở S4 với đơn quá hạn ở S5 — cùng "hủy sau hạn chốt", khác kết quả.** Đơn S4 đã thanh
toán nên chỗ đã cam kết với nhà cung cấp, giữ lại. Đơn S5 chưa trả tiền nên chưa có cam kết nào,
trả chỗ về kho ngay. Chạy `php artisan bookings:release-expired` để thấy.

**Ba đơn của S7 — cùng một chuyến, ba kết luận.** Đơn thứ nhất cả hai khách có mặt, đơn thứ hai
cả hai vắng, đơn thứ ba không ai điểm danh. Chạy `php artisan bookings:finalize-completed`:

| Đơn | Bằng chứng | Kết quả |
| --- | --- | --- |
| 1 | Cả hai có mặt | Đã hoàn thành |
| 2 | Cả hai vắng | **Khách không có mặt** |
| 3 | Không ghi gì | Đã hoàn thành |

Đơn thứ ba là chỗ quan trọng. Thiếu bằng chứng thì **không** kết luận bất lợi cho khách.

### Kiểm chứng chính dữ liệu mẫu

Dữ liệu mẫu sai còn tệ hơn không có, vì người thử tay sẽ đi tìm lỗi trong mã ứng dụng trong khi
lỗi nằm ở mốc thời gian của dữ liệu. Bộ kiểm thử khóa từng con số trong bảng trên:

```
php artisan test --filter=BusinessScenarioSeederTest
php artisan bookings:check-seat-consistency      # phải báo mọi chuyến đều khớp
```

---

## 5. Nhóm A - Chuyến đi có vòng đời

### Mã gốc làm gì

`tour_schedules.status` là `enum('active','inactive','full')`.

```
git show bafa231:server/database/migrations/2026_05_15_173816_create_tour_schedules_table.php
```

Ba giá trị này nói **tình trạng bán**, không nói **chuyến đang ở đâu**. Không có gì phân biệt
chuyến chưa khởi hành với chuyến đang đi và chuyến đã về. Cũng không có `end_date`,
`min_people`, `booking_deadline`.

Hệ quả dây chuyền: mọi luật cần biết "chuyến đã lăn bánh chưa" đều không có chỗ bám. Đó là gốc
của cả nhóm D.

### Đã thêm

Sáu trạng thái `open → closed → confirmed → in_progress → completed`, cộng `cancelled`. Một ma
trận chuyển hợp lệ. Một cửa duy nhất để đổi trạng thái. Ba lệnh nền tự chuyển theo thời gian.

### Neo mã

| Việc | Vị trí |
| --- | --- |
| Sáu trạng thái và nhãn | `server/app/Enums/ScheduleStatus.php:32` |
| Ma trận chuyển hợp lệ | `server/app/Enums/ScheduleStatus.php:55` |
| Đổi trạng thái, khóa rồi đọc lại | `server/app/Services/ScheduleLifecycleService.php:84` |
| Trạng thái theo đồng hồ | `server/app/Services/ScheduleLifecycleService.php:129` |
| Ba lệnh nền | `server/app/Console/Commands/{CloseExpiredSchedules,ConfirmReadySchedules,AdvanceScheduleStatus}.php` |
| Đăng ký lịch chạy | `server/routes/console.php` |

### Hai chi tiết đáng đọc kỹ

**Không có đường `in_progress → cancelled`.** Xem `ScheduleStatus.php:55`. Đây không phải thiếu
sót mà là luật nghiệp vụ: đoàn đã lên đường thì không "hủy" được nữa, chỉ có ghi nhận sự cố.
Ràng buộc này nằm trong **cấu trúc dữ liệu**, không nằm trong một câu `if` ai đó có thể quên.

**Khóa dòng rồi mới đọc lại trạng thái**, `ScheduleLifecycleService.php:100`. Nếu đọc trước khi
khóa, hai người bấm cùng lúc sẽ cùng thấy trạng thái cũ và cùng ghi đè. Đọc sau khi khóa thì
người vào sau thấy trạng thái đã đổi và bị từ chối. Đây là mẫu lặp lại ở mọi chỗ chạm số chỗ và
tiền trong dự án.

### Chứng minh

```
php artisan test --filter="ScheduleStatusTest|ScheduleLifecycleTest|ScheduleAutomationTest"
```

Bài quan trọng nhất: chuyển từ `in_progress` sang `cancelled` bị từ chối.

---

## 6. Nhóm D - Đơn của chuyến đã lăn bánh thì khóa

### Mã gốc làm gì

Hai lối hủy, không lối nào biết chuyến đang ở đâu.

```
git show bafa231:server/app/Http/Controllers/Api/Admin/AdminBookingController.php
```

Quản trị hủy được mọi đơn ở `pending` hoặc `confirmed`, **không kiểm tra chuyến đã khởi hành
chưa**. Đoàn đang ở Hạ Long thì quản trị vẫn bấm hủy đơn được, chỗ trả về kho, và hệ thống bán
lại chỗ của người đang ngồi trên xe.

Phía khách còn lạ hơn: chỉ hủy được đơn `pending`. Đơn đã xác nhận thì khách **không có đường
hủy nào cả** — nên bài toán hoàn tiền chưa từng phát sinh.

### Đã thêm

`BookingPolicyService::assertCancellable` chặn ở tầng dịch vụ, áp cho cả bốn lối vào. Cộng D03:
đơn tự chốt thành **đã hoàn thành** hoặc **khách không có mặt** khi chuyến kết thúc.

### Neo mã

| Việc | Vị trí |
| --- | --- |
| Luật chặn hủy | `server/app/Services/BookingPolicyService.php:36` |
| Trạng thái chuyến chặn hủy | `server/app/Enums/ScheduleStatus.php:92` |
| Chốt đơn sau chuyến | `server/app/Services/BookingFinalizationService.php:36` |
| Quy tắc kết luận không có mặt | `server/app/Services/BookingFinalizationService.php:121` |
| Lệnh chạy nền | `server/app/Console/Commands/FinalizeCompletedBookings.php` |

### Hai chi tiết đáng đọc kỹ

**Vì sao đặt luật ở tầng dịch vụ chứ không ở controller.** Có bốn đường vào hủy: khách tự hủy,
quản trị hủy, tác vụ nền nhả chỗ quá hạn, và chuyển chuyến ở Mốc 2. Viết luật trong controller
thì phải chép bốn lần và lần thứ năm ai đó sẽ quên. Một hàm, bốn nơi gọi.

**Kết luận không có mặt lệch một chiều có chủ ý**, `BookingFinalizationService.php:121`. Chỉ
đánh `no_show` khi **mọi** hành khách trên đơn đều được ghi vắng tại điểm đón đầu tiên. Điểm
danh dở dang hoặc không có gì thì tính là đã đi. Lý do: `no_show` cắt đường hoàn tiền của khách,
nên nó cần bằng chứng cho từng người. Hướng dẫn viên quên điểm danh là lỗi vận hành, không được
quy sang khách.

### Chứng minh

```
php artisan test --filter="BookingCancellationGuardTest|BookingFinalizationTest"
```

Bài quan trọng nhất: `test_diem_danh_thieu_nguoi_thi_khong_ket_luan_khong_co_mat`.

Khách chỉ tự hủy được đơn **chưa thanh toán**; đơn đã thu tiền đi đường duyệt của nhóm F, chưa
dựng. Xem mục 10.

---

## 7. Nhóm B - Hủy trước bao lâu thì hoàn bao nhiêu

### Mã gốc làm gì

Không có gì. Không bảng chính sách, không công thức, không nơi nào tính ra một con số tiền hoàn.
Khách hủy thì đơn chuyển `cancelled`, hết.

```
git show bafa231:server/database/migrations/ | grep cancellation
```

Không có kết quả.

### Đã thêm

Hai bảng chính sách, năm mốc mặc định theo giờ trước khởi hành, dịch vụ tính phí và tiền hoàn,
màn quản trị chính sách, và báo giá hoàn cho khách xem **trước khi** bấm hủy.

### Neo mã

| Việc | Vị trí |
| --- | --- |
| Tìm mức hoàn theo số giờ còn lại | `server/app/Services/CancellationPolicyService.php:91` |
| Tính phí và tiền hoàn | `server/app/Services/CancellationPolicyService.php:135` |
| Năm mốc mặc định | `server/database/seeders/CancellationPolicySeeder.php` |
| Màn quản trị chính sách | `client/src/pages/admin/CancellationPolicyManagement.tsx` |
| Khách xem trước khi hủy | `client/src/components/RefundPolicyCard.tsx` |

### Hai chi tiết đáng đọc kỹ

**Kẹp dưới bằng 0.** `tiền hoàn = max(0, đã thu − phí hủy)`. Khách hủy thì không bao giờ phải
nộp thêm. Bỏ `max(0, ...)` thì khách đóng cọc ít mà hủy muộn sẽ nhận số âm, tức hệ thống đòi
thêm tiền của người vừa hủy.

**Chính sách sao chép vào đơn lúc đặt**, không đọc qua tour. Sửa bảng phí hôm nay không được đổi
điều khoản của đơn đặt tháng trước. Đây là chỗ dễ làm sai nhất vì đọc qua quan hệ trông gọn hơn.

### Chứng minh

```
php artisan test --filter="CancellationPolicyTest|RefundQuoteApiTest|AdminCancellationPolicyTest"
```

Bài quan trọng nhất: sửa chính sách không hồi tố đơn cũ.

### Muốn thử thêm phần không hồi tố

Vòng 1 của buổi test đã cho thấy năm bậc phí. Muốn chứng minh nốt việc sửa chính sách không hồi
tố thì làm thêm hai bước, và **phải làm cả hai** mới đủ nghĩa:

1. `/admin/cancellation-policies` đổi mức hoàn của một bậc, rồi xem lại năm đơn cũ — số **giữ
   nguyên**, vì chính sách đã sao chép vào đơn lúc đặt.
2. Đặt một đơn mới trên trang khách — đơn mới **ăn theo mức vừa sửa**.

Một mình bước 1 chỉ chứng minh số không đổi, có thể vì chính sách chưa được đọc lần nào.

---

## 8. Nhóm C - Hủy rồi thì chỗ có bán lại được không

### Mã gốc làm gì

`BookingHoldService::releaseHold` **luôn luôn** trả chỗ về kho:

```php
$schedule->decrement('booked_people', min($booking->guests, (int) $schedule->booked_people));
```

Không điều kiện. Khách hủy trước ngày đi một tuần hay hủy lúc nửa đêm hôm trước đều như nhau.

Thực tế thì không như nhau. Qua hạn chốt danh sách, phòng khách sạn và suất ăn đã đặt theo số
người gửi nhà cung cấp, tiền đã trả rồi. Trả chỗ đó về kho nghĩa là bán một suất mà công ty vẫn
phải trả tiền cho suất cũ.

### Đã thêm

Khái niệm **ghế chết**: chỗ trống về mặt vật lý nhưng chưa bán lại được. Quyết định trả chỗ dựa
trên `booking_deadline`. Ghế chết ở lại tới khi chuyến kết thúc — không có nút mở lại, lý do ở
[06](06-doi-chieu-feedback.md). Lệnh đối chiếu số chỗ chạy hằng giờ.

### Neo mã

| Việc | Vị trí |
| --- | --- |
| Quyết định trả chỗ hay không | `server/app/Services/BookingHoldService.php::shouldReleaseSeats` |
| Đã vào danh sách đoàn chưa | `server/app/Services/BookingHoldService.php::hasEnteredManifest` |
| Lệnh đối chiếu | `server/app/Console/Commands/CheckSeatConsistency.php` |
| Đếm ghế chết khi dời hạn chốt | `server/app/Services/ScheduleDeadlineService.php::impact` |

### Hai chi tiết đáng đọc kỹ

**Đơn chưa thanh toán thì luôn trả chỗ.** Xem `BookingHoldService.php:176`. Giữ chỗ chưa trả
tiền mà quá hạn thì chưa có cam kết nào với nhà cung cấp, giữ lại chỗ đó là tự làm hẹp kho hàng.
Chỉ đơn **đã vào danh sách đoàn** mới sinh ra ghế chết.

**`hasEnteredManifest` đọc `paid_at` và `confirmed_at`, không đọc `status`.** Lý do ở
`BookingHoldService.php:202`: tới thời điểm hàm này chạy thì `status` đã là `cancelled` rồi, hỏi
nó không còn ý nghĩa. Phải hỏi hai cột dấu vết.

**Lệnh đối chiếu chỉ báo cáo, không tự nắn số.** Lệch số chỗ là dấu hiệu có lỗi nghiệp vụ ở đâu
đó; tự sửa sẽ che mất nguyên nhân. Muốn nắn thì chạy kèm `--fix`, sau khi đã hiểu vì sao lệch.

### Chứng minh

```
php artisan test --filter="SeatReleaseRuleTest|HeldSeatsTest|SeatConsistencyCommandTest"
```

---

## 9. Nhóm H - Điểm danh chi tiết

### Mã gốc làm gì

Bảng `booking_checkins`:

```php
$table->foreignId('booking_id');
$table->foreignId('tour_itinerary_id');
$table->boolean('present')->default(true);
$table->unique(['booking_id', 'tour_itinerary_id']);
```

Điểm danh theo **đơn**, theo **ngày**, một giá trị **đúng/sai**.

Ba vấn đề nằm ngay trong bốn dòng đó. Một đơn bốn người thì bốn người chung một ô — không ghi
được ba người lên xe, một người không tới. Một ngày có nhiều điểm dừng nhưng chỉ có một ô. Và
`present = false` không nói được khách vắng vì sao, mà lý do mới là thứ quyết định có hoàn tiền
hay không.

`checkpoint_photos` cũng chỉ có `image_path`: không biết ảnh chụp ở đâu, lúc nào, tại điểm nào.

### Đã thêm

Bảng `itinerary_checkpoints` — điểm dừng trong ngày. `passenger_checkins` — từng người tại từng
điểm, năm trạng thái, ghi chú bắt buộc. `passenger_checkin_histories` — sửa thì lưu vết. Ảnh
gắn tọa độ và cảnh báo khi chụp cách điểm quá 200m. Chín quy tắc kiểm tra gom về một dịch vụ.

### Neo mã

| Việc | Vị trí |
| --- | --- |
| Bốn quy tắc về quyền và bối cảnh | `server/app/Services/AttendanceService.php:42` |
| Ghi điểm danh, quy tắc 5, 6, 7, 9 | `server/app/Services/AttendanceService.php:99` |
| Quy tắc 8, ảnh bắt buộc | `server/app/Services/AttendanceService.php:244` |
| Năm trạng thái và luật ghi chú | `server/app/Enums/PassengerCheckinStatus.php` |
| Chuyển dữ liệu cũ | `server/database/migrations/2026_08_12_150000_migrate_legacy_booking_checkins.php` |
| Cảnh báo khách vắng ở điểm biên | `server/app/Services/AttendanceService.php:349` |
| Màn hướng dẫn viên | `client/src/pages/guide/GuideAttendance.tsx` |
| Màn báo cáo quản trị | `client/src/pages/admin/ScheduleAttendance.tsx` |

### Chín quy tắc, đọc ở đâu

Định nghĩa nghiệp vụ ở [04 - Luồng điều hành](04-luong-dieu-hanh.md) mục 5.3. Trong mã, mỗi quy
tắc có số hiệu ghi ngay trên đoạn cài đặt.

| # | Quy tắc | Vì sao |
| --- | --- | --- |
| 1 | Chỉ hướng dẫn viên phụ trách | Người khác ghi thì dữ liệu mất giá trị đối chiếu |
| 2 | Chuyến phải đang chạy | Chặn điểm danh khống cho chuyến chưa khởi hành |
| 3 | Điểm dừng thuộc đúng tour | Chặn ghi nhầm sang chuyến khác |
| 4 | Không tick trước ngày chưa tới | Chặn tick sẵn cả hành trình từ hôm nay |
| 5 | Ghi bù muộn thì đánh dấu | Vẫn cho ghi nhưng truy vết được |
| 6 | Hành khách thuộc đơn còn hiệu lực | Đơn đã hủy không nằm trong danh sách đoàn |
| 7 | Ghi chú tối thiểu 10 ký tự | Ghi "vang" thì sáu tháng sau đọc lại vô nghĩa |
| 8 | Điểm bắt buộc ảnh phải có ảnh | **Chưa có nơi gọi**, xem mục 10 |
| 9 | Sửa thì lưu lịch sử | Không ghi đè lặng lẽ dữ liệu đối chiếu |

### Hai chi tiết đáng đọc kỹ

**Vì sao năm trạng thái chứ không phải đúng/sai.** Khách báo trước không tham gia một hoạt động
khác hẳn khách không liên lạc được, dù cả hai đều là "không có mặt". Mỗi tình huống dẫn tới một
hành động khác nhau, và tới lúc xét hoàn tiền thì sự khác nhau đó là tất cả.

**Migration chuyển dữ liệu cũ giữ nguyên bảng gốc.** Sai một lần là hỏng dữ liệu thật, nên bảng
`booking_checkins` không bị xóa. Chuyển được thì chuyển, không thì vẫn còn đường quay lại.

### Chứng minh

Hai bộ, và sự khác nhau giữa chúng là điều đáng nói nhất ở nhóm này:

```
php artisan test --filter=AttendanceRulesTest      # 21 bài, tầng dịch vụ
php artisan test --filter=GuideAttendanceTest      # 12 bài, qua HTTP
php artisan test --filter=LegacyAttendanceMigrationTest
php artisan test --filter=AdminAttendanceReportTest
```

`AttendanceRulesTest` chứng minh **luật đúng**. `GuideAttendanceTest` chứng minh **luật được
áp**. Đã có lần bộ thứ nhất xanh toàn bộ trong khi controller tự ghi thẳng vào model, bỏ qua bốn
quy tắc. Bộ thứ hai sinh ra để bịt đúng khe đó, bốn bài trong nó đỏ nếu ai lặp lại lỗi ấy.

---

## 10. Ranh giới - những gì nhóm A đến H chưa làm

Nói trước còn hơn để hội đồng tìm ra.

**Quy tắc 8 chưa có nơi gọi.** `assertCheckpointCompletable` viết xong, có ba bài kiểm ở tầng
dịch vụ, nhưng API chưa có hành động "chốt điểm dừng" nào để gắn vào. Giao diện mới chỉ hiện
cảnh báo. Đây là thiết kế còn thiếu, không phải lỗi cài đặt.

**Nhóm E, F, G của Mốc 1 chưa động tới.** Nhật ký thay đổi đơn, yêu cầu hủy có duyệt, quy tắc
giấy tờ hành khách. Ba nhóm này 34 công việc.

**Nhóm X mới xong một phần.** Còn khóa chống bấm đặt hai lần, tách `seat_count` khỏi `guests`,
nhật ký thư gửi hỏng, lệnh dọn đơn tồn đọng.

**Kiểm tra quyền điểm danh còn tra `tour_schedules.guide_id`.** Khi M02 làm phân công theo giai
đoạn thì phải đổi sang tra bảng phân công. Chỗ cần sửa đã ghi chú sẵn trong mã.

**Khách chỉ tự hủy được đơn chưa thanh toán.** `Customer\BookingController::cancelBooking` chặn
mọi trạng thái khác `pending`, và đó là **đúng thiết kế**: đơn đã thu tiền thì khách gửi yêu cầu
hủy, điều hành duyệt rồi mới hoàn. Tiền ra khỏi công ty phải có người chịu trách nhiệm, không để
một cú bấm quyết định.

Luồng yêu cầu và duyệt đó là **nhóm F**, chưa dựng. Hệ quả khi verify: đường khách đã trả tiền
xin hủy chưa có ở đâu cả, kể cả API. Bảng phí hủy của nhóm B hiện chỉ chạm tới đường quản trị hủy.

**Phạm vi cố ý bỏ ngoài** nằm ở [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md), tám mảng,
kèm lý do từng mảng.

## 11. Một buổi test đầy đủ, khoảng 40 phút

Thứ tự dưới đây có tính toán: bước sau không phá dữ liệu bước trước cần, và các thao tác không
đảo ngược được đặt sau các thao tác chỉ đọc. Đi đúng thứ tự thì một lần seed chạy hết được cả
buổi.

### Chuẩn bị

**Bước 1 — lấy mã mới và dựng lại dữ liệu.**

```
git pull origin dev

cd server
php artisan optimize:clear
php artisan migrate:fresh --seed
```

`migrate:fresh` **xóa sạch cơ sở dữ liệu rồi dựng lại**. Toàn bộ dữ liệu hiện tại là dữ liệu mẫu
nên không mất gì, nhưng nếu có đơn nào tự tạo mà muốn giữ thì sao lưu trước.

Chạy xong, lệnh in ra bảng chín chuyến và năm mã tra cứu. **Chụp lại hoặc để nguyên cửa sổ đó**,
lát nữa cần dùng.

**Seed ngay trước khi ngồi test.** Mọi mốc thời gian tính lùi từ lúc chạy seeder: S5 khởi hành
sau 26 giờ, S6 đang chạy và kết thúc sau 24 giờ. Seed hôm nay rồi test ngày mai thì mấy chuyến
đó đã trôi qua và không còn đúng tình huống nữa.

**Bước 2 — chạy hai tiến trình, mỗi cái một cửa sổ dòng lệnh.**

```
cd server
php artisan serve
```

```
cd client
npm run dev
```

Không cần chạy `php artisan schedule:work`. Seeder đặt sẵn trạng thái của cả chín chuyến, nên
không phải chờ tác vụ nền; các lệnh ở vòng 5 chạy tay để nhìn thấy chúng làm gì.

**Bước 3 — đăng nhập.**

| | Tài khoản | Vào |
| --- | --- | --- |
| Quản trị | `admin@gmail.com` / `admin123` | `/admin` |
| Hướng dẫn viên | `guide@gmail.com` / `guide123` | `/guide` |
| Khách | `customer@gmail.com` / `customer123` | `/my-bookings` |

Cùng một trình duyệt chỉ giữ được một phiên đăng nhập, nên hoặc dùng hai trình duyệt khác nhau,
hoặc đăng nhập lần lượt theo từng vòng. Thứ tự các vòng bên dưới đã gom theo vai: vòng 1 tới 3
chủ yếu là quản trị, vòng 4 là hướng dẫn viên, vòng 5 và 6 chạy ở dòng lệnh.

**Bước 4 — ghi lại một con số.** Vào `/admin/schedules`, lọc tour *Thử Nghiệm Nghiệp Vụ*, ghi lại
**id của chuyến S6** (chuyến đang khởi hành). Vòng 4 cần id này để mở màn điểm danh.

**Giờ hiển thị.** Ứng dụng chạy theo giờ UTC còn Việt Nam là UTC+7, nên giờ khởi hành trên màn
hình lệch 7 tiếng so với đồng hồ trên tường. Không ảnh hưởng gì tới các luật đang thử, vì mọi so
sánh đều là tương đối; chỉ đừng ngạc nhiên khi thấy chuyến "đang chạy" ghi giờ khởi hành lạ.

### Vòng 1 — Nhìn trước, chưa bấm gì (5 phút)

| # | Làm | Thấy | Nhóm |
| --- | --- | --- | --- |
| 1 | `/admin/schedules`, lọc tour Thử Nghiệm | Chín chuyến, sáu trạng thái nằm cạnh nhau | A |
| 2 | `/admin/bookings` → mở lần lượt năm đơn ghi "Hủy thử" → bấm **Hủy đơn** → đọc bảng dự báo → bấm **Không hủy nữa** | Mức hoàn 90 / 70 / 50 / 30 / 0 tương ứng | B |

Vòng này không mất dữ liệu. Đọc xong năm con số là thấy trọn bảng phí hủy.

### Vòng 2 — Cặp đối chứng của nhóm C (10 phút)

| # | Làm | Thấy | Nhóm |
| --- | --- | --- | --- |
| 3 | Hủy **thật** đơn của **S3** | Dự báo báo trước "chỗ sẽ được trả về kho". Xong, `/admin/schedules` cho thấy S3 giảm chỗ | C |
| 4 | Hủy **thật** đơn của **S4** | Dự báo cảnh báo đỏ **chỗ không quay lại kho**. Xong, số chỗ của S4 **giữ nguyên** | C |
| 5 | Chờ tác vụ nền chạy, xem lại **S4** | Số chỗ **vẫn giữ nguyên** — không tác vụ nào âm thầm trả ghế chết về kho | C |
| 6 | Dời hạn chốt của **S4** ra sau | Bảng xem trước đếm đúng số ghế chết đang treo trên chuyến | C, A |

Bước 3 với bước 4 là hai đơn giống hệt nhau, cách nhau 60 giờ, ra hai kết quả khác nhau. Nếu chỉ
demo được một thứ thì demo chỗ này.

Ghế chết ở lại tới khi chuyến kết thúc — cố ý không có nút mở lại, lý do ở
[06](06-doi-chieu-feedback.md) mục "Vì sao điểm 8 không có nút mở lại chỗ". Nếu hội đồng hỏi
*"xin thêm được phòng thì sao?"*: điều hành tăng sức chứa chuyến.

### Vòng 3 — Nhóm D, hai lối vào (5 phút)

| # | Làm | Thấy | Nhóm |
| --- | --- | --- | --- |
| 7 | `/admin/bookings` → đơn của **S6** → **Hủy đơn** | Nút xác nhận bị khóa, kèm câu nêu rõ chuyến đang chạy | D |
| 8 | Cửa sổ khách → `/my-bookings` → đơn **chờ thanh toán** của S1 → **Hủy đơn** | Hủy được, vì chuyến chưa khởi hành và đơn chưa thu tiền | D |

Bước 8 cũng cho thấy giới hạn hiện tại: chỉ đơn chưa thanh toán mới có nút. Đơn đã trả tiền đi
đường duyệt của nhóm F, chưa dựng — xem mục 10.

### Vòng 4 — Nhóm H (10 phút)

| # | Làm | Thấy | Nhóm |
| --- | --- | --- | --- |
| 9 | Cửa sổ hướng dẫn viên → `/guide/attendance/<id S6>` | Điểm dừng gom theo ngày. Một đơn đã ghi dở, một đơn chưa ghi gì | H |
| 10 | Đánh vắng một người, gõ ghi chú dưới 10 ký tự | Bị chặn ngay tại chỗ, chưa gửi đi | H |
| 11 | Gõ đủ ghi chú rồi lưu, sau đó sửa lại thành có mặt | Lưu được cả hai lần | H |
| 12 | Cửa sổ quản trị → `/admin/tour-schedules/<id S6>/attendance` | Trạng thái mới, và người vừa sửa hiện dấu vết thay đổi | H |
| 13 | Bấm sang điểm dừng của **ngày mai** rồi thử ghi | Bị từ chối: chưa tới ngày | H |

### Vòng 5 — Tác vụ nền (5 phút)

Chạy lần lượt, đọc kỹ phần in ra:

```
php artisan schedules:confirm-ready
```
S9 đủ khách nên **được chốt**; S4 thiếu khách nên chỉ **bị cảnh báo**. Cùng một lệnh, hai kết quả
— đó là điểm cần chỉ ra: lệnh biết phân biệt chứ không chốt bừa mọi chuyến tới hạn.

```
php artisan bookings:release-expired
```
Đơn chờ thanh toán quá hạn của S5 bị hủy và **chỗ được trả về**, dù S5 đã qua hạn chốt. Đối chứng
trực tiếp với ghế chết ở bước 4: chưa trả tiền thì chưa cam kết gì với nhà cung cấp.

```
php artisan bookings:finalize-completed
```
S7 ra ba kết quả: một đơn **khách không có mặt**, hai đơn **đã hoàn thành**. Đơn không ai điểm
danh vẫn là đã hoàn thành — thiếu bằng chứng thì không kết luận bất lợi cho khách.

### Vòng 6 — Đối soát cuối buổi

```
php artisan bookings:check-seat-consistency
```

Phải báo **"Số chỗ của mọi chuyến đều khớp"**.

Đây là bước đáng giá nhất của cả buổi. Sau khi đã hủy đơn, sinh ghế chết, mở lại chỗ, nhả đơn quá
hạn và chốt chuyến, số chỗ ghi trên từng chuyến vẫn khớp với số chỗ thực tế bị chiếm. Không phải
vì may, mà vì mọi đường ghi đều đi qua tầng dịch vụ.

Nếu lệnh này báo lệch, có lỗi thật. Đừng chạy `--fix` cho hết đỏ trước khi hiểu vì sao lệch.

### Nếu chỉ có mười phút

Bước 3, 4, 6 (nhóm C), bước 7 (nhóm D), bước 10 (nhóm H), rồi vòng 6. Bốn câu hội đồng đã hỏi
trực tiếp nằm trọn trong đó.

### Khi có gì đó không như mô tả

Trước khi đi tìm lỗi trong mã, loại trừ ba nguyên nhân hay gặp hơn:

| Hiện tượng | Nguyên nhân thường gặp |
| --- | --- |
| Trạng thái các chuyến không giống bảng | Seed từ hôm trước, mốc thời gian đã trôi. Seed lại |
| Màn điểm danh của hướng dẫn viên báo chưa có điểm dừng | Mở nhầm chuyến của tour khác, không phải tour Thử Nghiệm |
| Đơn chờ thanh toán biến mất khỏi trang khách | Đúng như vậy: tác vụ nhả chỗ hủy đơn quá hạn ngay khi mở danh sách. Đơn để bấm thử nằm ở S1 và còn hạn |
| Hủy đơn báo lỗi mà không rõ vì sao | Đọc kỹ câu thông báo, nó lấy nguyên từ tầng dịch vụ và nêu đúng luật đang chặn |
| Lệnh đối chiếu ở vòng 6 báo lệch | Đây là lỗi thật. Đừng chạy `--fix` cho hết đỏ trước khi hiểu vì sao lệch |

Cách phân biệt lỗi dữ liệu mẫu với lỗi ứng dụng:

```
php artisan test --filter=BusinessScenarioSeederTest
```

Bộ này khóa từng con số trong bảng chín chuyến. Nó xanh mà màn hình vẫn sai thì lỗi nằm ở ứng
dụng; nó đỏ thì dữ liệu mẫu đã trôi mốc thời gian, seed lại là xong.
