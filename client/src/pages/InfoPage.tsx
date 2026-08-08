import React from "react";
import { Link } from "react-router-dom";

const sections = [
  {
    id: "gioi-thieu",
    title: "Giới thiệu chung",
    body: (
      <>
        <p>
          Vivu Booking là nền tảng đặt tour du lịch trọn gói trực tuyến, kết nối khách
          hàng với các chương trình tour chất lượng trên khắp Việt Nam. Hệ thống hỗ trợ
          trọn vẹn hành trình của khách: tìm kiếm và so sánh tour, giữ chỗ trực tuyến,
          thanh toán an toàn qua VNPay, nhận thông tin điểm đón và hướng dẫn viên, cho
          tới đánh giá sau chuyến đi.
        </p>
        <p>
          Với hướng dẫn viên, Vivu Booking cung cấp công cụ quản lý đoàn và điểm danh
          tại từng chặng; với đơn vị vận hành là bộ máy quản trị tour, lịch khởi hành,
          đặt chỗ và thống kê doanh thu theo thời gian thực.
        </p>
      </>
    ),
  },
  {
    id: "dieu-khoan",
    title: "Điều khoản sử dụng",
    body: (
      <ul className="list-disc pl-5 space-y-2">
        <li>Khách hàng chịu trách nhiệm về tính chính xác của thông tin cung cấp khi đặt tour (họ tên, giấy tờ tùy thân, thông tin liên hệ).</li>
        <li>Đơn đặt tour chỉ được giữ chỗ trong 10 phút kể từ khi khởi tạo; quá thời hạn chưa thanh toán, hệ thống tự hủy và nhường chỗ cho khách khác.</li>
        <li>Đơn đã xác nhận là cam kết giữ chỗ chính thức giữa Vivu Booking và khách hàng.</li>
        <li>Vivu Booking có quyền từ chối hoặc hủy các đơn có dấu hiệu gian lận, kèm hoàn tiền theo quy định.</li>
      </ul>
    ),
  },
  {
    id: "chinh-sach",
    title: "Chính sách bảo mật",
    body: (
      <ul className="list-disc pl-5 space-y-2">
        <li>Thông tin cá nhân của khách hàng chỉ được dùng cho mục đích xử lý đơn đặt tour, làm bảo hiểm du lịch và chăm sóc khách hàng.</li>
        <li>Mật khẩu được mã hóa một chiều; giao dịch thanh toán được xử lý qua cổng VNPay với chữ ký bảo mật, Vivu Booking không lưu thông tin thẻ.</li>
        <li>Chúng tôi không chia sẻ dữ liệu khách hàng cho bên thứ ba ngoài phạm vi phục vụ chuyến đi (đơn vị vận chuyển, lưu trú).</li>
        <li>Khách hàng có thể yêu cầu chỉnh sửa hoặc xóa thông tin cá nhân qua email hỗ trợ.</li>
      </ul>
    ),
  },
  {
    id: "lien-he",
    title: "Liên hệ",
    body: (
      <div className="space-y-2">
        <p>
          <span className="text-gray-500">Tổng đài bán tour:</span>{" "}
          <strong className="text-primary-600">1900 1234</strong> (8:00 – 21:00 hằng ngày)
        </p>
        <p>
          <span className="text-gray-500">Email:</span>{" "}
          <strong>info@vivubooking.vn</strong>
        </p>
        <p>
          <span className="text-gray-500">Trụ sở chính:</span>{" "}
          Phố Kiều Mai, Phúc Diễn, Bắc Từ Liêm, Hà Nội
        </p>
      </div>
    ),
  },
];

export const InfoPage: React.FC = () => (
  <div className="min-h-screen bg-gray-50/60 py-10">
    <div className="max-w-3xl mx-auto px-4 sm:px-6">
      <nav className="flex items-center gap-2 text-xs md:text-sm text-gray-500 font-medium mb-8">
        <Link to="/" className="hover:text-primary-600 transition-colors">Trang chủ</Link>
        <span className="text-gray-300">/</span>
        <span className="text-gray-900">Về Vivu Booking</span>
      </nav>

      <div className="space-y-8">
        {sections.map((section) => (
          <section
            key={section.id}
            id={section.id}
            className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm"
          >
            <h2 className="text-xl font-bold text-gray-900 font-plus-jakarta mb-4">
              {section.title}
            </h2>
            <div className="text-sm text-gray-600 leading-relaxed space-y-3">
              {section.body}
            </div>
          </section>
        ))}
      </div>
    </div>
  </div>
);

export default InfoPage;
