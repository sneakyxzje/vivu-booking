# 14 - Nhóm A và D đã giải quyết bài toán gì

Tổng kết hai nhóm công việc đã hoàn thành và kiểm thử: **A - vòng đời chuyến khởi hành**
và **D - chặn hủy đơn khi chuyến đã lăn bánh**.

Tài liệu này viết để đọc hiểu nghiệp vụ, không phải để đọc mã. Chi tiết kỹ thuật xem
[01 - Tác nhân và vòng đời](01-tac-nhan-va-vong-doi.md) và
[03 - Luồng hủy và hoàn tiền](03-luong-huy-va-hoan-tien.md).

---

## Nhóm A - Chuyến đi có vòng đời

### Bài toán

Công ty lữ hành không bán "tour", mà bán **chỗ trên một chuyến khởi hành cụ thể**. Cùng tour
"Hạ Long 3N2Đ" có thể tồn tại song song ba chuyến: chuyến 20/08 đoàn đang đi, chuyến 27/08 đã
chốt danh sách, chuyến 03/09 đang bán. Ba chuyến này phải được đối xử khác nhau hoàn toàn.

Trước đây `tour_schedules.status` chỉ có ba giá trị `active`, `inactive`, `full`. Ba giá trị này
mô tả **việc bán hàng** (đang bán, ngừng bán, hết chỗ), không mô tả **việc vận hành**. Hệ thống
không có chỗ nào ghi được:

- chuyến này đã chốt danh sách khách chưa
- đoàn đã lên đường chưa
- chuyến đã kết thúc chưa

Nên khi hội đồng hỏi "tour đang chạy có được hủy không", câu trả lời thật lúc đó là: hệ thống
**không biết tour nào đang chạy**.

### Vòng đời một chuyến, từ đầu tới cuối

Lấy ví dụ thật: **Hạ Long 3N2Đ, khởi hành 20/08 lúc 05:30, sức chứa 20 chỗ.**

**01/08 - Admin tạo chuyến.** Nhập ba thông số vận hành:

| Thông số | Giá trị | Vì sao cần |
| --- | --- | --- |
| Sức chứa | 20 chỗ | Giới hạn vật lý của phương tiện và dịch vụ |
| Số khách tối thiểu | 8 | Dưới mức này chuyến chạy là lỗ: tiền xe và thù lao hướng dẫn viên cố định bất kể đoàn đông hay vắng |
| Hạn chốt danh sách | 17/08 05:30, trước 3 ngày | Khách sạn chốt phòng, nhà xe chốt ghế, nhà hàng chốt suất ăn trước ngày đi |

Chuyến vào trạng thái **`open`** - đang mở bán.

**01/08 đến 17/08 - Bán hàng.** Khách đặt bình thường. Mỗi đơn giữ chỗ 10 phút chờ thanh toán,
không trả tiền thì hệ thống tự nhả chỗ.

**17/08 05:30 - Tới hạn chốt danh sách.** Hai việc xảy ra tự động:

1. Lệnh `schedules:close-expired` đóng bán, chuyến sang **`closed`**. Từ giây này web không cho
   đặt chuyến này nữa, **dù còn 8 chỗ trống**. Vì danh sách đã phải gửi nhà cung cấp.
2. Lệnh `schedules:confirm-ready` đếm **số khách đã trả tiền**: được 12 người, lớn hơn 8, nên
   chuyến sang **`confirmed`** - chắc chắn khởi hành. Hệ thống gửi thư báo cho cả 12 khách.

> **Điểm quan trọng:** đếm khách **đã trả tiền**, không đếm số chỗ đang bị chiếm. Nếu đếm chỗ bị
> chiếm thì 12 người vào giữ chỗ rồi bỏ đi cũng làm chuyến được chốt, tức là chốt trên số ảo.

**Nếu tới hạn chốt chỉ có 5 khách trả tiền (nhỏ hơn 8):** chuyến **không** được chốt. Hệ thống
cảnh báo điều hành. Điều hành quyết định: vẫn chạy chịu lỗ, ghép với chuyến khác, hay hủy chuyến.
*(Ba lựa chọn này thuộc Mốc 2, chưa triển khai.)*

**20/08 05:30 - Xe lăn bánh.** Lệnh `schedules:advance-status` chuyển sang **`in_progress`**.
Từ giây này hướng dẫn viên mới điểm danh được, và không ai hủy đơn được nữa.

**22/08 23:59 - Về tới nơi.** Chuyến sang **`completed`**.

### Một luật cứng nằm trong cấu trúc dữ liệu

Bảng đường chuyển hợp lệ **không có đường từ `in_progress` sang `cancelled`**.

Không phải quên viết, mà là cố ý không tồn tại: chuyến đã lăn bánh thì tiền xe đã trả, khách sạn
đã nhận khách, hướng dẫn viên đã đi. Không thể coi như chưa từng xảy ra. Muốn dừng giữa chừng thì
vẫn kết thúc bằng `completed`, kèm bản ghi sự cố.

Vì luật này nằm trong enum `ScheduleStatus` chứ không nằm rải rác trong các màn hình, nên
**không thể quên áp ở đâu đó**.

### Mở bán lại

`closed` quay về `open` là đường chuyển hợp lệ. Điều hành vào trang quản lý lịch khởi hành đổi
trạng thái là bán tiếp được. Dùng khi xin thêm được phòng từ nhà cung cấp, hoặc có khách hủy nên
trống chỗ. Đây là **quyết định của con người**, hệ thống không tự làm.

---

## Nhóm D - Đơn của chuyến đã lăn bánh thì khóa

### Bài toán

Đoàn đang ở Hạ Long, hôm nay là ngày thứ hai của hành trình. Một khách vào web bấm "Hủy đơn".
Hoặc admin vào trang quản trị bấm "Hủy đơn".

Trước đây hệ thống **cho hủy**: đơn chuyển sang đã hủy, chỗ được trả về kho, và nếu có luồng hoàn
tiền thì hoàn luôn. Trong khi khách đang ngồi trên tàu.

### Nghiệp vụ đã cài vào

Khi chuyến ở `in_progress` hoặc `completed`, **mọi đường hủy đơn đều bị chặn**, kể cả của quản
trị viên.

Đây là chỗ dễ hiểu nhầm nên cần nói rõ: **không phải chuyện phân quyền**. Admin có quyền cao nhất
nhưng admin cũng không hủy được. Vì đây không phải câu hỏi "ai được phép", mà là "chuyện này còn
ý nghĩa không". Chi phí đã phát sinh rồi.

Thông báo trả về phân biệt hai tình huống:

| Trạng thái chuyến | Thông báo |
| --- | --- |
| Đang đi | Chuyến đi đã khởi hành nên không thể hủy đơn. Vui lòng liên hệ điều hành để ghi nhận khách vắng mặt hoặc rời đoàn giữa chừng. |
| Đã kết thúc | Chuyến đi đã kết thúc nên không thể hủy đơn. Vui lòng liên hệ điều hành nếu cần khiếu nại hoặc yêu cầu hoàn tiền. |

### Vì sao đặt luật ở tầng dịch vụ

Có **nhiều cửa** cùng dẫn tới việc hủy một đơn: khách tự hủy, quản trị hủy, tác vụ nền dọn đơn
quá hạn, và sau này là chuyển chuyến.

Đặt khóa ở từng cửa thì chỉ cần quên một cửa là thủng, mà không có cách nào biết mình đã quên.
Đặt khóa ở **phòng trong** thì mọi cửa đều buộc phải đi qua.

Riêng tác vụ nền dọn đơn quá hạn được **miễn trừ có chủ đích**: đơn đó chưa thanh toán, chưa vào
danh sách đoàn, chặn lại chỉ để tồn rác trong hệ thống.

### Một chi tiết đáng nêu khi bảo vệ

Luật này dựa trên **thời gian thực tế**, không dựa trên trạng thái đang lưu trong cơ sở dữ liệu.

Lý do: trạng thái trong cơ sở dữ liệu do tác vụ nền cập nhật. Nếu tác vụ nền chết, một chuyến
khởi hành từ hôm qua vẫn còn ghi `open`. Nếu luật đọc giá trị đó thì chỉ cần tác vụ nền dừng là
mọi ràng buộc thủng theo. Nên hệ thống đối chiếu với đồng hồ: đã qua giờ khởi hành thì coi như
đang đi, bất kể cơ sở dữ liệu ghi gì.

---

## Ranh giới - A và D chưa làm gì

Nói rõ để không nhầm là đã xong nhiều hơn thực tế.

| Chưa có | Thuộc nhóm |
| --- | --- |
| Hủy sau hạn chốt thì không trả chỗ về kho | C |
| Bảng phí hủy theo mốc thời gian, 90 / 70 / 50 / 30 / 0 phần trăm | B |
| Khách đã thanh toán gửi yêu cầu hủy để điều hành duyệt | F |
| Ghi nhận khách vắng mặt và rời đoàn giữa chừng | D03 và H |
| Ba lựa chọn khi chuyến thiếu khách: vẫn chạy, ghép chuyến, hủy chuyến | K và L |

Hiện khách vẫn chỉ tự hủy được đơn **chưa thanh toán**. Đó là hành vi cũ, chưa đụng tới.

---

## Nếu hội đồng hỏi

**Tour đang chạy có hủy được không?**

> Không, và chặn ở tầng dịch vụ chứ không ở giao diện, vì có nhiều lối vào cùng dẫn tới hủy đơn.
> Trong máy trạng thái của chuyến không tồn tại đường từ đang chạy về đã hủy: chi phí đã phát sinh
> và nhà cung cấp đã phục vụ. Thay vào đó có hai nghiệp vụ khác là ghi nhận khách không có mặt và
> rời đoàn giữa chừng.

**Sao lại có hạn chốt danh sách?**

> Vì nhà cung cấp chốt trước: khách sạn chốt phòng, nhà xe chốt ghế, nhà hàng chốt suất ăn. Qua
> mốc đó nhận thêm khách là bán ra một chỗ không có dịch vụ đi kèm. Mặc định trước 3 ngày, cấu
> hình được theo từng chuyến.

**Số khách tối thiểu để làm gì?**

> Chi phí xe và hướng dẫn viên là cố định bất kể đoàn đông hay vắng, nên mỗi chuyến có điểm hòa
> vốn. Dưới mức đó chuyến chạy là lỗ. Đây là căn cứ để điều hành quyết định vẫn chạy, ghép chuyến
> hay hủy chuyến.

**Ai chốt chuyến?**

> Hệ thống tự chốt khi tới hạn chốt danh sách và đủ số khách **đã thanh toán**. Không đếm số chỗ
> đang bị giữ, vì giữ chỗ chưa trả tiền có thể tự hủy sau mười phút. Chốt trên đó là chốt trên số ảo.

**Vì sao đóng bán khi vẫn còn chỗ trống?**

> Vì chỗ trống về mặt vật lý không đồng nghĩa với chỗ bán được. Sau hạn chốt, phòng và suất ăn đã
> đặt theo danh sách đã gửi nhà cung cấp. Bán thêm một chỗ là bán cho người không có dịch vụ.
