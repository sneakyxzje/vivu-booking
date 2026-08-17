import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import adminService from "@/services/adminService";
import { formatDateTime } from "@/utils/format";
import { statusClasses, statusLabel } from "@/utils/schedule";
import Pagination from "@/components/common/Pagination";
import type { AttendanceReportData } from "@/services/adminService";

/*
 * Kiểu của báo cáo khai một lần ở adminService và dùng lại ở đây.
 *
 * Trước đó hợp đồng này được viết hai lần, một ở service một ở trang này, rồi lệch nhau: trang
 * khai absence_logs còn máy chủ không trả về, TypeScript vẫn xanh và màn hình vỡ ngay khi mở.
 */
type ReportData = AttendanceReportData;

export default function AttendanceReport() {
  const [data, setData] = useState<ReportData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // Bộ lọc & Phân trang
  const [fromDate, setFromDate] = useState("");
  const [toDate, setToDate] = useState("");
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [activeTab, setActiveTab] = useState<"schedules" | "logs">("schedules");

  const fetchReport = (currentPage = page, currentPerPage = perPage) => {
    setLoading(true);
    adminService
      .getAttendanceReport({
        from_date: fromDate || undefined,
        to_date: toDate || undefined,
        search: searchTerm || undefined,
        status: statusFilter !== "all" ? statusFilter : undefined,
        page: currentPage,
        per_page: currentPerPage,
      })
      .then((result) => {
        if (!result) {
          setError("Không thể tải dữ liệu báo cáo.");
          return;
        }
        setData(result);
        setPage(currentPage);
      })
      .catch(() => setError("Không thể kết nối đến máy chủ."))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchReport(1, perPage);
  }, [fromDate, toDate, statusFilter, perPage]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchReport(1, perPage);
  };

  const handleResetFilters = () => {
    setFromDate("");
    setToDate("");
    setSearchTerm("");
    setStatusFilter("all");
    setPerPage(10);
    setPage(1);
  };

  if (loading && !data) {
    return (
      <div className="py-20 text-center space-y-3">
        <div className="w-10 h-10 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-base font-medium text-gray-500">Đang tải báo cáo điểm danh toàn hệ thống...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="max-w-xl mx-auto py-12">
        <div className="rounded-2xl bg-rose-50 border border-rose-200 p-6 text-center text-rose-700 space-y-3">
          <p className="font-semibold text-base">{error || "Không thể tải báo cáo."}</p>
          <button
            type="button"
            onClick={() => fetchReport(1, perPage)}
            className="px-4 py-2.5 bg-rose-600 text-white font-bold text-sm rounded-xl shadow-sm hover:bg-rose-700"
          >
            Tải lại trang
          </button>
        </div>
      </div>
    );
  }

  const { kpis, schedules, absence_logs } = data;

  return (
    <div className="space-y-5 animate-fade-in pb-12">
      {/* Header */}
      <div className="bg-white rounded-2xl p-4 sm:p-5 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-gray-900 font-jakarta">
            Báo cáo Điểm danh & Điều hành
          </h1>
          <p className="text-xs text-gray-500 mt-0.5">
            Thống kê tiến độ điểm danh, tỷ lệ có mặt và nhật ký vi phạm/vắng mặt trên toàn bộ các chuyến đi
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => fetchReport(page, perPage)}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors"
          >
            🔄 Cập nhật số liệu
          </button>
        </div>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        {/* Card 1: Tỷ lệ có mặt */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-gray-500 uppercase tracking-wider">
              Tỷ lệ có mặt trung bình
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 text-base">📈</span>
          </div>
          <p className="text-3xl font-extrabold text-emerald-600 font-jakarta">
            {kpis.overall_presence_rate}%
          </p>
          <p className="text-xs text-gray-400">
            Dựa trên {kpis.total_checkins} lượt điểm danh
          </p>
        </div>

        {/* Card 2: Tổng lượt điểm danh */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-gray-500 uppercase tracking-wider">
              Tổng lượt có mặt
            </span>
            <span className="p-1.5 rounded-lg bg-primary-50 text-primary-600 text-base">✅</span>
          </div>
          <p className="text-3xl font-extrabold text-primary-700 font-jakarta">
            {kpis.total_present}
          </p>
          <p className="text-xs text-gray-400">
            Đã điểm danh thành công
          </p>
        </div>

        {/* Card 3: Lượt vắng mặt */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-gray-500 uppercase tracking-wider">
              Lượt vắng mặt ghi nhận
            </span>
            <span className="p-1.5 rounded-lg bg-rose-50 text-rose-600 text-base">⚠️</span>
          </div>
          <p className="text-3xl font-extrabold text-rose-600 font-jakarta">
            {kpis.total_absent}
          </p>
          <p className="text-xs text-gray-400">
            Đã lưu kèm ghi chú lý do của HDV
          </p>
        </div>

        {/* Card 4: Cảnh báo thiếu ảnh */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-gray-500 uppercase tracking-wider">
              Chuyến thiếu ảnh check-in
            </span>
            <span className="p-1.5 rounded-lg bg-amber-50 text-amber-600 text-base">📸</span>
          </div>
          <p className="text-3xl font-extrabold text-amber-600 font-jakarta">
            {kpis.missing_photos_count}
          </p>
          <p className="text-xs text-gray-400">
            Chưa gửi ảnh check-in đoàn
          </p>
        </div>
      </div>

      {/* Main Container */}
      <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-5">
        {/* Nav Tabs & Advanced Filters */}
        <div className="flex flex-col gap-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
            {/* Tabs */}
            <div className="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl w-fit">
              <button
                type="button"
                onClick={() => setActiveTab("schedules")}
                className={`px-5 py-2.5 rounded-xl text-sm font-bold transition-all ${activeTab === "schedules"
                    ? "bg-white text-primary-700 shadow-sm"
                    : "text-gray-600 hover:text-gray-900"
                  }`}
              >
                Thống kê theo Chuyến ({schedules.total})
              </button>
              <button
                type="button"
                onClick={() => setActiveTab("logs")}
                className={`px-5 py-2.5 rounded-xl text-sm font-bold transition-all ${activeTab === "logs"
                    ? "bg-white text-primary-700 shadow-sm"
                    : "text-gray-600 hover:text-gray-900"
                  }`}
              >
                Nhật ký Vắng mặt ({absence_logs.length})
              </button>
            </div>
          </div>

          {/* Bộ Lọc Nâng Cao (Từ ngày -> Đến ngày, Trạng thái, Tìm kiếm) */}
          {activeTab === "schedules" && (
            <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-3 items-end bg-gray-50/70 p-4 rounded-2xl border border-gray-100">
              {/* Lọc từ ngày */}
              <div className="md:col-span-3">
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">
                  📅 Từ ngày
                </label>
                <input
                  type="date"
                  value={fromDate}
                  onChange={(e) => setFromDate(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white outline-none focus:border-primary-500 shadow-xs"
                />
              </div>

              {/* Lọc đến ngày */}
              <div className="md:col-span-3">
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">
                  📅 Đến ngày
                </label>
                <input
                  type="date"
                  value={toDate}
                  onChange={(e) => setToDate(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white outline-none focus:border-primary-500 shadow-xs"
                />
              </div>

              {/* Lọc trạng thái */}
              <div className="md:col-span-3">
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">
                  📌 Trạng thái chuyến
                </label>
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white outline-none focus:border-primary-500 shadow-xs"
                >
                  <option value="all">Tất cả trạng thái</option>
                  <option value="open">Mở bán</option>
                  <option value="confirmed">Đã xác nhận</option>
                  <option value="in_progress">Đang di chuyển</option>
                  <option value="completed">Hoàn tất</option>
                  <option value="cancelled">Đã hủy</option>
                </select>
              </div>

              {/* Ô tìm kiếm & Nút Lọc */}
              <div className="md:col-span-3 flex items-center gap-2">
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Tour hoặc HDV..."
                  className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white outline-none focus:border-primary-500 shadow-xs"
                />
                <button
                  type="submit"
                  className="px-4.5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm rounded-xl shadow-xs shrink-0 transition-colors"
                >
                  Lọc
                </button>
                {(fromDate || toDate || searchTerm || statusFilter !== "all") && (
                  <button
                    type="button"
                    onClick={handleResetFilters}
                    className="px-3 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold text-sm rounded-xl shrink-0 transition-colors"
                    title="Xóa bộ lọc"
                  >
                    ✕
                  </button>
                )}
              </div>
            </form>
          )}
        </div>

        {/* Tab 1: Thống kê theo Chuyến */}
        {activeTab === "schedules" && (
          <div className="space-y-4">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-gray-100 bg-gray-50/50 text-xs font-extrabold uppercase tracking-wider text-gray-600">
                    <th className="py-4 px-4">Chuyến / Tour</th>
                    <th className="py-4 px-4">Khởi hành</th>
                    <th className="py-4 px-4">HDV Phụ trách</th>
                    <th className="py-4 px-4">Tiến độ điểm danh</th>
                    <th className="py-4 px-4 text-center">Ảnh đoàn</th>
                    <th className="py-4 px-4 text-center">Trạng thái</th>
                    <th className="py-4 px-4 text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 text-base">
                  {schedules.data.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-12 text-center text-gray-500 text-sm">
                        Không tìm thấy chuyến đi nào trong khoảng thời gian / bộ lọc đã chọn.
                      </td>
                    </tr>
                  ) : (
                    schedules.data.map((sch) => (
                      <tr key={sch.id} className="hover:bg-gray-50/50 transition-colors">
                        <td className="py-4 px-4">
                          <p className="font-bold text-gray-900 text-base">{sch.tour_title}</p>
                          <p className="text-sm text-gray-500">
                            Mã chuyến #{sch.id} · {sch.number_of_days} ngày · {sch.booked_people} khách
                          </p>
                        </td>
                        <td className="py-4 px-4 whitespace-nowrap text-sm text-gray-700 font-medium">
                          {formatDateTime(sch.start_date)}
                        </td>
                        <td className="py-4 px-4 whitespace-nowrap text-sm">
                          {sch.guides.length > 0 ? (
                            <div className="space-y-1">
                              {sch.guides.map((guide) => (
                                <div key={guide.id}>
                                  <span className="font-bold text-gray-900">{guide.name}</span>
                                  <p className="text-xs text-gray-400">
                                    {guide.phone || "Không có SĐT"}
                                  </p>
                                </div>
                              ))}
                            </div>
                          ) : (
                            <span className="text-rose-600 font-semibold italic">Chưa phân công</span>
                          )}
                        </td>
                        <td className="py-4 px-4 min-w-[150px]">
                          <div className="space-y-1">
                            <div className="flex items-center justify-between text-sm">
                              <span className="font-bold text-emerald-600">{sch.presence_rate}%</span>
                              <span className="text-xs text-gray-400">
                                ({sch.present_count}/{sch.total_checkins})
                              </span>
                            </div>
                            <div className="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
                              <div
                                className="h-full bg-emerald-500 transition-all duration-300"
                                style={{ width: `${sch.presence_rate}%` }}
                              />
                            </div>
                          </div>
                        </td>
                        <td className="py-4 px-4 text-center whitespace-nowrap">
                          {sch.photo_count > 0 ? (
                            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                              📸 {sch.photo_count} ảnh
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200">
                              ⚠️ 0 ảnh
                            </span>
                          )}
                        </td>
                        <td className="py-4 px-4 text-center whitespace-nowrap">
                          <span
                            className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border ${statusClasses[sch.status] || "bg-gray-50 text-gray-600 border-gray-200"
                              }`}
                          >
                            {statusLabel[sch.status] || sch.status}
                          </span>
                        </td>
                        <td className="py-4 px-4 text-right whitespace-nowrap">
                          <Link
                            to={`/admin/tour-schedules/${sch.id}/attendance`}
                            className="inline-flex items-center gap-1 px-3.5 py-2 rounded-xl bg-primary-50 text-primary-700 text-xs font-bold hover:bg-primary-100 transition-colors"
                          >
                            Chi tiết →
                          </Link>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Phân Trang bằng Common Component Pagination */}
            <Pagination
              currentPage={schedules.current_page}
              lastPage={schedules.last_page}
              total={schedules.total}
              perPage={schedules.per_page}
              itemLabel="chuyến"
              onPageChange={(p) => fetchReport(p, perPage)}
              onPerPageChange={(newPerPage) => {
                setPerPage(newPerPage);
                fetchReport(1, newPerPage);
              }}
            />
          </div>
        )}

        {/* Tab 2: Nhật ký Khách vắng mặt */}
        {activeTab === "logs" && (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50/50 text-xs font-extrabold uppercase tracking-wider text-gray-600">
                  <th className="py-4 px-4">Hành khách</th>
                  <th className="py-4 px-4">Mã đơn</th>
                  <th className="py-4 px-4">Chặng vắng mặt</th>
                  <th className="py-4 px-4">HDV Ghi nhận</th>
                  <th className="py-4 px-4 text-right">Thời điểm điểm danh</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-base">
                {absence_logs.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="py-8 text-center text-gray-500 text-sm">
                      Không có trường hợp vắng mặt nào được ghi nhận.
                    </td>
                  </tr>
                ) : (
                  absence_logs.map((log) => (
                    <tr key={log.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="py-4 px-4">
                        {/* Điểm danh theo từng người, nên tên hành khách mới là chủ thể ở đây.
                            Người đứng đơn chỉ là đầu mối liên hệ. */}
                        <p className="font-bold text-gray-900 text-base">{log.passenger_name}</p>
                        <p className="text-sm text-gray-500">
                          Đơn của {log.customer_name} · 📞 {log.customer_phone}
                        </p>
                        <span className="inline-flex mt-1 px-2 py-0.5 rounded-md bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-bold">
                          {log.status_label}
                        </span>
                      </td>
                      <td className="py-4 px-4 whitespace-nowrap">
                        <span className="font-mono text-xs font-bold bg-gray-100 text-gray-700 px-2.5 py-1.5 rounded-md">
                          BK-{log.booking_id}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <p className="font-semibold text-gray-800 text-sm">
                          Ngày {log.day_number}: {log.checkpoint_name || log.itinerary_title}
                        </p>
                        {log.note && <p className="text-xs text-gray-500 mt-0.5">“{log.note}”</p>}
                      </td>
                      <td className="py-4 px-4 whitespace-nowrap text-sm font-medium text-gray-700">
                        {log.guide_name}
                      </td>
                      <td className="py-4 px-4 text-right whitespace-nowrap text-xs text-gray-500">
                        {log.checked_at ? formatDateTime(log.checked_at) : "Chưa ghi nhận"}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
