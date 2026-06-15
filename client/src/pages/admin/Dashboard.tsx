import { useState } from "react";
import AdminHeader from "@/pages/admin/AdminHeader";

export default function Dashboard() {
  const [sidebarOpen, setSidebarOpen] = useState(true);

  const [dashboardOpen, setDashboardOpen] = useState(true);
  const [tourOpen, setTourOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-gray-100">
      {/* Sidebar */}
      {sidebarOpen && (
        <aside className="w-72 bg-slate-900 text-white shadow-lg">
          <div className="p-6 border-b border-slate-700">
            <h2 className="text-2xl font-bold">
              Vivu Admin
            </h2>
          </div>

          <nav className="p-4">
            {/* Dashboard */}
            <div className="mb-2">
              <button
                onClick={() => setDashboardOpen(!dashboardOpen)}
                className="w-full flex justify-between items-center p-3 rounded-lg hover:bg-slate-800"
              >
                <span>Dashboard</span>
                <span>{dashboardOpen ? "−" : "+"}</span>
              </button>

              {dashboardOpen && (
                <div className="ml-4 mt-2 space-y-1">
                  <a
                    href="/admin/dashboard"
                    className="block p-2 rounded hover:bg-slate-800"
                  >
                    Tổng quan
                  </a>

                  <a
                    href="/admin/statistics"
                    className="block p-2 rounded hover:bg-slate-800"
                  >
                    Thống kê
                  </a>
                </div>
              )}
            </div>

            {/* Tours */}
            <div className="mb-2">
              <button
                onClick={() => setTourOpen(!tourOpen)}
                className="w-full flex justify-between items-center p-3 rounded-lg hover:bg-slate-800"
              >
                <span>Quản lý Tour</span>
                <span>{tourOpen ? "−" : "+"}</span>
              </button>

              {tourOpen && (
                <div className="ml-4 mt-2 space-y-1">
                  <a
                    href="/admin/tours"
                    className="block p-2 rounded hover:bg-slate-800"
                  >
                    Danh sách tour
                  </a>

                  <a
                    href="/admin/tours/create"
                    className="block p-2 rounded hover:bg-slate-800"
                  >
                    Thêm tour
                  </a>
                </div>
              )}
            </div>

            {/* Users */}
            <div className="mb-2">
              <button
                onClick={() => setUserOpen(!userOpen)}
                className="w-full flex justify-between items-center p-3 rounded-lg hover:bg-slate-800"
              >
                <span>Người dùng</span>
                <span>{userOpen ? "−" : "+"}</span>
              </button>

              {userOpen && (
                <div className="ml-4 mt-2 space-y-1">
                  <a
                    href="/admin/users"
                    className="block p-2 rounded hover:bg-slate-800"
                  >
                    Danh sách người dùng
                  </a>

                  <a
                    href="/admin/hosts"
                    className="block p-2 rounded hover:bg-slate-800"
                  >
                    Host
                  </a>
                </div>
              )}
            </div>

            <a
              href="/admin/bookings"
              className="block p-3 rounded-lg hover:bg-slate-800"
            >
              Đơn đặt tour
            </a>

            <a
              href="/admin/reviews"
              className="block p-3 rounded-lg hover:bg-slate-800"
            >
              Đánh giá
            </a>

            <a
              href="/admin/payments"
              className="block p-3 rounded-lg hover:bg-slate-800"
            >
              Thanh toán
            </a>

            <a
              href="/admin/settings"
              className="block p-3 rounded-lg hover:bg-slate-800"
            >
              Cài đặt
            </a>
          </nav>
        </aside>
      )}

      {/* Content */}
      <div className="flex-1 flex flex-col">
        <AdminHeader
          toggleSidebar={() =>
            setSidebarOpen(!sidebarOpen)
          }
        />

        <main className="p-8">
          <div className="bg-white rounded-xl shadow p-6">
            <h1 className="text-3xl font-bold mb-3">
              Dashboard Admin
            </h1>

            <p className="text-gray-500">
              Chào mừng đến với trang quản trị Vivu Booking
            </p>
          </div>
        </main>
      </div>
    </div>
  );
}