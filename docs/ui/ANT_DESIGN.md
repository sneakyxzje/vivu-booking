# Ant Design trong giao diện quản trị

Áp dụng theo yêu cầu người dùng ngày 15/09/2026: dùng Ant Design, hạn chế CSS riêng. Với màn đã chuyển sang Ant Design, hướng dẫn này thay cho việc dựng lại từng thành phần theo DESIGN.md.

- Cấu hình chung ở `client/src/components/admin/AdminUIProvider.tsx`: tiếng Việt, màu thương hiệu, cỡ chữ, chiều cao trường nhập và bo góc.
- Dùng `Table`, `Form`, `Input`, `InputNumber`, `Select`, `Modal`, `Steps`, `Tabs`, `Descriptions`, `Alert`, `Tag` có sẵn. Bố cục dùng `Flex`, `Row`, `Col`.
- Ưu tiên props và theme tokens; không viết lại nút, select, bảng hoặc hộp thoại bằng CSS/Tailwind. Inline style chỉ dành cho nhu cầu bố cục không có prop tương ứng, như chiều rộng ô tiền hoặc giới hạn vùng cuộn.
- CSS chung chỉ khai thứ tự layer `theme, base, antd, components, utilities` để Ant Design và Tailwind đang có cùng hoạt động. Không thêm bộ reset toàn trang.
- Biểu mẫu tiền phải gọi các API hiện có, hiển thị lỗi trả về và khóa thao tác khi đang lưu. Các bước hủy/chuyển phải giữ kiểm tra điều kiện và phần xem trước tác động.
- `AntStepperModal` kiểm tra điều kiện của các bước trước trước khi cho tiếp tục hoặc xác nhận. `Modal`, `StepperModal`, `TableActions` và `CustomAlert` của quản trị hiện dùng Ant Design bên trong; dùng lại chúng thay vì dựng lớp phủ hoặc menu thủ công.

Đã áp dụng cho các trang quản trị:

- Đơn đặt tour; yêu cầu đoàn; yêu cầu hủy.
- Lịch khởi hành: Tabs tách chuyến chưa đi, đang đi và lịch sử; Table từng chuyến với lọc tour/ngày/trạng thái/chưa phân công. Drawer chi tiết gom các thao tác, phần chạy thử thời gian nằm riêng trong Collapse. Hộp ghép hai cột; phân công, bàn giao, đổi hạn, danh sách khách và hủy chuyến bằng Modal.
- Sổ giao dịch, phải thu, hoàn tiền: Tabs; tổng phải trả cập nhật lại sau khi ghi nhận khoản hoàn.
- Danh sách và chi tiết tour; tạo/sửa tour: Input, Select có tìm kiếm, DatePicker, Steps, Upload. Upload chỉ chọn tệp vào biểu mẫu; tệp được gửi khi lưu tour, giữ khả năng thêm tiếp ảnh vào bộ ảnh.
- Lịch khởi hành khi tạo/sửa tour: chọn nhiều ngày, nhập giờ và số chỗ rồi bấm Thêm chuyến; chọn ngày chưa tự thêm chuyến. Table có dòng mở rộng để sửa riêng, kiểm lỗi ngay tại từng mốc thời gian. Sửa nhiều chuyến có nút áp dụng; các chuyến đã kết thúc/hủy/đang diễn ra chỉ đọc.
- Lịch trình từng ngày khi tạo/sửa tour: Tabs chọn ngày, mỗi lần soạn một ngày trên toàn bộ chiều rộng. Tiêu đề và hoạt động đặt trước; di chuyển/nghỉ chân và điểm danh cho hướng dẫn viên nằm trong Collapse. Các nơi đi qua nhập mỗi dòng một địa điểm. Tự tạo đủ ngày theo thời lượng, kể cả khi mở tour cũ còn thiếu lịch trình. Có nút ngày trước/sau; chuyển ngày cuộn về đầu phần soạn. Mỗi ngày cần tiêu đề và hoạt động; giao diện và API đều chặn lưu nếu thiếu hoặc trùng ngày. Giảm thời lượng giữ nguyên nội dung và chỉ cho xóa ngày khi đang thừa, có xác nhận. Kiểm tra tên điểm danh trước khi lưu.
- Tài khoản, hướng dẫn viên, hồ sơ năng lực, danh mục, dịch vụ, mã giảm giá, đánh giá, liên hệ, nhật ký, báo cáo điểm danh, sự cố và bàn giao.
- Tổng quan và thông báo quản trị.
- Khung quản trị dùng Layout, Menu, Drawer, Breadcrumb và Dropdown. Các route quản trị nạp riêng bằng lazy.

Ngày/giờ gửi API giữ dạng `YYYY-MM-DD` hoặc `YYYY-MM-DDTHH:mm` theo giờ địa phương, không đổi sang UTC khi chọn ngày. Select giữ giá trị chuỗi giống biểu mẫu cũ; các trường ID tiếp tục chuyển sang số ở handler hiện có.

Không thêm tệp CSS riêng. Một số bố cục phức tạp, nhãn trạng thái và biểu đồ còn dùng Tailwind cũ; đây là phần còn giữ lại, không phải đã chuyển toàn bộ CSS của website.

Cập nhật 21/09/2026: sổ giao dịch dùng Table có mã GD/BK, số tiền có dấu và màu, bộ lọc có nhãn,
Drawer xem chứng từ/ghi chú/người ghi và Timeline lịch sử đơn. Tổng công nợ tách khỏi tổng theo
bộ lọc. Trạng thái chuyến bỏ `closed`; hết hạn/hết chỗ hiện như lý do không nhận đặt bên cạnh
“Chờ chốt”. Xem [quy tắc cập nhật](../nghiep-vu/BO_TRANG_THAI_DONG_BAN.md).

Kiểm tra ngày 15/09/2026: TypeScript và build production thành công; đối chiếu các lời gọi API tại các trang quản trị chuyển trong đợt này không thấy đổi tên API hoặc đối số. Lint trong phạm vi quản trị còn 25 lỗi cũ, không phát sinh cảnh báo/lỗi mới so với HEAD (26 lỗi). Đây là kiểm tra tĩnh, không thay cho việc chạy nghiệp vụ hoặc kiểm tra bố cục bằng trình duyệt. Trình duyệt tích hợp chưa khả dụng trong phiên này.

Kiểm tra trực tiếp khi có trình duyệt: tìm kiếm/phân trang; mở rồi đổi đơn nhanh; các tab chi tiết; lỗi tải dữ liệu; thu tiền; hủy theo hai bên khởi xướng; chuyển chuyến có/không có căn cứ; hợp đồng; bàn phím Escape/Tab và màn hình nhỏ. Không dùng dữ liệu thật để xác nhận các thao tác tài chính khi chỉ kiểm tra giao diện.

Tham khảo: [Ant Design với Tailwind CSS 4](https://ant.design/docs/react/compatible-style/), [cấu hình theme](https://ant.design/docs/react/customize-theme/).

- Trang hướng dẫn viên dùng `App.useApp()` qua `useGuideFeedback`: `message` cho kết quả thao tác, `notification` cho lỗi tải dữ liệu, có nút tải lại trong trang. Ưu tiên lỗi validation/nghiệp vụ cụ thể từ API; không hiển thị chẩn đoán lỗi máy chủ. Thông báo lỗi tải được dọn khi đổi trang hoặc thử lại. Alert chỉ giữ cho thông tin nghiệp vụ và hướng dẫn ngay tại trường nhập.

- Tổng quan HDV ưu tiên chuyến hôm nay, chuyến chờ nhận và tiến độ điểm danh/ảnh của ngày hiện tại. Dữ liệu tiến độ chưa tải được hiện chưa rõ, không mặc định hoàn tất. Tour của tôi dùng thẻ gộp theo tour, chuyến đang đi lên đầu, các chuyến còn lại mới nhất trước; có lọc chuyến cũ, xem thêm và phân trang. Menu và trang sự cố dùng tên “Báo sự cố”.

- Điểm danh HDV: Tabs theo ngày, Select điểm dừng và nút trước/tiếp trong ngày. Danh sách khách có tìm kiếm, lọc trạng thái; nút Có mặt và Select các trạng thái khác. Modal lý do chỉ áp dụng khi xác nhận, hủy giữ nguyên trạng thái. Thanh lưu bám cuối màn hình, hiển thị số khách thay đổi; mỗi khách có hoàn tác phần chưa lưu. Chỉ gửi phần thay đổi, giữ bản nháp khi chuyển điểm, đánh dấu điểm/ngày chưa lưu và nhắc khi đóng hoặc tải lại trang. Ảnh gom tại điểm dừng; khi ghi đủ khách mà thiếu ảnh bắt buộc, nút chính chuyển sang thêm ảnh. Ngày quá khứ/tương lai và chuyến kết thúc vẫn chỉ xem theo quyền máy chủ.
- Chuyến được giao: Tabs Chờ trả lời / Đã nhận / Chuyến cũ, có tìm kiếm và phân trang. Chuyến đang đi ưu tiên, chuyến sắp đi xếp gần nhất trước; lịch sử mới nhất trước. Ngày khởi hành/về nổi bật, các mốc phụ trong Collapse. Nhận/Từ chối nằm cạnh nhau, từ chối bằng Modal có lý do; chuyến cũ không còn nút nhận/từ chối. Có lối vào điểm danh và bàn giao đoàn khi đang đi.

- Ảnh điểm danh: chọn tệp mở Modal xem trước có tên ảnh, ngày và điểm dừng, bấm Tải ảnh lên mới gửi. Giữ ảnh khi gửi lỗi để thử lại, cố định điểm nhận ảnh theo bản xem trước. Ảnh đã lưu hiện trực tiếp dưới điểm dừng, có giờ tải và nhãn Vừa tải lên, bấm mở ảnh lớn; không giấu trong Collapse. Khi cửa sổ chọn ảnh đóng, chờ xác minh quyền mới thay vì bỏ qua tệp do trang đang đồng bộ.
- Tải ảnh điểm danh không xin quyền GPS hoặc yêu cầu tọa độ điểm dừng; không hiển thị cảnh báo khoảng cách. Máy chủ vẫn kiểm tra quyền của HDV và ngày điểm danh.
- Lịch khởi hành admin dùng `effective_status` do máy chủ tính theo cùng đồng hồ với HDV để chia tab, lọc, hiển thị trạng thái và các thao tác trong chi tiết. API giữ `status` đã lưu để các thao tác vòng đời/demo tiếp tục dùng đúng trạng thái lưu trữ; đọc danh sách không tự cập nhật cơ sở dữ liệu.
