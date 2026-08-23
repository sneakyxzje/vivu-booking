import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";

export const Header: React.FC = () => {
  const navigate = useNavigate();
  const { isAuthenticated, user } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchKeyword, setSearchKeyword] = useState("");


  const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== "Enter") return;

    const keyword = searchKeyword.trim();
    navigate(keyword ? `/tours?q=${encodeURIComponent(keyword)}` : "/tours");
    setMobileOpen(false);
  };
  const navLinks = [
    { to: "/tours", label: "Tour trọn gói" },
    { to: "/group-booking", label: "Đặt theo đoàn" },
    { to: "/booking-lookup", label: "Tra cứu đơn" },
  ];

  return (
    /*
      Thanh trên cao 80px, nền trắng, chỉ một đường kẻ tóc dưới đáy — bỏ đổ bóng.
      DESIGN.md để thanh điều hướng phẳng; bóng dành cho thứ thật sự nổi lên khỏi mặt giấy.
    */
    <header className="fixed top-0 left-0 right-0 z-50 bg-canvas border-b border-hairline">
      <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20 gap-4">

          {/*
            Logo: dấu hiệu nhận diện đi kèm chữ. Tệp dùng ở đây là `logo-mark.png` — bản đã cắt
            sát nét của `logo.png`; bản gốc 5632×3072 có hơn nửa khung là khoảng trong suốt nên
            đặt thẳng vào thanh 80px thì hình co lại bé xíu và lệch lên trên.

            Chữ lấy `primary-500`, đúng bằng màu xanh trong logo. Các bậc còn lại của dải đậm hơn
            để giữ tương phản cho nút và liên kết; riêng ở đây cần khớp tuyệt đối với hình.

            Dưới màn hình nhỏ chỉ còn hình, vì thanh này đã chật.
          */}
          <Link to="/" className="flex items-center gap-2.5 shrink-0">
            <img
              src="/logo-mark.png"
              alt="Vivu Booking"
              width={57}
              height={40}
              className="h-10 w-auto"
            />
            <span className="hidden sm:inline text-2xl font-bold tracking-tight text-primary-500">
              Vivu Booking
            </span>
          </Link>

          {/*
            Ô tìm kiếm dạng viên thuốc: mặt trắng, viền tóc, và mang đúng tầng bóng duy nhất
            của hệ ngay khi đứng yên — đây là chỗ DESIGN.md cho phép nổi mà không cần rê chuột,
            vì nó là thứ được bấm nhiều nhất trên trang.
          */}
          <div className="hidden lg:flex items-center bg-canvas border border-hairline shadow-float rounded-full pl-4 pr-2 h-12 flex-1 max-w-sm">
            <svg className="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              placeholder="Tìm kiếm"
              value={searchKeyword}
              onChange={(e) => setSearchKeyword(e.target.value)}
              onKeyDown={handleSearchKeyDown}
              className="bg-transparent border-none focus:outline-none px-3 w-full text-body-sm text-ink placeholder:text-muted-soft"
            />
          </div>

          {/* Navigation Links */}
          <nav className="hidden xl:flex items-center justify-center gap-6">
            {navLinks.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                className="text-nav text-ink hover:text-primary-600 transition-colors flex items-center gap-1 whitespace-nowrap"
              >
                {link.label}
              </Link>
            ))}
          </nav>

          {/* Right Actions */}
          <div className="flex items-center gap-4 lg:gap-6 shrink-0">
            {/* Locale / Currency */}
            <div className="hidden lg:flex items-center gap-4">
              <button className="flex items-center gap-1 text-sm font-medium text-gray-700 hover:text-primary-600">
                <span className="w-5 h-5 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px]">★</span>
                VND
                <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
              </button>

              <button className="flex items-center gap-1 text-sm font-medium text-gray-700 hover:text-primary-600">
                <svg className="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                TP. Hồ Chí Minh
                <svg className="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
              </button>
            </div>

            {/* Auth Button */}
            {isAuthenticated ? (
              <Link
                to={
                  user?.role === "guide"
                    ? "/guide/dashboard"
                    : user?.role === "admin"
                      ? "/admin/dashboard"
                      : "/profile"
                }
                className="flex items-center gap-2 text-sm font-medium bg-primary-50 text-primary-600 px-4 py-2 rounded-full hover:bg-primary-100 transition-colors"
              >
                <div className="w-6 h-6 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-semibold">
                  {user?.name?.charAt(0).toUpperCase() ?? "U"}
                </div>
                {user?.role === "guide" ? "Quản lý Guide" : "Tài khoản"}
              </Link>
            ) : (
              <Link
                to="/login"
                className="flex items-center gap-2 text-sm font-semibold bg-primary-50 text-primary-600 px-5 py-2.5 rounded-full hover:bg-primary-100 transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                Đăng nhập
              </Link>
            )}

            {/* Cart */}
            <button className="text-gray-700 hover:text-primary-600 relative">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </button>

            {/* Mobile Menu Toggle */}
            <button
              className="xl:hidden p-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
              onClick={() => setMobileOpen(!mobileOpen)}
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {mobileOpen ? (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                ) : (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                )}
              </svg>
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Menu */}
      {mobileOpen && (
        <div className="xl:hidden bg-white border-t border-gray-100 px-4 py-4 max-h-[calc(100vh-5rem)] overflow-y-auto shadow-lg">
          <div className="mb-4 bg-gray-100 rounded-full px-4 py-2 flex items-center">
            <svg className="w-5 h-5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              placeholder="Tìm kiếm"
              value={searchKeyword}
              onChange={(e) => setSearchKeyword(e.target.value)}
              onKeyDown={handleSearchKeyDown}
              className="bg-transparent border-none focus:outline-none focus:ring-0 px-2 w-full text-sm text-gray-700"
            />
          </div>

          <nav className="flex flex-col gap-2">
            {navLinks.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                onClick={() => setMobileOpen(false)}
                className="text-[15px] font-medium text-gray-800 hover:text-primary-600 hover:bg-gray-50 px-3 py-2 rounded-lg transition-colors flex justify-between items-center"
              >
                {link.label}
              </Link>
            ))}
          </nav>
        </div>
      )}
    </header>
  );
};

export default Header;
