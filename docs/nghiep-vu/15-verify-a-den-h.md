# 15 - Verify nhóm A đến H: từ mã gốc đã thêm được gì

Tài liệu này để **kiểm chứng**, không phải để giới thiệu. [14 - Nhóm A, B, C, D đã giải quyết
bài toán gì](14-nhom-a-va-d.md) kể câu chuyện nghiệp vụ; tài liệu này chỉ ra chỗ đứng của từng
luật trong mã và cách tự chứng minh nó còn sống.

Đọc theo thứ tự: mốc so sánh, ba cách verify, rồi từng nhóm.

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

## 2. Ba cách verify

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

**Tầng 3 - thử tay trên giao diện.** Chạy ở máy nhà vì cần MySQL. Mỗi nhóm có kịch bản thử
riêng, chọn đúng những tình huống hội đồng đã hỏi.

Một điều cần phân biệt khi verify: **kiểm thử ở tầng dịch vụ chứng minh luật đúng, kiểm thử qua
HTTP chứng minh luật được áp**. Đã có lần bộ kiểm tầng dịch vụ xanh 21/21 trong khi đường API
thật thủng bốn quy tắc, vì bộ đó không đi qua controller. Nhóm H bên dưới có cả hai loại, và đó
là lý do.

## 2b. Dữ liệu để thử tay

`BusinessScenarioSeeder` dựng sẵn một tour tên **Tour Thử Nghiệm Nghiệp Vụ 3N2Đ** với chín
chuyến phủ hết sáu trạng thái vòng đời và năm bậc phí hủy. Mọi tình huống của A, B, C, D, H đều
có sẵn dữ liệu, không phải tự tạo bằng tay.

```
cd server
php artisan migrate:fresh --seed        # dựng lại toàn bộ
php artisan db:seed --class=BusinessScenarioSeeder   # chỉ dựng lại phòng thí nghiệm
```

Lệnh in ra bảng chín chuyến kèm trạng thái. Đăng nhập khách: `customer@gmail.com` /
`customer123` — mọi đơn kịch bản đều gắn vào tài khoản này để xem một lần là hết.

| Mã | Cách hiện tại | Trạng thái | Dùng để thử |
| --- | --- | --- | --- |
| S1 | còn 480h | Đang mở bán | Hủy → hoàn **90%**, chỗ trả về kho. Có sẵn một đơn vừa hủy 2 giờ trước để thử **mở lại đơn** |
| S2 | còn 240h | Đang mở bán | Hủy → hoàn **70%** |
| S3 | còn 120h | Đang mở bán | Hủy → hoàn **50%**, vẫn **trước** hạn chốt nên chỗ trả về |
| S4 | còn 60h | Đã đóng bán | Hủy → hoàn **30%** nhưng **sau** hạn chốt nên sinh **ghế chết**. Có sẵn một ghế chết cho màn mở lại chỗ |
| S5 | còn 26h | Đã chốt chuyến | Hủy → hoàn **0%**. Có sẵn một đơn quá hạn thanh toán để chạy `bookings:release-expired` |
| S6 | đã qua 24h | Đang khởi hành | **Chặn hủy** ở cả hai trang. Điểm danh: một đơn đã ghi dở, một đơn chưa ghi gì |
| S7 | đã qua 120h | Đã kết thúc | Chạy `bookings:finalize-completed`: ba đơn ra ba kết quả khác nhau |
| S8 | còn 360h | Đã hủy chuyến | Trạng thái cuối, không đi đâu được nữa |
| S9 | còn 720h | Đang mở bán | Thiếu khách so với mức tối thiểu, `schedules:confirm-ready` sẽ cảnh báo |

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

## 3. Nhóm A - Chuyến đi có vòng đời

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

### Thử tay

1. Tạo chuyến mới, xác nhận trạng thái mặc định là **Đang mở bán**.
2. Đặt đủ chỗ, xác nhận chuyến tự chuyển **Đã đóng bán**.
3. Đẩy `start_date` về quá khứ, chạy `php artisan schedules:advance-status`, xác nhận chuyến
   chuyển **Đang khởi hành**.

---

## 4. Nhóm D - Đơn của chuyến đã lăn bánh thì khóa

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

### Thử tay

1. Mở `/admin/bookings`, tìm đơn của chuyến **S6** (đang khởi hành).
2. Bấm Hủy đơn, nhập lý do, xác nhận — phải bị từ chối kèm thông báo nêu rõ chuyến đang chạy.
3. Chạy `php artisan bookings:release-expired` — tác vụ nền cũng không đụng được vào đơn đó.

Đây là câu hội đồng hỏi trực tiếp.

Lưu ý về lối vào thứ hai: **trang khách chưa có nút hủy**. API `PUT /api/my-bookings/{id}/cancel`
đã có và đã chịu cùng luật chặn, nhưng `MyBookingsTab.tsx` mới chỉ hiện trạng thái. Muốn thử lối
này phải gọi thẳng API. Xem mục 8.

---

## 5. Nhóm B - Hủy trước bao lâu thì hoàn bao nhiêu

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

### Thử tay

Hai đường đều xem được, không phải hủy thật:

- `/admin/bookings` → mở đơn của S1 tới S5 → bấm **Hủy đơn**. Hộp thoại hiện mức hoàn, phí hủy,
  số hoàn khách và tình trạng chỗ, trước khi xác nhận. Bấm **Không hủy nữa** để thoát.
- Hoặc mở `/booking-success/<mã tra cứu>` — seeder in sẵn năm mã ứng với năm bậc.

1. Xem lần lượt năm đơn, đối chiếu với bảng 90 / 70 / 50 / 30 / 0.
2. Vào `/admin/cancellation-policies` đổi mức hoàn của một bậc.
3. Xem lại đúng năm đơn đó, xác nhận số **vẫn giữ nguyên** — chính sách đã sao chép vào đơn lúc đặt.
4. Đặt một đơn mới trên trang khách, xác nhận đơn mới **ăn theo mức vừa sửa**.

Bước 3 với bước 4 đi liền nhau mới đủ nghĩa: một mình bước 3 chỉ chứng minh số không đổi, có thể
vì chính sách chưa được đọc lần nào.

---

## 6. Nhóm C - Hủy rồi thì chỗ có bán lại được không

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
trên `booking_deadline`. Màn quản trị để điều hành mở lại chỗ bằng tay khi xin thêm được suất.
Lệnh đối chiếu số chỗ chạy hằng giờ.

### Neo mã

| Việc | Vị trí |
| --- | --- |
| Quyết định trả chỗ hay không | `server/app/Services/BookingHoldService.php:176` |
| Đã vào danh sách đoàn chưa | `server/app/Services/BookingHoldService.php:202` |
| Mở lại chỗ bằng tay | `server/app/Services/BookingHoldService.php:262` |
| Lệnh đối chiếu | `server/app/Console/Commands/CheckSeatConsistency.php` |
| Màn quản trị | `client/src/pages/admin/HeldSeatsManagement.tsx` |

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

### Thử tay

Dùng cặp đối chứng S3 và S4 đã dựng sẵn.

1. `/admin/bookings` → hủy đơn của **S3**. Hộp thoại báo trước "chỗ sẽ được trả về kho". Hủy xong
   xem `/admin/schedules`: số chỗ của S3 giảm.
2. Hủy đơn của **S4**. Hộp thoại cảnh báo đỏ **chỗ không quay lại kho**. Hủy xong xem lại: số chỗ
   của S4 **giữ nguyên**.
3. Mở `/admin/held-seats`, mở lại chỗ đó kèm lý do, lúc này số chỗ mới giảm.

Bước 2 là câu hội đồng hỏi trực tiếp. Điểm đáng chỉ ra khi demo: hệ thống **nói trước** hậu quả
chứ không để người dùng tự phát hiện sau.

---

## 7. Nhóm H - Điểm danh chi tiết

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
| 8 | Điểm bắt buộc ảnh phải có ảnh | **Chưa có nơi gọi**, xem mục 8 |
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

### Thử tay

1. Vào màn điểm danh của hướng dẫn viên, chọn một điểm dừng, đánh vắng một người.
2. Xác nhận bị bắt nhập ghi chú, và ghi chú dưới 10 ký tự không lưu được.
3. Sửa lại thành có mặt, vào màn báo cáo quản trị xem lịch sử thay đổi.
4. Thử điểm danh cho một điểm dừng thuộc ngày mai — bị chặn.

---

## 8. Ranh giới - những gì nhóm A đến H chưa làm

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

**Trang khách chưa có nút hủy đơn.** `MyBookingsTab.tsx` mới chỉ hiện trạng thái. API
`PUT /api/my-bookings/{id}/cancel` và luật chặn đằng sau nó đã có đủ, nên đây là việc dựng giao
diện chứ không phải việc nghiệp vụ. Hệ quả khi verify: nhóm D chỉ thử được từ trang quản trị.

**Phạm vi cố ý bỏ ngoài** nằm ở [00 - Phạm vi và giới hạn](00-pham-vi-va-gioi-han.md), tám mảng,
kèm lý do từng mảng.

## 9. Kịch bản demo ngắn nhất

Nếu chỉ có mười phút, chạy đúng bốn bước này. Đây là bốn câu hội đồng đã hỏi trực tiếp.

| Bước | Thao tác | Chứng minh nhóm |
| --- | --- | --- |
| 1 | Hủy đơn sau hạn chốt, chỉ ra số chỗ **không** trả về | C |
| 2 | Mở màn Chỗ đã hủy chưa mở bán lại, mở lại kèm lý do | C |
| 3 | Thử hủy đơn của chuyến S6 từ trang quản trị, chỉ ra bị chặn | A, D |
| 4 | Điểm danh một người vắng, chỉ ra ghi chú bắt buộc và lịch sử sửa | H |

Nhóm B khó demo bằng thao tác vì phải chờ mốc thời gian. Thay bằng mở
`CancellationPolicyManagement`, đổi một mức hoàn, rồi chỉ ra đơn cũ vẫn giữ mức cũ.
