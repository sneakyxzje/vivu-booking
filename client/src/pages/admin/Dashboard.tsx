import { useEffect, useState } from "react";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
  PieChart,
  Pie
} from "recharts";
import {
  DollarSign,
  Briefcase,
  Users,
  Compass,
  Calendar
} from "lucide-react";
import adminService, { type AdminDashboardData } from "@/services/adminService";
import { formatDateTime, formatPrice } from "@/utils/format";

const DESTINATION_COLORS = ["#6366f1", "#06b6d4", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6"];

export default function Dashboard() {
  const [data, setData] = useState<AdminDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    adminService
      .getDashboard()
      .then((result) => setData(result))
      .catch(() => setError("Không thể tải dữ liệu tổng quan."))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64 text-gray-500">
        Đang tải dữ liệu tổng quan...
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="flex items-center justify-center h-64 text-red-500 text-sm">
        {error || "Không thể tải dữ liệu tổng quan."}
      </div>
    );
  }

  const bookingSummary = data.booking_summary;
  const monthlyData = data.monthly_performance;
  const destData = data.destinations.map((item, index) => ({
    ...item,
    color: DESTINATION_COLORS[index % DESTINATION_COLORS.length],
  }));
  const bookingsData = data.recent_bookings;

  const metrics = [
    {
      title: "Tổng doanh thu",
      value: formatPrice(bookingSummary.total_revenue),
      trend: `Tháng này: ${formatPrice(bookingSummary.revenue_this_month)}`,
      icon: DollarSign,
      color: "bg-indigo-50 text-indigo-600 border-indigo-100"
    },
    {
      title: "Số đơn Booking",
      value: `${bookingSummary.total_bookings} đơn đặt`,
      trend: `${bookingSummary.confirmed_bookings} đã xác nhận · ${bookingSummary.pending_bookings} đang chờ`,
      icon: Briefcase,
      color: "bg-cyan-50 text-cyan-600 border-cyan-100"
    },
    {
      title: "Khách hàng mới",
      value: `${bookingSummary.new_customers_this_month} thành viên`,
      trend: "Đăng ký mới trong tháng này",
      icon: Users,
      color: "bg-emerald-50 text-emerald-600 border-emerald-100"
    },
    {
      title: "Tỷ lệ lấp đầy",
      value: `${bookingSummary.occupancy_rate}%`,
      trend: "Số chỗ đã đặt trên tổng số chỗ mở bán",
      icon: Compass,
      color: "bg-amber-50 text-amber-600 border-amber-100"
    }
  ];

  return (
    <div className="space-y-6">
      {/* HEADER INFO */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
            Tổng quan hệ thống
          </h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Phân tích số liệu doanh thu, hiệu suất bán tour du lịch và phân tích xu hướng
          </p>
        </div>
        <div className="flex items-center gap-2 bg-white px-3 py-1.5 border border-gray-200 rounded-md shadow-xs self-start sm:self-center">
          <Calendar className="w-4 h-4 text-gray-400" />
          <span className="text-xs font-semibold text-gray-600 font-mono">Năm {new Date().getFullYear()}</span>
        </div>
      </div>

      {/* KPI METRICS GRID */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {metrics.map((item, index) => {
          const Icon = item.icon;
          return (
            <div
              key={index}
              className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5"
            >
              <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{item.title}</p>
                <div className={`p-2 rounded ${item.color} border shrink-0`}>
                  <Icon className="w-5 h-5" />
                </div>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 mt-3">{item.value}</h3>
              <p className="mt-2.5 text-xs font-medium text-gray-400">{item.trend}</p>
            </div>
          );
        })}
      </div>

      {/* CHARTS LAYER */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Revenue & Bookings Line Chart */}
        <div className="lg:col-span-2 bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex flex-col">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h3 className="text-base font-bold text-gray-900 tracking-tight">Doanh thu & Booking theo tháng</h3>
              <p className="text-xs text-gray-400 mt-0.5">Biểu đồ biểu diễn doanh thu (triệu đồng) và lượng khách đặt</p>
            </div>
            <div className="flex gap-4 text-xs font-bold text-gray-500">
              <span className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 bg-indigo-600 rounded-full"></span> Doanh thu
              </span>
              <span className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 bg-cyan-400 rounded-full"></span> Bookings
              </span>
            </div>
          </div>

          <div className="h-72 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={monthlyData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#4f46e5" stopOpacity={0.15}/>
                    <stop offset="95%" stopColor="#4f46e5" stopOpacity={0.01}/>
                  </linearGradient>
                  <linearGradient id="colorBookings" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#06b6d4" stopOpacity={0.15}/>
                    <stop offset="95%" stopColor="#06b6d4" stopOpacity={0.01}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                <XAxis dataKey="name" stroke="#94a3b8" fontSize={10} tickLine={false} axisLine={false} />
                <YAxis stroke="#94a3b8" fontSize={10} tickLine={false} axisLine={false} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "#0f172a",
                    border: "none",
                    borderRadius: "6px",
                    color: "#fff",
                    fontFamily: "monospace",
                    fontSize: "11px"
                  }}
                  itemStyle={{ color: "#fff" }}
                />
                <Area type="monotone" dataKey="revenue" stroke="#4f46e5" strokeWidth={2.5} fillOpacity={1} fill="url(#colorRevenue)" name="Doanh thu (tr)" />
                <Area type="monotone" dataKey="bookings" stroke="#06b6d4" strokeWidth={2.5} fillOpacity={1} fill="url(#colorBookings)" name="Bookings (lượt)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Destination Share Pie Chart */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex flex-col">
          <div className="mb-6">
            <h3 className="text-base font-bold text-gray-900 tracking-tight">Phân bố thị phần điểm đến</h3>
            <p className="text-xs text-gray-400 mt-0.5">Số lượng khách hàng lựa chọn đặt tour theo các khu vực</p>
          </div>

          <div className="h-56 relative flex items-center justify-center">
            {destData.length === 0 && (
              <p className="absolute text-sm text-gray-400">Chưa có booking để thống kê.</p>
            )}
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={destData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={80}
                  paddingAngle={3}
                  dataKey="value"
                >
                  {destData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    backgroundColor: "#0f172a",
                    border: "none",
                    borderRadius: "6px",
                    color: "#fff",
                    fontSize: "11px"
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
          </div>

          {/* Custom Legends list */}
          <div className="grid grid-cols-2 gap-2 text-xs font-semibold text-gray-600 mt-2">
            {destData.map((item, index) => (
              <div key={index} className="flex items-center gap-2">
                <span className="w-2.5 h-2.5 rounded shrink-0" style={{ backgroundColor: item.color }} />
                <span className="truncate">{item.name}</span>
                <span className="text-gray-400 font-mono">({item.value} khách)</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* RECENT BOOKINGS TABLE */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-xs">
        <div className="p-5 border-b border-gray-100">
          <h3 className="text-base font-bold text-gray-900 tracking-tight">Các giao dịch Booking mới nhất</h3>
          <p className="text-xs text-gray-400 mt-0.5">Theo dõi lịch trình thanh toán và trạng thái phê duyệt của khách hàng</p>
        </div>

        <div className="overflow-x-visible">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase border-b border-gray-200 tracking-wider">
                <th className="py-3.5 px-6 w-20 text-center">ID</th>
                <th className="py-3.5 px-6">Họ tên khách hàng</th>
                <th className="py-3.5 px-6 w-96">Tên chương trình Tour</th>
                <th className="py-3.5 px-6 text-right">Tổng thanh toán</th>
                <th className="py-3.5 px-6 text-center">Thời gian</th>
                <th className="py-3.5 px-6 text-center">Trạng thái</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 text-sm text-gray-700">
              {bookingsData.length === 0 && (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-sm text-gray-400">
                    Chưa có giao dịch nào.
                  </td>
                </tr>
              )}
              {bookingsData.map((bk) => (
                <tr key={bk.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="py-3.5 px-6 text-center text-gray-400 font-mono">#{bk.id}</td>
                  <td className="py-3.5 px-6 font-semibold text-gray-950">{bk.customer}</td>
                  <td className="py-3.5 px-6 text-gray-600">{bk.tour}</td>
                  <td className="py-3.5 px-6 text-right font-bold text-gray-900 font-mono">
                    {bk.price.toLocaleString()} đ
                  </td>
                  <td className="py-3.5 px-6 text-center text-gray-400 text-xs font-mono">{formatDateTime(bk.date)}</td>
                  <td className="py-3.5 px-6 text-center">
                    <span
                      className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold border ${
                        bk.status === "confirmed"
                          ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                          : bk.status === "cancelled"
                            ? "bg-rose-50 text-rose-700 border-rose-200"
                            : "bg-amber-50 text-amber-700 border-amber-200"
                      }`}
                    >
                      {bk.status === "confirmed" && "Đã duyệt"}
                      {bk.status === "cancelled" && "Đã hủy"}
                      {bk.status === "pending" && "Chờ xác nhận"}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}