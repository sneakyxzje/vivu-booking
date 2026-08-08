import React from "react";
import { Link } from "react-router-dom";

export const Footer: React.FC = () => {
  return (
    <footer className="bg-white border-t border-gray-200">
      <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 lg:gap-12">
          
          <div className="lg:col-span-2">
            <Link to="/" className="flex items-center mb-6">
              <div className="text-3xl font-bold tracking-tight">
                <span className="text-primary-600">Vivu Booking</span>
              </div>
            </Link>
            <p className="text-sm text-gray-600 leading-relaxed mb-6 max-w-sm">
              Mạng bán tour trực tuyến đầu tiên tại Việt Nam. Đặt tour trong và ngoài nước dễ dàng, an toàn, nhanh chóng.
            </p>
            {/* Social icons placeholder */}
            <div className="flex gap-4">
              <a href="#" className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-primary-50 hover:text-primary-600 transition-colors">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" /></svg>
              </a>
              <a href="#" className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-primary-50 hover:text-primary-600 transition-colors">
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" /></svg>
              </a>
            </div>
          </div>

          <div>
            <h3 className="text-[17px] font-bold text-gray-900 mb-6 uppercase tracking-wide">
              Về chúng tôi
            </h3>
            <ul className="space-y-4 text-[15px]">
              {[
                { to: "/about", label: "Giới thiệu chung" },
                { to: "/terms", label: "Điều khoản sử dụng" },
                { to: "/privacy", label: "Chính sách bảo mật" },
                { to: "/contact", label: "Liên hệ" },
              ].map((link) => (
                <li key={link.to}>
                  <Link
                    to={link.to}
                    className="text-gray-600 hover:text-primary-600 transition-colors"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-[17px] font-bold text-gray-900 mb-6 uppercase tracking-wide">
              Dịch vụ
            </h3>
            <ul className="space-y-4 text-[15px]">
              {[
                { to: "/tours", label: "Tour trọn gói" },
                { to: "/tra-cuu-don", label: "Tra cứu đơn đặt tour" },
              ].map((link) => (
                <li key={link.to}>
                  <Link
                    to={link.to}
                    className="text-gray-600 hover:text-primary-600 transition-colors"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-[17px] font-bold text-gray-900 mb-6 uppercase tracking-wide">
              Tổng đài CSKH
            </h3>
            <ul className="space-y-4 text-[15px]">
              <li>
                <div className="text-gray-500 text-sm mb-1">Tổng đài bán tour</div>
                <div className="text-primary-600 font-bold text-xl">1900 1234</div>
              </li>
              <li>
                <div className="text-gray-500 text-sm mb-1">Email</div>
                <div className="text-gray-800 font-medium">info@vivubooking.vn</div>
              </li>
              <li>
                <div className="text-gray-500 text-sm mb-1">Trụ sở chính</div>
                <div className="text-gray-800 leading-relaxed">
                  Phố Kiều Mai, Phúc Diễn, Bắc Từ Liêm, Hà Nội
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div className="bg-gray-50 border-t border-gray-200">
        <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="text-sm text-gray-500">
            Bản quyền &copy; {new Date().getFullYear()} thuộc về Vivu Booking
          </div>
          <div className="flex items-center gap-4">
            <span className="text-sm text-gray-500">Thanh toán an toàn qua</span>
            <span className="inline-flex items-center rounded border border-gray-200 bg-white px-2.5 py-1 text-xs font-bold">
              <span className="text-[#005baa]">VN</span>
              <span className="text-[#ed1c24]">PAY</span>
            </span>
          </div>
        </div>
      </div>

      {/* Floating Action Buttons */}
      <div className="fixed bottom-6 right-6 flex flex-col gap-3 z-50">
        <button className="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-lg hover:bg-blue-700 transition-colors">
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
          </svg>
        </button>
      </div>

    </footer>
  );
};

export default Footer;
