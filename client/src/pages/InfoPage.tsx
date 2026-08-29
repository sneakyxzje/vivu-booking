import React from "react";
import { Link } from "react-router-dom";
import { useDocumentMeta } from "@/hooks/useDocumentMeta";

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
  /*
   * "Điều khoản sử dụng" và "Chính sách bảo mật" đã chuyển sang trang /chinh-sach.
   *
   * Ba văn bản ấy trả lời cùng một câu hỏi - "tôi đang đồng ý với cái gì" - và trước đây nằm rải
   * ở ba đường dẫn, trong đó hai đường dẫn hiện ra cùng một trang. Gộp lại thì khách đọc một mạch
   * thay vì phải tự ghép ba mảnh.
   */
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

export const InfoPage: React.FC = () => {
  useDocumentMeta({
    title: "Về Vivu Booking",
    description:
      "Giới thiệu Vivu Booking, cách chúng tôi tổ chức tour và thông tin liên hệ với bộ phận điều hành.",
  });

  return (
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
};

export default InfoPage;
