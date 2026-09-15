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
- Lịch khởi hành: nhóm theo tour bằng Collapse và Table; hộp ghép hai cột; phân công, bàn giao, dời hạn, danh sách đoàn và hủy chuyến bằng Modal.
- Sổ giao dịch, phải thu, hoàn tiền: Tabs; tổng phải trả cập nhật lại sau khi ghi nhận khoản hoàn.
- Danh sách và chi tiết tour; tạo/sửa tour: Input, Select có tìm kiếm, DatePicker, Steps, Upload. Upload chỉ chọn tệp vào biểu mẫu; tệp được gửi khi lưu tour, giữ khả năng thêm tiếp ảnh vào bộ ảnh.
- Tài khoản, hướng dẫn viên, hồ sơ năng lực, danh mục, dịch vụ, mã giảm giá, đánh giá, liên hệ, nhật ký, báo cáo điểm danh, sự cố và bàn giao.
- Tổng quan, sân thử nghiệp vụ và thông báo quản trị.
- Khung quản trị dùng Layout, Menu, Drawer, Breadcrumb và Dropdown. Các route quản trị nạp riêng bằng lazy.

Ngày/giờ gửi API giữ dạng `YYYY-MM-DD` hoặc `YYYY-MM-DDTHH:mm` theo giờ địa phương, không đổi sang UTC khi chọn ngày. Select giữ giá trị chuỗi giống biểu mẫu cũ; các trường ID tiếp tục chuyển sang số ở handler hiện có.

Không thêm tệp CSS riêng. Một số bố cục phức tạp, nhãn trạng thái và biểu đồ còn dùng Tailwind cũ; đây là phần còn giữ lại, không phải đã chuyển toàn bộ CSS của website.

Kiểm tra ngày 15/09/2026: TypeScript và build production thành công; đối chiếu các lời gọi API tại các trang quản trị chuyển trong đợt này không thấy đổi tên API hoặc đối số. Lint trong phạm vi quản trị còn 25 lỗi cũ, không phát sinh cảnh báo/lỗi mới so với HEAD (26 lỗi). Đây là kiểm tra tĩnh, không thay cho việc chạy nghiệp vụ hoặc kiểm tra bố cục bằng trình duyệt. Trình duyệt tích hợp chưa khả dụng trong phiên này.

Kiểm tra trực tiếp khi có trình duyệt: tìm kiếm/phân trang; mở rồi đổi đơn nhanh; các tab chi tiết; lỗi tải dữ liệu; thu tiền; hủy theo hai bên khởi xướng; chuyển chuyến có/không có căn cứ; hợp đồng; bàn phím Escape/Tab và màn hình nhỏ. Không dùng dữ liệu thật để xác nhận các thao tác tài chính khi chỉ kiểm tra giao diện.

Tham khảo: [Ant Design với Tailwind CSS 4](https://ant.design/docs/react/compatible-style/), [cấu hình theme](https://ant.design/docs/react/customize-theme/).
