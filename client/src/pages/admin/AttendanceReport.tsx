import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Select as AntSelect,
  Table as AntTable,
  Typography as AntTypography,
} from "antd";
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import adminService from "@/services/adminService";
import { formatDateTime } from "@/utils/format";
import { statusClasses, statusLabel } from "@/utils/schedule";
import Pagination from "@/components/admin/AdminPagination";
import { DateRangePicker } from "@/components/admin/AdminDateRangePicker";
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
          <AntButton htmlType="button" onClick={() => fetchReport(1, perPage)} type="primary" danger>Tải lại trang
          </AntButton>
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
          <AntTypography.Title level={3} >Báo cáo Điểm danh & Điều hành
          </AntTypography.Title>
          <p className="text-xs text-gray-500 mt-0.5">
            Thống kê tiến độ điểm danh, tỷ lệ có mặt và nhật ký vi phạm/vắng mặt trên toàn bộ các chuyến đi
          </p>
        </div>

        <UIFlex    align="center"  gap={8}><AntButton htmlType="button" onClick={() => fetchReport(page, perPage)}>Cập nhật số liệu
          </AntButton></UIFlex>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        {/* Card 1: Tỷ lệ có mặt */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <span className="block text-xs font-bold text-gray-500 uppercase tracking-wider">
            Tỷ lệ có mặt trung bình
          </span>
          <p className="text-3xl font-extrabold text-emerald-600 font-jakarta">
            {kpis.overall_presence_rate}%
          </p>
          <p className="text-xs text-gray-400">
            Dựa trên {kpis.total_checkins} lượt điểm danh
          </p>
        </div>

        {/* Card 2: Tổng lượt điểm danh */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <span className="block text-xs font-bold text-gray-500 uppercase tracking-wider">
            Tổng lượt có mặt
          </span>
          <p className="text-3xl font-extrabold text-primary-700 font-jakarta">
            {kpis.total_present}
          </p>
          <p className="text-xs text-gray-400">
            Đã điểm danh thành công
          </p>
        </div>

        {/* Card 3: Lượt vắng mặt */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <span className="block text-xs font-bold text-gray-500 uppercase tracking-wider">
            Lượt vắng mặt ghi nhận
          </span>
          <p className="text-3xl font-extrabold text-rose-600 font-jakarta">
            {kpis.total_absent}
          </p>
          <p className="text-xs text-gray-400">
            Đã lưu kèm ghi chú lý do của HDV
          </p>
        </div>

        {/* Card 4: Cảnh báo thiếu ảnh */}
        <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm space-y-1 relative overflow-hidden">
          <span className="block text-xs font-bold text-gray-500 uppercase tracking-wider">
            Chuyến thiếu ảnh check-in
          </span>
          <p className="text-3xl font-extrabold text-amber-600 font-jakarta">
            {kpis.missing_photos_count}
          </p>
          <p className="text-xs text-gray-400">
            Chưa gửi ảnh check-in đoàn
          </p>
        </div>
      </div>

      {/* Main Container */}
      <UICard  ><UIFlex vertical gap="middle">{/* Nav Tabs & Advanced Filters */}<UIFlex  vertical    gap={16}><div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
            {/* Tabs */}
            <div className="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl w-fit">
              <AntButton htmlType="button" onClick={() => setActiveTab("schedules")}>Thống kê theo Chuyến ({schedules.total})
              </AntButton>
              <AntButton htmlType="button" onClick={() => setActiveTab("logs")}>Nhật ký Vắng mặt ({absence_logs.length})
              </AntButton>
            </div>
          </div>{/* Bộ Lọc Nâng Cao (Từ ngày -> Đến ngày, Trạng thái, Tìm kiếm) */}{activeTab === "schedules" && (
            <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-3 items-end bg-gray-50/70 p-4 rounded-2xl border border-gray-100">
              {/*
                Không chọn giờ ở đây: lọc theo NGÀY KHỞI HÀNH của chuyến, và một chuyến khởi hành
                vào ngày nào là chuyện của ngày đó. Máy chủ cũng dùng `whereDate`, nên cho chỉnh
                giờ sẽ là một ô không có tác dụng gì.
              */}
              <div className="md:col-span-6">
                <DateRangePicker
                  label="Khoảng ngày khởi hành"
                  value={{ from: fromDate, to: toDate }}
                  onChange={(khoang) => {
                    setFromDate(khoang.from);
                    setToDate(khoang.to);
                  }}
                />
              </div>

              {/* Lọc trạng thái */}
              <div className="md:col-span-3">
                <label className="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">
                  Trạng thái chuyến
                </label>
                <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((statusFilter) ?? "")} onChange={(e) => setStatusFilter(e)} style={{ width: "100%" }} options={[{ value: String("all"), label: "Tất cả trạng thái", disabled: false },{ value: String("open"), label: "Mở bán", disabled: false },{ value: String("confirmed"), label: "Đã xác nhận", disabled: false },{ value: String("in_progress"), label: "Đang di chuyển", disabled: false },{ value: String("completed"), label: "Hoàn tất", disabled: false },{ value: String("cancelled"), label: "Đã hủy", disabled: false }].flat().filter((option) => !!option)} />
              </div>

              {/* Ô tìm kiếm & Nút Lọc */}
              <div className="md:col-span-3 flex items-center gap-2">
                <AntInput type="text" value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} placeholder="Tour hoặc HDV..." style={{ width: "100%" }} />
                <AntButton htmlType="submit" type="primary">Lọc
                </AntButton>
                {(fromDate || toDate || searchTerm || statusFilter !== "all") && (
                  <AntButton htmlType="button" onClick={handleResetFilters}>Bỏ lọc
                  </AntButton>
                )}
              </div>
            </form>
          )}</UIFlex>{/* Tab 1: Thống kê theo Chuyến */}{activeTab === "schedules" && (
          <UIFlex vertical gap={16} ><div className="overflow-x-auto">
              <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={schedules.data.map((sch) => (
                      {key: sch.id, cells: [<>
                          <p className="font-bold text-gray-900 text-base">{sch.tour_title}</p>
                          <p className="text-sm text-gray-500">
                            Mã chuyến #{sch.id} · {sch.number_of_days} ngày · {sch.booked_people} khách
                          </p>
                        </>,<>
                          {formatDateTime(sch.start_date)}
                        </>,<>
                          {sch.guides.length > 0 ? (
                            <UIFlex vertical gap={4} >{sch.guides.map((guide) => (
                                <div key={guide.id}>
                                  <span className="font-bold text-gray-900">{guide.name}</span>
                                  <p className="text-xs text-gray-400">
                                    {guide.phone || "Không có SĐT"}
                                  </p>
                                </div>
                              ))}</UIFlex>
                          ) : (
                            <span className="text-rose-600 font-semibold italic">Chưa phân công</span>
                          )}
                        </>,<>
                          <UIFlex vertical gap={4} ><div className="flex items-center justify-between text-sm">
                              <span className="font-bold text-emerald-600">{sch.presence_rate}%</span>
                              <span className="text-xs text-gray-400">
                                ({sch.present_count}/{sch.total_checkins})
                              </span>
                            </div><div className="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
                              <div
                                className="h-full bg-emerald-500 transition-all duration-300"
                                style={{ width: `${sch.presence_rate}%` }}
                              />
                            </div></UIFlex>
                        </>,<>
                          {sch.photo_count > 0 ? (
                            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                              {sch.photo_count} ảnh
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200">
                              Chưa có ảnh
                            </span>
                          )}
                        </>,<>
                          <span
                            className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border ${statusClasses[sch.status] || "bg-gray-50 text-gray-600 border-gray-200"
                              }`}
                          >
                            {statusLabel[sch.status] || sch.status}
                          </span>
                        </>,<>
                          <Link
                            to={`/admin/tour-schedules/${sch.id}/attendance`}
                            className="inline-flex items-center gap-1 px-3.5 py-2 rounded-xl bg-primary-50 text-primary-700 text-xs font-bold hover:bg-primary-100 transition-colors"
                          >
                            Chi tiết
                          </Link>
                        </>], rowProps: {}}
                    ))}
    columns={[{ key: "0", title: <>Chuyến / Tour</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Khởi hành</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>HDV Phụ trách</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Tiến độ điểm danh</>, align: "left", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Ảnh đoàn</>, align: "center", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Trạng thái</>, align: "center", render: (_value, record) => record.cells[5] },{ key: "6", title: <>Thao tác</>, align: "right", render: (_value, record) => record.cells[6] }]}
    onRow={(record) => record.rowProps}
    locale={{ emptyText: <>
                        Không tìm thấy chuyến đi nào trong khoảng thời gian / bộ lọc đã chọn.
                      </> }} />
            </div>{/* Phân Trang bằng Common Component Pagination */}<Pagination
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
            /></UIFlex>
        )}{/* Tab 2: Nhật ký Khách vắng mặt */}{activeTab === "logs" && (
          <div className="overflow-x-auto">
            <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={absence_logs.map((log) => (
                    {key: log.id, cells: [<>
                        {/* Điểm danh theo từng người, nên tên hành khách mới là chủ thể ở đây.
                            Người đứng đơn chỉ là đầu mối liên hệ. */}
                        <p className="font-bold text-gray-900 text-base">{log.passenger_name}</p>
                        <p className="text-sm text-gray-500">
                          Đơn của {log.customer_name} · {log.customer_phone}
                        </p>
                        <span className="inline-flex mt-1 px-2 py-0.5 rounded-md bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-bold">
                          {log.status_label}
                        </span>
                      </>,<>
                        <span className="font-mono text-xs font-bold bg-gray-100 text-gray-700 px-2.5 py-1.5 rounded-md">
                          BK-{log.booking_id}
                        </span>
                      </>,<>
                        <p className="font-semibold text-gray-800 text-sm">
                          Ngày {log.day_number}: {log.checkpoint_name || log.itinerary_title}
                        </p>
                        {log.note && <p className="text-xs text-gray-500 mt-0.5">“{log.note}”</p>}
                      </>,<>
                        {log.guide_name}
                      </>,<>
                        {log.checked_at ? formatDateTime(log.checked_at) : "Chưa ghi nhận"}
                      </>], rowProps: {}}
                  ))}
    columns={[{ key: "0", title: <>Hành khách</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Mã đơn</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Chặng vắng mặt</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>HDV Ghi nhận</>, align: "left", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Thời điểm điểm danh</>, align: "right", render: (_value, record) => record.cells[4] }]}
    onRow={(record) => record.rowProps}
    locale={{ emptyText: <>
                      Không có trường hợp vắng mặt nào được ghi nhận.
                    </> }} />
          </div>
        )}</UIFlex></UICard>
    </div>
  );
}
