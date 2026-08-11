import { useEffect, useState, useMemo } from "react";
import { Link } from "react-router-dom";
import {
  CalendarDays,
  Clock,
  UserRound,
  Users,
  Search,
  Filter,
  CheckCircle,
  AlertTriangle,
  Play,
  Check,
  XCircle,
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

      {/* STATS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3 bg-primary-50 text-primary-600 rounded-xl">
            <CalendarDays className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-semibold text-gray-400 uppercase">Tổng số chuyến</p>
            <h3 className="text-lg font-bold text-gray-900 mt-0.5">{stats.total} chuyến</h3>
          </div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
            <CheckCircle className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-semibold text-gray-400 uppercase">Đang mở bán</p>
            <h3 className="text-lg font-bold text-emerald-600 mt-0.5">{stats.open} chuyến</h3>
          </div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3 bg-blue-50 text-blue-600 rounded-xl">
            <Check className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-semibold text-gray-400 uppercase">Đã chốt chạy</p>
            <h3 className="text-lg font-bold text-blue-600 mt-0.5">{stats.confirmed} chuyến</h3>
          </div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3 bg-amber-50 text-amber-600 rounded-xl">
            <Play className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-semibold text-gray-400 uppercase">Đang di chuyển</p>
            <h3 className="text-lg font-bold text-amber-600 mt-0.5">{stats.running} chuyến</h3>
          </div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3 bg-rose-50 text-rose-600 rounded-xl">
            <XCircle className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-semibold text-gray-400 uppercase">Đã hủy</p>
            <h3 className="text-lg font-bold text-rose-600 mt-0.5">{stats.cancelled} chuyến</h3>
          </div>
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

      {/* SCHEDULES LIST */}
      {loading ? (
        <div className="space-y-4">
          {[1, 2, 3].map((n) => (
            <div key={n} className="bg-white h-48 rounded-2xl border border-gray-100 animate-pulse" />
          ))}
        </div>
      ) : filteredSchedules.length ? (
        <div className="space-y-4">
          {filteredSchedules.map((schedule) => {
            const status = schedule.status || "open";
            const deadline = schedule.booking_deadline;
            const minPeople = schedule.min_people || 5;
            const isOverdue = deadline ? new Date(deadline) < new Date() : false;

            return (
              <div
                key={schedule.id}
                className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-300"
              >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  {/* Left block: Title, dates, capacity */}
                  <div className="space-y-3 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="text-xs font-bold text-primary-700 font-mono">
                        CHUYẾN #{schedule.id}
                      </span>
                      <Link
                        to={`/admin/tours/${schedule.tour_id}`}
                        className="text-sm font-bold text-gray-900 hover:text-primary-600 transition-colors"
                      >
                        {schedule.tour_title}
                      </Link>
                      <span
                        className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                          statusClasses[status] || statusClasses.open
                        }`}
                      >
                        {statusLabel[status]}
                      </span>
                      {status === "cancelled" && schedule.cancelled_reason && (
                        <span className="text-xs text-rose-600 bg-rose-50 px-2 py-1 rounded-lg border border-rose-100 flex items-center gap-1">
                          <AlertTriangle className="w-3.5 h-3.5" /> Lý do hủy: {schedule.cancelled_reason}
                        </span>
                      )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                      {/* Time info */}
                      <div className="flex items-center gap-2.5">
                        <CalendarDays className="h-4.5 w-4.5 text-gray-400 shrink-0" />
                        <div className="text-xs">
                          <p className="text-gray-400">Thời gian khởi hành</p>
                          <p className="font-semibold text-gray-900 mt-0.5">
                            {formatDateTime(schedule.start_date)} -{" "}
                            {getEndDate(schedule.start_date, schedule.number_of_days)}
                          </p>
                        </div>
                      </div>

                      {/* Booking deadline */}
                      <div className="flex items-center gap-2.5">
                        <Clock className={`h-4.5 w-4.5 shrink-0 ${isOverdue && (status === "open" || status === "active") ? "text-amber-500 animate-pulse" : "text-gray-400"}`} />
                        <div className="text-xs">
                          <p className="text-gray-400">Hạn đặt (Booking Deadline)</p>
                          <p className="font-semibold text-gray-900 mt-0.5">
                            {deadline ? formatDateTime(deadline) : "Không giới hạn"}
                            {isOverdue && (status === "open" || status === "active") && (
                              <span className="ml-1.5 text-[9px] bg-amber-50 text-amber-700 px-1 py-0.5 rounded font-bold uppercase tracking-wide">Quá hạn</span>
                            )}
                          </p>
                        </div>
                      </div>

                      {/* Guest capacity info */}
                      <div className="flex items-center gap-2.5">
                        <Users className="h-4.5 w-4.5 text-gray-400 shrink-0" />
                        <div className="text-xs">
                          <p className="text-gray-400">Tình trạng chỗ</p>
                          <p className="font-semibold text-gray-900 mt-0.5">
                            {schedule.booked_people} / {schedule.max_people} khách{" "}
                            <span className="text-gray-400 font-normal">
                              (Tối thiểu: {minPeople})
                            </span>
                          </p>
                        </div>
                      </div>
                    </div>

                    {/* Guide assign section */}
                    <div className="pt-2 flex flex-col gap-2 sm:flex-row sm:items-center max-w-lg">
                      <div className="shrink-0 text-xs font-semibold text-gray-500 flex items-center gap-1">
                        <UserRound className="h-3.5 w-3.5" /> Hướng dẫn viên:
                      </div>
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
                        className="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-gray-50"
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
                        className="shrink-0 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 transition-colors"
                      >
                        {assigningScheduleId === schedule.id ? "Đang lưu..." : "Lưu HDV"}
                      </button>
                    </div>
                  </div>

                  {/* Right block: Operations */}
                  <div className="flex flex-col gap-2 shrink-0 w-full sm:w-auto lg:items-end border-t border-gray-100 pt-3 lg:border-t-0 lg:pt-0">
                    <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                      Vận hành chuyến
                    </p>

                    <div className="flex flex-wrap gap-1.5">
                      {/* Open/Close toggle */}
                      {(status === "open" || status === "active") && (
                        <button
                          type="button"
                          onClick={() => handleUpdateStatus(schedule.id, "closed")}
                          className="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-205 cursor-pointer"
                        >
                          Đóng bán
                        </button>
                      )}
                      {(status === "closed" || status === "full") && (
                        <button
                          type="button"
                          onClick={() => handleUpdateStatus(schedule.id, "open")}
                          className="rounded-lg border border-gray-255 bg-white px-2.5 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 transition-all active:scale-95 duration-205 cursor-pointer"
                        >
                          Mở bán lại
                        </button>
                      )}

                      {/* Confirm action */}
                      {(status === "open" || status === "active" || status === "closed" || status === "full") && (
                        <button
                          type="button"
                          onClick={() => handleUpdateStatus(schedule.id, "confirmed")}
                          className="rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 shadow-sm transition-all active:scale-95 duration-205 cursor-pointer"
                        >
                          Chốt chuyến
                        </button>
                      )}

                      {/* Start tour action */}
                      {status === "confirmed" && (
                        <button
                          type="button"
                          onClick={() => handleUpdateStatus(schedule.id, "in_progress")}
                          className="rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 shadow-sm transition-all active:scale-95 duration-205 cursor-pointer"
                        >
                          Bắt đầu chuyến
                        </button>
                      )}

                      {/* Complete tour action */}
                      {status === "in_progress" && (
                        <button
                          type="button"
                          onClick={() => handleUpdateStatus(schedule.id, "completed")}
                          className="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 shadow-sm transition-all active:scale-95 duration-205 cursor-pointer"
                        >
                          Kết thúc chuyến
                        </button>
                      )}

                      {/* Cancel action */}
                      {(status === "open" || status === "active" || status === "closed" || status === "full" || status === "confirmed") && (
                        <button
                          type="button"
                          onClick={() => openCancelDialog(schedule.id)}
                          className="rounded-lg border border-rose-150 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-all active:scale-95 duration-205 cursor-pointer"
                        >
                          Hủy chuyến
                        </button>
                      )}

                      {/* Closed lifecycle states */}
                      {(status === "completed" || status === "cancelled") && (
                        <span className="text-xs text-gray-400 italic">
                          Chuyến đi đã kết thúc vòng đời
                        </span>
                      )}
                    </div>

                    <Link
                      to={`/admin/tour-schedules/${schedule.id}/attendance`}
                      className="mt-1 text-xs font-semibold text-primary-600 hover:underline flex items-center gap-1 lg:self-end"
                    >
                      Xem điểm danh & check-in →
                    </Link>
                  </div>
                </div>
              </div>
            );
          })}
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
