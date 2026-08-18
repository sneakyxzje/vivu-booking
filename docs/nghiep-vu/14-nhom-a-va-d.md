# 14 - Nhóm A, B, C, D đã giải quyết bài toán gì

Tổng kết bốn nhóm công việc đã hoàn thành và kiểm thử.

Tài liệu này viết để đọc hiểu nghiệp vụ, không phải để đọc mã. Chi tiết kỹ thuật xem
[01 - Tác nhân và vòng đời](01-tac-nhan-va-vong-doi.md) và
[03 - Luồng hủy và hoàn tiền](03-luong-huy-va-hoan-tien.md).

---

## Bức tranh chung

Bốn nhóm không song song mà xếp tầng. **A dựng nền, ba nhóm còn lại là ba câu hỏi hệ thống
phải trả lời mỗi khi có người bấm Hủy.**

```
Khách hoặc điều hành bấm "Hủy đơn"
        │
        ├─ D:  Còn hủy được không?        →  chuyến đã lăn bánh chưa
        ├─ B:  Hoàn lại bao nhiêu tiền?   →  còn bao lâu tới ngày đi
        └─ C:  Chỗ có bán lại được không? →  đã qua hạn chốt danh sách chưa
```

Cả ba câu đều cần biết **chuyến đang ở giai đoạn nào và còn cách ngày đi bao lâu** — đó chính
là thứ nhóm A tạo ra.

| Nhóm | Trả lời câu hỏi | Câu hội đồng |
| --- | --- | --- |
| A | Chuyến này đang ở giai đoạn nào của vòng đời | Nền cho câu 9, 15, 16 |
| D | Đơn này còn hủy được không | Câu 9 |
| B | Hủy thì hoàn lại bao nhiêu | Câu 7 |
| C | Hủy rồi thì chỗ có bán lại được không | Câu 8 |

Điểm dễ lẫn nhất: **B và C là hai mặt của cùng một hành động.** Cùng một lần hủy, B quyết định
**tiền đi đâu**, C quyết định **chỗ đi đâu**. Hai quyết định độc lập, dựa trên hai mốc khác nhau.

---

## Nhóm A - Chuyến đi có vòng đời

### Bài toán

Công ty lữ hành không bán "tour", mà bán **chỗ trên một chuyến khởi hành cụ thể**. Cùng tour
"Hạ Long 3N2Đ" có thể tồn tại song song ba chuyến: chuyến 20/08 đoàn đang đi, chuyến 27/08 đã
chốt danh sách, chuyến 03/09 đang bán. Ba chuyến này phải được đối xử khác nhau hoàn toàn.

Trước đây `tour_schedules.status` chỉ có ba giá trị `active`, `inactive`, `full`. Ba giá trị này
mô tả **việc bán hàng**, không mô tả **việc vận hành**. Hệ thống không có chỗ nào ghi được chuyến
đã chốt danh sách chưa, đoàn đã lên đường chưa, chuyến đã kết thúc chưa.

Nên khi hội đồng hỏi "tour đang chạy có được hủy không", câu trả lời thật lúc đó là: hệ thống
**không biết tour nào đang chạy**.

### Vòng đời một chuyến, từ đầu tới cuối

Ví dụ: **Hạ Long 3N2Đ, khởi hành 20/08 lúc 05:30, sức chứa 20 chỗ.**

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
   đặt chuyến này nữa, **dù còn 8 chỗ trống**, vì danh sách đã phải gửi nhà cung cấp.
2. Lệnh `schedules:confirm-ready` đếm **số khách đã trả tiền**: được 12 người, lớn hơn 8, nên
   chuyến sang **`confirmed`** - chắc chắn khởi hành. Hệ thống gửi thư báo cho cả 12 khách.

> **Điểm quan trọng:** đếm khách **đã trả tiền**, không đếm số chỗ đang bị chiếm. Nếu đếm chỗ bị
> chiếm thì 12 người vào giữ chỗ rồi bỏ đi cũng làm chuyến được chốt, tức là chốt trên số ảo.

**Nếu tới hạn chốt chỉ có 5 khách trả tiền:** chuyến **không** được chốt. Hệ thống cảnh báo điều
hành. Điều hành quyết định: vẫn chạy chịu lỗ, ghép với chuyến khác, hay hủy chuyến.
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

`closed` quay về `open` là đường chuyển hợp lệ. Điều hành đổi trạng thái là bán tiếp được. Dùng
khi xin thêm được phòng từ nhà cung cấp, hoặc có khách hủy nên trống chỗ. Đây là **quyết định của
con người**, hệ thống không tự làm.

---

## Nhóm D - Đơn của chuyến đã lăn bánh thì khóa

### Bài toán

Đoàn đang ở Hạ Long, hôm nay là ngày thứ hai. Một khách vào web bấm "Hủy đơn". Hoặc admin vào
trang quản trị bấm "Hủy đơn".

Trước đây hệ thống **cho hủy**: đơn chuyển sang đã hủy, chỗ được trả về kho, và nếu có luồng hoàn
tiền thì hoàn luôn. Trong khi khách đang ngồi trên tàu.

### Nghiệp vụ đã cài vào

Khi chuyến ở `in_progress` hoặc `completed`, **mọi đường hủy đơn đều bị chặn**, kể cả của quản
trị viên.

Đây là chỗ dễ hiểu nhầm nên cần nói rõ: **không phải chuyện phân quyền**. Admin có quyền cao nhất
nhưng admin cũng không hủy được. Vì đây không phải câu hỏi "ai được phép", mà là "chuyện này còn
ý nghĩa không". Chi phí đã phát sinh rồi.

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

### Dựa vào đồng hồ, không dựa vào cơ sở dữ liệu

Trạng thái trong cơ sở dữ liệu do tác vụ nền cập nhật. Nếu tác vụ nền chết, một chuyến khởi hành
từ hôm qua vẫn còn ghi `open`. Nếu luật đọc giá trị đó thì chỉ cần tác vụ nền dừng là mọi ràng
buộc thủng theo.

Nên hệ thống đối chiếu với đồng hồ: đã qua giờ khởi hành thì coi như đang đi, bất kể cơ sở dữ
liệu ghi gì.

---

## Nhóm B - Hủy trước bao lâu thì hoàn bao nhiêu

### Bài toán

Trước đây hệ thống không có khái niệm phí hủy. Admin bấm hủy là đơn chuyển sang đã hủy, hết.
Không có chỗ nào ghi khách được nhận lại bao nhiêu, và cũng không có căn cứ nào để giải thích
con số đó với khách.

### Bảng phí và cơ sở của nó

| Hủy trước khởi hành | Hoàn |
| --- | --- |
| Từ 15 ngày | 90% |
| 8 đến 14 ngày | 70% |
| 4 đến 7 ngày | 50% |
| 2 đến 3 ngày | 30% |
| Dưới 48 giờ, hoặc đã qua giờ khởi hành | 0% |

Vì sao **bậc** chứ không phải tỷ lệ giảm đều theo ngày: chi phí của một chuyến không phát sinh
đều mà **nhảy bậc tại các mốc chốt với nhà cung cấp**. Khách sạn chốt phòng khoảng 7 ngày trước,
nhà xe chốt 3 ngày, suất ăn chốt 1 đến 2 ngày. Khách hủy càng sát thì phần chi phí hãng đã cam
kết mà không hủy được càng lớn.

### Hai quy tắc dễ làm ngược

```
phí hủy   = tổng giá trị đơn x (100 - phần trăm hoàn) / 100
tiền hoàn = max(0, số đã thu - phí hủy)
```

**Phí tính trên giá trị đơn, tiền hoàn trừ trên số đã thu.** Đơn 10 triệu, hủy trước 3 ngày, mức
hoàn 30 phần trăm: phí hủy là 7 triệu, khách nhận lại 3 triệu. Nếu khách mới đóng cọc 3 triệu thì
mất trắng cọc, đúng bản chất - cọc là khoản đảm bảo cho cam kết.

**Tiền hoàn kẹp dưới bằng 0.** Khách hủy thì không bao giờ phải nộp thêm, kể cả khi phí hủy lớn
hơn số đã trả.

### Chính sách sao chép vào đơn, không đọc qua tour

Đơn hàng lưu `cancellation_policy_id` ngay lúc đặt. Khi cần tính phí thì đọc từ đơn, **không** đọc
qua tour.

Lý do: sửa chính sách của tour về sau không được làm đổi điều khoản mà khách đã đồng ý khi đặt.
Cùng nguyên tắc với việc đơn lưu giá tại thời điểm đặt.

Thứ tự tra: **chính sách trên đơn → chính sách mặc định trong cơ sở dữ liệu → bảng phí trong mã**.
Tầng cuối để hệ thống vẫn tính đúng trên cơ sở dữ liệu chưa seed.

### Khách xem con số trước khi bấm hủy

Trang chi tiết đơn hiện sẵn: hủy bây giờ được hoàn bao nhiêu, phí hủy bao nhiêu, và **cả bảng
phí với bậc đang áp dụng được tô sáng**.

Phần lớn khiếu nại sau hủy đến từ việc khách không biết trước mình mất bao nhiêu. Cho xem con số
kèm bậc mà nó rơi vào thì khách tự đối chiếu được, không cần tranh cãi.

---

## Nhóm C - Hủy rồi thì chỗ có bán lại được không

### Bài toán

Đây là câu hỏi hay nhất trong danh sách của hội đồng, vì câu trả lời **không phải có hoặc không**,
mà là **có điều kiện**.

Trước đây hủy đơn luôn trả chỗ về kho. Nghe thì hợp lý, nhưng sai trong một tình huống cụ thể.

### Quy tắc

| Tình huống | Trả chỗ về kho |
| --- | --- |
| Đơn đã vào danh sách đoàn, hủy **trước** hạn chốt | **Có** - còn thời gian bán lại |
| Đơn đã vào danh sách đoàn, hủy **sau** hạn chốt | **Không** - ghế chết |
| Giữ chỗ **chưa thanh toán** hết hạn, bất kể lúc nào | **Có** |

**Vì sao sau hạn chốt thì không trả:** phòng, ghế và suất ăn đã đặt theo danh sách đã gửi nhà
cung cấp. Trả chỗ về kho là bán ra một chỗ **không có dịch vụ đi kèm** - khách lên xe rồi không
có phòng. Chỗ đó thành **ghế chết**: hãng đã trả tiền cho nó nhưng không có khách.

**Vì sao giữ chỗ chưa thanh toán lại luôn trả:** chỗ đó chưa bao giờ nằm trong danh sách gửi nhà
cung cấp. Nếu giữ lại thì một người vào giữ chỗ lúc hai giờ sáng rồi bỏ đi cũng làm mất vĩnh viễn
một chỗ bán được.

Hệ thống phân biệt hai nhóm bằng `paid_at` và `confirmed_at`, không dùng `status`. Vì tại thời
điểm kiểm tra thì `status` đã bị đổi sang `cancelled` rồi - đọc nó sẽ luôn ra "chưa thanh toán"
và mọi chỗ đều bị trả về kho.

Trường hợp dễ sót nhất: đơn được **quản trị xác nhận tay** không có `paid_at` nhưng vẫn nằm trong
danh sách gửi nhà cung cấp, nên phải đối xử như đơn đã thanh toán.

### Ghế chết ở lại tới hết chuyến

Từng có màn hình `/admin/held-seats` liệt kê chỗ đang bị giữ kèm nút mở lại. **Đã bỏ.**

Lý do: phí hủy đã bù phần chi phí đã cam kết với nhà cung cấp, nên việc còn lại thuần túy là đừng
bán ra một chỗ không có dịch vụ đi kèm - và luật ở trên lo trọn. Thêm một màn hình và một thao tác
thủ công không giải quyết thêm vấn đề nào.

Điều hành xin thêm được suất và muốn bán tiếp thì **tăng `max_people` của chuyến**. Đúng bản chất
hơn: chuyến chứa được nhiều hơn thật, chứ không phải chỗ cũ sống lại.

Lý do đầy đủ ở [06 - Đối chiếu feedback](06-doi-chieu-feedback.md), mục "Vì sao điểm 8 không có
nút mở lại chỗ".

### Lệnh đối chiếu

Từ khi có quy tắc này, `booked_people` không còn bằng tổng đơn chưa hủy nữa - nó gồm cả ghế chết.
Một chỗ lệch sẽ không làm đỏ bài kiểm thử nào và cũng không báo lỗi ở đâu, nó chỉ âm thầm khiến
hệ thống bán thừa hoặc bán thiếu.

`bookings:check-seat-consistency` chạy hằng giờ, đối chiếu và **báo lỗi chứ không tự sửa**. Lệch
số chỗ là dấu hiệu có lỗi nghiệp vụ ở đâu đó, tự sửa sẽ che mất nguyên nhân. Có `--fix` để nắn
lại sau khi đã hiểu nguyên nhân.

---

## Ranh giới - bốn nhóm này chưa làm gì

| Chưa có | Thuộc nhóm |
| --- | --- |
| Khách đã thanh toán gửi yêu cầu hủy để điều hành duyệt | F |
| Thực hiện hoàn tiền và tải chứng từ | N |
| Ghi nhận khách vắng mặt và rời đoàn giữa chừng | D03 và H |
| Ba lựa chọn khi chuyến thiếu khách: vẫn chạy, ghép, hủy | K và L |
| Nhật ký thay đổi đơn hàng | E |

Hiện khách vẫn chỉ tự hủy được đơn **chưa thanh toán**. Đơn đã thanh toán thì chỉ điều hành hủy
được, và hệ thống mới **tính ra** số tiền hoàn chứ chưa **thực hiện** việc hoàn.

---

## Nếu hội đồng hỏi

**Tour đang chạy có hủy được không?**

> Không, và chặn ở tầng dịch vụ chứ không ở giao diện, vì có nhiều lối vào cùng dẫn tới hủy đơn.
> Trong máy trạng thái của chuyến không tồn tại đường từ đang chạy về đã hủy: chi phí đã phát sinh
> và nhà cung cấp đã phục vụ. Thay vào đó có hai nghiệp vụ khác là ghi nhận khách không có mặt và
> rời đoàn giữa chừng.

**Hủy tour phải trước bao lâu?**

> Áp bảng phí năm bậc theo số giờ còn lại: từ 15 ngày hoàn 90 phần trăm, giảm dần, dưới 48 giờ
> không hoàn. Chia bậc chứ không giảm đều vì chi phí nhảy bậc tại các mốc chốt với nhà cung cấp.
> Bảng phí lưu thành dữ liệu để mỗi tour đặt riêng được, và đơn sao chép chính sách lúc đặt nên
> sửa về sau không hồi tố.

**Hủy sát giờ thì có cộng lại slot cho tour không?**

> Có điều kiện. Hủy trước hạn chốt danh sách thì trả chỗ ngay vì còn bán lại được. Hủy sau hạn
> chốt thì không trả tự động, vì phòng và suất ăn đã đặt theo danh sách đã chốt - trả về kho là
> bán ra một chỗ không có dịch vụ đi kèm. Chỗ đó là ghế chết, hãng đã trả tiền mà không có khách.
> Riêng giữ chỗ chưa thanh toán thì luôn trả, vì nó chưa bao giờ nằm trong danh sách nhà cung cấp.
> Điều hành vẫn mở lại thủ công được khi xin thêm được suất.

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

**Khách có biết trước mình mất bao nhiêu không?**

> Có. Trang chi tiết đơn hiện sẵn số tiền hoàn nếu hủy ngay bây giờ, kèm cả bảng phí với bậc đang
> áp dụng được tô sáng, nên khách tự đối chiếu được con số của mình rơi vào bậc nào.
