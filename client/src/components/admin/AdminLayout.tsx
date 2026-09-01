import React, { useState } from "react";
import {
  Link,
  NavLink,
  Outlet,
  useLocation,
  useNavigate,
} from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import { useNotifications } from "@/hooks/useNotifications";

/*
 * Menu quản trị: bốn nhóm có menu con, kẹp giữa hai mục đứng riêng.
 *
 * Trước đây mười lăm mục nằm phẳng thành một cột dài, phải cuộn mới thấy hết và không có gì cho
 * biết mục nào họ hàng với mục nào — "Chính sách hủy" đứng cạnh "Yêu cầu hủy của khách" chỉ vì
 * tình cờ được thêm vào cùng lúc.
 *
 * Nhóm chia theo việc người dùng đang làm, không theo bảng dữ liệu: dựng sản phẩm để bán, xử lý
 * đơn đã bán, điều hành chuyến đang chạy. Một mục chỉ thuộc đúng một nhóm — trùng chỗ thì lại
 * thành phải đoán lần nữa.
 *
 * "Tổng quan" và "Nhật ký hệ thống" không vào nhóm nào: cái đầu là nơi mở màn, cái sau là nơi tra
 * ngược khi có chuyện. Nhét vào một nhóm chỉ để cho đều là tạo ra một ngăn chứa đồ thừa.
 */
type NavLeaf = { to: string; label: string };

type NavEntry =
  | { kind: "link"; to: string; label: string; icon: React.ReactNode }
  | {
      kind: "group";
      id: string;
      label: string;
      icon: React.ReactNode;
      items: NavLeaf[];
    };

const icon = (d: string, strokeWidth = 2) => (
  <svg
    className="w-5 h-5 shrink-0"
    fill="none"
    stroke="currentColor"
    viewBox="0 0 24 24"
  >
    <path
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={strokeWidth}
      d={d}
    />
  </svg>
);

const navEntries: NavEntry[] = [
  {
    kind: "link",
    to: "/admin/dashboard",
    label: "Tổng quan",
    icon: icon(
      "M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z",
    ),
  },
  {
    kind: "group",
    id: "san-pham",
    label: "Sản phẩm",
    icon: icon(
      "M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7",
    ),
    items: [
      { to: "/admin/tours", label: "Quản lý tour" },
      { to: "/admin/categories", label: "Danh mục tour" },
      /*
       * "Dịch vụ đi kèm", không phải "Dịch vụ phát sinh".
       *
       * Màn này quản lý những thứ tour ĐÃ bao gồm trong giá bán — xe đưa đón, bảo hiểm, vé tham
       * quan. Chi phí phát sinh thật, tức khoản sinh ra ngoài ý muốn khi có bão hay xe hỏng, nằm
       * ở "Sự cố dọc đường" và đi qua bảng `booking_surcharges`.
       *
       * Hai chuyện ngược nhau mà tên cũ dùng chung một chữ.
       */
      { to: "/admin/services", label: "Dịch vụ đi kèm" },
      { to: "/admin/discount-codes", label: "Mã giảm giá" },
      /*
       * Đánh giá nằm ở nhóm "sản phẩm" chứ không ở nhóm "đơn hàng": nó là thứ hiện trên trang
       * bán tour và kéo điểm tour lên xuống, không phải một bước trong vòng đời một đơn.
       */
      { to: "/admin/reviews", label: "Đánh giá của khách" },
    ],
  },
  {
    kind: "group",
    id: "don-hang",
    label: "Đơn hàng",
    icon: icon(
      "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01",
    ),
    items: [
      { to: "/admin/bookings", label: "Hoá đơn" },
      { to: "/admin/group-bookings", label: "Booking theo đoàn" },
      { to: "/admin/change-requests", label: "Yêu cầu huỷ" },
      /*
       * Hai mục tiền, đặt cạnh nhau và đúng thứ tự vào trước ra sau.
       *
       * "Sổ giao dịch" là toàn bộ dòng tiền; "Hoàn tiền" là một lát cắt của nó — phần công ty còn
       * nợ khách. Trước đây chỉ có mục thứ hai, tức tiền đi ra có màn riêng còn tiền đi vào thì
       * phải mở từng đơn mới xem được, trong khi công ty thu nhiều hơn chi rất nhiều lần.
       */
      { to: "/admin/transactions", label: "Sổ giao dịch" },
      { to: "/admin/refunds", label: "Hoàn tiền" },
      { to: "/admin/cancellation-policies", label: "Chính sách hủy" },
    ],
  },
  {
    kind: "group",
    id: "dieu-hanh",
    label: "Điều hành",
    icon: icon(
      "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z",
    ),
    items: [
      { to: "/admin/schedules", label: "Quản lý chuyến" },
      { to: "/admin/guides", label: "Hướng dẫn viên" },
      { to: "/admin/handovers", label: "Bàn giao HDV" },
      { to: "/admin/incidents", label: "Chi phí phát sinh" },
      { to: "/admin/attendance-reports", label: "Báo cáo điểm danh" },
    ],
  },
  {
    kind: "link",
    to: "/admin/notifications",
    label: "Thông báo",
    icon: icon(
      "M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9",
    ),
  },
  {
    /*
     * Tài khoản đứng riêng chứ không nằm trong nhóm "điều hành chuyến": nó cắt ngang cả ba nhóm
     * kia — cùng một màn quản lý cả khách hàng, hướng dẫn viên lẫn người điều hành.
     */
    kind: "link",
    to: "/admin/users",
    label: "Tài khoản",
    icon: icon(
      "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
    ),
  },
  {
    kind: "link",
    to: "/admin/contact-messages",
    label: "Liên hệ & bản tin",
    icon: icon(
      "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
    ),
  },
  {
    kind: "link",
    to: "/admin/audit-logs",
    label: "Nhật ký hệ thống",
    icon: icon("M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"),
  },
];

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-3 px-4 py-3 rounded-md text-sm font-medium transition-all duration-200 ${
    isActive
      ? "bg-primary-600 text-white font-semibold shadow-sm"
      : "text-slate-400 hover:bg-slate-800 hover:text-white"
  }`;

/*
 * Mục con: không có biểu tượng, thụt vào sau một đường kẻ dọc.
 *
 * Đường kẻ làm việc mà biểu tượng thứ hai không làm được — nó cho thấy các mục này thuộc về đầu
 * mục ngay trên, và nhìn một cái là biết nhóm dài đến đâu.
 */
const subLinkClass = ({ isActive }: { isActive: boolean }) =>
  `block rounded-md py-2 pl-4 pr-3 text-sm transition-colors duration-200 ${
    isActive
      ? "bg-primary-600/15 text-white font-semibold"
      : "text-slate-400 hover:bg-slate-800 hover:text-white"
  }`;

export const AdminLayout: React.FC = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  // Chuông chỉ cần con số; danh sách nằm ở màn riêng.
  const { unread } = useNotifications();
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);

  /*
   * Nhóm nào chứa trang đang mở thì bung sẵn, các nhóm khác đóng.
   *
   * Tính một lần lúc dựng chứ không đồng bộ theo `pathname`: nếu đồng bộ liên tục thì người dùng
   * mở tay một nhóm khác để so sánh sẽ bị đóng sập lại ngay khi họ điều hướng. Bung lần đầu là
   * việc của hệ thống, sau đó là việc của người dùng.
   */
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
    const active = navEntries.find(
      (entry) =>
        entry.kind === "group" &&
        entry.items.some((item) => pathname.startsWith(item.to)),
    );

    return active && active.kind === "group" ? { [active.id]: true } : {};
  });

  const toggleGroup = (id: string) =>
    setOpenGroups((prev) => ({ ...prev, [id]: !prev[id] }));

  const handleLogout = () => {
    logout();
    navigate("/login");
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col md:flex-row">
      {/* Mobile Sidebar Backdrop Overlay */}
      {isSidebarOpen && (
        <div
          className="fixed inset-0 bg-slate-950/40 backdrop-blur-sm z-40 md:hidden"
          onClick={() => setIsSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-slate-900 text-slate-300 border-r border-slate-800 transition-transform duration-300 ease-in-out shrink-0 md:sticky md:top-0 md:bottom-auto md:h-screen md:self-start md:translate-x-0 ${
          isSidebarOpen ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="p-5 border-b border-slate-800 flex items-center justify-between">
          <Link
            to="/"
            className="text-lg font-bold text-white tracking-tight flex items-center gap-2"
          >
            <span className="w-8 h-8 rounded-md bg-primary-600 flex items-center justify-center text-white text-sm font-semibold">
              VB
            </span>
            VivuBooking
            <span className="text-[10px] bg-primary-950 text-primary-400 px-1.5 py-0.5 rounded font-semibold uppercase border border-primary-800/30">
              Admin
            </span>
          </Link>
          {/* Close button for mobile sidebar */}
          <button
            type="button"
            onClick={() => setIsSidebarOpen(false)}
            className="text-slate-400 hover:text-white md:hidden focus:outline-none cursor-pointer"
          >
            <svg
              className="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        {/* Navigation items */}
        <nav className="flex-1 p-4 space-y-1.5 overflow-y-auto">
          {navEntries.map((entry) => {
            if (entry.kind === "link") {
              return (
                <NavLink
                  key={entry.to}
                  to={entry.to}
                  className={linkClass}
                  onClick={() => setIsSidebarOpen(false)}
                >
                  {entry.icon}
                  {entry.label}
                </NavLink>
              );
            }

            const isOpen = Boolean(openGroups[entry.id]);
            const hasActive = entry.items.some((item) =>
              pathname.startsWith(item.to),
            );

            return (
              <div key={entry.id}>
                <button
                  type="button"
                  onClick={() => toggleGroup(entry.id)}
                  aria-expanded={isOpen}
                  aria-controls={`nav-${entry.id}`}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-md text-sm font-medium transition-colors duration-200 cursor-pointer ${
                    /*
                      Nhóm đang chứa trang mở mà bị thu lại thì vẫn phải nhìn ra — nếu không, đóng
                      nhóm lại là mất dấu hoàn toàn chỗ mình đang đứng.
                    */
                    hasActive && !isOpen
                      ? "bg-slate-800 text-white font-semibold"
                      : "text-slate-400 hover:bg-slate-800 hover:text-white"
                  }`}
                >
                  {entry.icon}
                  <span className="flex-1 text-left">{entry.label}</span>
                  <svg
                    className={`w-4 h-4 shrink-0 transition-transform duration-200 ${isOpen ? "rotate-180" : ""}`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </button>

                {isOpen && (
                  <div
                    id={`nav-${entry.id}`}
                    className="mt-1 ml-6 space-y-0.5 border-l border-slate-800 pl-2"
                  >
                    {entry.items.map((item) => (
                      <NavLink
                        key={item.to}
                        to={item.to}
                        className={subLinkClass}
                        onClick={() => setIsSidebarOpen(false)}
                      >
                        {item.label}
                      </NavLink>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </nav>

        <div className="p-4 border-t border-slate-800 space-y-1">
          <Link
            to="/"
            className="flex items-center gap-3 text-sm text-slate-400 hover:text-white px-4 py-2.5 rounded-md hover:bg-slate-800 transition-colors"
          >
            <svg
              className="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
            Trang chủ
          </Link>
          <button
            type="button"
            onClick={handleLogout}
            className="w-full flex items-center gap-3 text-sm text-rose-400 hover:bg-rose-950/30 hover:text-rose-300 px-4 py-2.5 rounded-md transition-colors text-left cursor-pointer"
          >
            <svg
              className="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
              />
            </svg>
            Đăng xuất
          </button>
        </div>
      </aside>

      {/* Content Container */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header */}
        <header className="sticky top-0 z-30 flex h-16 w-full shrink-0 items-center justify-between border-b border-gray-200 bg-white px-6">
          {/* Left: Mobile hamburger menu & page title info */}
          <div className="flex items-center gap-4">
            <button
              type="button"
              onClick={() => setIsSidebarOpen(true)}
              className="text-gray-500 hover:text-gray-700 md:hidden focus:outline-none cursor-pointer"
            >
              <svg
                className="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M4 6h16M4 12h16M4 18h16"
                />
              </svg>
            </button>
            <div className="hidden sm:flex items-center">
              <span className="text-sm font-semibold text-gray-500">
                Hệ thống quản trị
              </span>
              <span className="mx-2 text-gray-300">/</span>
              <span className="text-sm font-bold text-primary-600">
                VivuBooking
              </span>
            </div>
          </div>

          {/*
            Chuông thông báo.

            Chỉ một con số và một đường dẫn — không dựng danh sách thả xuống. Toàn bộ thông báo
            nằm ở màn riêng; nhồi thêm một bản sao rút gọn vào thanh trên cùng là hai chỗ cùng
            hiển thị một dữ liệu, và hai chỗ ấy sớm muộn lệch nhau.
          */}
          <Link
            to="/admin/notifications"
            className="relative ml-auto mr-2 rounded-md p-2 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
            title="Thông báo"
          >
            <svg
              className="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
              />
            </svg>

            {unread > 0 && (
              <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                {unread > 99 ? "99+" : unread}
              </span>
            )}
          </Link>

          {/* Right: User Dropdown */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setIsDropdownOpen(!isDropdownOpen)}
              className="flex items-center gap-3 rounded-md hover:bg-gray-50 p-1.5 transition-colors focus:outline-none cursor-pointer"
            >
              <div className="w-9 h-9 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold text-sm shadow-sm ring-2 ring-white">
                {user?.name?.charAt(0).toUpperCase() ?? "A"}
              </div>
              <div className="hidden md:block text-left">
                <p className="text-sm font-semibold text-gray-700 leading-tight">
                  {user?.name ?? "Administrator"}
                </p>
                <p className="text-xs text-gray-400 leading-tight">
                  Quản trị viên
                </p>
              </div>
              <svg
                className={`w-4 h-4 text-gray-400 transition-transform duration-200 ${isDropdownOpen ? "rotate-180" : ""}`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M19 9l-7 7-7-7"
                />
              </svg>
            </button>

            {/* Dropdown Menu */}
            {isDropdownOpen && (
              <>
                {/* Backdrop for closing dropdown */}
                <div
                  className="fixed inset-0 z-10"
                  onClick={() => setIsDropdownOpen(false)}
                />
                <div className="absolute right-0 mt-2 w-56 rounded-md bg-white border border-gray-200 shadow-xl py-2 z-20 animate-fade-in">
                  <div className="px-4 py-3 border-b border-gray-100">
                    <p className="text-sm font-bold text-gray-800 truncate">
                      {user?.name ?? "Administrator"}
                    </p>
                    <p className="text-xs text-gray-500 truncate">
                      {user?.email}
                    </p>
                    <span className="inline-block mt-1 text-[9px] font-bold uppercase tracking-wider text-primary-700 bg-primary-50 border border-primary-100 px-1.5 py-0.5 rounded">
                      Admin Role
                    </span>
                  </div>

                  <div className="p-1.5 space-y-0.5">
                    <Link
                      to="/"
                      onClick={() => setIsDropdownOpen(false)}
                      className="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-600 hover:bg-slate-50 rounded transition-colors"
                    >
                      <svg
                        className="w-4 h-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                        />
                      </svg>
                      Xem trang chủ
                    </Link>

                    <button
                      type="button"
                      onClick={() => {
                        setIsDropdownOpen(false);
                        handleLogout();
                      }}
                      className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-rose-50 rounded transition-colors text-left font-medium cursor-pointer"
                    >
                      <svg
                        className="w-4 h-4 text-red-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                        />
                      </svg>
                      Đăng xuất
                    </button>
                  </div>
                </div>
              </>
            )}
          </div>
        </header>

        {/* Main content */}
        <main className="flex-1 p-4 md:p-6 w-full">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AdminLayout;
