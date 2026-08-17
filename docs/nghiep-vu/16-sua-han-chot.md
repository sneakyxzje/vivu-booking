# 16 - Dời hạn chốt danh sách

Tài liệu này trả lời một câu duy nhất: **quản trị viên có được đổi hạn chốt danh sách không, và
nếu được thì chuyện gì xảy ra.**

Đọc kèm [03 - Luồng hủy và hoàn tiền](03-luong-huy-va-hoan-tien.md) mục 3 (quy tắc trả chỗ) và
[04 - Luồng điều hành chuyến đi](04-luong-dieu-hanh.md) mục 1 (chốt chuyến).

---

## 1. Hạn chốt là cái gì

`tour_schedules.booking_deadline` là ngày công ty gửi danh sách khách cho nhà cung cấp và chốt
số phòng, số ghế, số suất ăn. Sau ngày đó số phòng đã cố định và đã trả tiền; khách hủy thì
phòng vẫn mất.

Chuyến không cấu hình riêng thì dùng mặc định `config('booking.booking_deadline_days')`, hiện
là 3 ngày trước khởi hành.

## 2. Nó là một cái vạch trên trục thời gian

```
   hôm nay 16/08          vạch 17/08              đi 20/08
        |------------------- | ---------------------|
             BÊN TRÁI VẠCH        BÊN PHẢI VẠCH
          - còn bán chỗ        - ngừng bán
          - sửa tên được       - không sửa tên
          - hủy thì trả chỗ    - hủy thì mất chỗ
          - chuyển chuyến được - không chuyển được
          - ghép chuyến được   - không ghép được
```

Đây là mốc duy nhất trong hệ thống điều khiển **năm** quy tắc thuộc năm nhóm khác nhau:

| Quy tắc | Nằm ở |
| --- | --- |
| Bán chỗ mới | `TourSchedule::isBookable()` |
| Chỗ có quay về kho khi khách hủy hay không | `BookingHoldService::shouldReleaseSeats()` |
| Sửa tên hành khách | `PassengerPolicyService::editability()` |
| Chuyển đơn sang chuyến khác | `BookingTransferService::assertCanTransfer()` |
| Ghép hai chuyến | `ScheduleMergeService::assertCanMerge()` |

Cộng thêm hai tác vụ nền đọc nó: `CloseExpiredSchedules` và `ConfirmReadySchedules`.

**Sửa hạn chốt tức là kéo cái vạch đó.** Toàn bộ tài liệu này chỉ nói về hệ quả của việc kéo.

## 3. Quyết định: cho phép sửa

Có ba lý do.

**Mốc thật khác nhau theo loại tour.** Tour trong ngày chốt trước vài tiếng, tour bay dài chốt
trước vài tuần. Khóa cứng con số 3 ngày là để lập trình viên áp một giá trị lên mọi loại tour.

**Nhà cung cấp có gia hạn thật.** Hệ thống không phản ánh được thực tế thì điều hành quay ra làm
tay ngoài hệ thống — đúng điều hội đồng đã chê ở lần bảo vệ thử.

**Không có gì bị phá hỏng.** Xem mục 4.

Có một lý do phụ đáng ghi lại: **quản trị viên đã có nút riêng để ngừng bán** (đổi trạng thái
chuyến sang "đóng bán"). Không ai đi sửa hạn chốt để làm việc đó, nên nỗi lo "sửa hạn chốt để
lách luật đóng bán" là nỗi lo tưởng tượng.

## 4. Nguyên tắc trung tâm: kéo vạch không tính lại quá khứ

> Chị Lan hủy ngày 18/08 khi hạn chốt là 17/08, nên chị mất chỗ. Ngày 20/08 điều hành xin thêm
> được phòng và dời hạn chốt sang 19/08. Chị Lan có được tính lại không?
>
> **Không.**

Vì kết quả **được ghi cứng vào đơn tại thời điểm hủy** — cột `bookings.seats_released` — chứ
không phải phép tính chạy lại mỗi lần mở màn hình.

Đây không phải bảo vệ do tài liệu này thêm vào, mà là hệ quả sẵn có của thiết kế lưu trữ. Nó
cùng nguyên tắc với việc chép `cancellation_policy_id` sang đơn lúc đặt: **hệ thống không hồi tố
các quyết định đã áp cho khách.**

Một nguyên tắc dùng lại được ở hai chỗ độc lập thường là nguyên tắc đúng.

Hệ quả: kéo vạch chỉ có hiệu lực **từ lúc kéo trở đi**, cả hai chiều.

## 5. Kéo sang phải (gia hạn)

Vùng "còn bán được" rộng ra. Nhưng có **hai cái bẫy**, và cả hai đều là hiểu nhầm chứ không phải
lỗi:

**Bẫy 1 — chuyến không tự mở bán lại.** `CloseExpiredSchedules` chỉ đóng, không có đường ngược.
Gia hạn xong chuyến vẫn ở `closed`, phải bấm "Mở bán" riêng.

**Bẫy 2 — ghế chết không tự sống lại.** Đơn đã hủy vẫn `seats_released = false`. Hệ thống cố ý
không tự trả các chỗ này về kho: chỉ con người mới biết có thật sự xin thêm được suất hay không
(`BookingHoldService::releaseHeldSeats`, có chú thích tại chỗ).

**Thứ tự đúng: gia hạn trước, mở lại chỗ sau.** Làm ngược lại thì lúc mở chỗ hệ thống thấy vẫn
quá hạn nên không mở bán, phải bấm thêm lần nữa.

Cả hai bẫy được xử lý bằng cách **nói thẳng ra trên màn hình** trước khi lưu, không phải bằng
cách tự động hóa.

## 6. Kéo sang trái (rút ngắn)

Có hiệu lực ngay: ngừng bán, khóa sửa tên, chặn chuyển và ghép, và từ đó khách hủy thì chỗ không
quay lại kho.

Không có bẫy nào, và **không hồi tố** — đơn đã hủy trước đó giữ nguyên kết quả cũ.

Điểm cần lưu ý về mặt vận hành: thao tác này **tước quyền của khách đang có mà không báo trước**.
Đó là lý do hộp thoại phải liệt kê số đơn bị ảnh hưởng.

## 7. Những gì không đổi

Ba điều này luôn đúng, bất kể kéo vạch thế nào. Hộp thoại nói lại hai điều đầu mỗi lần, vì đó
đúng là hai thứ người bấm hay lo nhất.

**Tiền hoàn không đổi.** Phần trăm hoàn tính theo số giờ trước `start_date`, không đọc hạn chốt.
Dời hạn chốt không làm lệch tiền hoàn của bất kỳ đơn nào.

**Đơn đã hủy giữ nguyên kết quả cũ.** Mục 4.

**Đơn chưa thanh toán luôn được trả chỗ.** `shouldReleaseSeats` kiểm tra `hasEnteredManifest`
trước khi đọc hạn chốt, nên đơn giữ chỗ bỏ ngang không bao giờ thành ghế chết — chỗ đó chưa từng
được cam kết với nhà cung cấp.

## 8. Luật chặn

Chỉ có hai, và cả hai đều chặn thứ **vô nghĩa**, không chặn lựa chọn của người dùng:

1. Hạn chốt phải trước ngày khởi hành. Sau ngày đi thì mốc này không có nghĩa gì.
2. Không sửa khi chuyến ở `in_progress`, `completed` hoặc `cancelled` (`isOperationallyLocked`).

Cả hai kiểm ở máy chủ, nên gọi API thẳng cũng không lách được.

## 9. Nhật ký

Bảng `schedule_audit_logs` (migration `2026_08_17_000001`). `booking_audit_logs` gắn cứng vào
`booking_id` nên không ghi được thay đổi thuộc về cả chuyến.

Ghi: ai sửa, lúc nào, từ ngày nào sang ngày nào, lý do, địa chỉ mạng.

Câu hỏi bảng này phải trả lời được: *ba tháng sau khách khiếu nại "hạn chốt là 19/08 sao tôi hủy
ngày 18 vẫn mất chỗ", thì lúc khách hủy hạn chốt đang là ngày nào, và ai đã đổi nó.*

Đây **không phải luật, không chặn ai cả**. Nếu hội đồng hỏi "ai kiểm soát quản trị viên" thì câu
trả lời là nhật ký, không phải trói tay họ.

Đọc ở màn hình **Nhật ký hệ thống** (`/admin/audit-logs`), nơi gộp bảng này với
`booking_audit_logs` thành một dòng thời gian. Gộp chứ không tách tab, vì một lần hủy đơn và một
lần dời hạn chốt của chính chuyến đó là hai mảnh của cùng một câu chuyện.

Nhật ký riêng của từng đơn vẫn giữ nguyên trong hộp chi tiết đơn — ở đó câu hỏi là *"đơn này đã
trải qua những gì"*. Màn hình gộp phục vụ chiều ngược lại: *"hôm qua ai đụng vào tiền"*, có bộ lọc
riêng dựa trên `BookingAuditAction::touchesMoney()`.

## 10. Hai đường ghi, một chỗ luật

Hạn chốt ghi được từ hai nơi:

1. Form sửa tour (`AdminTourController::update`) — dùng lúc dựng tour.
2. Nút "Sửa hạn chốt" ở màn hình quản lý chuyến (`AdminScheduleDeadlineController`) — dùng lúc
   vận hành, có xem trước tác động.

**Cả hai đều gọi `ScheduleDeadlineService::change()`**, nên luật kiểm tra và việc ghi nhật ký chỉ
nằm ở một chỗ.

Điều này cố ý: *luật nằm ở một đường ghi mà thiếu ở đường kia* là khuôn chung của phần lớn lỗi
đã gặp trong dự án — bán/hủy/chuyển/ghép đều từng dính. Bài
`test_sua_tu_form_sua_tour_cung_duoc_ghi_nhat_ky` giữ đường thứ nhất khỏi bị bỏ quên.

## 11. Lỗi vá kèm

Không sinh ra từ việc cho sửa hạn chốt, nhưng cùng họ và tìm ra khi rà phần này.

`BookingHoldService::releaseHeldSeats` (đường thủ công) có điều kiện `conTrongHanChot` trước khi
mở bán lại. `BookingHoldService::releaseHold` (đường tự động) thiếu.

Hậu quả: đơn chưa thanh toán hết hạn giữ chỗ **sau** hạn chốt thì chỗ về kho (đúng), rồi hệ thống
mở bán lại chuyến dù đã quá hạn (sai). Khách vào vẫn bị từ chối, còn `CloseExpiredSchedules` chạy
sau đó đóng về ngay — trạng thái chuyến nhấp nháy đóng-mở, điều hành không tin được màn hình.

Đã thêm điều kiện. Test: `BookingHoldExpiryTest::test_qua_han_chot_thi_van_nha_cho_nhung_khong_mo_ban_lai`
và bài đối chứng `test_con_trong_han_chot_thi_nha_cho_xong_mo_ban_lai`.

## 12. Đã cân nhắc và bỏ

Ghi lại để khỏi bàn lại.

**Đóng băng hạn chốt khi đã có ghế chết.** Bỏ. Nó giải bài toán "lật ngược quyết định cũ" mà
thiết kế lưu trữ ở mục 4 đã giải xong. Thêm vào chỉ là ràng buộc thừa.

**Buộc buffer tối thiểu 1–2 ngày trước khởi hành.** Bỏ. Tour trong ngày hoàn toàn có thể chốt
trước 4 tiếng. Đặt sàn cứng là lấy một con số tự nghĩ ra rồi áp cho mọi loại tour. Mặc định 3
ngày đã nằm ở tệp cấu hình và sửa được — thế là đủ.

**Chép hạn chốt vào từng đơn lúc đặt.** Bỏ, và nếu làm thì **sai**. Hạn chốt là ngày gửi *một*
danh sách cho nhà cung cấp, thuộc về cả chuyến. Chép vào từng đơn nghĩa là hai khách cùng một
chuyến có hai hạn chốt khác nhau, trong khi chỉ có đúng một danh sách được gửi đi. Mối lo đứng
sau đề xuất này — đổi hạn chốt làm lệch tiền hoàn — không tồn tại ở đây, vì bậc hoàn tính theo
giờ trước khởi hành (mục 7).

**Kiểm lại hạn chốt ở bước thanh toán.** Bỏ, và nếu làm thì **sai**. Chỗ bị trừ ngay lúc tạo đơn
chứ không phải lúc thanh toán, nên kiểm lại ở bước thanh toán là cướp mất chỗ khách đã giữ hợp lệ.
Đường tạo đơn đã khóa dòng rồi mới kiểm (`BookingController::store`), không có khe hở nào cần vá.

**Gợi ý buffer theo loại tour, thông báo cho khách đang quan tâm.** Bỏ vì thiếu dữ liệu nền
(phân loại nội địa/quốc tế, danh sách quan tâm). Không phải ý tồi, chỉ là chưa tới lượt.

## 13. Mã ở đâu

| Việc | Tệp |
| --- | --- |
| Luật, tính tác động, ghi nhật ký | `app/Services/ScheduleDeadlineService.php` |
| Ghi bảng nhật ký | `app/Services/ScheduleAuditLogger.php` |
| Bảng và model | `2026_08_17_000001_create_schedule_audit_logs_table.php`, `app/Models/ScheduleAuditLog.php` |
| Endpoint xem trước và lưu | `app/Http/Controllers/Api/Admin/AdminScheduleDeadlineController.php` |
| Đường ghi thứ hai | `AdminTourController::update()` |
| Hộp thoại sửa hạn chốt | `client/src/pages/admin/ScheduleManagement.tsx` |
| Đọc nhật ký gộp | `AdminAuditLogController.php`, `client/src/pages/admin/AuditLogManagement.tsx` |
| Kiểm thử | `tests/Feature/ScheduleDeadlineTest.php` (9 bài), `tests/Feature/AdminAuditLogTest.php` (6 bài), `tests/Feature/BookingHoldExpiryTest.php` (2 bài mới) |
