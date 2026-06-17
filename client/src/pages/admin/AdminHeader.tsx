import { useState } from "react";

interface AdminHeaderProps {
  toggleSidebar: () => void;
}

export default function AdminHeader({
  toggleSidebar,
}: AdminHeaderProps) {
  const [profileOpen, setProfileOpen] = useState(false);
  const [notifyOpen, setNotifyOpen] = useState(false);
  const [messageOpen, setMessageOpen] = useState(false);

  return (
    <header className="h-16 bg-white border-b shadow-sm flex items-center justify-between px-6">
      {/* Left */}
      <div className="flex items-center gap-4">
        <button
          onClick={toggleSidebar}
          className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
        >
          ☰ Menu
        </button>

        <div className="hidden md:block">
          <input
            type="text"
            placeholder="Search..."
            className="w-80 border rounded-lg px-4 py-2 outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      {/* Right */}
      <div className="flex items-center gap-4">
        {/* Notification */}
        <div className="relative">
          <button
            onClick={() => setNotifyOpen(!notifyOpen)}
            className="relative p-2 rounded-lg hover:bg-gray-100"
          >
            🔔
            <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
          </button>

          {notifyOpen && (
            <div className="absolute right-0 mt-2 w-80 bg-white shadow-lg rounded-xl border z-50">
              <div className="p-4 border-b font-semibold">
                Thông báo
              </div>

              <div className="p-4 hover:bg-gray-50">
                Khách hàng vừa đặt tour Đà Nẵng
              </div>

              <div className="p-4 hover:bg-gray-50">
                Có đánh giá mới
              </div>
            </div>
          )}
        </div>

        {/* Message */}
        <div className="relative">
          <button
            onClick={() => setMessageOpen(!messageOpen)}
            className="relative p-2 rounded-lg hover:bg-gray-100"
          >
            💬
            <span className="absolute top-1 right-1 w-2 h-2 bg-blue-500 rounded-full"></span>
          </button>

          {messageOpen && (
            <div className="absolute right-0 mt-2 w-80 bg-white shadow-lg rounded-xl border z-50">
              <div className="p-4 border-b font-semibold">
                Tin nhắn
              </div>

              <div className="p-4 hover:bg-gray-50">
                Jacob: Tour này còn chỗ không?
              </div>

              <div className="p-4 hover:bg-gray-50">
                Admin: Đã xác nhận thanh toán
              </div>
            </div>
          )}
        </div>

        {/* Profile */}
        <div className="relative">
          <button
            onClick={() => setProfileOpen(!profileOpen)}
            className="flex items-center gap-3 hover:bg-gray-100 px-3 py-2 rounded-lg"
          >
            <img
              src="https://i.pravatar.cc/40"
              alt=""
              className="w-10 h-10 rounded-full"
            />

            <div className="text-left hidden md:block">
              <h4 className="font-semibold text-sm">
                Adam Joe
              </h4>

              <p className="text-xs text-gray-500">
                Admin
              </p>
            </div>
          </button>

          {profileOpen && (
            <div className="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-lg border z-50">
              <div className="p-4 border-b">
                <h4 className="font-semibold">
                  Adam Joe
                </h4>

                <p className="text-sm text-gray-500">
                  admin@gmail.com
                </p>
              </div>

              <a
                href="#"
                className="block px-4 py-3 hover:bg-gray-50"
              >
                Hồ sơ
              </a>

              <a
                href="#"
                className="block px-4 py-3 hover:bg-gray-50"
              >
                Cài đặt
              </a>

              <hr />

              <a
                href="#"
                className="block px-4 py-3 text-red-600 hover:bg-red-50"
              >
                Đăng xuất
              </a>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}