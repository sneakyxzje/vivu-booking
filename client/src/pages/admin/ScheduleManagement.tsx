import {
  Button as AntButton,
  Card as UICard,
  Checkbox as AntCheckbox,
  Collapse as AntCollapse,
  Flex as UIFlex,
  Input as AntInput,
  Modal as AntModal,
  Radio as AntRadio,
  Select as AntSelect,
  Table as AntTable,
  Tag as AntTag,
  Typography as AntTypography,
} from "antd";
import { useCallback, useEffect, useState, useMemo } from "react";
import { useLatestRequest } from "@/components/admin/useLatestRequest";
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
  ClipboardCheck,
  GitMerge,
  Lock,
  Unlock,
} from "lucide-react";
import { TableActions } from "@/components/admin/TableActions";
import { ScheduleMergeDialog } from "@/components/admin/ScheduleMergeDialog";
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
import type {
  Tour,
  Guide,
  ExtendedSchedule,
  GuideDecline,
  GuideSuitability,
} from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import {
  formatDateTime,
  formatPrice,
  getEndDate,
  toDateTimeLocalValue,
} from "@/utils/format";
import { BulkProposalDialog } from "@/components/admin/BulkProposalDialog";
import {
  LY_DO_DOI_HAN_TOI_THIEU,
  statusLabel,
  statusClasses,
} from "@/utils/schedule";
import Pagination from "@/components/admin/AdminPagination";
import { DateTimePicker } from "@/components/admin/AdminDateTimePicker";

type ScheduleStatus = ExtendedSchedule["status"];

/** Chuyến đã kết thúc vòng đời thì không còn gì để xử lý. */
const conSong = (s: ExtendedSchedule) => {
  const status = s.status || "open";
  return status !== "cancelled" && status !== "completed";
};

const thieuNguoiDan = (s: ExtendedSchedule) =>
  conSong(s) && (s.guides ?? []).length === 0;

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
  if (!s.booking_deadline || new Date(s.booking_deadline).getTime() >= bayGio)
    return false;

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
  const [assigningScheduleId, setAssigningScheduleId] = useState<number | null>(
    null,
  );
  // Phân công nhiều hướng dẫn viên cho một chuyến, sửa trong hộp thoại riêng.
  const [guideDialogScheduleId, setGuideDialogScheduleId] = useState<
    number | null
  >(null);
  const [pendingGuideIds, setPendingGuideIds] = useState<number[]>([]);
  // Ai đã từ chối chuyến đang mở hộp thoại, kèm lý do.
  const [declines, setDeclines] = useState<GuideDecline[]>([]);
  // Cả đội ngũ đã chấm cho chuyến đang mở: ai hợp, ai bị chặn, và vì sao.
  const [suitability, setSuitability] = useState<GuideSuitability[]>([]);

  // Bàn giao giữa chừng. Tách khỏi phân công vì bắt buộc kèm lý do và tình trạng đoàn.
  const [handoverScheduleId, setHandoverScheduleId] = useState<number | null>(
    null,
  );
  const [handoverPanel, setHandoverPanel] =
    useState<HandoverPanelResponse | null>(null);
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
  const [handoverRequests, setHandoverRequests] = useState<
    PendingHandoverRequest[]
  >([]);

  // State Hủy chuyến
  // K - Hủy chuyến. Mỗi đơn đã thanh toán phải có một phương án trước khi hủy được.
  const [cancellingScheduleId, setCancellingScheduleId] = useState<
    number | null
  >(null);
  const [cancelReasonInput, setCancelReasonInput] = useState("");
  const [cancelPreview, setCancelPreview] =
    useState<ScheduleCancelPreviewResponse | null>(null);
  const [cancelPreviewLoading, setCancelPreviewLoading] = useState(false);
  const [cancelPlans, setCancelPlans] = useState<Record<number, CancelPlan>>(
    {},
  );
  const [cancelSaving, setCancelSaving] = useState(false);
  const [cancelError, setCancelError] = useState("");

  // G05 - Kiểm tra danh sách đoàn trước khi gửi nhà cung cấp
  const [manifestScheduleId, setManifestScheduleId] = useState<number | null>(
    null,
  );

  // State Gửi đề xuất hàng loạt
  const [bulkProposalSchedule, setBulkProposalSchedule] = useState<ExtendedSchedule | null>(null);

  const [dangXuatDanhSach, setDangXuatDanhSach] = useState(false);
  const [manifest, setManifest] = useState<ScheduleManifestResponse | null>(
    null,
  );
  const [manifestLoading, setManifestLoading] = useState(false);
  // Nhóm đang mở xem chi tiết. Mở sẵn tất cả thì đoàn đông thành một bức tường chữ.
  const [openGroupIds, setOpenGroupIds] = useState<number[]>([]);

  // L03 - Ghép chuyến
  const [mergeScheduleId, setMergeScheduleId] = useState<number | null>(null);
  const [mergeData, setMergeData] = useState<MergeCandidatesResponse | null>(
    null,
  );
  const [mergeLoading, setMergeLoading] = useState(false);
  const [mergeTargetId, setMergeTargetId] = useState<number | null>(null);
  const [mergeReason, setMergeReason] = useState("");
  const [mergeSaving, setMergeSaving] = useState(false);
  const [mergeError, setMergeError] = useState("");
  const { begin: beginMergeRequest, isCurrent: isCurrentMergeRequest, invalidate: invalidateMergeRequest } = useLatestRequest();

  // Dời hạn chốt danh sách. Xem docs/nghiep-vu/16-sua-han-chot.md.
  const [deadlineScheduleId, setDeadlineScheduleId] = useState<number | null>(
    null,
  );
  const [deadlineValue, setDeadlineValue] = useState("");
  const [deadlineReason, setDeadlineReason] = useState("");
  const [deadlineImpact, setDeadlineImpact] =
    useState<DeadlineImpactResponse | null>(null);
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
      })),
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
        nhom = {
          tour_id: schedule.tour_id,
          tour_title: schedule.tour_title,
          schedules: [],
        };
        theoTour.set(schedule.tour_id, nhom);
      }
      nhom.schedules.push(schedule);
    }

    return [...theoTour.values()].map((nhom) => {
      const schedules = [...nhom.schedules].sort(
        (a, b) =>
          new Date(a.start_date).getTime() - new Date(b.start_date).getTime(),
      );

      /*
       * Đếm theo thứ tự vòng đời chứ không theo thứ tự gặp phải, để dãy nhãn trên mỗi hàng tour
       * luôn đọc cùng một chiều: mở bán → đóng bán → chốt → đang chạy → xong → hủy.
       */
      const dem = schedules.reduce<Partial<Record<ScheduleStatus, number>>>(
        (tong, s) => {
          const status = (s.status || "open") as ScheduleStatus;
          tong[status] = (tong[status] ?? 0) + 1;
          return tong;
        },
        {},
      );

      const demTrangThai = THU_TU_TRANG_THAI.filter(
        (status) => dem[status],
      ).map((status) => ({
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
        (s) =>
          thieuNguoiDan(s) ||
          quaHanConMoBan(s, bayGio) ||
          thieuKhachToiThieu(s, bayGio),
      ).length;

      /*
       * Chuyến thiếu khách tách riêng, vì nó là loại việc khác hẳn: hai cái kia sửa bằng một
       * thao tác, còn cái này buộc phải chọn giữa hủy chuyến và chạy lỗ.
       */
      const thieuKhach = schedules.filter((s) =>
        thieuKhachToiThieu(s, bayGio),
      ).length;

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
    setHandoverForm({
      from_guide_id: 0,
      to_guide_id: 0,
      reason: "",
      handover_note: "",
    });

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
      const message = await adminService.handoverGuide(
        handoverScheduleId,
        handoverForm,
      );

      setHandoverScheduleId(null);
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
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
              (item.guides ?? []).map((g) => [
                g.id,
                g.pivot?.accepted_at ?? null,
              ]),
            );

            return {
              ...item,
              guides: guides
                .filter((g) => guideIds.includes(g.id))
                .map((g) => ({
                  ...g,
                  pivot: { accepted_at: truoc.get(g.id) ?? null },
                })),
            };
          }),
        })),
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
        (error as { response?: { data?: { message?: string } } }).response?.data
          ?.message ?? "Không thể phân công hướng dẫn viên.";
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
      const updatedSchedule = await adminService.updateScheduleStatus(
        scheduleId,
        nextStatus,
        reason,
      );

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
        (error as { response?: { data?: { message?: string } } }).response?.data
          ?.message ?? "Không thể cập nhật trạng thái chuyến khởi hành.";
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
      setToast({
        isOpen: true,
        type: "error",
        message: "Không tạo được tệp danh sách đoàn.",
      });
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

  const openMergeDialog = useCallback(async (scheduleId: number) => {
    const requestId = beginMergeRequest();
    setMergeScheduleId(scheduleId);
    setMergeData(null);
    setMergeTargetId(null);
    setMergeReason("");
    setMergeError("");
    setMergeLoading(true);

    try {
      const data = await adminService.getMergeCandidates(scheduleId);
      if (!isCurrentMergeRequest(requestId)) return;
      if (!data) throw new Error("Missing merge preview");
      setMergeData(data);
    } catch (err) {
      console.error("Lỗi tải danh sách chuyến có thể ghép:", err);
      if (isCurrentMergeRequest(requestId)) {
        setMergeError("Không tải được chuyến nhận khách. Vui lòng thử lại.");
      }
    } finally {
      if (isCurrentMergeRequest(requestId)) setMergeLoading(false);
    }
  }, [beginMergeRequest, isCurrentMergeRequest]);

  const closeMergeDialog = () => {
    invalidateMergeRequest();
    setMergeScheduleId(null);
    setMergeData(null);
    setMergeTargetId(null);
    setMergeReason("");
    setMergeError("");
  };

  const confirmMerge = async () => {
    if (mergeSaving || mergeLoading || !mergeScheduleId ||
      !mergeData?.candidates.some((item) => item.schedule_id === mergeTargetId && item.can_merge) ||
      !mergeTargetId || mergeReason.trim().length < 10 || mergeReason.trim().length > 500)
      return;

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
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
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
        const data = await adminService.getDeadlineImpact(
          scheduleId,
          value || null,
        );
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
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
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
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
      setCancelError(response?.message || "Không hủy được chuyến.");
    } finally {
      setCancelSaving(false);
    }
  };

  return (
    <UIFlex vertical gap="large" >{/* HEADER */}<div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <AntTypography.Title level={3} >Quản lý Chuyến khởi hành
          </AntTypography.Title>
          <p className="text-sm text-gray-500">
            Quản lý chi tiết vòng đời chuyến đi, theo dõi thời hạn đăng ký, chốt
            chuyến chạy và hủy chuyến.
          </p>
        </div>
      </div>{/*
        Yêu cầu bàn giao đang chờ — đặt ngay dưới tiêu đề, trên cả bộ lọc.

        Hướng dẫn viên gửi lên đúng lúc họ không dẫn tiếp được, mà đoàn thì đang trên đường. Nằm
        dưới bảng chuyến thì phải cuộn hết trang mới thấy, và thứ này không chờ được.
      */}{handoverRequests.length > 0 && (
        <Link
          to="/admin/handovers"
          className="flex flex-wrap items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm shadow-sm hover:bg-amber-100/60 transition-colors"
        >
          <AlertTriangle className="h-4 w-4 text-amber-700" />
          <span className="font-bold text-amber-900">
            {handoverRequests.length} yêu cầu bàn giao đang chờ bạn cử người
            thay
          </span>
          <span className="text-xs text-amber-800">
            {handoverRequests[0].requester_name}
            {handoverRequests.length > 1
              ? ` và ${handoverRequests.length - 1} người nữa`
              : ""}
          </span>
          <span className="ml-auto text-xs font-semibold text-amber-900 underline">
            Xử lý ngay
          </span>
        </Link>
      )}{/* FILTER & SEARCH */}<div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <div  className="relative w-full sm:max-w-xs"><AntInput prefix={<Search size={16} />} type="text" placeholder="Tìm theo ID, tên tour..." value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} style={{ width: "100%" }} /></div>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <Filter className="h-4 w-4 text-gray-400" />
          <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((statusFilter) ?? "")} onChange={(e) => setStatusFilter(e)} style={{ width: "100%" }} options={[{ value: String("all"), label: "Tất cả trạng thái", disabled: false },{ value: String("open"), label: "Đang mở bán", disabled: false },{ value: String("closed"), label: "Đã đóng bán", disabled: false },{ value: String("confirmed"), label: "Đã chốt chạy", disabled: false },{ value: String("in_progress"), label: "Đang di chuyển", disabled: false },{ value: String("completed"), label: "Đã hoàn thành", disabled: false },{ value: String("cancelled"), label: "Đã hủy", disabled: false }].flat().filter((option) => !!option)} />

          {(searchQuery !== "" || statusFilter !== "all") && (
            <AntButton htmlType="button" title="Đặt lại bộ lọc" onClick={() => {
                setSearchQuery("");
                setStatusFilter("all");
                setCurrentPage(1);
              }} danger><RotateCcw className="h-4 w-4" /></AntButton>
          )}
        </div>
      </div>{/* SCHEDULES TABLE */}{loading ? (
        <UICard  ><UIFlex vertical gap="middle"><div className="h-8 bg-gray-100 rounded-lg animate-pulse" />{[1, 2, 3, 4, 5].map((n) => (
            <div key={n} className="h-14 bg-gray-50 rounded-lg animate-pulse" />
          ))}</UIFlex></UICard>
      ) : filteredSchedules.length ? (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
          <div className="overflow-x-auto">
            <AntCollapse activeKey={expandedTourIds.map(String)} onChange={(keys) => setExpandedTourIds((Array.isArray(keys) ? keys : [keys]).map(Number))}
  items={paginatedGroups.map((nhom) => ({
    key: String(nhom.tour_id),
    label: <UIFlex wrap gap="small" align="center">
      <AntTypography.Text strong>{nhom.tour_title}</AntTypography.Text><AntTag>{nhom.schedules.length} chuyến</AntTag>
      {nhom.demTrangThai.map(({ status, soLuong }) => <AntTag key={status}>{soLuong} {statusLabel[status].toLowerCase()}</AntTag>)}
      {nhom.canXuLy > 0 && <AntTag color="warning">{nhom.canXuLy} cần xử lý</AntTag>}
      {nhom.thieuKhach > 0 && <AntTag color="error">{nhom.thieuKhach} chưa đủ khách</AntTag>}
      <AntTypography.Text type="secondary">{nhom.sapToi ? "Gần nhất: " + formatDateTime(nhom.sapToi.start_date) : "Không còn chuyến sắp tới"}</AntTypography.Text>
    </UIFlex>,
    extra: <Link to={"/admin/tours/" + nhom.tour_id} onClick={(event) => event.stopPropagation()}>Xem tour</Link>,
    children: <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }} dataSource={nhom.schedules.map((schedule) => {
                        const status = schedule.status || "open";
                        const deadline = schedule.booking_deadline;
                        const minPeople = schedule.min_people || 5;
                        const isOverdue = deadline
                          ? new Date(deadline) < new Date()
                          : false;

                        return (
                          { key: schedule.id, cells: [<>
                              #{schedule.id}
                            </>,<>
                              <UIFlex    align="center"  gap={6}><CalendarDays className="h-3.5 w-3.5 text-gray-400" /><div>
                                  <p className="font-semibold text-gray-955">
                                    {formatDateTime(schedule.start_date)}
                                  </p>
                                  <p className="text-xs text-gray-400 mt-0.5">
                                    Đến:{" "}
                                    {getEndDate(
                                      schedule.start_date,
                                      schedule.number_of_days,
                                    )}
                                  </p>
                                </div></UIFlex>
                            </>,<>
                              {deadline ? (
                                <UIFlex    align="center"  gap={6}><Clock
                                    className={`h-3.5 w-3.5 ${isOverdue && status === "open" ? "text-amber-500 animate-pulse" : "text-gray-400"}`}
                                  /><div>
                                    <p
                                      className={`font-semibold ${isOverdue && status === "open" ? "text-amber-600" : "text-gray-955"}`}
                                    >
                                      {formatDateTime(deadline)}
                                    </p>
                                    {isOverdue && status === "open" && (
                                      <span className="inline-block text-[10px] bg-amber-50 text-amber-700 px-1 py-0.5 rounded font-bold uppercase tracking-wider mt-0.5">
                                        Quá hạn
                                      </span>
                                    )}
                                  </div></UIFlex>
                              ) : (
                                <span className="text-gray-400">
                                  Không giới hạn
                                </span>
                              )}
                            </>,<>
                              <UIFlex    align="center"  gap={6}><Users className="h-3.5 w-3.5 text-gray-400" /><div>
                                  <p className="font-bold text-gray-900">
                                    {schedule.booked_people} /{" "}
                                    {schedule.max_people} khách
                                  </p>
                                  {/*
                                    Số ĐÃ TRẢ TIỀN mới quyết định chuyến có chốt được không. Khi
                                    thiếu thì nói thẳng con số ấy ra, thay vì chỉ ghi mức tối thiểu
                                    rồi để người đọc tự trừ với một số khác.
                                  */}
                                  {thieuKhachToiThieu(schedule, Date.now()) ? (
                                    <p className="text-xs font-bold text-rose-600 mt-0.5">
                                      Mới {schedule.paid_people ?? 0}/
                                      {minPeople} khách đã trả tiền
                                    </p>
                                  ) : (
                                    <p className="text-xs text-gray-400 mt-0.5">
                                      Tối thiểu: {minPeople} khách
                                    </p>
                                  )}
                                </div></UIFlex>
                            </>,<>
                              <div className="flex flex-wrap items-center gap-1 min-w-44">
                                {(schedule.guides ?? []).length === 0 ? (
                                  <span className="text-xs text-gray-400">
                                    Chưa phân công
                                  </span>
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

                                <AntButton htmlType="button" disabled={
                                    status === "cancelled" ||
                                    status === "completed"
                                  } onClick={() => openGuideDialog(schedule)}>Sửa
                                </AntButton>
                              </div>
                            </>,<>
                              <UIFlex  vertical  align="start"  gap={4}><span
                                  className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                    statusClasses[status] || statusClasses.open
                                  }`}
                                >
                                  {statusLabel[status]}
                                </span>{status === "cancelled" &&
                                  schedule.cancelled_reason && (
                                    <span
                                      className="text-xs text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-100 font-medium max-w-40 truncate"
                                      title={schedule.cancelled_reason}
                                    >
                                      Lý do: {schedule.cancelled_reason}
                                    </span>
                                  )}
                                {status === "cancelled" &&
                                  schedule.merged_into_schedule_id && (
                                    <span
                                      className="text-xs text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200 font-bold max-w-40 truncate"
                                    >
                                      Đã ghép vào #{schedule.merged_into_schedule_id}
                                    </span>
                                  )}
                              </UIFlex>
                            </>,<>
                              <UIFlex    align="center" justify="end" gap={8}>{(status === "completed" ||
                                  status === "cancelled") && (
                                  <span className="text-caption-sm text-muted-soft italic">
                                    Đã hoàn thành
                                  </span>
                                )}<TableActions
                                  id={schedule.id}
                                  label="Vận hành chuyến"
                                  actions={[
                                    /*
                                      G05 - Danh sách đoàn theo từng nhóm. Trả lời hai câu ở cùng một
                                      chỗ: gửi cho nhà cung cấp được chưa, và nhóm này gồm những ai.
                                    */
                                    ...(status !== "cancelled"
                                      ? [
                                          {
                                            label: "Danh sách đoàn",
                                            onClick: () =>
                                              openManifestCheck(schedule.id),
                                            icon: <Users className="w-4 h-4" />,
                                          },
                                        ]
                                      : []),

                                    {
                                      label: "Xem điểm danh",
                                      onClick: () =>
                                        navigate(
                                          `/admin/tour-schedules/${schedule.id}/attendance`,
                                        ),
                                      icon: (
                                        <ClipboardCheck className="w-4 h-4" />
                                      ),
                                    },

                                    ...(status === "open"
                                      ? [
                                          {
                                            label: "Đóng bán",
                                            onClick: () =>
                                              handleUpdateStatus(
                                                schedule.id,
                                                "closed",
                                              ),
                                            icon: <Lock className="w-4 h-4" />,
                                          },
                                        ]
                                      : []),

                                    ...(status === "closed"
                                      ? [
                                          {
                                            label: "Mở bán lại",
                                            onClick: () =>
                                              handleUpdateStatus(
                                                schedule.id,
                                                "open",
                                              ),
                                            icon: (
                                              <Unlock className="w-4 h-4" />
                                            ),
                                          },
                                        ]
                                      : []),

                                    /* Dời hạn chốt. Chuyến đã chạy hoặc đã xong thì mốc này hết nghĩa. */
                                    ...(status === "open" ||
                                    status === "closed" ||
                                    status === "confirmed"
                                      ? [
                                          {
                                            label: "Sửa hạn chốt danh sách",
                                            onClick: () =>
                                              openDeadlineDialog(schedule),
                                            icon: <Clock className="w-4 h-4" />,
                                          },
                                        ]
                                      : []),

                                    /* Bulk Proposal: Đề xuất thay đổi hàng loạt */
                                    ...(status === "open"
                                      ? [
                                          {
                                            label: "Đề xuất thay đổi",
                                            onClick: () =>
                                              setBulkProposalSchedule(schedule),
                                            icon: <ClipboardCheck className="w-4 h-4" />,
                                          },
                                        ]
                                      : []),

                                    ...(status === "open" || status === "closed"
                                      ? [
                                          {
                                            label: "Chốt chuyến",
                                            onClick: () =>
                                              handleUpdateStatus(
                                                schedule.id,
                                                "confirmed",
                                              ),
                                            icon: (
                                              <CheckCircle2 className="w-4 h-4" />
                                            ),
                                            variant: "success" as const,
                                          },
                                        ]
                                      : []),

                                    /* L03 - Ghép chuyến: chỉ có nghĩa khi chưa khởi hành và ít khách. */
                                    ...(status === "open"
                                      ? [
                                          {
                                            label: "Ghép chuyến",
                                            onClick: () => openMergeDialog(schedule.id),
                                            icon: (
                                              <GitMerge className="w-4 h-4" />
                                            ),
                                          },
                                        ]
                                      : []),

                                    /* Bàn giao: chỉ có nghĩa khi đoàn sắp hoặc đã lên đường và đang có
                                       người phụ trách để mà giao. */
                                    ...((status === "confirmed" ||
                                      status === "in_progress") &&
                                    (schedule.guides ?? []).length > 0
                                      ? [
                                          {
                                            label: "Bàn giao hướng dẫn viên",
                                            onClick: () =>
                                              openHandoverDialog(schedule.id),
                                            icon: (
                                              <RotateCcw className="w-4 h-4" />
                                            ),
                                            variant: "warning" as const,
                                          },
                                        ]
                                      : []),

                                    /* Nguy hiểm nằm cuối, TableActions tự chèn đường kẻ tách phía trên. */
                                    ...(status === "open" ||
                                    status === "closed" ||
                                    status === "confirmed"
                                      ? [
                                          {
                                            label: "Hủy chuyến",
                                            hint: "Phải gán phương án cho từng đơn đã thu tiền",
                                            onClick: () =>
                                              openCancelDialog(schedule.id),
                                            icon: (
                                              <AlertTriangle className="w-4 h-4" />
                                            ),
                                            variant: "danger" as const,
                                          },
                                        ]
                                      : []),
                                  ]}
                                /></UIFlex>
                            </>] }
                        );
                      })} columns={[{key: "0", title: "Mã chuyến",  render: (_value, record) => record.cells[0]},{key: "1", title: "Khởi hành / kết thúc",  render: (_value, record) => record.cells[1]},{key: "2", title: "Hạn chốt danh sách",  render: (_value, record) => record.cells[2]},{key: "3", title: "Số chỗ / mức tối thiểu",  render: (_value, record) => record.cells[3]},{key: "4", title: "Hướng dẫn viên",  render: (_value, record) => record.cells[4]},{key: "5", title: "Trạng thái",  render: (_value, record) => record.cells[5]},{key: "6", title: "Thao tác", fixed: "right", render: (_value, record) => record.cells[6]}]} />,
  }))} />
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
        <UICard  ><UIFlex vertical gap="middle">Không tìm thấy chuyến đi nào khớp với bộ lọc.
        </UIFlex></UICard>
      )}{/* Bàn giao hướng dẫn viên giữa chừng */}{handoverScheduleId !== null && (
        <AntModal open title={<>
                Bàn giao hướng dẫn viên — chuyến #{handoverScheduleId}
              </>} width={720} onCancel={() => setHandoverScheduleId(null)} closable={!(handoverSaving)} keyboard={!(handoverSaving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Người cũ mất quyền ghi ngay khi lưu. Dữ liệu họ đã ghi giữ
                nguyên, chỉ chuyển quyền ghi tiếp.
              </p>
            </div>

            {!handoverPanel && (
              <p className="text-sm text-gray-500">Đang tải...</p>
            )}

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
                      Đoàn đang trên đường và không có hướng dẫn viên nào khác
                      đang dẫn đoàn cùng lúc để nhờ. Hãy bấm{" "}
                      <strong>Sửa</strong> ở cột hướng dẫn viên phân công thêm
                      một người cho chuyến, rồi quay lại đây.
                    </>
                  ) : (
                    <>
                      Gỡ người dẫn duy nhất ra thì đoàn không có ai cho tới khi
                      người mới tới nơi. Nên chỉ chọn được người{" "}
                      <strong>đang dẫn một đoàn khác</strong> — họ đã ở ngoài
                      đường. Người đó sẽ tạm giữ hai đoàn, hệ thống đánh dấu để
                      bạn xử lý tiếp.
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
                      <label className="block text-xs font-bold text-gray-700 mb-1">
                        Người giao
                      </label>
                      <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((handoverForm.from_guide_id) ?? "")} onChange={(e) =>
                          setHandoverForm((truoc) => ({
                            ...truoc,
                            from_guide_id: Number(e),
                          }))} style={{ width: "100%" }} options={[handoverPanel.current_guides.map((g) => (
                          { value: String(g.id), label: g.name, disabled: false }
                        ))].flat().filter((option) => !!option)} />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">
                        Người nhận
                      </label>
                      <AntSelect showSearch={{ optionFilterProp: "label" }} value={String((handoverForm.to_guide_id) ?? "")} onChange={(e) =>
                          setHandoverForm((truoc) => ({
                            ...truoc,
                            to_guide_id: Number(e),
                          }))} style={{ width: "100%" }} options={[nguoiThayChonDuoc.map((g) => (
                          { value: String(g.id), label: [g.name, g.leading_other_group
                              ? " — đang dẫn đoàn khác"
                              : ""].join(" "), disabled: false }
                        ))].flat().filter((option) => !!option)} />
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Lý do thay <span className="text-rose-500">*</span>
                  </label>
                  <AntInput value={handoverForm.reason} onChange={(e) =>
                      setHandoverForm((truoc) => ({
                        ...truoc,
                        reason: e.target.value,
                      }))
                    } placeholder="VD: Hướng dẫn viên cũ bị sốt cao, phải về sớm..." style={{ width: "100%" }} />
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Tình trạng đoàn <span className="text-rose-500">*</span>
                  </label>
                  <AntInput.TextArea rows={3} value={handoverForm.handover_note} onChange={(e) =>
                      setHandoverForm((truoc) => ({
                        ...truoc,
                        handover_note: e.target.value,
                      }))
                    } placeholder="Đoàn đang ở đâu, đã điểm danh tới chặng nào, khách nào cần để ý, việc gì đang dở..." style={{ width: "100%" }} />
                  <p className="mt-1 text-[11px] text-gray-400">
                    Ít nhất 20 ký tự. Người nhận chỉ có đúng đoạn này để bắt
                    nhịp với đoàn.
                  </p>
                </div>

                {/* Lịch sử: chuyến đổi người nhiều lần thì đây là chỗ lần ra ai dẫn lúc nào */}
                {handoverPanel.handovers.length > 0 && (
                  <div className="space-y-1.5">
                    <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      Đã bàn giao trước đó
                    </p>
                    {handoverPanel.handovers.map((bg) => (
                      <div
                        key={bg.id}
                        className="rounded-lg border border-gray-200 p-2.5 text-xs"
                      >
                        <p className="font-semibold text-gray-900">
                          {bg.from_guide?.name} → {bg.to_guide?.name}
                          <span className="ml-2 font-normal text-gray-500">
                            {formatDateTime(bg.handed_over_at)}
                          </span>
                        </p>
                        <p className="text-gray-600">{bg.reason}</p>
                        <p className="mt-0.5 text-gray-500">
                          {bg.handover_note}
                        </p>
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

            <UIFlex     justify="end" gap={8}><AntButton htmlType="button" onClick={() => setHandoverScheduleId(null)} disabled={handoverSaving}>Quay lại
              </AntButton><AntButton htmlType="button" onClick={confirmHandover} disabled={
                  handoverSaving ||
                  khongCoAiNhoDuoc ||
                  !handoverForm.from_guide_id ||
                  !handoverForm.to_guide_id ||
                  handoverForm.reason.trim().length < 10 ||
                  handoverForm.handover_note.trim().length < 20
                } type="primary">{handoverSaving ? "Đang lưu..." : "Xác nhận bàn giao"}</AntButton></UIFlex>
          </UIFlex></AntModal>
      )}{/* Phân công hướng dẫn viên — nhiều người cho một chuyến */}{guideDialogScheduleId !== null && (
        <AntModal open title={<>
                Hướng dẫn viên — chuyến #{guideDialogScheduleId}
              </>} width={720} onCancel={() => setGuideDialogScheduleId(null)} closable={!(assigningScheduleId === guideDialogScheduleId)} keyboard={!(assigningScheduleId === guideDialogScheduleId)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Chọn được nhiều người. Đoàn đông thì cần thêm người dẫn, bao
                nhiêu là đủ do bạn quyết — hệ thống không tính hộ theo số khách.
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
                <p className="px-1 py-2 text-xs text-gray-500">
                  Đang tải danh sách...
                </p>
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
                    <AntCheckbox checked={daChon} disabled={biChan} onChange={() =>
                        setPendingGuideIds((truoc) =>
                          truoc.includes(ung.id)
                            ? truoc.filter((id) => id !== ung.id)
                            : [...truoc, ung.id],
                        )
                      } />

                    <span className="min-w-0 flex-1 space-y-0.5">
                      <span className="flex flex-wrap items-center gap-1.5">
                        <span className="font-medium text-gray-800">
                          {ung.name}
                        </span>

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
                        <span
                          key={canBiet}
                          className="block text-[11px] text-amber-700"
                        >
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
                    <span className="font-semibold">
                      {tc.guide_name ?? "Không rõ"}
                    </span>
                    <span className="text-rose-700/70">
                      {" "}
                      · {formatDateTime(tc.declined_at)}
                    </span>
                    <p className="text-rose-800/90">{tc.reason}</p>
                  </div>
                ))}
              </div>
            )}

            <p className="text-[11px] text-gray-400">
              Xếp theo mức hợp với tour: chuyên đúng loại hình và quen tuyến lên
              trước, đang gánh nhiều chuyến thì lùi xuống. Chỉ đúng một thứ thật
              sự chặn — trùng lịch, vì một người không đứng ở hai đoàn cùng lúc.
              Phần còn lại chỉ là gợi ý, bạn vẫn quyết.
            </p>

            <UIFlex     justify="end" gap={8}><AntButton htmlType="button" onClick={() => setGuideDialogScheduleId(null)} disabled={assigningScheduleId === guideDialogScheduleId}>Quay lại
              </AntButton><AntButton htmlType="button" onClick={() =>
                  assignGuides(guideDialogScheduleId, pendingGuideIds)
                } disabled={assigningScheduleId === guideDialogScheduleId} type="primary">{assigningScheduleId === guideDialogScheduleId
                  ? "Đang lưu..."
                  : "Lưu phân công"}</AntButton></UIFlex>
          </UIFlex></AntModal>
      )}{/* Dời hạn chốt danh sách, có xem trước tác động */}{deadlineScheduleId !== null && (
        <AntModal open title={<>
                Hạn chốt danh sách — chuyến #{deadlineScheduleId}
              </>} width={720} onCancel={closeDeadlineDialog} closable={!(deadlineSaving)} keyboard={!(deadlineSaving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Đây là mốc gửi danh sách khách cho khách sạn và nhà xe. Dời mốc
                này là dời cùng lúc quyền bán chỗ, sửa tên hành khách, chuyển
                chuyến và ghép chuyến.
              </p>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Hạn chốt mới
              </label>
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

            {deadlineLoading && (
              <p className="text-sm text-gray-500">Đang tính tác động...</p>
            )}

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
                    <li
                      key={dong}
                      className="text-xs text-amber-900 flex gap-2"
                    >
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
              <AntInput.TextArea rows={2} value={deadlineReason} onChange={(e) => setDeadlineReason(e.target.value)} placeholder="VD: Khách sạn cho thêm 2 phòng, chốt lại ngày 19/08..." style={{ width: "100%" }} />
              <p className="text-[11px] text-gray-400 mt-1">
                Bắt buộc, ít nhất {LY_DO_DOI_HAN_TOI_THIEU} ký tự. Ba tháng nữa
                người đọc nhật ký cần biết vì sao mốc bị dời, và lúc đó không ai
                nhớ lại giúp được.
              </p>
            </div>

            {deadlineError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {deadlineError}
              </div>
            )}

            <UIFlex     justify="end" gap={8}><AntButton htmlType="button" onClick={closeDeadlineDialog} disabled={deadlineSaving}>Quay lại
              </AntButton><AntButton htmlType="button" onClick={confirmDeadline} disabled={
                  deadlineSaving ||
                  deadlineLoading ||
                  !deadlineImpact?.impact.can_change ||
                  deadlineImpact?.impact.direction === "unchanged" ||
                  deadlineReason.trim().length < LY_DO_DOI_HAN_TOI_THIEU
                } type="primary">{deadlineSaving ? "Đang lưu..." : "Đồng ý, lưu hạn chốt mới"}</AntButton></UIFlex>
          </UIFlex></AntModal>
      )}{/* L03 - Ghép chuyến */}{mergeScheduleId !== null && (
        <ScheduleMergeDialog
          schedules={allSchedules}
          sourceId={mergeScheduleId}
          data={mergeData}
          loading={mergeLoading}
          targetId={mergeTargetId}
          reason={mergeReason}
          saving={mergeSaving}
          error={mergeError}
          onSourceChange={openMergeDialog}
          onTargetChange={(id) => { setMergeTargetId(id); setMergeError(""); }}
          onReasonChange={setMergeReason}
          onClose={closeMergeDialog}
          onConfirm={confirmMerge}
        />
      )}{/* G05 - Kiểm tra danh sách đoàn trước khi gửi nhà cung cấp */}{manifestScheduleId !== null && (
        <AntModal open title={<>
                Danh sách đoàn — chuyến #{manifestScheduleId}
              </>} width={960} onCancel={() => setManifestScheduleId(null)} closable={true} keyboard={true} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Mỗi đơn là một nhóm, thường do một người đứng ra đăng ký cho cả
                nhà hoặc cả phòng ban. Bấm vào nhóm để xem nhóm đó gồm những ai.
              </p>
            </div>

            {manifestLoading && (
              <p className="text-sm text-gray-500">Đang tải danh sách...</p>
            )}

            {manifest && (
              <>
                {manifest.can_export_manifest ? (
                  <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                    {manifest.total_groups} nhóm, {manifest.total_guests} khách,
                    đã khai đủ. Gửi được cho khách sạn và nhà xe.
                  </div>
                ) : (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                    {manifest.total_groups} nhóm, đã khai{" "}
                    {manifest.total_declared} trên {manifest.total_guests}{" "}
                    khách. Chưa gửi được danh sách đoàn.
                  </div>
                )}

                {/*
                  Q07 - Đưa danh sách ra khỏi màn hình.

                  Cho tải cả khi còn khai thiếu: điều hành cần bản nháp để đối chiếu và để biết
                  còn thiếu ai — tệp ghi rõ nhóm nào chưa khai. Ô cảnh báo phía trên đã nói đủ về
                  việc gửi ra ngoài, chặn thêm ở đây chỉ khiến người ta chép tay.
                */}
                <AntButton htmlType="button" onClick={xuatDanhSachDoan} disabled={dangXuatDanhSach} style={{ width: "100%" }}>{dangXuatDanhSach
                    ? "Đang tạo tệp..."
                    : "Tải danh sách đoàn (Excel)"}</AntButton>

                {manifest.groups.length === 0 && (
                  <p className="text-sm text-gray-500">
                    Chuyến này chưa có đơn nào.
                  </p>
                )}

                <UIFlex vertical gap={8} >{manifest.groups.map((nhom) => {
                    const dangMo = openGroupIds.includes(nhom.booking_id);
                    const nguoiLienHe = nhom.passengers.find(
                      (khach) => khach.is_contact,
                    );

                    return (
                      <div
                        key={nhom.booking_id}
                        className="rounded-lg border border-gray-200 overflow-hidden"
                      >
                        <AntButton htmlType="button" onClick={() => toggleGroup(nhom.booking_id)} style={{ width: "100%", height: "auto", whiteSpace: "normal", textAlign: "left" }}><UIFlex    align="center" justify="space-between" gap={8}><span className="font-bold text-gray-900">
                              BK-{nhom.booking_id} · {nhom.customer_name}
                            </span><span className="flex items-center gap-2">
                              <span
                                className={`font-mono ${
                                  nhom.missing > 0
                                    ? "text-amber-700 font-bold"
                                    : "text-gray-500"
                                }`}
                              >
                                {nhom.declared}/{nhom.guests} người
                              </span>
                              <span className="text-gray-400">
                                {dangMo ? "▾" : "▸"}
                              </span>
                            </span></UIFlex><p className="mt-0.5 flex flex-wrap gap-x-3 text-gray-500">
                            {nhom.customer_phone && (
                              <span>{nhom.customer_phone}</span>
                            )}
                            {nguoiLienHe && (
                              <span>Liên hệ đoàn: {nguoiLienHe.name}</span>
                            )}
                          </p>{nhom.warnings.map((warning) => (
                            <p key={warning} className="mt-0.5 text-amber-800">
                              {warning}
                            </p>
                          ))}</AntButton>

                        {dangMo && (
                          <div className="border-t border-gray-100 bg-gray-50/60 p-3">
                            {nhom.passengers.length === 0 ? (
                              <p className="text-xs text-gray-500">
                                Nhóm này chưa khai tên người nào.
                              </p>
                            ) : (
                              <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={nhom.passengers.map((khach) => (
                                    {key: khach.id, cells: [<>
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
                                      </>,<>
                                        {khach.type === "adult"
                                          ? "Người lớn"
                                          : khach.type === "child"
                                            ? "Trẻ em"
                                            : "Em bé"}
                                      </>,<>
                                        {khach.date_of_birth
                                          ? formatDateTime(khach.date_of_birth)
                                          : "—"}
                                      </>,<>
                                        {khach.identity_number ?? "—"}
                                      </>], rowProps: {}}
                                  ))}
    columns={[{ key: "0", title: <>
                                      Họ tên
                                    </>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Loại</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>
                                      Ngày sinh
                                    </>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>
                                      Giấy tờ
                                    </>, align: "left", render: (_value, record) => record.cells[3] }]}
    onRow={(record) => record.rowProps}
     />
                            )}
                          </div>
                        )}
                      </div>
                    );
                  })}</UIFlex>
              </>
            )}

            <UIFlex     justify="end" ><AntButton htmlType="button" onClick={() => setManifestScheduleId(null)}>Đóng
              </AntButton></UIFlex>
          </UIFlex></AntModal>
      )}{/*
        K - Hủy chuyến, ba bước bắt buộc: xem tác động, gán phương án cho từng đơn đã thanh toán,
        rồi mới xác nhận. Trước đây chỗ này chỉ hỏi lý do rồi đổi trạng thái, còn đơn của khách
        thì không ai đụng tới.
      */}{cancellingScheduleId !== null && (
        <AntModal open title={<>
                  Hủy chuyến #{cancellingScheduleId}
                </>} width={960} onCancel={closeCancelDialog} closable={!(cancelSaving)} keyboard={!(cancelSaving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <UIFlex    align="start"  gap={12}><div className="p-2.5 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 shrink-0">
                <AlertTriangle className="w-5 h-5" />
              </div><div>

                <p className="text-xs text-gray-500 mt-0.5">
                  Lỗi không thuộc về khách, nên mỗi đơn đã thanh toán phải được
                  hoàn đủ 100% hoặc chuyển miễn phí sang chuyến khác. Không áp
                  bảng phí hủy.
                </p>
              </div></UIFlex>

            {cancelPreviewLoading && (
              <p className="text-sm text-gray-500">Đang tính tác động...</p>
            )}

            {cancelPreview && !cancelPreview.impact.can_cancel && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {cancelPreview.impact.blocked_reason}
              </div>
            )}

            {cancelPreview && cancelPreview.impact.can_cancel && (
              <>
                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-1">
                  <p>
                    <strong>
                      {cancelPreview.impact.total_paid_bookings} đơn đã thanh
                      toán
                    </strong>{" "}
                    ({cancelPreview.impact.total_paid_guests} khách), tổng đã
                    thu{" "}
                    <strong>
                      {formatPrice(
                        cancelPreview.impact.total_refund_if_all_refunded,
                      )}
                    </strong>
                    .
                  </p>
                  {cancelPreview.impact.unpaid_bookings > 0 && (
                    <p className="text-xs">
                      Ngoài ra {cancelPreview.impact.unpaid_bookings} đơn chưa
                      thanh toán ({cancelPreview.impact.unpaid_guests} khách) sẽ
                      được hủy tự động, không cần chọn phương án.
                    </p>
                  )}
                </div>

                {cancelPreview.impact.paid_bookings.length === 0 ? (
                  <p className="text-sm text-gray-500">
                    Chuyến này chưa có đơn nào đã thanh toán.
                  </p>
                ) : (
                  <UIFlex vertical gap={8} ><p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      Phương án cho từng đơn
                    </p>{cancelPreview.impact.paid_bookings.map((don) => {
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
                              {don.guests} khách · đã thu{" "}
                              {formatPrice(don.paid_amount)}
                            </span>
                          </div>

                          <div className="flex flex-wrap items-center gap-3 text-xs">
                            <label className="flex cursor-pointer items-center gap-1.5">
                              <AntRadio checked={plan?.action === "refund"} onChange={() =>
                                  setCancelPlans((truoc) => ({
                                    ...truoc,
                                    [don.booking_id]: {
                                      booking_id: don.booking_id,
                                      action: "refund",
                                    },
                                  }))
                                } />
                              Hoàn đủ {formatPrice(don.paid_amount)}
                            </label>

                            <label className="flex cursor-pointer items-center gap-1.5">
                              <AntRadio disabled={
                                  cancelPreview.impact.transfer_options
                                    .length === 0
                                } checked={plan?.action === "transfer"} onChange={() =>
                                  setCancelPlans((truoc) => ({
                                    ...truoc,
                                    [don.booking_id]: {
                                      booking_id: don.booking_id,
                                      action: "transfer",
                                      to_schedule_id:
                                        cancelPreview.impact.transfer_options[0]
                                          ?.schedule_id ?? null,
                                    },
                                  }))
                                } />
                              Chuyển sang chuyến khác
                            </label>

                            {plan?.action === "transfer" && (
                              <AntSelect showSearch={{ optionFilterProp: "label" }} value={String(plan.to_schedule_id ?? "")} onChange={(e) =>
                                  setCancelPlans((truoc) => ({
                                    ...truoc,
                                    [don.booking_id]: {
                                      ...truoc[don.booking_id],
                                      to_schedule_id: Number(e),
                                    },
                                  }))} style={{ width: "100%" }} options={[cancelPreview.impact.transfer_options.map(
                                  (item) => (
                                    { value: String(item.schedule_id), label: ["#", item.schedule_id, "·", " ", formatDateTime(item.start_date), "· còn", " ", item.remaining_seats, "chỗ"].join(" "), disabled: false }
                                  ),
                                )].flat().filter((option) => !!option)} />
                            )}
                          </div>

                          {cancelPreview.impact.transfer_options.length ===
                            0 && (
                            <p className="text-[11px] text-gray-400">
                              Không có chuyến nào nhận được khách, nên chỉ còn
                              cách hoàn tiền.
                            </p>
                          )}
                        </div>
                      );
                    })}</UIFlex>
                )}

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Lý do hủy <span className="text-rose-500">*</span>
                  </label>
                  <AntInput.TextArea rows={2} value={cancelReasonInput} onChange={(e) => setCancelReasonInput(e.target.value)} placeholder="VD: Nhà xe báo hỏng xe, không thu xếp được xe thay thế..." style={{ width: "100%" }} />
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

            <UIFlex     justify="end" gap={8}><AntButton htmlType="button" onClick={closeCancelDialog} disabled={cancelSaving}>Không hủy nữa
              </AntButton><AntButton htmlType="button" onClick={confirmCancelSchedule} disabled={
                  cancelSaving ||
                  cancelPreviewLoading ||
                  !cancelPreview?.impact.can_cancel ||
                  cancelReasonInput.trim().length < 10
                } type="primary" danger>{cancelSaving ? "Đang hủy..." : "Xác nhận hủy chuyến"}</AntButton></UIFlex>
          </UIFlex></AntModal>
      )}
      {/* Hộp thoại Đề xuất thay đổi (Bulk Proposal) */}
      <BulkProposalDialog
        scheduleId={bulkProposalSchedule?.id ?? 0}
        scheduleStartDate={bulkProposalSchedule?.start_date ?? ""}
        isOpen={bulkProposalSchedule !== null}
        onClose={() => setBulkProposalSchedule(null)}
      />

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((current) => ({ ...current, isOpen: false }))}
      /></UIFlex>
  );
}
