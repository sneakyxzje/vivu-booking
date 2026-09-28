import PassengerSupplementPanel from "@/components/admin/PassengerSupplementPanel";
import { ScheduleDetailsDrawer } from "@/components/admin/ScheduleDetailsDrawer";
import { filterScheduleList, scheduleStatusText, scheduleStatus, scheduleView, type ScheduleView } from "@/utils/scheduleList";
import dayjs from "dayjs";
import api from "@/services/api";
import { businessNow } from "@/utils/demoClock";
import {
  Button as AntButton,
  Card as UICard,
  Checkbox as AntCheckbox,
  Alert as AntAlert,
  DatePicker as AntDatePicker,
  Empty as AntEmpty,
  Form as AntForm,
  Tabs as AntTabs,
  Tooltip as AntTooltip,
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
  Search,
  AlertTriangle,
  RotateCcw,
} from "lucide-react";
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
  toDateTimeLocalValue,
} from "@/utils/format";
import {
  LY_DO_DOI_HAN_TOI_THIEU,
  getScheduleUnavailableReason,
  getScheduleDeadline,
} from "@/utils/schedule";
import Pagination from "@/components/admin/AdminPagination";
import { DateTimePicker } from "@/components/admin/AdminDateTimePicker";

export default function ScheduleManagement() {
  const navigate = useNavigate();
  const [tours, setTours] = useState<Tour[]>([]);
  const [guides, setGuides] = useState<Guide[]>([]);
  const [loading, setLoading] = useState(true);
  const [canAdvanceTime, setCanAdvanceTime] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [view, setView] = useState<ScheduleView>("upcoming");
  const [tourFilter, setTourFilter] = useState<number>();
  const [dateRange, setDateRange] = useState<[string, string] | null>(null);
  const [unassignedOnly, setUnassignedOnly] = useState(false);
  const [detailScheduleId, setDetailScheduleId] = useState<number | null>(null);
  const [loadError, setLoadError] = useState("");
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

  const loadData = useCallback(() => Promise.all([
    adminService.getTours(),
    adminService.getGuides(),
  ]).then(([toursData, guidesData]) => {
    setLoadError("");
    setTours(toursData);
    setGuides(guidesData?.data.filter(guide => guide.status === "active") ?? []);
  }).catch(() => {
    setLoadError("Không tải được lịch khởi hành. Vui lòng thử lại.");
  }).finally(() => setLoading(false)), []);

  useEffect(() => {
    void loadData();
    const controller = new AbortController();
    api.get("/admin/demo-availability", { signal: controller.signal })
      .then(response => setCanAdvanceTime(response.data.data.enabled)).catch(() => {});
    return () => controller.abort();
  }, [loadData]);

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

  const filteredSchedules = useMemo(() => filterScheduleList(allSchedules, {
    view, query: searchQuery, tourId: tourFilter, status: statusFilter,
    dateRange, unassigned: unassignedOnly,
  }), [allSchedules, view, searchQuery, tourFilter, statusFilter, dateRange, unassignedOnly]);

  const totalItems = filteredSchedules.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
  const visiblePage = Math.min(currentPage, totalPages);
  const pageSchedules = filteredSchedules.slice((visiblePage - 1) * itemsPerPage, visiblePage * itemsPerPage);
  const detailSchedule = allSchedules.find(schedule => schedule.id === detailScheduleId) ?? null;
  const counts = allSchedules.reduce((result, schedule) => {
    result[scheduleView(schedule)]++;
    return result;
  }, { upcoming: 0, running: 0, history: 0 });
  const resetFilters = () => {
    setSearchQuery("");
    setTourFilter(undefined);
    setStatusFilter("all");
    setDateRange(null);
    setUnassignedOnly(false);
    setCurrentPage(1);
  };
  const hasFilters = Boolean(searchQuery || tourFilter || statusFilter !== "all" || dateRange || unassignedOnly);

  const loadHandoverRequests = useCallback(() =>
    adminService.getPendingHandoverRequests()
      .then(requests => setHandoverRequests(requests))
      .catch(err => console.error("Lỗi tải yêu cầu bàn giao:", err)),
  []);

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
    nextStatus: "open" | "confirmed" | "cancelled",
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
        message: `Đã cập nhật trạng thái chuyến khởi hành thành "${scheduleStatusText[updatedSchedule.status]}".`,
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
    <UIFlex vertical gap="large">
      <UIFlex justify="space-between" align="center" wrap gap="middle">
        <AntTypography.Title level={3} style={{ margin: 0 }}>Lịch khởi hành</AntTypography.Title>
        <AntButton icon={<RotateCcw size={16} />} loading={loading} onClick={() => { setLoading(true); void loadData(); }}>Làm mới</AntButton>
      </UIFlex>

      {handoverRequests.length > 0 && <AntAlert type="warning" showIcon
        title={`${handoverRequests.length} yêu cầu đổi hướng dẫn viên đang chờ`}
        action={<Link to="/admin/handovers">Xem yêu cầu</Link>} />}

      <UICard styles={{ body: { padding: "8px 20px 20px" } }}>
        <AntTabs activeKey={view} onChange={key => {
          setView(key as ScheduleView);
          setStatusFilter("all");
          setUnassignedOnly(false);
          setCurrentPage(1);
        }} items={[
          { key: "upcoming", label: `Chưa khởi hành (${counts.upcoming})` },
          { key: "running", label: `Đang diễn ra (${counts.running})` },
          { key: "history", label: `Đã kết thúc / hủy (${counts.history})` },
        ]} />
        <AntForm layout="vertical">
          <UIFlex gap="middle" wrap align="end">
            <AntForm.Item label="Tìm chuyến" style={{ flex: "1 1 220px", marginBottom: 12 }}>
              <AntInput allowClear prefix={<Search size={16} />} placeholder="Tên tour hoặc mã chuyến" value={searchQuery}
                onChange={event => { setSearchQuery(event.target.value); setCurrentPage(1); }} />
            </AntForm.Item>
            <AntForm.Item label="Tour" style={{ flex: "1 1 220px", marginBottom: 12 }}>
              <AntSelect allowClear showSearch optionFilterProp="label" placeholder="Tất cả tour" value={tourFilter}
                options={tours.map(tour => ({ value: tour.id, label: tour.title }))}
                onChange={value => { setTourFilter(value); setCurrentPage(1); }} />
            </AntForm.Item>
            <AntForm.Item label="Ngày khởi hành" style={{ flex: "1 1 260px", marginBottom: 12 }}>
              <AntDatePicker.RangePicker style={{ width: "100%" }} format="DD/MM/YYYY" placeholder={["Từ ngày", "Đến ngày"]}
                value={dateRange ? [dayjs(dateRange[0]), dayjs(dateRange[1])] : null}
                onChange={dates => { setDateRange(dates?.[0] && dates[1] ? [dates[0].format("YYYY-MM-DD"), dates[1].format("YYYY-MM-DD")] : null); setCurrentPage(1); }} />
            </AntForm.Item>
            {view !== "running" && <AntForm.Item label="Trạng thái" style={{ flex: "0 1 200px", minWidth: 180, marginBottom: 12 }}>
              <AntSelect value={statusFilter} onChange={value => { setStatusFilter(value); setCurrentPage(1); }}
                options={[
                  { value: "all", label: "Tất cả trạng thái" },
                  ...(view === "history" ? ["completed", "cancelled"] as const : ["open", "confirmed"] as const)
                    .map(value => ({ value, label: scheduleStatusText[value] })),
                ]} />
            </AntForm.Item>}
          </UIFlex>
        </AntForm>
        <UIFlex align="center" justify="space-between" wrap gap="small">
          {view !== "history" ? <AntCheckbox checked={unassignedOnly} onChange={event => { setUnassignedOnly(event.target.checked); setCurrentPage(1); }}>Chưa có hướng dẫn viên</AntCheckbox> : <span />}
          <UIFlex gap="middle" align="center">
            <AntTypography.Text type="secondary">{totalItems} chuyến</AntTypography.Text>
            {hasFilters && <AntButton type="link" onClick={resetFilters}>Xóa bộ lọc</AntButton>}
          </UIFlex>
        </UIFlex>
      </UICard>

      {loadError && <AntAlert type="error" showIcon title={loadError} action={<AntButton onClick={loadData}>Thử lại</AntButton>} />}

      <UICard styles={{ body: { padding: 0 } }}>
        <AntTable<ExtendedSchedule> rowKey="id" loading={loading} pagination={false} dataSource={pageSchedules}
          scroll={{ x: 1050 }} locale={{ emptyText: <AntEmpty image={AntEmpty.PRESENTED_IMAGE_SIMPLE} description={hasFilters ? "Không có chuyến phù hợp với bộ lọc." : "Chưa có chuyến trong mục này."}>{hasFilters && <AntButton onClick={resetFilters}>Xóa bộ lọc</AntButton>}</AntEmpty> }}
          columns={[
            { key: "trip", title: "Chuyến đi", width: 290, render: (_, schedule) => <UIFlex vertical gap={4}>
              <AntTypography.Text strong>{schedule.tour_title}</AntTypography.Text>
              <AntTypography.Text>Khởi hành {formatDateTime(schedule.start_date)}</AntTypography.Text>
              <AntTypography.Text type="secondary">#{schedule.id}{schedule.end_date ? ` · Về ${formatDateTime(schedule.end_date)}` : ""}</AntTypography.Text>
            </UIFlex> },
            { key: "deadline", title: <AntTooltip title="Hạn đặt chỗ, khai hành khách và thanh toán đủ tiền.">Hạn chốt danh sách</AntTooltip>, width: 170, render: (_, schedule) => {
              const deadline = getScheduleDeadline(schedule);
              const overdue = deadline && deadline.getTime() <= businessNow(schedule);
              return <UIFlex vertical gap={4}>
                <AntTypography.Text>{deadline ? formatDateTime(deadline.toISOString()) : "Chưa có"}</AntTypography.Text>
                {overdue && scheduleStatus(schedule) === "open" && <AntTypography.Text type="secondary">Đã hết hạn nhận khách</AntTypography.Text>}
              </UIFlex>;
            } },
            { key: "seats", title: "Chỗ đã đặt", width: 130, render: (_, schedule) => <UIFlex vertical gap={4}>
              <AntTypography.Text strong>{schedule.booked_people} / {schedule.max_people} chỗ</AntTypography.Text>
              <AntTypography.Text type="secondary">Còn {Math.max(0, schedule.max_people - schedule.booked_people)} chỗ</AntTypography.Text>
            </UIFlex> },
            { key: "guides", title: "Hướng dẫn viên", width: 180, render: (_, schedule) => <UIFlex vertical gap={4}>
              {schedule.guides?.length ? <>
                <AntTypography.Text>{schedule.guides.map(guide => guide.name).join(", ")}</AntTypography.Text>
                {schedule.guides.some(guide => !guide.pivot?.accepted_at) && <AntTypography.Text type="secondary">Có người chưa phản hồi</AntTypography.Text>}
              </> : <AntTypography.Text type="secondary">Chưa phân công</AntTypography.Text>}
              {scheduleView(schedule) !== "history" && <AntButton type="link" size="small" style={{ padding: 0, alignSelf: "flex-start" }} onClick={() => openGuideDialog(schedule)}>{schedule.guides?.length ? "Đổi phân công" : "Phân công"}</AntButton>}
            </UIFlex> },
            { key: "status", title: "Trạng thái", width: 160, render: (_, schedule) => <UIFlex vertical align="start" gap={4}>
              <AntTag color={scheduleStatus(schedule) === "in_progress" ? "processing" : scheduleStatus(schedule) === "confirmed" ? "success" : undefined}>{scheduleStatusText[scheduleStatus(schedule)]}</AntTag>
              {scheduleStatus(schedule) === "open" && <AntTypography.Text type="secondary">{getScheduleUnavailableReason(schedule) || "Đang nhận đặt chỗ"}</AntTypography.Text>}
            </UIFlex> },
            { key: "actions", title: "", width: 105, fixed: "right", render: (_, schedule) =>
              <AntButton aria-label={`Xem chi tiết chuyến #${schedule.id}`} onClick={() => setDetailScheduleId(schedule.id)}>Chi tiết</AntButton> },
          ]} />
        <UIFlex justify="end" style={{ padding: 16 }}>
          <Pagination currentPage={visiblePage} lastPage={totalPages} total={totalItems} perPage={itemsPerPage} itemLabel="chuyến"
            onPageChange={setCurrentPage} onPerPageChange={value => { setItemsPerPage(value); setCurrentPage(1); }} />
        </UIFlex>
      </UICard>

      <ScheduleDetailsDrawer schedule={detailSchedule} canAdvanceTime={canAdvanceTime} onClose={() => setDetailScheduleId(null)}
        onReload={loadData} onFeedback={(message, type) => setToast({ message, type, isOpen: true })}
        onGuides={openGuideDialog} onManifest={openManifestCheck}
        onAttendance={id => navigate(`/admin/tour-schedules/${id}/attendance`)}
        onDeadline={openDeadlineDialog}
        onConfirm={schedule => handleUpdateStatus(schedule.id, "confirmed")}
        onMerge={openMergeDialog} onHandover={openHandoverDialog} onCancel={openCancelDialog} />
      {/* Bàn giao hướng dẫn viên giữa chừng */}{handoverScheduleId !== null && (
        <AntModal open title={<>
                Bàn giao hướng dẫn viên — chuyến #{handoverScheduleId}
              </>} width={720} onCancel={() => setHandoverScheduleId(null)} closable={!(handoverSaving)} keyboard={!(handoverSaving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Sau khi lưu, người mới tiếp quản điểm danh và cập nhật chuyến.
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
                    : "Chỉ còn một hướng dẫn viên phụ trách."}
                </p>
                <p className="text-xs mt-0.5">
                  {khongCoAiNhoDuoc ? (
                    <>
                      Cần thêm hướng dẫn viên trước khi bàn giao. Đóng hộp thoại,
                      chọn <strong>Đổi phân công</strong> để thêm người phụ trách.
                    </>
                  ) : (
                    <>
                      Người nhận sẽ tạm phụ trách hai đoàn. Cần sắp xếp người
                      thay thế để mỗi đoàn có hướng dẫn viên riêng.
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
                Phân công hướng dẫn viên — chuyến #{guideDialogScheduleId}
              </>} width={720} onCancel={() => setGuideDialogScheduleId(null)} closable={!(assigningScheduleId === guideDialogScheduleId)} keyboard={!(assigningScheduleId === guideDialogScheduleId)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Chọn một hoặc nhiều hướng dẫn viên cho chuyến này.
              </p>
              <Link
                to="/admin/guides"
                className="mt-1 inline-block text-[11px] font-semibold text-primary-600 hover:underline"
              >
                Xem hồ sơ hướng dẫn viên
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
              Người phù hợp được xếp trước. Không thể chọn người trùng lịch.
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
                Đổi hạn chốt — chuyến #{deadlineScheduleId}
              </>} width={720} onCancel={closeDeadlineDialog} closable={!(deadlineSaving)} keyboard={!(deadlineSaving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Đổi hạn đặt chỗ, khai hành khách và thanh toán đủ tiền của chuyến.
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
              <p className="text-sm text-gray-500">Đang kiểm tra các đơn liên quan...</p>
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
                Ít nhất {LY_DO_DOI_HAN_TOI_THIEU} ký tự.
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
                } type="primary">{deadlineSaving ? "Đang lưu..." : "Lưu hạn chốt"}</AntButton></UIFlex>
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
                Danh sách khách — chuyến #{manifestScheduleId}
              </>} width={960} onCancel={() => setManifestScheduleId(null)} closable={true} keyboard={true} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

              <p className="text-xs text-gray-500 mt-0.5">
                Chọn một đơn để xem danh sách hành khách.
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
                            <div className="mb-4"><PassengerSupplementPanel bookingId={nhom.booking_id} onChanged={async () => {
                              const updated = await adminService.getScheduleManifest(manifestScheduleId);
                              setManifest(updated);
                            }} /></div>
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
                  Chọn hoàn đủ tiền hoặc chuyển chuyến miễn phí cho từng đơn đã thanh toán.
                </p>
              </div></UIFlex>

            {cancelPreviewLoading && (
              <p className="text-sm text-gray-500">Đang kiểm tra các đơn liên quan...</p>
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

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((current) => ({ ...current, isOpen: false }))}
      /></UIFlex>
  );
}
