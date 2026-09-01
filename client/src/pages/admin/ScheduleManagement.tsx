import { useCallback, useEffect, useState, useMemo } from "react";
import { Link, useNavigate } from "react-router-dom";
import {
  CalendarDays,
  Clock,
  Users,
  Search,
  Filter,
  AlertTriangle,
  RotateCcw,
  CheckCircle2,
  ChevronDown,
  ClipboardCheck,
  GitMerge,
  Lock,
  Unlock,
} from "lucide-react";
import { TableActions } from "@/components/admin/TableActions";
import adminService from "@/services/adminService";
import type {
  CancelPlan,
  DeadlineImpactResponse,
  HandoverPanelResponse,
  PendingHandoverRequest,
  ScheduleCancelPreviewResponse,
  ScheduleManifestResponse,
  MergeCandidatesResponse,
} from "@/services/adminService";
import type { Tour, Guide, ExtendedSchedule, GuideDecline, GuideSuitability } from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime, formatPrice, getEndDate, toDateTimeLocalValue } from "@/utils/format";
import { LY_DO_DOI_HAN_TOI_THIEU, statusLabel, statusClasses } from "@/utils/schedule";
import Pagination from "@/components/common/Pagination";
import { DateTimePicker } from "@/components/DateTimePicker";

type ScheduleStatus = ExtendedSchedule["status"];

/** Chuyến đã kết thúc vòng đời thì không còn gì để xử lý. */
const conSong = (s: ExtendedSchedule) => {
  const status = s.status || "open";
  return status !== "cancelled" && status !== "completed";
};

const thieuNguoiDan = (s: ExtendedSchedule) => conSong(s) && (s.guides ?? []).length === 0;

const quaHanConMoBan = (s: ExtendedSchedule, bayGio: number) =>
  (s.status || "open") === "open" &&
  s.booking_deadline !== null &&
  s.booking_deadline !== undefined &&
  new Date(s.booking_deadline).getTime() < bayGio;

/**
 * Chuyến đã tới hạn chốt mà số khách ĐÃ TRẢ TIỀN chưa đạt mức tối thiểu.
 *
 * So `paid_people` chứ không so `booked_people`: chỗ đang giữ mà chưa trả tiền thì có thể biến
 * mất bất cứ lúc nào, và lệnh nền `ConfirmReadySchedules` cũng đếm đúng con số này khi quyết chốt
 * chuyến hay không. Hai bên nhìn hai con số khác nhau thì màn hình báo đủ khách trong khi tác vụ
 * nền lặng lẽ không chốt.
 *
 * Chỉ tính khi đã qua hạn chốt. Trước đó thiếu khách là chuyện bình thường — chuyến còn đang bán.
 */
const thieuKhachToiThieu = (s: ExtendedSchedule, bayGio: number) => {
  if (!conSong(s)) return false;
  if (s.status === "confirmed" || s.status === "in_progress") return false;
  if (!s.booking_deadline || new Date(s.booking_deadline).getTime() >= bayGio) return false;

  return (s.paid_people ?? 0) < (s.min_people || 1);
};

const THU_TU_TRANG_THAI: ScheduleStatus[] = [
  "open",
  "closed",
  "confirmed",
  "in_progress",
  "completed",
  "cancelled",
];

export default function ScheduleManagement() {
  const navigate = useNavigate();
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
  // Ai đã từ chối chuyến đang mở hộp thoại, kèm lý do.
  const [declines, setDeclines] = useState<GuideDecline[]>([]);
  // Cả đội ngũ đã chấm cho chuyến đang mở: ai hợp, ai bị chặn, và vì sao.
  const [suitability, setSuitability] = useState<GuideSuitability[]>([]);

  // Bàn giao giữa chừng. Tách khỏi phân công vì bắt buộc kèm lý do và tình trạng đoàn.
  const [handoverScheduleId, setHandoverScheduleId] = useState<number | null>(null);
  const [handoverPanel, setHandoverPanel] = useState<HandoverPanelResponse | null>(null);
  const [handoverForm, setHandoverForm] = useState({
    from_guide_id: 0,
    to_guide_id: 0,
    reason: "",
    handover_note: "",
  });
  const [handoverSaving, setHandoverSaving] = useState(false);
  const [handoverError, setHandoverError] = useState("");

  /*
   * Yêu cầu bàn giao đang chờ — ở đây chỉ để báo, không xử lý.
   *
   * Việc duyệt nằm trọn ở /admin/handovers. Trước đó nó có một bản sao ngay trong trang này, tức
   * hai chỗ dựng cùng một hộp thoại chọn người thay; sửa luật ở một chỗ mà quên chỗ kia là chuyện
   * sớm muộn.
   */
  const [handoverRequests, setHandoverRequests] = useState<PendingHandoverRequest[]>([]);

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
  const [dangXuatDanhSach, setDangXuatDanhSach] = useState(false);
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

  /*
   * Gom chuyến theo tour.
   *
   * Một tour bán quanh năm thì có vài chục chuyến, và bảng phẳng cũ lặp lại tên tour ấy vài chục
   * lần — cuộn mười trang mà vẫn chỉ đang xem đúng ba sản phẩm. Gom lại thì mỗi tour một hàng,
   * bấm vào mới mở ra các chuyến của nó.
   *
   * Phần tóm tắt trên hàng tour phải nói đủ để KHÔNG cần mở ra: bao nhiêu chuyến, chuyến gần
   * nhất là ngày nào, và có bao nhiêu chuyến đang cần xử lý. Nếu thu gọn mà giấu mất vấn đề thì
   * còn tệ hơn bảng phẳng.
   */
  const tourGroups = useMemo(() => {
    const bayGio = Date.now();
    const theoTour = new Map<
      number,
      { tour_id: number; tour_title: string; schedules: ExtendedSchedule[] }
    >();

    for (const schedule of filteredSchedules) {
      let nhom = theoTour.get(schedule.tour_id);
      if (!nhom) {
        nhom = { tour_id: schedule.tour_id, tour_title: schedule.tour_title, schedules: [] };
        theoTour.set(schedule.tour_id, nhom);
      }
      nhom.schedules.push(schedule);
    }

    return [...theoTour.values()].map((nhom) => {
      const schedules = [...nhom.schedules].sort(
        (a, b) => new Date(a.start_date).getTime() - new Date(b.start_date).getTime(),
      );

      /*
       * Đếm theo thứ tự vòng đời chứ không theo thứ tự gặp phải, để dãy nhãn trên mỗi hàng tour
       * luôn đọc cùng một chiều: mở bán → đóng bán → chốt → đang chạy → xong → hủy.
       */
      const dem = schedules.reduce<Partial<Record<ScheduleStatus, number>>>((tong, s) => {
        const status = (s.status || "open") as ScheduleStatus;
        tong[status] = (tong[status] ?? 0) + 1;
        return tong;
      }, {});

      const demTrangThai = THU_TU_TRANG_THAI.filter((status) => dem[status]).map((status) => ({
        status,
        soLuong: dem[status] as number,
      }));

      const sapToi = schedules.find(
        (s) =>
          new Date(s.start_date).getTime() >= bayGio &&
          s.status !== "cancelled" &&
          s.status !== "completed",
      );

      /*
       * "Cần xử lý" = chuyến còn sống mà thiếu một trong ba thứ điều hành phải lo: chưa có người
       * dẫn, đã qua hạn chốt danh sách mà vẫn đang mở bán, hoặc **không đủ khách tối thiểu**.
       */
      const canXuLy = schedules.filter(
        (s) => thieuNguoiDan(s) || quaHanConMoBan(s, bayGio) || thieuKhachToiThieu(s, bayGio),
      ).length;

      /*
       * Chuyến thiếu khách tách riêng, vì nó là loại việc khác hẳn: hai cái kia sửa bằng một
       * thao tác, còn cái này buộc phải chọn giữa hủy chuyến và chạy lỗ.
       */
      const thieuKhach = schedules.filter((s) => thieuKhachToiThieu(s, bayGio)).length;

      return { ...nhom, schedules, demTrangThai, sapToi, canXuLy, thieuKhach };
    });
  }, [filteredSchedules]);

  // Phân trang giờ đếm theo TOUR, không phải theo chuyến.
  const totalItems = tourGroups.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

  const paginatedGroups = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return tourGroups.slice(startIndex, startIndex + itemsPerPage);
  }, [tourGroups, currentPage, itemsPerPage]);

  const [expandedTourIds, setExpandedTourIds] = useState<number[]>([]);

  const toggleTour = (tourId: number) =>
    setExpandedTourIds((truoc) =>
      truoc.includes(tourId) ? truoc.filter((id) => id !== tourId) : [...truoc, tourId],
    );

  /*
   * Đang lọc thì bung sẵn mọi nhóm khớp: người ta gõ tìm là để thấy chuyến, không phải để thấy
   * tên tour rồi bấm thêm một lần nữa. Xóa bộ lọc thì thu hết về.
   *
   * Cố ý KHÔNG để `tourGroups` vào danh sách phụ thuộc. Mỗi lần phân công hướng dẫn viên hay đổi
   * trạng thái là dữ liệu tải lại và `tourGroups` là mảng mới — nếu phụ thuộc vào nó thì mọi
   * nhóm người dùng tự thu lại sẽ bung ra sau mỗi thao tác.
   */
  useEffect(() => {
    setCurrentPage(1);

    const dangLoc = searchQuery.trim() !== "" || statusFilter !== "all";
    setExpandedTourIds(dangLoc ? tourGroups.map((nhom) => nhom.tour_id) : []);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchQuery, statusFilter]);

  const loadHandoverRequests = useCallback(async () => {
    try {
      setHandoverRequests(await adminService.getPendingHandoverRequests());
    } catch (err) {
      console.error("Lỗi tải yêu cầu bàn giao:", err);
    }
  }, []);

  useEffect(() => {
    loadHandoverRequests();
  }, [loadHandoverRequests]);

  /**
   * Đoàn đang trên đường mà chỉ còn một người phụ trách — chưa bàn giao được.
   *
   * Phải phân công thêm một người cho chuyến trước, để sau khi một người rời đi vẫn còn ai đó
   * bên đoàn.
   */
  const canNhoTrongHo = handoverPanel?.blocked_needs_second_guide === true;

  const nguoiThayChonDuoc = handoverPanel?.available_guides ?? [];

  const khongCoAiNhoDuoc = canNhoTrongHo || nguoiThayChonDuoc.length === 0;

  const openHandoverDialog = async (scheduleId: number) => {
    setHandoverScheduleId(scheduleId);
    setHandoverPanel(null);
    setHandoverError("");
    setHandoverForm({ from_guide_id: 0, to_guide_id: 0, reason: "", handover_note: "" });

    try {
      const data = await adminService.getHandoverPanel(scheduleId);
      setHandoverPanel(data);
      setHandoverForm((truoc) => ({
        ...truoc,
        from_guide_id: data?.current_guides[0]?.id ?? 0,
        to_guide_id: data?.available_guides[0]?.id ?? 0,
      }));
    } catch (err) {
      console.error("Lỗi lấy thông tin bàn giao:", err);
    }
  };

  const confirmHandover = async () => {
    if (!handoverScheduleId) return;

    setHandoverSaving(true);
    setHandoverError("");

    try {
      const message = await adminService.handoverGuide(handoverScheduleId, handoverForm);

      setHandoverScheduleId(null);
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setHandoverError(response?.message || "Không bàn giao được.");
    } finally {
      setHandoverSaving(false);
    }
  };

  const openGuideDialog = async (schedule: ExtendedSchedule) => {
    setGuideDialogScheduleId(schedule.id);
    setPendingGuideIds((schedule.guides ?? []).map((guide) => guide.id));
    setDeclines([]);
    setSuitability([]);

    /*
     * Hai thứ đọc kèm, đều đúng lúc người ta đang chọn:
     *
     *   - Ai đã từ chối chuyến này. Từ chối gỡ người ra, nên nhìn bảng chỉ thấy thiếu người chứ
     *     không thấy đã có ai trả lời.
     *   - Chấm mức phù hợp. Đây là chỗ thay danh sách tên phẳng bằng danh sách biết ai hợp, ai
     *     bận, ai thẻ hết hạn.
     */
    try {
      const [daTuChoi, chamDiem] = await Promise.all([
        adminService.getScheduleGuideDeclines(schedule.id),
        adminService.getScheduleGuideSuitability(schedule.id),
      ]);

      setDeclines(daTuChoi);
      setSuitability(chamDiem);
    } catch (err) {
      console.error("Lỗi tải dữ liệu chọn hướng dẫn viên:", err);
    }
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

      setTours((currentTours) =>
        currentTours.map((t) => ({
          ...t,
          schedules: t.schedules?.map((item) => {
            if (item.id !== scheduleId) return item;

            /*
             * Chép lại mốc đã xác nhận của những người vẫn còn trong danh sách.
             *
             * Máy chủ giữ accepted_at qua mỗi lần sửa danh sách, nên nếu ở đây dựng lại thẻ từ
             * danh sách hướng dẫn viên chung - vốn không có dữ liệu bảng nối - thì thêm một
             * người là cả đoàn nhìn như chưa ai xác nhận, cho tới lần tải lại trang.
             */
            const truoc = new Map(
              (item.guides ?? []).map((g) => [g.id, g.pivot?.accepted_at ?? null]),
            );

            return {
              ...item,
              guides: guides
                .filter((g) => guideIds.includes(g.id))
                .map((g) => ({ ...g, pivot: { accepted_at: truoc.get(g.id) ?? null } })),
            };
          }),
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

  /* Q07 - Tải danh sách đoàn về máy để gửi khách sạn, nhà xe, hoặc in cho hướng dẫn viên. */
  const xuatDanhSachDoan = async () => {
    if (manifestScheduleId === null) return;

    setDangXuatDanhSach(true);

    try {
      await adminService.exportScheduleManifest(manifestScheduleId);
    } catch (err) {
      console.error("Lỗi xuất danh sách đoàn:", err);
      setToast({ isOpen: true, type: "error", message: "Không tạo được tệp danh sách đoàn." });
    } finally {
      setDangXuatDanhSach(false);
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

      {/*
        Yêu cầu bàn giao đang chờ — đặt ngay dưới tiêu đề, trên cả bộ lọc.

        Hướng dẫn viên gửi lên đúng lúc họ không dẫn tiếp được, mà đoàn thì đang trên đường. Nằm
        dưới bảng chuyến thì phải cuộn hết trang mới thấy, và thứ này không chờ được.
      */}
      {handoverRequests.length > 0 && (
        <Link
          to="/admin/handovers"
          className="flex flex-wrap items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm shadow-sm hover:bg-amber-100/60 transition-colors"
        >
          <AlertTriangle className="h-4 w-4 text-amber-700" />
          <span className="font-bold text-amber-900">
            {handoverRequests.length} yêu cầu bàn giao đang chờ bạn cử người thay
          </span>
          <span className="text-xs text-amber-800">
            {handoverRequests[0].requester_name}
            {handoverRequests.length > 1 ? ` và ${handoverRequests.length - 1} người nữa` : ""}
          </span>
          <span className="ml-auto text-xs font-semibold text-amber-900 underline">
            Xử lý ngay
          </span>
        </Link>
      )}

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
              {/*
                Bảy cột, không còn cột "Tour du lịch": tên tour giờ nằm trên hàng nhóm phía trên,
                lặp lại ở từng chuyến chỉ tốn chỗ.
              */}
              <thead>
                <tr className="bg-slate-50 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase tracking-wider">
                  <th className="py-4 px-5">Mã chuyến</th>
                  <th className="py-4 px-5">Thời gian khởi hành</th>
                  <th className="py-4 px-5">Hạn đặt (Deadline)</th>
                  <th className="py-4 px-5">Số khách (Min/Max)</th>
                  <th className="py-4 px-5">Hướng dẫn viên</th>
                  <th className="py-4 px-5">Trạng thái</th>
                  <th className="py-4 px-5 text-right">Vận hành & Thao tác</th>
                </tr>
              </thead>

              {paginatedGroups.map((nhom) => {
                const dangMoNhom = expandedTourIds.includes(nhom.tour_id);

                return (
                  <tbody key={nhom.tour_id} className="border-b border-gray-100">
                    {/* HÀNG TOUR — bấm để mở/đóng các chuyến bên dưới */}
                    <tr className={dangMoNhom ? "bg-slate-50" : "hover:bg-slate-50/60 transition-colors"}>
                      {/*
                        Nút mở/đóng và liên kết sang trang tour là hai phần tử cạnh nhau, không
                        lồng nhau: một thẻ <a> nằm trong <button> là HTML sai và trình duyệt xử lý
                        mỗi nơi một kiểu.
                      */}
                      <td colSpan={7} className="p-0">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 px-5 py-3.5">
                        <button
                          type="button"
                          onClick={() => toggleTour(nhom.tour_id)}
                          aria-expanded={dangMoNhom}
                          className="flex flex-1 min-w-0 flex-wrap items-center gap-x-3 gap-y-1.5 text-left cursor-pointer"
                        >
                          <ChevronDown
                            className={`h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 ${dangMoNhom ? "" : "-rotate-90"}`}
                          />

                          <span className="font-bold text-gray-900 text-sm">{nhom.tour_title}</span>

                          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                            {nhom.schedules.length} chuyến
                          </span>

                          {/*
                            Đếm theo trạng thái, dùng lại đúng bảng màu của hàng chuyến bên dưới,
                            để hai tầng không nói hai thứ tiếng.
                          */}
                          {nhom.demTrangThai.map(({ status, soLuong }) => (
                            <span
                              key={status}
                              className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusClasses[status]}`}
                            >
                              {soLuong} {statusLabel[status].toLowerCase()}
                            </span>
                          ))}

                          {nhom.canXuLy > 0 && (
                            <span
                              className="rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800"
                              title="Chuyến chưa phân công hướng dẫn viên, quá hạn chốt mà vẫn mở bán, hoặc chưa đủ khách tối thiểu"
                            >
                              {nhom.canXuLy} cần xử lý
                            </span>
                          )}

                          {/*
                            Thiếu khách tách thành nhãn riêng, màu nặng hơn: hai loại việc kia sửa
                            bằng một thao tác, còn cái này buộc phải chọn giữa hủy chuyến và chạy
                            lỗ — và chọn muộn thì mất luôn quyền hủy.
                          */}
                          {nhom.thieuKhach > 0 && (
                            <span
                              className="rounded-full border border-rose-300 bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-800"
                              title="Đã qua hạn chốt mà số khách đã thanh toán chưa đạt mức tối thiểu"
                            >
                              {nhom.thieuKhach} chưa đủ khách
                            </span>
                          )}
                        </button>

                        <span className="text-xs text-gray-500 whitespace-nowrap">
                          {nhom.sapToi
                            ? `Gần nhất: ${formatDateTime(nhom.sapToi.start_date)}`
                            : "Không còn chuyến sắp tới"}
                        </span>

                        <Link
                          to={`/admin/tours/${nhom.tour_id}`}
                          className="shrink-0 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-primary-600 hover:bg-primary-50 transition-colors"
                        >
                          Xem tour
                        </Link>
                        </div>
                      </td>
                    </tr>

                    {dangMoNhom &&
                      nhom.schedules.map((schedule) => {
                        const status = schedule.status || "open";
                        const deadline = schedule.booking_deadline;
                        const minPeople = schedule.min_people || 5;
                        const isOverdue = deadline ? new Date(deadline) < new Date() : false;

                        return (
                          <tr key={schedule.id} className="border-t border-gray-100 hover:bg-slate-50/50 transition-colors text-sm text-gray-700">
                            {/* Mã chuyến */}
                            <td className="py-4 px-5 font-bold text-primary-700 font-mono">
                              #{schedule.id}
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
                                  {/*
                                    Số ĐÃ TRẢ TIỀN mới quyết định chuyến có chốt được không. Khi
                                    thiếu thì nói thẳng con số ấy ra, thay vì chỉ ghi mức tối thiểu
                                    rồi để người đọc tự trừ với một số khác.
                                  */}
                                  {thieuKhachToiThieu(schedule, Date.now()) ? (
                                    <p className="text-xs font-bold text-rose-600 mt-0.5">
                                      Mới {schedule.paid_people ?? 0}/{minPeople} khách đã trả tiền
                                    </p>
                                  ) : (
                                    <p className="text-xs text-gray-400 mt-0.5">
                                      Tối thiểu: {minPeople} khách
                                    </p>
                                  )}
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
                                    /*
                                      Chưa xác nhận thì thẻ nhạt đi và có dấu chấm.

                                      Vẫn là đã phân công — người ta có tên trong đoàn — nhưng chưa ai
                                      trả lời là chưa chắc họ biết. Phân biệt được thì mới còn nhắc,
                                      chứ hai thứ nhìn giống nhau thì đến ngày đi mới biết.
                                    */
                                    <span
                                      key={guide.id}
                                      title={
                                        guide.pivot?.accepted_at
                                          ? `Đã xác nhận ${formatDateTime(guide.pivot.accepted_at)}`
                                          : "Chưa xác nhận nhận chuyến"
                                      }
                                      className={`flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-semibold ${
                                        guide.pivot?.accepted_at
                                          ? "bg-gray-100 text-gray-700"
                                          : "border border-dashed border-amber-300 bg-amber-50 text-amber-800"
                                      }`}
                                    >
                                      {!guide.pivot?.accepted_at && (
                                        <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
                                      )}
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

                            {/*
                              Vận hành & Thao tác — gói sau một nút bánh răng.

                              Trước đây tám nút trải ngang trên cùng một hàng: cột này rộng hơn cả cột
                              dữ liệu, và muốn tìm đúng nút thì phải đọc hết tám nhãn. Danh sách dọc
                              trong menu đọc nhanh hơn hẳn, và hàng bảng gọn lại.

                              Thứ tự trong menu theo vòng đời chuyến chứ không theo mức độ hay tần suất:
                              xem trước, rồi bán, rồi chốt, rồi điều người, cuối cùng mới tới hủy. Ai
                              quen vòng đời thì đoán được vị trí mà không cần đọc.
                            */}
                            <td className="py-4 px-5 text-right whitespace-nowrap">
                              <div className="flex items-center gap-2 justify-end">
                                {(status === "completed" || status === "cancelled") && (
                                  <span className="text-caption-sm text-muted-soft italic">
                                    Đã kết thúc vòng đời
                                  </span>
                                )}

                                <TableActions
                                  id={schedule.id}
                                  label="Vận hành chuyến"
                                  actions={[
                                    /*
                                      G05 - Danh sách đoàn theo từng nhóm. Trả lời hai câu ở cùng một
                                      chỗ: gửi cho nhà cung cấp được chưa, và nhóm này gồm những ai.
                                    */
                                    ...(status !== "cancelled"
                                      ? [{
                                          label: "Danh sách đoàn",
                                          onClick: () => openManifestCheck(schedule.id),
                                          icon: <Users className="w-4 h-4" />,
                                        }]
                                      : []),

                                    {
                                      label: "Xem điểm danh",
                                      onClick: () => navigate(`/admin/tour-schedules/${schedule.id}/attendance`),
                                      icon: <ClipboardCheck className="w-4 h-4" />,
                                    },

                                    ...(status === "open"
                                      ? [{
                                          label: "Đóng bán",
                                          onClick: () => handleUpdateStatus(schedule.id, "closed"),
                                          icon: <Lock className="w-4 h-4" />,
                                        }]
                                      : []),

                                    ...(status === "closed"
                                      ? [{
                                          label: "Mở bán lại",
                                          onClick: () => handleUpdateStatus(schedule.id, "open"),
                                          icon: <Unlock className="w-4 h-4" />,
                                        }]
                                      : []),

                                    /* Dời hạn chốt. Chuyến đã chạy hoặc đã xong thì mốc này hết nghĩa. */
                                    ...(status === "open" || status === "closed" || status === "confirmed"
                                      ? [{
                                          label: "Sửa hạn chốt danh sách",
                                          onClick: () => openDeadlineDialog(schedule),
                                          icon: <Clock className="w-4 h-4" />,
                                        }]
                                      : []),

                                    ...(status === "open" || status === "closed"
                                      ? [{
                                          label: "Chốt chuyến",
                                          onClick: () => handleUpdateStatus(schedule.id, "confirmed"),
                                          icon: <CheckCircle2 className="w-4 h-4" />,
                                          variant: "success" as const,
                                        }]
                                      : []),

                                    /* L03 - Ghép chuyến: chỉ có nghĩa khi chưa khởi hành và ít khách. */
                                    ...(status === "open" || status === "closed" || status === "confirmed"
                                      ? [{
                                          label: "Ghép chuyến",
                                          onClick: () => openMergeDialog(schedule.id),
                                          icon: <GitMerge className="w-4 h-4" />,
                                        }]
                                      : []),

                                    /* Bàn giao: chỉ có nghĩa khi đoàn sắp hoặc đã lên đường và đang có
                                       người phụ trách để mà giao. */
                                    ...((status === "confirmed" || status === "in_progress") &&
                                    (schedule.guides ?? []).length > 0
                                      ? [{
                                          label: "Bàn giao hướng dẫn viên",
                                          onClick: () => openHandoverDialog(schedule.id),
                                          icon: <RotateCcw className="w-4 h-4" />,
                                          variant: "warning" as const,
                                        }]
                                      : []),

                                    /* Nguy hiểm nằm cuối, TableActions tự chèn đường kẻ tách phía trên. */
                                    ...(status === "open" || status === "closed" || status === "confirmed"
                                      ? [{
                                          label: "Hủy chuyến",
                                          hint: "Phải gán phương án cho từng đơn đã thu tiền",
                                          onClick: () => openCancelDialog(schedule.id),
                                          icon: <AlertTriangle className="w-4 h-4" />,
                                          variant: "danger" as const,
                                        }]
                                      : []),
                                  ]}
                                />
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                  </tbody>
                );
              })}
            </table>
          </div>

          {/* PAGINATION PANEL */}
          <div className="bg-slate-50 border-t border-gray-100 px-5 py-3">
            <Pagination
              currentPage={currentPage}
              lastPage={totalPages}
              total={totalItems}
              perPage={itemsPerPage}
              itemLabel="tour"
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


      {/* Bàn giao hướng dẫn viên giữa chừng */}
      {handoverScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Bàn giao hướng dẫn viên — chuyến #{handoverScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Người cũ mất quyền ghi ngay khi lưu. Dữ liệu họ đã ghi giữ nguyên, chỉ chuyển
                quyền ghi tiếp.
              </p>
            </div>

            {!handoverPanel && <p className="text-sm text-gray-500">Đang tải...</p>}

            {canNhoTrongHo && (
              <div
                className={`rounded-lg border px-4 py-3 text-sm ${
                  khongCoAiNhoDuoc
                    ? "border-rose-200 bg-rose-50 text-rose-800"
                    : "border-amber-200 bg-amber-50 text-amber-900"
                }`}
              >
                <p className="font-semibold">
                  {khongCoAiNhoDuoc
                    ? "Chưa bàn giao được."
                    : "Đoàn chỉ còn một người — chỉ nhờ được đoàn khác."}
                </p>
                <p className="text-xs mt-0.5">
                  {khongCoAiNhoDuoc ? (
                    <>
                      Đoàn đang trên đường và không có hướng dẫn viên nào khác đang dẫn đoàn cùng
                      lúc để nhờ. Hãy bấm <strong>Sửa</strong> ở cột hướng dẫn viên phân công thêm
                      một người cho chuyến, rồi quay lại đây.
                    </>
                  ) : (
                    <>
                      Gỡ người dẫn duy nhất ra thì đoàn không có ai cho tới khi người mới tới nơi.
                      Nên chỉ chọn được người <strong>đang dẫn một đoàn khác</strong> — họ đã ở
                      ngoài đường. Người đó sẽ tạm giữ hai đoàn, hệ thống đánh dấu để bạn xử lý tiếp.
                    </>
                  )}
                </p>
              </div>
            )}

            {handoverPanel && (
              <>
                {nguoiThayChonDuoc.length === 0 ? (
                  <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {canNhoTrongHo
                      ? "Không có hướng dẫn viên nào đang dẫn đoàn khác để nhờ."
                      : "Không còn hướng dẫn viên nào khác đang hoạt động để nhận đoàn."}
                  </p>
                ) : (
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Người giao</label>
                      <select
                        value={handoverForm.from_guide_id}
                        onChange={(e) =>
                          setHandoverForm((truoc) => ({ ...truoc, from_guide_id: Number(e.target.value) }))
                        }
                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-amber-400"
                      >
                        {handoverPanel.current_guides.map((g) => (
                          <option key={g.id} value={g.id}>
                            {g.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Người nhận</label>
                      <select
                        value={handoverForm.to_guide_id}
                        onChange={(e) =>
                          setHandoverForm((truoc) => ({ ...truoc, to_guide_id: Number(e.target.value) }))
                        }
                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-amber-400"
                      >
                        {nguoiThayChonDuoc.map((g) => (
                          <option key={g.id} value={g.id}>
                            {g.name}
                            {g.leading_other_group ? " — đang dẫn đoàn khác" : ""}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Lý do thay <span className="text-rose-500">*</span>
                  </label>
                  <input
                    value={handoverForm.reason}
                    onChange={(e) => setHandoverForm((truoc) => ({ ...truoc, reason: e.target.value }))}
                    placeholder="VD: Hướng dẫn viên cũ bị sốt cao, phải về sớm..."
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-amber-400"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Tình trạng đoàn <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={3}
                    value={handoverForm.handover_note}
                    onChange={(e) =>
                      setHandoverForm((truoc) => ({ ...truoc, handover_note: e.target.value }))
                    }
                    placeholder="Đoàn đang ở đâu, đã điểm danh tới chặng nào, khách nào cần để ý, việc gì đang dở..."
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-amber-400"
                  />
                  <p className="mt-1 text-[11px] text-gray-400">
                    Ít nhất 20 ký tự. Người nhận chỉ có đúng đoạn này để bắt nhịp với đoàn.
                  </p>
                </div>

                {/* Lịch sử: chuyến đổi người nhiều lần thì đây là chỗ lần ra ai dẫn lúc nào */}
                {handoverPanel.handovers.length > 0 && (
                  <div className="space-y-1.5">
                    <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      Đã bàn giao trước đó
                    </p>
                    {handoverPanel.handovers.map((bg) => (
                      <div key={bg.id} className="rounded-lg border border-gray-200 p-2.5 text-xs">
                        <p className="font-semibold text-gray-900">
                          {bg.from_guide?.name} → {bg.to_guide?.name}
                          <span className="ml-2 font-normal text-gray-500">
                            {formatDateTime(bg.handed_over_at)}
                          </span>
                        </p>
                        <p className="text-gray-600">{bg.reason}</p>
                        <p className="mt-0.5 text-gray-500">{bg.handover_note}</p>
                      </div>
                    ))}
                  </div>
                )}
              </>
            )}

            {handoverError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {handoverError}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setHandoverScheduleId(null)}
                disabled={handoverSaving}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Quay lại
              </button>
              <button
                type="button"
                onClick={confirmHandover}
                disabled={
                  handoverSaving ||
                  khongCoAiNhoDuoc ||
                  !handoverForm.from_guide_id ||
                  !handoverForm.to_guide_id ||
                  handoverForm.reason.trim().length < 10 ||
                  handoverForm.handover_note.trim().length < 20
                }
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-40"
              >
                {handoverSaving ? "Đang lưu..." : "Xác nhận bàn giao"}
              </button>
            </div>
          </div>
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
              <Link
                to="/admin/guides"
                className="mt-1 inline-block text-[11px] font-semibold text-primary-600 hover:underline"
              >
                Sửa hồ sơ năng lực hướng dẫn viên →
              </Link>
            </div>

            {/*
              Danh sách đã chấm, không còn là danh sách tên phẳng.

              Ba mức, và khác nhau thật chứ không chỉ khác màu:
                - Bị chặn: ô chọn khóa lại, kèm đúng câu máy chủ sẽ từ chối. Vẫn hiện, vì giấu đi
                  thì người ta đi tìm mãi một cái tên đáng lẽ phải có.
                - Cảnh báo: nói ra rồi thôi, vẫn bấm được. Quá sức dẫn hay đang gánh nhiều chuyến
                  là chuyện điều hành cân, không phải chuyện hệ thống cấm.
                - Điểm hợp: hiện thành chữ ("Chuyên Biển đảo", "Quen tuyến Hạ Long") chứ không
                  phải một con số — xếp hạng mà không nói vì sao thì hoặc bị tin mù, hoặc bị bỏ qua.
            */}
            <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
              {suitability.length === 0 && (
                <p className="px-1 py-2 text-xs text-gray-500">Đang tải danh sách...</p>
              )}

              {suitability.map((ung) => {
                const biChan = ung.blocked_reason !== null;
                const daChon = pendingGuideIds.includes(ung.id);

                return (
                  <label
                    key={ung.id}
                    className={`flex gap-2 rounded px-1.5 py-1.5 text-sm ${
                      biChan
                        ? "cursor-not-allowed bg-gray-50 opacity-70"
                        : "cursor-pointer hover:bg-gray-50"
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={daChon}
                      disabled={biChan}
                      onChange={() =>
                        setPendingGuideIds((truoc) =>
                          truoc.includes(ung.id)
                            ? truoc.filter((id) => id !== ung.id)
                            : [...truoc, ung.id],
                        )
                      }
                      className="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600"
                    />

                    <span className="min-w-0 flex-1 space-y-0.5">
                      <span className="flex flex-wrap items-center gap-1.5">
                        <span className="font-medium text-gray-800">{ung.name}</span>

                        {ung.matches.map((hop) => (
                          <span
                            key={hop}
                            className="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                          >
                            {hop}
                          </span>
                        ))}

                        {ung.workload > 0 && (
                          <span className="text-[11px] text-gray-400">
                            {ung.workload} chuyến quanh ngày này
                          </span>
                        )}
                      </span>

                      {biChan && (
                        <span className="block text-[11px] font-medium text-rose-700">
                          {ung.blocked_reason}
                        </span>
                      )}

                      {ung.warnings.map((canBiet) => (
                        <span key={canBiet} className="block text-[11px] text-amber-700">
                          {canBiet}
                        </span>
                      ))}

                      {ung.languages.length > 0 && (
                        <span className="block text-[11px] text-gray-400">
                          {ung.languages.join(", ")}
                        </span>
                      )}
                    </span>
                  </label>
                );
              })}
            </div>

            {/*
              Ai đã từ chối chuyến này.

              Không chặn gán lại — có khi người ta đổi lịch được, hoặc bạn đã gọi điện xong. Chỉ
              là bạn nên biết trước khi tích lại đúng cái tên vừa nói không.
            */}
            {declines.length > 0 && (
              <div className="rounded-lg border border-rose-100 bg-rose-50/60 p-3 space-y-2">
                <p className="text-xs font-bold text-rose-800">
                  Đã từ chối chuyến này ({declines.length})
                </p>
                {declines.map((tc) => (
                  <div key={tc.id} className="text-xs text-rose-900">
                    <span className="font-semibold">{tc.guide_name ?? "Không rõ"}</span>
                    <span className="text-rose-700/70"> · {formatDateTime(tc.declined_at)}</span>
                    <p className="text-rose-800/90">{tc.reason}</p>
                  </div>
                ))}
              </div>
            )}

            <p className="text-[11px] text-gray-400">
              Xếp theo mức hợp với tour: chuyên đúng loại hình và quen tuyến lên trước, đang gánh
              nhiều chuyến thì lùi xuống. Chỉ đúng một thứ thật sự chặn — trùng lịch, vì một người
              không đứng ở hai đoàn cùng lúc. Phần còn lại chỉ là gợi ý, bạn vẫn quyết.
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
              <DateTimePicker
                withTime
                value={deadlineValue}
                onChange={setDeadlineValue}
                placeholder="Để trống dùng mốc mặc định"
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
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Lý do dời hạn <span className="text-red-500">*</span>
              </label>
              <textarea
                rows={2}
                value={deadlineReason}
                onChange={(e) => setDeadlineReason(e.target.value)}
                placeholder="VD: Khách sạn cho thêm 2 phòng, chốt lại ngày 19/08..."
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                Bắt buộc, ít nhất {LY_DO_DOI_HAN_TOI_THIEU} ký tự. Ba tháng nữa người đọc nhật ký cần biết
                vì sao mốc bị dời, và lúc đó không ai nhớ lại giúp được.
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
                  deadlineImpact?.impact.direction === "unchanged" ||
                  deadlineReason.trim().length < LY_DO_DOI_HAN_TOI_THIEU
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

                {/*
                  Q07 - Đưa danh sách ra khỏi màn hình.

                  Cho tải cả khi còn khai thiếu: điều hành cần bản nháp để đối chiếu và để biết
                  còn thiếu ai — tệp ghi rõ nhóm nào chưa khai. Ô cảnh báo phía trên đã nói đủ về
                  việc gửi ra ngoài, chặn thêm ở đây chỉ khiến người ta chép tay.
                */}
                <button
                  type="button"
                  onClick={xuatDanhSachDoan}
                  disabled={dangXuatDanhSach}
                  className="w-full rounded-lg border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-bold text-primary-700 hover:bg-primary-100 disabled:opacity-50 transition-colors"
                >
                  {dangXuatDanhSach ? "Đang tạo tệp..." : "Tải danh sách đoàn (Excel)"}
                </button>

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
