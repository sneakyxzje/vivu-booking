# 04 - Luồng điều hành chuyến đi

Tài liệu này mô tả toàn bộ nghiệp vụ sau khi bán được tour: chốt chuyến, ghép chuyến,
hủy chuyến, phân công hướng dẫn viên, điểm danh và xử lý sự cố.

## 1. Chốt chuyến

### 1.1 Vì sao cần chốt chuyến

Một chuyến chỉ chạy có lãi khi đạt số khách tối thiểu, vì chi phí xe, hướng dẫn viên và
điều hành gần như cố định bất kể đoàn đông hay vắng. Hệ thống hiện chưa có khái niệm này,
nên không phát hiện được chuyến sắp lỗ.

### 1.2 Luồng

1. Hệ thống chạy tác vụ nền hàng ngày, quét các chuyến có `booking_deadline` trong 24 giờ tới.
2. Với mỗi chuyến, tính `paid_people` bằng tổng số khách của các đơn ở `deposit_paid`,
   `paid` hoặc `confirmed`.
3. So sánh với `min_people`:
   - Đạt: chuyển chuyến sang `confirmed`, gửi thư báo chuyến chắc chắn khởi hành cho khách,
     gửi danh sách đoàn cho hướng dẫn viên và nhà cung cấp.
   - Không đạt: đưa vào danh sách cảnh báo trên bảng điều khiển của điều hành,
     kèm ba lựa chọn ở mục 1.3.
4. Tại `booking_deadline`, chuyến tự chuyển `open` sang `closed`, ngừng nhận đặt mới.

### 1.3 Ba lựa chọn khi chuyến không đủ khách

| Lựa chọn | Khi nào phù hợp | Hệ quả |
| --- | --- | --- |
| Vẫn chạy | Đoàn thiếu ít, có khách quan trọng, hoặc giữ uy tín | Chấp nhận lỗ, ghi lý do vào chuyến |
| Ghép chuyến | Có chuyến khác cùng tour ngày gần kề còn chỗ | Chuyển toàn bộ đơn, xem mục 2 |
| Hủy chuyến | Không có phương án khác | Hoàn 100 phần trăm hoặc chuyển tour, xem mục 3 |

Quy định về thông báo: khi hãng chủ động thay đổi, phải báo trước cho khách tối thiểu
số ngày ghi trong điều khoản, đề xuất 3 ngày với tour nội địa. Hệ thống hiển thị cảnh báo
nếu điều hành thao tác muộn hơn mốc này.

### 1.4 Edge case

| Tình huống | Xử lý |
| --- | --- |
| Đúng lúc chốt thì có đơn mới thanh toán | Tính lại `paid_people` trong giao dịch có khóa dòng chuyến |
| Chuyến đủ khách rồi tụt xuống dưới mức tối thiểu do khách hủy | Cảnh báo lại cho điều hành, cho phép quay về `closed` để cân nhắc |
| `min_people` để trống | Mặc định bằng 1, chuyến luôn chạy nếu có khách |
| Chuyến đã `confirmed` nhưng hết hướng dẫn viên | Không hạ trạng thái, phải tìm hướng dẫn viên thay thế hoặc thuê ngoài |

## 2. Ghép chuyến và ghép đoàn

Hội đồng nêu ở mục "Ghép tour". Có hai nghiệp vụ khác nhau cùng tên gọi này.

### 2.1 Ghép chuyến

Hai chuyến của **cùng một tour**, ngày khởi hành gần nhau, mỗi chuyến ít khách,
được dồn về một chuyến.

Điều kiện:

1. Cùng `tour_id`.
2. Cả hai chuyến ở `open` hoặc `closed`, chưa `in_progress`.
3. Chuyến đích còn đủ chỗ cho toàn bộ khách của chuyến nguồn.
4. Chênh lệch ngày khởi hành nằm trong ngưỡng cho phép, đề xuất tối đa 2 ngày, vì đổi ngày
   xa hơn ảnh hưởng lớn tới kế hoạch của khách.

Luồng:

1. Điều hành chọn chuyến nguồn và chuyến đích, xem trước danh sách đơn bị ảnh hưởng.
2. Hệ thống mở giao dịch, khóa hai chuyến theo thứ tự khóa chính tăng dần.
3. Chuyển toàn bộ đơn còn hiệu lực sang chuyến đích, cập nhật `tour_schedule_id`
   và `departure_date`.
4. Cộng dồn `booked_people`, đặt chuyến nguồn thành `cancelled` với
   `merged_into_schedule_id` trỏ tới chuyến đích.
5. Ghi `booking_transfers` cho từng đơn với `initiated_by = operator`, `fee = 0`.
6. Gửi thư thông báo đổi giờ khởi hành cho từng khách, nêu rõ giá không đổi.
7. Đơn `pending` chưa thanh toán thì hủy thay vì chuyển, kèm thư mời đặt lại chuyến mới.

Edge case:

| Tình huống | Xử lý |
| --- | --- |
| Chuyến đích không đủ chỗ cho toàn bộ | Từ chối ghép toàn phần, cho phép ghép một phần và hỏi điều hành xử lý phần dư |
| Hai chuyến có hướng dẫn viên khác nhau | Giữ hướng dẫn viên của chuyến đích, giải phóng lịch của người còn lại |
| Khách không đồng ý đổi ngày | Cho phép khách yêu cầu hoàn 100 phần trăm vì thay đổi do hãng |
| Ghép dây chuyền A vào B rồi B vào C | Cho phép nhưng phải cập nhật `merged_into_schedule_id` theo chuỗi, hiển thị chuyến cuối cùng cho khách |

### 2.2 Ghép đoàn

Đây là mô hình kinh doanh, không phải thao tác. Cần phân biệt hai loại tour:

| Loại | Mô tả | Số khách tối thiểu | Giá |
| --- | --- | --- | --- |
| Tour ghép | Nhiều khách lẻ chung một đoàn, chung xe, chung hướng dẫn viên | Có, thường 10 đến 15 | Thấp hơn |
| Tour riêng | Một đoàn độc quyền toàn bộ chuyến | Không | Cao hơn, tính theo đoàn |

Đề xuất thêm `tours.type` với hai giá trị `shared` và `private`. Khi một đoàn đặt trọn
chuyến `private`, chuyến chuyển ngay sang `closed` để ngừng bán lẻ.

## 3. Hủy chuyến và xóa tour khi đã có người thanh toán

Hội đồng nêu ở mục "Xóa tour khi có ít người đã thanh toán". Nguyên tắc: **không bao giờ
xóa, chỉ hủy có phương án**.

### 3.1 Luồng hủy chuyến bắt buộc ba bước

Bước 1 - Đánh giá tác động. Hệ thống hiển thị:

- Số đơn theo từng trạng thái.
- Tổng số khách bị ảnh hưởng.
- Tổng tiền đã thu cần xử lý.
- Số ngày còn lại tới khởi hành, kèm cảnh báo nếu vi phạm quy định báo trước.

Bước 2 - Gán phương án cho từng đơn đã thu tiền. Không cho bỏ trống đơn nào.

| Phương án | Mô tả |
| --- | --- |
| Hoàn 100 phần trăm | Không áp bảng phí hủy vì lỗi thuộc về hãng |
| Chuyển sang chuyến khác cùng tour | Miễn phí, giữ nguyên giá |
| Chuyển sang tour khác | Miễn phí, hoàn chênh nếu tour đích rẻ hơn |
| Ghi nhận công nợ | Khách đồng ý giữ tiền cho lần sau, có hạn sử dụng |

Bước 3 - Xác nhận. Nhập lý do hủy chuyến, chọn `cancel_type`, xác nhận lần hai.
Hệ thống thực hiện trong một giao dịch: đổi trạng thái chuyến, xử lý từng đơn theo phương án,
tạo các giao dịch hoàn tiền, giải phóng lịch hướng dẫn viên, gửi thư xin lỗi kèm phương án
cho từng khách, ghi nhật ký.

### 3.2 Ràng buộc kỹ thuật

```php
// Chặn ở tầng dịch vụ, không chỉ ở giao diện.
$unresolved = $schedule->bookings()
    ->whereIn('status', ['deposit_paid', 'paid', 'confirmed'])
    ->whereNull('cancellation_plan')
    ->count();

if ($unresolved > 0) {
    throw new BusinessRuleException(
        "Còn {$unresolved} đơn đã thanh toán chưa có phương án xử lý. Không thể hủy chuyến."
    );
}
```

### 3.3 Xóa tour

| Trường hợp | Cho phép |
| --- | --- |
| Tour chưa có chuyến khởi hành nào | Xóa cứng |
| Tour có chuyến nhưng chưa có đơn nào | Xóa cứng, kèm xác nhận |
| Tour có đơn đã hủy hết, không còn đơn hiệu lực | Chỉ chuyển `inactive` |
| Tour còn đơn hiệu lực ở chuyến chưa khởi hành | Chỉ `inactive`, các chuyến đã chốt vẫn phải chạy |
| Tour có lịch sử đơn hoàn thành | Chỉ `inactive`, giữ vĩnh viễn để phục vụ báo cáo và đánh giá |

Thông điệp lỗi phải nêu rõ số đơn đang chặn, kèm liên kết tới danh sách đơn đó,
để điều hành xử lý chứ không bị chặn mù.

## 4. Hướng dẫn viên

### 4.1 Hồ sơ năng lực

Hiện `users` chỉ có vai trò `guide`, không có thông tin nghề nghiệp. Đề xuất bảng
`guide_profiles`:

| Cột | Ý nghĩa |
| --- | --- |
| `user_id` | Liên kết tài khoản |
| `license_number` | Số thẻ hướng dẫn viên |
| `license_type` | Nội địa hoặc quốc tế |
| `license_expiry` | Ngày hết hạn thẻ |
| `languages` | JSON, các ngôn ngữ sử dụng |
| `regions` | JSON, các vùng hoặc tỉnh chuyên tuyến |
| `specialties` | JSON, loại hình chuyên: biển đảo, trekking, tâm linh, du lịch cộng đồng |
| `max_group_size` | Sức dẫn tối đa |
| `max_days_per_month` | Giới hạn ngày công |
| `rating` | Điểm trung bình từ đánh giá của khách |
| `is_available` | Tạm ngừng nhận tour |

Lý do có `license_expiry`: theo Luật Du lịch, hướng dẫn viên phải có thẻ còn hiệu lực khi
hành nghề. Hệ thống chặn phân công nếu thẻ hết hạn trước ngày kết thúc chuyến. Đây là chi tiết
nhỏ nhưng cho thấy đồ án có tìm hiểu quy định thực tế.

### 4.2 Gợi ý hướng dẫn viên phù hợp

Khi điều hành phân công, hệ thống xếp hạng các hướng dẫn viên theo bộ tiêu chí:

| Tiêu chí | Loại | Ý nghĩa |
| --- | --- | --- |
| Không trùng lịch với chuyến khác | Bắt buộc | Loại thẳng nếu vi phạm |
| Thẻ còn hiệu lực tới hết chuyến | Bắt buộc | Loại thẳng nếu vi phạm |
| Đang nhận tour | Bắt buộc | Loại nếu `is_available` bằng false |
| Sức dẫn đủ cho quy mô đoàn | Bắt buộc | Loại nếu đoàn vượt `max_group_size` |
| Ngôn ngữ khớp yêu cầu đoàn | Ưu tiên cao | Với đoàn khách nước ngoài |
| Vùng chuyên tuyến khớp điểm đến | Ưu tiên cao | Hiểu địa bàn, có quan hệ nhà cung cấp |
| Loại hình chuyên khớp danh mục tour | Ưu tiên trung bình | |
| Chưa vượt ngày công trong tháng | Ưu tiên trung bình | Tránh quá tải |
| Điểm đánh giá cao | Ưu tiên thấp | Phân biệt khi các tiêu chí trên ngang nhau |

### 4.3 Kiểm tra trùng lịch

Đây là điểm kỹ thuật nên nêu khi bảo vệ. Hai khoảng thời gian giao nhau khi và chỉ khi
`a.start < b.end` và `b.start < a.end`.

```php
$conflict = TourSchedule::query()
    ->where('guide_id', $guideId)
    ->where('id', '!=', $scheduleId)
    ->whereNotIn('status', ['cancelled'])
    // Giao nhau khi bắt đầu của chuyến này trước khi chuyến kia kết thúc và ngược lại.
    ->where('start_date', '<', $schedule->end_date)
    ->where('end_date', '>', $schedule->start_date)
    ->exists();
```

Cần thêm khoảng nghỉ tối thiểu giữa hai chuyến, đề xuất 12 giờ, bằng cách nới hai mốc
so sánh. Một hướng dẫn viên vừa kết thúc tour 4 ngày lúc 22 giờ không thể bắt đầu tour khác
lúc 5 giờ sáng hôm sau.

### 4.4 Thay hướng dẫn viên giữa chừng

Hội đồng nêu trực tiếp. Mô hình hiện tại chỉ có `tour_schedules.guide_id`, tức là một chuyến
chỉ có một hướng dẫn viên trong suốt hành trình, không mô tả được việc thay người.

Đề xuất bảng `schedule_guide_assignments`:

| Cột | Ý nghĩa |
| --- | --- |
| `tour_schedule_id` | Chuyến |
| `guide_id` | Hướng dẫn viên |
| `role` | `lead` là trưởng đoàn, `assistant` là phụ, `replacement` là người thay |
| `effective_from` | Thời điểm bắt đầu phụ trách |
| `effective_to` | Thời điểm kết thúc, để trống nghĩa là tới hết chuyến |
| `reason` | Lý do thay: ốm, tai nạn, việc gia đình, đổi chặng theo vùng |
| `handover_note` | Ghi chú bàn giao cho người kế nhiệm |
| `assigned_by` | Người phân công |

`tour_schedules.guide_id` giữ lại làm hướng dẫn viên đang phụ trách hiện tại, được cập nhật
theo bản ghi có hiệu lực, để không phải sửa lại toàn bộ truy vấn cũ.

Luồng thay giữa chừng:

1. Điều hành chọn chuyến đang `in_progress`, bấm thay hướng dẫn viên.
2. Nhập lý do và thời điểm bàn giao, mặc định là hiện tại.
3. Hệ thống kiểm tra người mới không trùng lịch trong khoảng còn lại của chuyến.
4. Đóng bản ghi phân công cũ bằng `effective_to`, tạo bản ghi mới.
5. Người cũ **mất quyền ghi** từ thời điểm bàn giao nhưng **vẫn xem được** dữ liệu
   đã ghi trước đó, phục vụ đối chiếu.
6. Người mới thấy được toàn bộ tình trạng đoàn: danh sách khách, điểm danh các chặng đã qua,
   ghi chú vắng mặt, sự cố đã ghi nhận, ghi chú bàn giao.
7. Gửi thông báo cho khách trong đoàn về hướng dẫn viên mới kèm số điện thoại.

Kiểm tra quyền điểm danh phải đổi từ so sánh `guide_id` sang tra bản ghi có hiệu lực
tại thời điểm thao tác:

```php
$assigned = ScheduleGuideAssignment::query()
    ->where('tour_schedule_id', $scheduleId)
    ->where('guide_id', $userId)
    ->where('effective_from', '<=', now())
    ->where(function ($q) {
        $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
    })
    ->exists();
```

Edge case:

| Tình huống | Xử lý |
| --- | --- |
| Không tìm được người thay | Cho phép gán tạm cho điều hành để đoàn không mất người phụ trách trên hệ thống, đánh dấu cần xử lý |
| Thay người trước khi chuyến khởi hành | Vẫn dùng cùng cơ chế, `effective_from` bằng `start_date` |
| Hai hướng dẫn viên cùng phụ trách một đoàn lớn | Cho phép nhiều bản ghi hiệu lực cùng lúc với `role` khác nhau, cả hai đều điểm danh được |
| Người cũ đã điểm danh trước thời điểm bàn giao | Giữ nguyên dữ liệu, không cho sửa, chỉ người mới ghi tiếp từ chặng sau |
| Thay người sau khi chuyến đã `completed` | Từ chối |

## 5. Điểm danh

Hội đồng nêu ba điểm: validate điểm danh, điểm danh từng điểm đến từng ngày, ghi chú khi
khách vắng mặt.

### 5.1 Hiện trạng và khoảng cách

Hiện tại `booking_checkins` khóa duy nhất theo cặp `(booking_id, tour_itinerary_id)` với
cột `present` kiểu boolean. Nghĩa là:

- Điểm danh theo **đơn hàng**, không theo từng hành khách. Một đơn 4 người chỉ tick một lần.
- Điểm danh theo **ngày**, không theo từng điểm dừng trong ngày.
- Chỉ có hai giá trị có mặt hoặc vắng, không ghi được lý do.
- Chưa kiểm tra thời điểm điểm danh so với ngày của chặng.
- Chưa bắt buộc có ảnh check-in.

### 5.2 Mô hình đề xuất

Thêm bảng `itinerary_checkpoints`, mỗi chặng ngày có nhiều điểm dừng:

| Cột | Ý nghĩa |
| --- | --- |
| `tour_itinerary_id` | Thuộc ngày nào |
| `name` | Tên điểm, ví dụ điểm đón, cảng tàu, nhà hàng trưa, khách sạn |
| `type` | `pickup`, `sightseeing`, `meal`, `hotel`, `dropoff` |
| `expected_at` | Giờ dự kiến trong ngày |
| `requires_attendance` | Có bắt buộc điểm danh không |
| `requires_photo` | Có bắt buộc ảnh không |
| `order` | Thứ tự trong ngày |

Đổi `booking_checkins` thành `passenger_checkins`:

| Cột | Ý nghĩa |
| --- | --- |
| `booking_passenger_id` | Điểm danh tới từng người |
| `itinerary_checkpoint_id` | Tại điểm dừng nào |
| `tour_schedule_id` | Chuyến nào, để truy vấn nhanh và tránh nhầm giữa các chuyến cùng tour |
| `status` | `present`, `absent`, `late`, `left_early`, `excused` |
| `note` | Ghi chú, bắt buộc khi trạng thái khác `present` |
| `checked_at` | Thời điểm ghi nhận |
| `guide_id` | Ai ghi nhận |
| `is_late_entry` | Ghi bù sau, không ghi tại thời điểm thực tế |

Khóa duy nhất `(booking_passenger_id, itinerary_checkpoint_id)`.

Điểm quan trọng: chuyển từ `booking_id` sang `booking_passenger_id` giải quyết luôn tình
huống thực tế "đơn 4 người nhưng chỉ 3 người có mặt", điều mà mô hình hiện tại không mô tả được.

### 5.3 Quy tắc kiểm tra khi điểm danh

| Quy tắc | Lý do |
| --- | --- |
| Chỉ hướng dẫn viên có phân công còn hiệu lực mới ghi được | Chống ghi nhầm chuyến của người khác |
| Chuyến phải ở `in_progress` | Không cho điểm danh chuyến chưa khởi hành hoặc đã kết thúc |
| Điểm dừng phải thuộc lịch trình của tour của chuyến đó | Chống truyền tham số tùy tiện |
| Không cho điểm danh điểm dừng của ngày trong tương lai | Chống tick trước cho xong việc |
| Ghi bù quá 24 giờ thì đánh dấu `is_late_entry` và cảnh báo điều hành | Vẫn cho ghi vì thực tế có lúc mất sóng, nhưng phải truy vết được |
| Hành khách phải thuộc đơn còn hiệu lực của chuyến | Không điểm danh đơn đã hủy |
| Trạng thái khác `present` bắt buộc có ghi chú tối thiểu 10 ký tự | Yêu cầu trực tiếp của hội đồng |
| Điểm dừng có `requires_photo` phải có ít nhất một ảnh trước khi chốt | Chứng minh hướng dẫn viên có mặt tại điểm |
| Sửa điểm danh đã ghi thì lưu lịch sử, không ghi đè lặng lẽ | Truy vết trách nhiệm |

Đề xuất thêm tọa độ vào ảnh check-in: `latitude`, `longitude`, `captured_at`. Kết hợp với
tọa độ dự kiến của điểm dừng, hệ thống cảnh báo nếu ảnh chụp cách điểm quá xa. Đây là cơ chế
chống gian lận đơn giản mà hiệu quả, và là chi tiết dễ gây ấn tượng khi bảo vệ.

### 5.4 Ghi chú khi khách vắng mặt

Khi trạng thái là `absent`, giao diện bắt nhập ghi chú và gợi ý các lý do thường gặp,
đồng thời cho nhập tự do:

- Khách báo trước không tham gia hoạt động này, tự đi riêng.
- Khách mệt, nghỉ tại khách sạn.
- Khách chưa có mặt tại điểm tập trung, đã liên hệ, đang tới.
- Không liên lạc được.
- Khách đã rời đoàn về sớm.

Hệ quả theo lý do:

| Lý do | Hệ quả |
| --- | --- |
| Không liên lạc được tại điểm đón đầu tiên | Hệ thống tạo cảnh báo mức cao cho điều hành, đơn có nguy cơ chuyển `no_show` |
| Vắng tại điểm giữa hành trình | Ghi nhận bình thường, không ảnh hưởng tài chính |
| Rời đoàn về sớm | Đánh dấu tất cả điểm dừng còn lại là `left_early`, mở luồng xét hoàn phần dịch vụ chưa dùng |
| Vắng mặt tại điểm cuối, tức không về cùng đoàn | Cảnh báo mức cao, đây là tình huống nghiêm trọng cần xử lý ngay |

Quy tắc chốt chặng: không cho hướng dẫn viên chuyển sang điểm dừng tiếp theo khi còn hành
khách chưa được điểm danh ở điểm hiện tại. Đây là ràng buộc mềm, cho phép bỏ qua nhưng phải
xác nhận và ghi lý do.

### 5.5 Báo cáo điểm danh

Sau khi chuyến kết thúc, hệ thống tự sinh báo cáo cho điều hành:

- Tỷ lệ có mặt theo từng điểm dừng.
- Danh sách hành khách vắng kèm lý do.
- Các điểm dừng thiếu ảnh check-in.
- Các lần ghi bù muộn.
- Thời gian thực tế so với giờ dự kiến của từng điểm, phát hiện chặng thường xuyên trễ
  để điều chỉnh lịch trình.

## 6. Sự cố và chi phí phát sinh

Hội đồng nêu ví dụ rất cụ thể: đi tàu ra biển gặp bão, phải đổi sang chương trình khác có
chi phí cao hơn, xử lý thế nào.

### 6.1 Mô hình

Bảng `schedule_incidents`:

| Cột | Ý nghĩa |
| --- | --- |
| `tour_schedule_id` | Chuyến gặp sự cố |
| `tour_itinerary_id` | Xảy ra ở chặng nào, có thể để trống |
| `type` | `weather`, `vehicle`, `health`, `supplier`, `security`, `other` |
| `severity` | `low`, `medium`, `high` |
| `occurred_at` | Thời điểm |
| `description` | Diễn biến |
| `reported_by` | Hướng dẫn viên báo cáo |
| `resolution` | Phương án xử lý đã áp dụng |
| `cost_delta` | Chênh lệch chi phí, dương là tăng |
| `who_bears` | `company`, `customer`, `insurance`, `shared` |
| `approved_by`, `approved_at` | Điều hành duyệt |
| `evidence_photos` | Ảnh hiện trường, biên bản |

Bảng `booking_surcharges` cho phần khách phải chịu:

| Cột | Ý nghĩa |
| --- | --- |
| `booking_id` | Đơn nào |
| `incident_id` | Phát sinh từ sự cố nào |
| `reason` | Diễn giải cho khách |
| `amount` | Số tiền |
| `status` | `pending`, `paid`, `waived` |
| `approved_by` | Người duyệt |
| `customer_consent_at` | Thời điểm khách đồng ý |

### 6.2 Luồng xử lý sự cố

1. Hướng dẫn viên tạo báo cáo sự cố ngay tại hiện trường, đính kèm ảnh.
   **Hướng dẫn viên không được tự quyết mức phí và không được tự thu tiền.**
2. Hệ thống gửi thông báo ngay cho điều hành, mức `high` gửi kèm tin nhắn.
3. Điều hành quyết định phương án thay thế và phân bổ chi phí.
4. Nếu khách phải chịu một phần: hệ thống sinh bản xác nhận, hướng dẫn viên đọc cho đoàn,
   thu chữ ký hoặc xác nhận điện tử của từng khách hoặc của trưởng đoàn, chụp ảnh biên bản
   tải lên.
5. Kế toán thu tiền sau chuyến hoặc trừ vào phần chưa thanh toán, không thu tiền mặt
   không chứng từ.
6. Nếu chương trình bị rút ngắn: tính phần dịch vụ chưa sử dụng theo **giá vốn**, không phải
   giá bán, tạo giao dịch hoàn.

### 6.3 Nguyên tắc phân bổ chi phí bất khả kháng

Cần ghi vào điều khoản hợp đồng và hiển thị khi khách đặt tour:

| Khoản chi phí | Ai chịu |
| --- | --- |
| Chi phí điều hành, hướng dẫn viên phát sinh thêm | Hãng |
| Chi phí dịch vụ thực tế khách sử dụng thêm: phòng nghỉ thêm đêm, bữa ăn thêm | Khách |
| Chi phí phương tiện thay thế khi phương tiện gốc không hoạt động được | Hãng |
| Chi phí y tế cá nhân | Khách hoặc bảo hiểm |
| Dịch vụ đã đặt nhưng không sử dụng được, nhà cung cấp không hoàn | Hãng chịu, không thu lại của khách |
| Dịch vụ đã đặt nhưng không sử dụng, nhà cung cấp hoàn lại | Hoàn cho khách theo giá vốn |

Đây là cách chia phổ biến trong ngành và bảo vệ được cả hai phía. Điểm mấu chốt khi trình bày:
hãng chịu chi phí thuộc về nghĩa vụ tổ chức, khách chịu chi phí thuộc về tiêu dùng cá nhân
thực tế phát sinh.

### 6.4 Edge case sự cố

| Tình huống | Xử lý |
| --- | --- |
| Khách không đồng ý đóng phụ thu | Ghi nhận từ chối, điều hành quyết định miễn hoặc xử lý theo hợp đồng. Không được bỏ khách lại |
| Sự cố xảy ra khi mất sóng, báo cáo muộn | Cho phép nhập `occurred_at` trong quá khứ, đánh dấu ghi bù |
| Chuyến phải dừng hoàn toàn giữa chừng | Chuyến vẫn kết thúc bằng `completed`, mọi đơn `completed`, tạo hoàn theo phần chưa sử dụng |
| Một phần đoàn tiếp tục, một phần về sớm | Ghi nhận riêng từng hành khách qua điểm danh `left_early` |
| Sự cố do lỗi nhà cung cấp | Ghi `who_bears = company` với khách, đồng thời mở phiếu đòi bồi thường nhà cung cấp, ngoài phạm vi hệ thống hiện tại |
