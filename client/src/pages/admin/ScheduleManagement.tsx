import { useEffect, useState, useMemo } from "react";
import { Link } from "react-router-dom";
import {
  CalendarDays,
  Clock,
  UserRound,
  Users,
  Search,
  Filter,
  AlertTriangle,
} from "lucide-react";
import adminService from "@/services/adminService";
import type { Tour, Guide, ExtendedSchedule } from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime, getEndDate } from "@/utils/format";
import { statusLabel, statusClasses } from "@/utils/schedule";

export default function ScheduleManagement() {
  const [tours, setTours] = useState<Tour[]>([]);
  const [guides, setGuides] = useState<Guide[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [currentPage, setCurrentPage] = useState(1);
  const ITEMS_PER_PAGE = 10;
  
  // State phân công Hướng dẫn viên
  const [assigningScheduleId, setAssigningScheduleId] = useState<number | null>(null);
  const [pendingGuideIds, setPendingGuideIds] = useState<Record<number, string>>({});

  // State Hủy chuyến
  const [cancellingScheduleId, setCancellingScheduleId] = useState<number | null>(null);
  const [cancelReasonInput, setCancelReasonInput] = useState("");
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);

  const [toast, setToast] = useState({
    message: "",
    type: "success" as "success" | "error" | "info",
    isOpen: false,
  });

  const loadData = async () => {
    setLoading(true);
    try {
      const [toursData, guidesData] = await Promise.all([
        adminService.getTours(),
        adminService.getGuides(),
      ]);
      setTours(toursData);
      setGuides(guidesData?.data.filter((g) => g.status === "active") ?? []);
    } catch (err) {
      console.error("Failed to load schedules data: ", err);
      setToast({
        message: "Không thể tải dữ liệu quản lý chuyến.",
        type: "error",
        isOpen: true,
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchQuery, statusFilter]);

  // Làm phẳng danh sách chuyến đi từ danh sách Tour
  const allSchedules = useMemo<ExtendedSchedule[]>(() => {
    return tours.flatMap((tour) =>
      (tour.schedules || []).map((schedule) => ({
        ...schedule,
        tour_title: tour.title,
        tour_id: tour.id,
        number_of_days: tour.number_of_days,
      }))
    );
  }, [tours]);

  // Bộ lọc tìm kiếm
  const filteredSchedules = useMemo(() => {
    return allSchedules.filter((schedule) => {
      const matchesSearch =
        schedule.tour_title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        String(schedule.id).includes(searchQuery);
      
      const status = schedule.status || "open";
      const matchesStatus =
        statusFilter === "all" ||
        status === statusFilter ||
        (statusFilter === "open" && status === "active") ||
        (statusFilter === "closed" && status === "full");

      return matchesSearch && matchesStatus;
    });
  }, [allSchedules, searchQuery, statusFilter]);

  const totalItems = filteredSchedules.length;
  const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);

  const paginatedSchedules = useMemo(() => {
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    return filteredSchedules.slice(startIndex, startIndex + ITEMS_PER_PAGE);
  }, [filteredSchedules, currentPage]);

  // Thống kê KPIs
  const stats = useMemo(() => {
    const total = allSchedules.length;
    const open = allSchedules.filter((s) => s.status === "open" || s.status === "active").length;
    const confirmed = allSchedules.filter((s) => s.status === "confirmed").length;
    const running = allSchedules.filter((s) => s.status === "in_progress").length;
    const cancelled = allSchedules.filter((s) => s.status === "cancelled").length;

    return { total, open, confirmed, running, cancelled };
  }, [allSchedules]);

  // Phân công hướng dẫn viên cho chuyến khởi hành
  const assignGuide = async (scheduleId: number, guideId: number | null) => {
    setAssigningScheduleId(scheduleId);
    try {
      await adminService.assignGuideToSchedule(scheduleId, guideId);
      const guide = guides.find((item) => item.id === guideId) ?? null;

      setPendingGuideIds((current) => ({
        ...current,
        [scheduleId]: guideId === null ? "" : String(guideId),
      }));

      setTours((currentTours) =>
        currentTours.map((t) => ({
          ...t,
          schedules: t.schedules?.map((item) =>
            item.id === scheduleId ? { ...item, guide_id: guideId, guide } : item
          ),
        }))
      );

      setToast({
        message:
          guideId === null
            ? "Đã bỏ phân công hướng dẫn viên."
            : "Phân công hướng dẫn viên thành công.",
        type: "success",
        isOpen: true,
      });
    } catch (error: unknown) {
      const message =
        (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
        "Không thể phân công hướng dẫn viên.";
      setToast({ message, type: "error", isOpen: true });
    } finally {
      setAssigningScheduleId(null);
    }
  };

  // Cập nhật trạng thái thủ công (Vòng đời)
  const handleUpdateStatus = async (scheduleId: number, nextStatus: string, reason?: string) => {
    try {
      console.log(`Updating schedule ${scheduleId} to status: ${nextStatus}`);

      setTours((currentTours) =>
        currentTours.map((t) => ({
          ...t,
          schedules: t.schedules?.map((item) => {
            if (item.id === scheduleId) {
              const updated = {
                ...item,
                status: nextStatus as any,
              };
              if (nextStatus === "cancelled") {
                updated.cancelled_reason = reason || "Điều hành hủy chuyến";
              }
              return updated;
            }
            return item;
          }),
        }))
      );

      setToast({
        message: `Đã cập nhật trạng thái chuyến khởi hành thành "${statusLabel[nextStatus]}".`,
        type: "success",
        isOpen: true,
      });
    } catch {
      setToast({
        message: "Không thể cập nhật trạng thái chuyến khởi hành.",
        type: "error",
        isOpen: true,
      });
    }
  };

  const openCancelDialog = (scheduleId: number) => {
    setCancellingScheduleId(scheduleId);
    setCancelReasonInput("");
    setIsCancelModalOpen(true);
  };

  const confirmCancelSchedule = () => {
    if (!cancellingScheduleId) return;
    handleUpdateStatus(cancellingScheduleId, "cancelled", cancelReasonInput || "Lý do bất khả kháng");
    setIsCancelModalOpen(false);
    setCancellingScheduleId(null);
  };

  return (
    <div className="space-y-6">
      {/* HEADER */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
            Quản lý Chuyến khởi hành
          </h1>
          <p className="text-sm text-gray-500">
            Quản lý chi tiết vòng đời chuyến đi, theo dõi thời hạn đăng ký, chốt chuyến chạy và hủy chuyến.
          </p>
        </div>
      </div>

      {/* FILTER & SEARCH */}
      <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <div className="relative w-full sm:max-w-xs">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="Tìm theo ID, tên tour..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-white placeholder-gray-400 outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-100"
          />
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <Filter className="h-4 w-4 text-gray-400" />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="flex-1 sm:flex-initial bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-primary-500"
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="open">Đang mở bán</option>
            <option value="closed">Đã đóng bán</option>
            <option value="confirmed">Đã chốt chạy</option>
            <option value="in_progress">Đang di chuyển</option>
            <option value="completed">Đã hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
        </div>
      </div>

      {/* SCHEDULES TABLE */}
      {loading ? (
        <div className="bg-white rounded-2xl border border-gray-100 p-6 space-y-4">
          <div className="h-8 bg-gray-100 rounded-lg animate-pulse" />
          {[1, 2, 3, 4, 5].map((n) => (
            <div key={n} className="h-14 bg-gray-50 rounded-lg animate-pulse" />
          ))}
        </div>
      ) : filteredSchedules.length ? (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase tracking-wider">
                  <th className="py-4 px-5">Mã chuyến</th>
                  <th className="py-4 px-5">Tour du lịch</th>
                  <th className="py-4 px-5">Thời gian khởi hành</th>
                  <th className="py-4 px-5">Hạn đặt (Deadline)</th>
                  <th className="py-4 px-5">Số khách (Min/Max)</th>
                  <th className="py-4 px-5">Hướng dẫn viên</th>
                  <th className="py-4 px-5">Trạng thái</th>
                  <th className="py-4 px-5 text-right">Vận hành & Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {paginatedSchedules.map((schedule) => {
                  const status = schedule.status || "open";
                  const deadline = schedule.booking_deadline;
                  const minPeople = schedule.min_people || 5;
                  const isOverdue = deadline ? new Date(deadline) < new Date() : false;

                  return (
                    <tr key={schedule.id} className="hover:bg-slate-50/50 transition-colors text-sm text-gray-700">
                      {/* Mã chuyến */}
                      <td className="py-4 px-5 font-bold text-primary-700 font-mono">
                        #{schedule.id}
                      </td>

                      {/* Tour du lịch */}
                      <td className="py-4 px-5 max-w-xs">
                        <Link
                          to={`/admin/tours/${schedule.tour_id}`}
                          className="font-bold text-gray-900 hover:text-primary-650 transition-colors line-clamp-2"
                        >
                          {schedule.tour_title}
                        </Link>
                      </td>

                      {/* Thời gian */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        <div className="flex items-center gap-1.5">
                          <CalendarDays className="h-3.5 w-3.5 text-gray-400" />
                          <div>
                            <p className="font-semibold text-gray-955">
                              {formatDateTime(schedule.start_date)}
                            </p>
                            <p className="text-xs text-gray-400 mt-0.5">
                              Đến: {getEndDate(schedule.start_date, schedule.number_of_days)}
                            </p>
                          </div>
                        </div>
                      </td>

                      {/* Hạn chốt đặt */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        {deadline ? (
                          <div className="flex items-center gap-1.5">
                            <Clock className={`h-3.5 w-3.5 ${isOverdue && (status === "open" || status === "active") ? "text-amber-500 animate-pulse" : "text-gray-400"}`} />
                            <div>
                              <p className={`font-semibold ${isOverdue && (status === "open" || status === "active") ? "text-amber-600" : "text-gray-955"}`}>
                                {formatDateTime(deadline)}
                              </p>
                              {isOverdue && (status === "open" || status === "active") && (
                                <span className="inline-block text-[10px] bg-amber-50 text-amber-700 px-1 py-0.5 rounded font-bold uppercase tracking-wider mt-0.5">Quá hạn</span>
                              )}
                            </div>
                          </div>
                        ) : (
                          <span className="text-gray-400">Không giới hạn</span>
                        )}
                      </td>

                      {/* Số khách */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        <div className="flex items-center gap-1.5">
                          <Users className="h-3.5 w-3.5 text-gray-400" />
                          <div>
                            <p className="font-bold text-gray-900">
                              {schedule.booked_people} / {schedule.max_people} khách
                            </p>
                            <p className="text-xs text-gray-400 mt-0.5">
                              Tối thiểu: {minPeople} khách
                            </p>
                          </div>
                        </div>
                      </td>

                      {/* Hướng dẫn viên */}
                      <td className="py-4 px-5">
                        <div className="flex items-center gap-1.5 min-w-44">
                          <select
                            id={"schedule-guide-" + schedule.id}
                            value={
                              pendingGuideIds[schedule.id] ??
                              String(schedule.guide_id ?? "")
                            }
                            disabled={assigningScheduleId === schedule.id || status === "cancelled" || status === "completed"}
                            onChange={(event) =>
                              setPendingGuideIds((current) => ({
                                ...current,
                                [schedule.id]: event.target.value,
                              }))
                            }
                            className="w-full rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs text-gray-800 outline-none focus:border-primary-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                          >
                            <option value="">Chưa phân công</option>
                            {guides.map((guide) => (
                              <option key={guide.id} value={guide.id}>
                                {guide.name}
                              </option>
                            ))}
                          </select>
                          <button
                            type="button"
                            disabled={
                              assigningScheduleId === schedule.id ||
                              status === "cancelled" ||
                              status === "completed" ||
                              (pendingGuideIds[schedule.id] ??
                                String(schedule.guide_id ?? "")) ===
                                String(schedule.guide_id ?? "")
                            }
                            onClick={() => {
                              const value =
                                pendingGuideIds[schedule.id] ??
                                String(schedule.guide_id ?? "");
                              assignGuide(schedule.id, value ? Number(value) : null);
                            }}
                            className="rounded-lg bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-150 disabled:text-gray-400 transition-colors shrink-0"
                          >
                            {assigningScheduleId === schedule.id ? "..." : "Lưu"}
                          </button>
                        </div>
                      </td>

                      {/* Trạng thái */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        <div className="flex flex-col gap-1 items-start">
                          <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                              statusClasses[status] || statusClasses.open
                            }`}
                          >
                            {statusLabel[status]}
                          </span>
                          {status === "cancelled" && schedule.cancelled_reason && (
                            <span className="text-xs text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-100 font-medium max-w-40 truncate" title={schedule.cancelled_reason}>
                              Lý do: {schedule.cancelled_reason}
                            </span>
                          )}
                        </div>
                      </td>

                      {/* Vận hành & Thao tác */}
                      <td className="py-4 px-5 text-right whitespace-nowrap">
                        <div className="flex flex-col gap-2 items-end">
                          <div className="flex flex-wrap gap-1 justify-end">
                            {/* Open/Close toggle */}
                            {(status === "open" || status === "active") && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "closed")}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Đóng bán
                              </button>
                            )}
                            {(status === "closed" || status === "full") && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "open")}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-primary-655 hover:bg-primary-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Mở bán lại
                              </button>
                            )}

                            {/* Confirm action */}
                            {(status === "open" || status === "active" || status === "closed" || status === "full") && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "confirmed")}
                                className="rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-700 shadow-sm transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Chốt chuyến
                              </button>
                            )}

                            {/* Start tour action */}
                            {status === "confirmed" && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "in_progress")}
                                className="rounded bg-amber-600 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-700 shadow-sm transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Bắt đầu chuyến
                              </button>
                            )}

                            {/* Complete tour action */}
                            {status === "in_progress" && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "completed")}
                                className="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white hover:bg-indigo-700 shadow-sm transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Kết thúc chuyến
                              </button>
                            )}

                            {/* Cancel action */}
                            {(status === "open" || status === "active" || status === "closed" || status === "full" || status === "confirmed") && (
                              <button
                                type="button"
                                onClick={() => openCancelDialog(schedule.id)}
                                className="rounded border border-rose-150 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Hủy chuyến
                              </button>
                            )}

                            {/* Closed lifecycle states */}
                            {(status === "completed" || status === "cancelled") && (
                              <span className="text-xs text-gray-400 italic">
                                Đã kết thúc vòng đời
                              </span>
                            )}
                          </div>

                          <Link
                            to={`/admin/tour-schedules/${schedule.id}/attendance`}
                            className="text-xs font-semibold text-primary-600 hover:underline inline-flex items-center gap-0.5"
                          >
                            Xem điểm danh →
                          </Link>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* PAGINATION PANEL */}
          {totalPages >= 1 && (
            <div className="bg-slate-50 border-t border-gray-100 px-5 py-4 flex items-center justify-between flex-wrap gap-3">
              <span className="text-xs text-gray-500">
                Hiển thị trang <strong className="text-gray-800">{currentPage}</strong> / <strong className="text-gray-800">{totalPages}</strong> trang (Tổng <strong className="text-gray-800">{totalItems}</strong> chuyến đi)
              </span>

              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  disabled={currentPage === 1}
                  onClick={() => setCurrentPage((c) => Math.max(1, c - 1))}
                  className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                >
                  Trước
                </button>
                
                {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                  <button
                    key={page}
                    type="button"
                    onClick={() => setCurrentPage(page)}
                    className={`rounded-lg px-3 py-1.5 text-xs font-bold cursor-pointer transition-all duration-150 ${
                      page === currentPage
                        ? "bg-primary-600 text-white shadow-xs"
                        : "border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                    }`}
                  >
                    {page}
                  </button>
                ))}

                <button
                  type="button"
                  disabled={currentPage === totalPages}
                  onClick={() => setCurrentPage((c) => Math.min(totalPages, c + 1))}
                  className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                >
                  Sau
                </button>
              </div>
            </div>
          )}
        </div>
      ) : (
        <div className="p-10 text-center rounded-2xl border border-gray-100 bg-white text-sm text-gray-500">
          Không tìm thấy chuyến đi nào khớp với bộ lọc.
        </div>
      )}

      {/* Modal Hủy Chuyến */}
      {isCancelModalOpen && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in pointer-events-auto">
          <div className="bg-white w-full max-w-sm rounded-xl shadow-2xl border border-gray-100 p-6 flex flex-col items-center text-center animate-scale-up">
            <div className="p-3.5 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 mb-4">
              <AlertTriangle className="w-6 h-6" />
            </div>
            <h4 className="text-base font-bold text-gray-900 mb-1">Xác nhận hủy chuyến đi</h4>
            <p className="text-xs text-gray-500 mb-4">
              Vui lòng cung cấp lý do chi tiết hủy chuyến đi này. Chỗ ngồi sẽ được trả lại và không thể phục hồi.
            </p>
            <textarea
              value={cancelReasonInput}
              onChange={(e) => setCancelReasonInput(e.target.value)}
              placeholder="Nhập lý do hủy (ví dụ: Không đủ khách tối thiểu, lý do thời tiết...)"
              rows={3}
              className="w-full rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-800 placeholder:text-gray-400 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 mb-4 resize-none"
            />
            <div className="flex w-full gap-2">
              <button
                type="button"
                onClick={() => setIsCancelModalOpen(false)}
                className="flex-1 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl transition-colors"
              >
                Hủy bỏ
              </button>
              <button
                type="button"
                onClick={confirmCancelSchedule}
                disabled={!cancelReasonInput.trim()}
                className="flex-1 py-2 text-xs font-semibold text-white rounded-xl shadow-md bg-rose-600 hover:bg-rose-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Hủy chuyến
              </button>
            </div>
          </div>
        </div>
      )}

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((current) => ({ ...current, isOpen: false }))}
      />
    </div>
  );
}
