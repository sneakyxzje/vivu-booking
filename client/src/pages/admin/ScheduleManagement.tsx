import { useEffect, useState, useMemo } from "react";
import { Link } from "react-router-dom";
import {
  CalendarDays,
  Clock,
  Users,
  Search,
  Filter,
  AlertTriangle,
  RotateCcw,
} from "lucide-react";
import adminService from "@/services/adminService";
import type {
  CancelPlan,
  DeadlineImpactResponse,
  ScheduleCancelPreviewResponse,
  ScheduleManifestResponse,
  MergeCandidatesResponse,
} from "@/services/adminService";
import type { Tour, Guide, ExtendedSchedule } from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime, formatPrice, getEndDate, toDateTimeLocalValue } from "@/utils/format";
import { statusLabel, statusClasses } from "@/utils/schedule";
import Pagination from "@/components/common/Pagination";

export default function ScheduleManagement() {
  const [tours, setTours] = useState<Tour[]>([]);
  const [guides, setGuides] = useState<Guide[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  // State phân công Hướng dẫn viên
  const [assigningScheduleId, setAssigningScheduleId] = useState<number | null>(null);
  // Phân công nhiều hướng dẫn viên cho một chuyến, sửa trong hộp thoại riêng.
  const [guideDialogScheduleId, setGuideDialogScheduleId] = useState<number | null>(null);
  const [pendingGuideIds, setPendingGuideIds] = useState<number[]>([]);

  // State Hủy chuyến
  // K - Hủy chuyến. Mỗi đơn đã thanh toán phải có một phương án trước khi hủy được.
  const [cancellingScheduleId, setCancellingScheduleId] = useState<number | null>(null);
  const [cancelReasonInput, setCancelReasonInput] = useState("");
  const [cancelPreview, setCancelPreview] = useState<ScheduleCancelPreviewResponse | null>(null);
  const [cancelPreviewLoading, setCancelPreviewLoading] = useState(false);
  const [cancelPlans, setCancelPlans] = useState<Record<number, CancelPlan>>({});
  const [cancelSaving, setCancelSaving] = useState(false);
  const [cancelError, setCancelError] = useState("");

  // G05 - Kiểm tra danh sách đoàn trước khi gửi nhà cung cấp
  const [manifestScheduleId, setManifestScheduleId] = useState<number | null>(null);
  const [manifest, setManifest] = useState<ScheduleManifestResponse | null>(null);
  const [manifestLoading, setManifestLoading] = useState(false);
  // Nhóm đang mở xem chi tiết. Mở sẵn tất cả thì đoàn đông thành một bức tường chữ.
  const [openGroupIds, setOpenGroupIds] = useState<number[]>([]);

  // L03 - Ghép chuyến
  const [mergeScheduleId, setMergeScheduleId] = useState<number | null>(null);
  const [mergeData, setMergeData] = useState<MergeCandidatesResponse | null>(null);
  const [mergeLoading, setMergeLoading] = useState(false);
  const [mergeTargetId, setMergeTargetId] = useState<number | null>(null);
  const [mergeReason, setMergeReason] = useState("");
  const [mergeSaving, setMergeSaving] = useState(false);
  const [mergeError, setMergeError] = useState("");

  // Dời hạn chốt danh sách. Xem docs/nghiep-vu/16-sua-han-chot.md.
  const [deadlineScheduleId, setDeadlineScheduleId] = useState<number | null>(null);
  const [deadlineValue, setDeadlineValue] = useState("");
  const [deadlineReason, setDeadlineReason] = useState("");
  const [deadlineImpact, setDeadlineImpact] = useState<DeadlineImpactResponse | null>(null);
  const [deadlineLoading, setDeadlineLoading] = useState(false);
  const [deadlineSaving, setDeadlineSaving] = useState(false);
  const [deadlineError, setDeadlineError] = useState("");

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
      const matchesStatus = statusFilter === "all" || status === statusFilter;

      return matchesSearch && matchesStatus;
    });
  }, [allSchedules, searchQuery, statusFilter]);

  const totalItems = filteredSchedules.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

  const paginatedSchedules = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return filteredSchedules.slice(startIndex, startIndex + itemsPerPage);
  }, [filteredSchedules, currentPage, itemsPerPage]);

  const openGuideDialog = (schedule: ExtendedSchedule) => {
    setGuideDialogScheduleId(schedule.id);
    setPendingGuideIds((schedule.guides ?? []).map((guide) => guide.id));
  };

  /**
   * Đặt lại cả danh sách một lần.
   *
   * Máy chủ được ăn cả ngã về không: một người vướng lịch thì cả lần phân công bị từ chối, nên
   * không có trạng thái nửa vời để xử lý ở đây.
   */
  const assignGuides = async (scheduleId: number, guideIds: number[]) => {
    setAssigningScheduleId(scheduleId);

    try {
      await adminService.assignGuidesToSchedule(scheduleId, guideIds);

      const daChon = guides.filter((item) => guideIds.includes(item.id));

      setTours((currentTours) =>
        currentTours.map((t) => ({
          ...t,
          schedules: t.schedules?.map((item) =>
            item.id === scheduleId ? { ...item, guides: daChon } : item
          ),
        }))
      );

      setGuideDialogScheduleId(null);

      setToast({
        message:
          guideIds.length === 0
            ? "Đã bỏ phân công hướng dẫn viên."
            : `Đã phân công ${guideIds.length} hướng dẫn viên.`,
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

  const handleUpdateStatus = async (
    scheduleId: number,
    nextStatus: "open" | "closed" | "confirmed" | "cancelled",
    reason?: string,
  ) => {
    try {
      const updatedSchedule = await adminService.updateScheduleStatus(scheduleId, nextStatus, reason);

      if (!updatedSchedule) {
        throw new Error("Missing updated schedule response");
      }

      setTours((currentTours) =>
        currentTours.map((t) => ({
          ...t,
          schedules: t.schedules?.map((item) =>
            item.id === scheduleId ? { ...item, ...updatedSchedule } : item,
          ),
        })),
      );

      setToast({
        message: `Đã cập nhật trạng thái chuyến khởi hành thành "${statusLabel[updatedSchedule.status]}".`,
        type: "success",
        isOpen: true,
      });
    } catch (error: unknown) {
      const message =
        (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
        "Không thể cập nhật trạng thái chuyến khởi hành.";
      setToast({ message, type: "error", isOpen: true });
    }
  };

  const openCancelDialog = async (scheduleId: number) => {
    setCancellingScheduleId(scheduleId);
    setCancelReasonInput("");
    setCancelPreview(null);
    setCancelPlans({});
    setCancelError("");
    setCancelPreviewLoading(true);

    try {
      const data = await adminService.getScheduleCancelPreview(scheduleId);
      setCancelPreview(data);

      // Mặc định hoàn đủ cho mọi đơn: đó là phương án luôn hợp lệ, còn chuyển chuyến thì phụ
      // thuộc chuyến đích có chỗ hay không. Điều hành đổi lại từng đơn nếu muốn.
      setCancelPlans(
        Object.fromEntries(
          (data?.impact.paid_bookings ?? []).map((don) => [
            don.booking_id,
            { booking_id: don.booking_id, action: "refund" as const },
          ]),
        ),
      );
    } catch (err) {
      console.error("Lỗi lấy tác động hủy chuyến:", err);
    } finally {
      setCancelPreviewLoading(false);
    }
  };

  const openManifestCheck = async (scheduleId: number) => {
    setManifestScheduleId(scheduleId);
    setManifest(null);
    setOpenGroupIds([]);
    setManifestLoading(true);

    try {
      setManifest(await adminService.getScheduleManifest(scheduleId));
    } catch (err) {
      console.error("Lỗi lấy danh sách đoàn:", err);
    } finally {
      setManifestLoading(false);
    }
  };

  const toggleGroup = (bookingId: number) => {
    setOpenGroupIds((truoc) =>
      truoc.includes(bookingId)
        ? truoc.filter((id) => id !== bookingId)
        : [...truoc, bookingId],
    );
  };

  const openMergeDialog = async (scheduleId: number) => {
    setMergeScheduleId(scheduleId);
    setMergeData(null);
    setMergeTargetId(null);
    setMergeReason("");
    setMergeError("");
    setMergeLoading(true);

    try {
      setMergeData(await adminService.getMergeCandidates(scheduleId));
    } catch (err) {
      console.error("Lỗi tải danh sách chuyến có thể ghép:", err);
    } finally {
      setMergeLoading(false);
    }
  };

  const closeMergeDialog = () => {
    setMergeScheduleId(null);
    setMergeData(null);
    setMergeTargetId(null);
    setMergeReason("");
    setMergeError("");
  };

  const confirmMerge = async () => {
    if (!mergeScheduleId || !mergeTargetId || mergeReason.trim().length < 10) return;

    setMergeSaving(true);
    setMergeError("");

    try {
      const message = await adminService.mergeSchedule(
        mergeScheduleId,
        mergeTargetId,
        mergeReason.trim(),
      );

      closeMergeDialog();
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setMergeError(response?.message || "Không ghép được chuyến.");
    } finally {
      setMergeSaving(false);
    }
  };

  const openDeadlineDialog = (schedule: ExtendedSchedule) => {
    setDeadlineScheduleId(schedule.id);
    setDeadlineValue(toDateTimeLocalValue(schedule.booking_deadline));
    setDeadlineReason("");
    setDeadlineImpact(null);
    setDeadlineError("");
  };

  const closeDeadlineDialog = () => {
    setDeadlineScheduleId(null);
    setDeadlineValue("");
    setDeadlineReason("");
    setDeadlineImpact(null);
    setDeadlineError("");
  };

  /*
   * Tác động do máy chủ tính, lấy lại mỗi khi người dùng đổi ngày.
   *
   * Chờ 400ms rồi mới gọi: ô datetime-local bắn sự kiện theo từng ký tự, gọi ngay thì gõ một
   * chữ số là một lượt gọi mạng. Tính ở trình duyệt cho nhanh thì sớm muộn con số hiện ra sẽ
   * lệch với luật máy chủ thực sự áp.
   */
  useEffect(() => {
    if (deadlineScheduleId === null) return;

    const scheduleId = deadlineScheduleId;
    const value = deadlineValue;
    let daHuy = false;

    const hen = setTimeout(async () => {
      setDeadlineLoading(true);

      try {
        const data = await adminService.getDeadlineImpact(scheduleId, value || null);
        if (!daHuy) setDeadlineImpact(data);
      } catch {
        if (!daHuy) setDeadlineImpact(null);
      } finally {
        if (!daHuy) setDeadlineLoading(false);
      }
    }, 400);

    return () => {
      daHuy = true;
      clearTimeout(hen);
    };
  }, [deadlineScheduleId, deadlineValue]);

  const confirmDeadline = async () => {
    if (deadlineScheduleId === null) return;

    setDeadlineSaving(true);
    setDeadlineError("");

    try {
      const message = await adminService.updateScheduleDeadline(
        deadlineScheduleId,
        deadlineValue || null,
        deadlineReason.trim(),
      );

      closeDeadlineDialog();
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setDeadlineError(response?.message || "Không đổi được hạn chốt.");
    } finally {
      setDeadlineSaving(false);
    }
  };

  const closeCancelDialog = () => {
    setCancellingScheduleId(null);
    setCancelPreview(null);
    setCancelPlans({});
    setCancelReasonInput("");
    setCancelError("");
  };

  const confirmCancelSchedule = async () => {
    if (!cancellingScheduleId) return;

    setCancelSaving(true);
    setCancelError("");

    try {
      const message = await adminService.cancelSchedule(
        cancellingScheduleId,
        cancelReasonInput.trim(),
        Object.values(cancelPlans),
      );

      closeCancelDialog();
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setCancelError(response?.message || "Không hủy được chuyến.");
    } finally {
      setCancelSaving(false);
    }
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

          {(searchQuery !== "" || statusFilter !== "all") && (
            <button
              type="button"
              title="Đặt lại bộ lọc"
              onClick={() => {
                setSearchQuery("");
                setStatusFilter("all");
                setCurrentPage(1);
              }}
              className="p-2 rounded-xl border border-gray-200 bg-white text-gray-400 hover:text-rose-500 hover:border-rose-300 hover:bg-rose-50 transition-all duration-150 cursor-pointer"
            >
              <RotateCcw className="h-4 w-4" />
            </button>
          )}
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
                            <Clock className={`h-3.5 w-3.5 ${isOverdue && status === "open" ? "text-amber-500 animate-pulse" : "text-gray-400"}`} />
                            <div>
                              <p className={`font-semibold ${isOverdue && status === "open" ? "text-amber-600" : "text-gray-955"}`}>
                                {formatDateTime(deadline)}
                              </p>
                              {isOverdue && status === "open" && (
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

                      {/*
                        Hướng dẫn viên — nhiều người cho một chuyến.

                        Hiện dạng thẻ rồi mở hộp thoại để sửa, vì ô chọn một dòng không chứa nổi
                        danh sách. Bao nhiêu người là đủ thì điều hành quyết, hệ thống không
                        cảnh báo theo số khách.
                      */}
                      <td className="py-4 px-5">
                        <div className="flex flex-wrap items-center gap-1 min-w-44">
                          {(schedule.guides ?? []).length === 0 ? (
                            <span className="text-xs text-gray-400">Chưa phân công</span>
                          ) : (
                            (schedule.guides ?? []).map((guide) => (
                              <span
                                key={guide.id}
                                className="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-700"
                              >
                                {guide.name}
                              </span>
                            ))
                          )}

                          <button
                            type="button"
                            disabled={status === "cancelled" || status === "completed"}
                            onClick={() => openGuideDialog(schedule)}
                            className="rounded border border-gray-200 bg-white px-1.5 py-0.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 disabled:cursor-not-allowed disabled:text-gray-300 transition-colors"
                          >
                            Sửa
                          </button>
                        </div>
                      </td>

                      {/* Trạng thái */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        <div className="flex flex-col gap-1 items-start">
                          <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusClasses[status] || statusClasses.open
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
                            {/*
                              G05 - Danh sách đoàn, chia theo từng nhóm. Trả lời hai câu ở cùng
                              một chỗ: gửi cho nhà cung cấp được chưa, và nhóm này gồm những ai.
                              Đặt ngay trên hàng chuyến vì cả hai câu đều hỏi lúc đang nhìn chuyến,
                              không phải lúc đang mở một đơn.
                            */}
                            {status !== "cancelled" && (
                              <button
                                type="button"
                                onClick={() => openManifestCheck(schedule.id)}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Danh sách đoàn
                              </button>
                            )}

                            {/* Open/Close toggle */}
                            {status === "open" && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "closed")}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Đóng bán
                              </button>
                            )}
                            {status === "closed" && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "open")}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-primary-655 hover:bg-primary-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Mở bán lại
                              </button>
                            )}

                            {/* Dời hạn chốt danh sách. Chuyến đã chạy hoặc đã xong thì mốc này
                                không còn nghĩa gì nên không cho sửa. */}
                            {(status === "open" || status === "closed" || status === "confirmed") && (
                              <button
                                type="button"
                                onClick={() => openDeadlineDialog(schedule)}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Sửa hạn chốt
                              </button>
                            )}

                            {/* L03 - Ghép chuyến. Chỉ có nghĩa khi chuyến chưa khởi hành và
                                đang ít khách, nên để cạnh nút chốt chuyến. */}
                            {(status === "open" || status === "closed" || status === "confirmed") && (
                              <button
                                type="button"
                                onClick={() => openMergeDialog(schedule.id)}
                                className="rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Ghép chuyến
                              </button>
                            )}

                            {/* Confirm action */}
                            {(status === "open" || status === "closed") && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "confirmed")}
                                className="rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-700 shadow-sm transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Chốt chuyến
                              </button>
                            )}

                            {/* Cancel action */}
                            {(status === "open" || status === "closed" || status === "confirmed") && (
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
          <div className="bg-slate-50 border-t border-gray-100 px-5 py-3">
            <Pagination
              currentPage={currentPage}
              lastPage={totalPages}
              total={totalItems}
              perPage={itemsPerPage}
              itemLabel="chuyến đi"
              onPageChange={(p) => setCurrentPage(p)}
              onPerPageChange={(newPerPage) => {
                setItemsPerPage(newPerPage);
                setCurrentPage(1);
              }}
            />
          </div>
        </div>
      ) : (
        <div className="p-10 text-center rounded-2xl border border-gray-100 bg-white text-sm text-gray-500">
          Không tìm thấy chuyến đi nào khớp với bộ lọc.
        </div>
      )}

      {/* Phân công hướng dẫn viên — nhiều người cho một chuyến */}
      {guideDialogScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-md rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Hướng dẫn viên — chuyến #{guideDialogScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Chọn được nhiều người. Đoàn đông thì cần thêm người dẫn, bao nhiêu là đủ do bạn
                quyết — hệ thống không tính hộ theo số khách.
              </p>
            </div>

            <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
              {guides.length === 0 && (
                <p className="px-1 py-2 text-xs text-gray-500">Chưa có hướng dẫn viên nào.</p>
              )}

              {guides.map((guide) => (
                <label
                  key={guide.id}
                  className="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1.5 text-sm hover:bg-gray-50"
                >
                  <input
                    type="checkbox"
                    checked={pendingGuideIds.includes(guide.id)}
                    onChange={() =>
                      setPendingGuideIds((truoc) =>
                        truoc.includes(guide.id)
                          ? truoc.filter((id) => id !== guide.id)
                          : [...truoc, guide.id],
                      )
                    }
                    className="h-4 w-4 rounded border-gray-300 text-primary-600"
                  />
                  <span className="text-gray-800">{guide.name}</span>
                </label>
              ))}
            </div>

            <p className="text-[11px] text-gray-400">
              Người đang bận một chuyến khác trùng ngày sẽ bị máy chủ từ chối, và khi đó cả lần
              phân công này không được lưu.
            </p>

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setGuideDialogScheduleId(null)}
                disabled={assigningScheduleId === guideDialogScheduleId}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Quay lại
              </button>
              <button
                type="button"
                onClick={() => assignGuides(guideDialogScheduleId, pendingGuideIds)}
                disabled={assigningScheduleId === guideDialogScheduleId}
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-40"
              >
                {assigningScheduleId === guideDialogScheduleId ? "Đang lưu..." : "Lưu phân công"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Dời hạn chốt danh sách, có xem trước tác động */}
      {deadlineScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Hạn chốt danh sách — chuyến #{deadlineScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Đây là mốc gửi danh sách khách cho khách sạn và nhà xe. Dời mốc này là dời cùng
                lúc quyền bán chỗ, sửa tên hành khách, chuyển chuyến và ghép chuyến.
              </p>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Hạn chốt mới</label>
              <input
                type="datetime-local"
                value={deadlineValue}
                onChange={(e) => setDeadlineValue(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                Để trống thì chuyến dùng mốc mặc định của hệ thống.
              </p>
            </div>

            {deadlineLoading && <p className="text-sm text-gray-500">Đang tính tác động...</p>}

            {deadlineImpact && !deadlineImpact.impact.can_change && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {deadlineImpact.impact.blocked_reason}
              </div>
            )}

            {deadlineImpact && deadlineImpact.impact.can_change && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-2">
                <p className="text-xs font-bold uppercase tracking-wider text-amber-800">
                  {deadlineImpact.impact.direction === "unchanged"
                    ? "Chưa có thay đổi nào"
                    : "Lưu xong sẽ có hiệu lực ngay"}
                </p>

                <ul className="space-y-1.5">
                  {deadlineImpact.impact.warnings.map((dong) => (
                    <li key={dong} className="text-xs text-amber-900 flex gap-2">
                      <span className="text-amber-500 shrink-0">•</span>
                      <span>{dong}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Lý do dời hạn</label>
              <textarea
                rows={2}
                value={deadlineReason}
                onChange={(e) => setDeadlineReason(e.target.value)}
                placeholder="VD: Khách sạn cho thêm 2 phòng, chốt lại ngày 19/08..."
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                Không bắt buộc, nhưng có thì nhật ký về sau đọc mới hiểu được vì sao mốc bị dời.
              </p>
            </div>

            {deadlineError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {deadlineError}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={closeDeadlineDialog}
                disabled={deadlineSaving}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Quay lại
              </button>
              <button
                type="button"
                onClick={confirmDeadline}
                disabled={
                  deadlineSaving ||
                  deadlineLoading ||
                  !deadlineImpact?.impact.can_change ||
                  deadlineImpact?.impact.direction === "unchanged"
                }
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-40"
              >
                {deadlineSaving ? "Đang lưu..." : "Đồng ý, lưu hạn chốt mới"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* L03 - Ghép chuyến */}
      {mergeScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-2xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Ghép chuyến #{mergeScheduleId} vào chuyến khác
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Toàn bộ đơn đã thanh toán sẽ chuyển sang chuyến đích, giá giữ nguyên. Chuyến này
                sau đó chuyển thành đã hủy.
              </p>
            </div>

            {mergeLoading && <p className="text-sm text-gray-500">Đang tìm chuyến phù hợp...</p>}

            {mergeData && mergeData.candidates.length === 0 && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-1">
                <p className="font-semibold">Không có chuyến nào ghép được.</p>
                <p className="text-xs">
                  Chuyến đích phải cùng tour, còn đủ chỗ, lệch ngày không quá 2 ngày, và{" "}
                  <strong>cả hai chuyến đều còn trước hạn chốt danh sách</strong> — vì mục đích của
                  ghép là gửi một danh sách đúng cho nhà cung cấp, thay vì gửi hai rồi đi vá.
                </p>
              </div>
            )}

            {/* Máy chủ đã loại chuyến không ghép được và tính sẵn tác động, nên đây chỉ hiển thị. */}
            <div className="space-y-2">
              {mergeData?.candidates.map((item) => {
                const dangChon = mergeTargetId === item.schedule_id;

                return (
                  <button
                    key={item.schedule_id}
                    type="button"
                    onClick={() => setMergeTargetId(item.schedule_id)}
                    className={`w-full text-left rounded-lg border p-3 transition-colors ${
                      dangChon
                        ? "border-blue-500 bg-blue-50/60 ring-2 ring-blue-200"
                        : "border-gray-200 bg-white hover:bg-gray-50"
                    }`}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-sm font-bold text-gray-900">
                        #{item.schedule_id} · {formatDateTime(item.start_date)}
                      </span>
                      <span className="text-[11px] text-gray-500">
                        {item.booked_people}/{item.max_people} chỗ
                      </span>
                    </div>
                    <p className="mt-1 text-xs text-gray-600">
                      Chuyển {item.transferring} đơn ({item.transferring_guests} khách)
                      {item.cancelling > 0 && (
                        <span className="text-amber-800 font-semibold">
                          {" "}· hủy {item.cancelling} đơn chưa thanh toán
                        </span>
                      )}
                    </p>
                  </button>
                );
              })}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Lý do ghép <span className="text-rose-500">*</span>
              </label>
              <textarea
                rows={2}
                value={mergeReason}
                onChange={(e) => setMergeReason(e.target.value)}
                placeholder="VD: Hai chuyến đều chưa đủ khách tối thiểu nên dồn về một chuyến..."
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                Khách sẽ đọc được nội dung này khi được thông báo đổi ngày khởi hành.
              </p>
            </div>

            {mergeError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {mergeError}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={closeMergeDialog}
                disabled={mergeSaving}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Không ghép nữa
              </button>
              <button
                type="button"
                onClick={confirmMerge}
                disabled={mergeSaving || !mergeTargetId || mergeReason.trim().length < 10}
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-40"
              >
                {mergeSaving ? "Đang ghép..." : "Xác nhận ghép"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* G05 - Kiểm tra danh sách đoàn trước khi gửi nhà cung cấp */}
      {manifestScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-2xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Danh sách đoàn — chuyến #{manifestScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Mỗi đơn là một nhóm, thường do một người đứng ra đăng ký cho cả nhà hoặc cả phòng
                ban. Bấm vào nhóm để xem nhóm đó gồm những ai.
              </p>
            </div>

            {manifestLoading && <p className="text-sm text-gray-500">Đang tải danh sách...</p>}

            {manifest && (
              <>
                {manifest.can_export_manifest ? (
                  <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                    {manifest.total_groups} nhóm, {manifest.total_guests} khách, đã khai đủ. Gửi
                    được cho khách sạn và nhà xe.
                  </div>
                ) : (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                    {manifest.total_groups} nhóm, đã khai {manifest.total_declared} trên{" "}
                    {manifest.total_guests} khách. Chưa gửi được danh sách đoàn.
                  </div>
                )}

                {manifest.groups.length === 0 && (
                  <p className="text-sm text-gray-500">Chuyến này chưa có đơn nào.</p>
                )}

                <div className="space-y-2">
                  {manifest.groups.map((nhom) => {
                    const dangMo = openGroupIds.includes(nhom.booking_id);
                    const nguoiLienHe = nhom.passengers.find((khach) => khach.is_contact);

                    return (
                      <div
                        key={nhom.booking_id}
                        className="rounded-lg border border-gray-200 overflow-hidden"
                      >
                        <button
                          type="button"
                          onClick={() => toggleGroup(nhom.booking_id)}
                          className="w-full text-left p-3 text-xs hover:bg-gray-50 transition-colors"
                        >
                          <div className="flex items-center justify-between gap-2">
                            <span className="font-bold text-gray-900">
                              BK-{nhom.booking_id} · {nhom.customer_name}
                            </span>
                            <span className="flex items-center gap-2">
                              <span
                                className={`font-mono ${
                                  nhom.missing > 0 ? "text-amber-700 font-bold" : "text-gray-500"
                                }`}
                              >
                                {nhom.declared}/{nhom.guests} người
                              </span>
                              <span className="text-gray-400">{dangMo ? "▾" : "▸"}</span>
                            </span>
                          </div>

                          <p className="mt-0.5 flex flex-wrap gap-x-3 text-gray-500">
                            {nhom.customer_phone && <span>{nhom.customer_phone}</span>}
                            {nguoiLienHe && <span>Liên hệ đoàn: {nguoiLienHe.name}</span>}
                          </p>

                          {nhom.warnings.map((warning) => (
                            <p key={warning} className="mt-0.5 text-amber-800">
                              {warning}
                            </p>
                          ))}
                        </button>

                        {dangMo && (
                          <div className="border-t border-gray-100 bg-gray-50/60 p-3">
                            {nhom.passengers.length === 0 ? (
                              <p className="text-xs text-gray-500">
                                Nhóm này chưa khai tên người nào.
                              </p>
                            ) : (
                              <table className="w-full text-xs">
                                <thead>
                                  <tr className="text-left text-gray-500">
                                    <th className="pb-1 font-semibold">Họ tên</th>
                                    <th className="pb-1 font-semibold">Loại</th>
                                    <th className="pb-1 font-semibold">Ngày sinh</th>
                                    <th className="pb-1 font-semibold">Giấy tờ</th>
                                  </tr>
                                </thead>
                                <tbody className="align-top">
                                  {nhom.passengers.map((khach) => (
                                    <tr key={khach.id} className="border-t border-gray-200">
                                      <td className="py-1.5 pr-2 font-semibold text-gray-900">
                                        {khach.name}
                                        {khach.is_contact && (
                                          <span className="ml-1.5 rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary-700">
                                            Liên hệ
                                          </span>
                                        )}
                                        {khach.special_request && (
                                          <span className="block font-normal text-amber-700">
                                            {khach.special_request}
                                          </span>
                                        )}
                                      </td>
                                      <td className="py-1.5 pr-2 text-gray-600">
                                        {khach.type === "adult"
                                          ? "Người lớn"
                                          : khach.type === "child"
                                            ? "Trẻ em"
                                            : "Em bé"}
                                      </td>
                                      <td className="py-1.5 pr-2 text-gray-600">
                                        {khach.date_of_birth
                                          ? formatDateTime(khach.date_of_birth)
                                          : "—"}
                                      </td>
                                      <td className="py-1.5 font-mono text-gray-600">
                                        {khach.identity_number ?? "—"}
                                      </td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            )}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </>
            )}

            <div className="flex justify-end">
              <button
                type="button"
                onClick={() => setManifestScheduleId(null)}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Đóng
              </button>
            </div>
          </div>
        </div>
      )}

      {/*
        K - Hủy chuyến, ba bước bắt buộc: xem tác động, gán phương án cho từng đơn đã thanh toán,
        rồi mới xác nhận. Trước đây chỗ này chỉ hỏi lý do rồi đổi trạng thái, còn đơn của khách
        thì không ai đụng tới.
      */}
      {cancellingScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-2xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div className="flex items-start gap-3">
              <div className="p-2.5 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 shrink-0">
                <AlertTriangle className="w-5 h-5" />
              </div>
              <div>
                <h4 className="text-base font-bold text-gray-900">
                  Hủy chuyến #{cancellingScheduleId}
                </h4>
                <p className="text-xs text-gray-500 mt-0.5">
                  Lỗi không thuộc về khách, nên mỗi đơn đã thanh toán phải được hoàn đủ 100% hoặc
                  chuyển miễn phí sang chuyến khác. Không áp bảng phí hủy.
                </p>
              </div>
            </div>

            {cancelPreviewLoading && <p className="text-sm text-gray-500">Đang tính tác động...</p>}

            {cancelPreview && !cancelPreview.impact.can_cancel && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {cancelPreview.impact.blocked_reason}
              </div>
            )}

            {cancelPreview && cancelPreview.impact.can_cancel && (
              <>
                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-1">
                  <p>
                    <strong>{cancelPreview.impact.total_paid_bookings} đơn đã thanh toán</strong>{" "}
                    ({cancelPreview.impact.total_paid_guests} khách), tổng đã thu{" "}
                    <strong>{formatPrice(cancelPreview.impact.total_refund_if_all_refunded)}</strong>.
                  </p>
                  {cancelPreview.impact.unpaid_bookings > 0 && (
                    <p className="text-xs">
                      Ngoài ra {cancelPreview.impact.unpaid_bookings} đơn chưa thanh toán (
                      {cancelPreview.impact.unpaid_guests} khách) sẽ được hủy tự động, không cần
                      chọn phương án.
                    </p>
                  )}
                </div>

                {cancelPreview.impact.paid_bookings.length === 0 ? (
                  <p className="text-sm text-gray-500">
                    Chuyến này chưa có đơn nào đã thanh toán.
                  </p>
                ) : (
                  <div className="space-y-2">
                    <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      Phương án cho từng đơn
                    </p>

                    {cancelPreview.impact.paid_bookings.map((don) => {
                      const plan = cancelPlans[don.booking_id];

                      return (
                        <div
                          key={don.booking_id}
                          className="rounded-lg border border-gray-200 p-3 space-y-2"
                        >
                          <div className="flex items-baseline justify-between gap-2 text-xs">
                            <span className="font-bold text-gray-900">
                              BK-{don.booking_id} · {don.customer_name}
                            </span>
                            <span className="text-gray-500">
                              {don.guests} khách · đã thu {formatPrice(don.paid_amount)}
                            </span>
                          </div>

                          <div className="flex flex-wrap items-center gap-3 text-xs">
                            <label className="flex cursor-pointer items-center gap-1.5">
                              <input
                                type="radio"
                                checked={plan?.action === "refund"}
                                onChange={() =>
                                  setCancelPlans((truoc) => ({
                                    ...truoc,
                                    [don.booking_id]: {
                                      booking_id: don.booking_id,
                                      action: "refund",
                                    },
                                  }))
                                }
                                className="h-3.5 w-3.5 border-gray-300 text-primary-600"
                              />
                              Hoàn đủ {formatPrice(don.paid_amount)}
                            </label>

                            <label className="flex cursor-pointer items-center gap-1.5">
                              <input
                                type="radio"
                                disabled={cancelPreview.impact.transfer_options.length === 0}
                                checked={plan?.action === "transfer"}
                                onChange={() =>
                                  setCancelPlans((truoc) => ({
                                    ...truoc,
                                    [don.booking_id]: {
                                      booking_id: don.booking_id,
                                      action: "transfer",
                                      to_schedule_id:
                                        cancelPreview.impact.transfer_options[0]?.schedule_id ?? null,
                                    },
                                  }))
                                }
                                className="h-3.5 w-3.5 border-gray-300 text-primary-600 disabled:cursor-not-allowed"
                              />
                              Chuyển sang chuyến khác
                            </label>

                            {plan?.action === "transfer" && (
                              <select
                                value={String(plan.to_schedule_id ?? "")}
                                onChange={(e) =>
                                  setCancelPlans((truoc) => ({
                                    ...truoc,
                                    [don.booking_id]: {
                                      ...truoc[don.booking_id],
                                      to_schedule_id: Number(e.target.value),
                                    },
                                  }))
                                }
                                className="rounded border border-gray-200 px-2 py-1 text-xs outline-none focus:border-primary-400"
                              >
                                {cancelPreview.impact.transfer_options.map((item) => (
                                  <option key={item.schedule_id} value={item.schedule_id}>
                                    #{item.schedule_id} · {formatDateTime(item.start_date)} · còn{" "}
                                    {item.remaining_seats} chỗ
                                  </option>
                                ))}
                              </select>
                            )}
                          </div>

                          {cancelPreview.impact.transfer_options.length === 0 && (
                            <p className="text-[11px] text-gray-400">
                              Không có chuyến nào nhận được khách, nên chỉ còn cách hoàn tiền.
                            </p>
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Lý do hủy <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={2}
                    value={cancelReasonInput}
                    onChange={(e) => setCancelReasonInput(e.target.value)}
                    placeholder="VD: Nhà xe báo hỏng xe, không thu xếp được xe thay thế..."
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
                  />
                  <p className="text-[11px] text-gray-400 mt-1">
                    Khách sẽ đọc được nội dung này. Ít nhất 10 ký tự.
                  </p>
                </div>
              </>
            )}

            {cancelError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {cancelError}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={closeCancelDialog}
                disabled={cancelSaving}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Không hủy nữa
              </button>
              <button
                type="button"
                onClick={confirmCancelSchedule}
                disabled={
                  cancelSaving ||
                  cancelPreviewLoading ||
                  !cancelPreview?.impact.can_cancel ||
                  cancelReasonInput.trim().length < 10
                }
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-rose-600 hover:bg-rose-700 disabled:opacity-40"
              >
                {cancelSaving ? "Đang hủy..." : "Xác nhận hủy chuyến"}
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
