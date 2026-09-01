# 17 - Nhiều hướng dẫn viên cho một chuyến

Đọc kèm [04 - Luồng điều hành chuyến đi](04-luong-dieu-hanh.md) mục phân công hướng dẫn viên.

---

## 1. Vấn đề

`tour_schedules.guide_id` là một khóa ngoại đơn, tức mỗi chuyến đúng một người dẫn.

Đoàn đông thì một người không kham nổi: điểm danh ở nhiều điểm dừng cùng lúc, khách tách nhóm khi
tham quan, có khi thêm cả xe thứ hai đi cùng tuyến.

## 2. Điều hệ thống cố ý không làm

**Không tự suy ra cần bao nhiêu hướng dẫn viên cho bao nhiêu khách.**

Tỷ lệ ấy khác nhau theo loại tour, theo tuyến, theo cách từng công ty vận hành. Đặt một con số
cứng - kiểu "1 người trên 20 khách" - là áp một giá trị do lập trình viên nghĩ ra lên mọi tour,
rồi mọi lập luận về sau đều dựa trên con số không có căn cứ ấy.

Một người dẫn đoàn 45 khách là quyết định của điều hành. Có thể liều, nhưng là việc của họ.

Cùng nguyên tắc với [16 - Dời hạn chốt danh sách](16-sua-han-chot.md) mục 13: hệ thống hỗ trợ
quyết định, không quyết định thay.

Bài `test_khong_ap_nguong_so_khach_tren_moi_huong_dan_vien` giữ điều này khỏi bị thêm vào sau.

## 3. Luật duy nhất còn lại

**Một người không đứng ở hai đoàn cùng lúc.** Đây là luật vật lý, không phải lựa chọn nghiệp vụ.

So sánh theo ngày chứ không theo giờ: đoàn về lúc 22h thì hôm đó người dẫn coi như bận cả ngày,
không nhận tiếp chuyến khác khởi hành cùng ngày.

Luật này đã có từ trước, chỉ chuyển sang đọc qua bảng nối.

## 4. Bỏ hẳn cột cũ, không giữ làm "hướng dẫn viên chính"

Migration `2026_08_17_000002` tạo bảng `tour_schedule_guides`, chép dữ liệu sang, rồi **xóa cột
`tour_schedules.guide_id`**.

Giữ lại cột cũ làm người phụ trách chính nghe có vẻ an toàn hơn, nhưng đó là **hai chỗ cùng lưu
một sự thật** - khuôn chung của phần lớn lỗi đã gặp ở dự án này. Sớm muộn sẽ có đường ghi cập nhật
bảng nối mà quên cột, rồi màn hình này hiện một đằng màn hình kia hiện một nẻo.

Hệ quả: **mọi hướng dẫn viên trong danh sách có quyền như nhau.** Không ai là trưởng đoàn về mặt
dữ liệu. Ai trong số họ cũng điểm danh được, cũng xác nhận đơn được.

`booking_checkins.guide_id` và `checkpoint_photos.guide_id` **giữ nguyên** - đó là "ai đã bấm
điểm danh", một câu hỏi khác hẳn.

## 5. Được ăn cả ngã về không

Một người vướng lịch thì **cả lần phân công bị từ chối**, chứ không gán được ai thì gán.

Gán một nửa rồi báo lỗi sẽ để lại trạng thái không ai chủ ý tạo ra, và người bấm tưởng cả lần phân
công đã bị bỏ.

## 6. Một chỗ giữ luật

`ScheduleGuideService` là đường duy nhất phân công. Trước đây phép kiểm chồng lịch nằm ở **hai
nơi** - lúc lưu tour và lúc phân công lẻ - với hai đoạn mã gần giống nhau; sửa một bên quên bên
kia là chuyện sớm muộn.

## 7. Mã ở đâu

| Việc | Tệp |
| --- | --- |
| Luật phân công, kiểm chồng lịch | `app/Services/ScheduleGuideService.php` |
| Bảng nối | `2026_08_17_000002_allow_many_guides_per_schedule.php` |
| Quan hệ | `TourSchedule::guides()`, `TourSchedule::hasGuide()`, `User::assignedSchedules()` |
| Endpoint | `AdminTourController::assignScheduleGuide()` — nhận `guide_ids` |
| Giao diện | `ScheduleManagement.tsx` (hộp thoại), `TourDetail.tsx` (chọn tại chỗ), `TourFormScheduleSection.tsx` (lúc tạo tour) |
| Kiểm thử | `tests/Feature/ScheduleGuideTest.php` (9 bài) |

---

# Phụ lục A - Danh sách đoàn chia theo nhóm

**Nhóm chính là một đơn đặt.** Thường có một người đứng ra đăng ký cho cả nhà hoặc cả phòng ban,
rồi mới khai tên từng người đi.

Nên câu hỏi của điều hành có hai tầng, và cả hai phải trả lời được ở cùng một chỗ:

1. Đoàn này gồm những nhóm nào, nhóm nào còn khai thiếu người
2. Nhóm đó cụ thể là ai — tên, ngày sinh, giấy tờ, yêu cầu đặc biệt, ai là người liên hệ

Trước đây `AdminPassengerController::incomplete` **lọc sẵn ở máy chủ**, chỉ trả về nhóm khai
thiếu. Như vậy trả lời được câu 1 nhưng không trả lời được câu 2. Đã đổi thành `manifest`, trả về
mọi nhóm, để màn hình tự lọc.

Mở ở `/admin/schedules` → nút **Danh sách đoàn** → bấm vào từng nhóm.

# Phụ lục B - Chặng, điểm dừng, nghỉ chân là không bắt buộc

Ba trường này ở tầng dữ liệu vốn đã `nullable`, nhưng form làm chúng trông như bắt buộc:

- Nút xóa của hàng "chặng đi qua" chỉ hiện khi có từ hai hàng, nên **hàng cuối cùng không xóa được**
- Xóa hàng cuối thì mã tự dựng lại một hàng rỗng
- Mở tour cũ không có chặng nào cũng thấy một ô trống chờ điền

Đã sửa cả ba, và ghi rõ "(không bắt buộc)" trên nhãn. Tour đi thẳng thì không có chặng trung gian
để ghi; chặng không phải thứ mọi tour đều có.
