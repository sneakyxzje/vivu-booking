import { useCallback, useEffect, useState, useMemo } from "react";
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
  HandoverPanelResponse,
  PendingHandoverRequest,
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
  // State phÃ¢n cÃ´ng HÆ°á»›ng dáº«n viÃªn
  const [assigningScheduleId, setAssigningScheduleId] = useState<number | null>(null);
  // PhÃ¢n cÃ´ng nhiá»u hÆ°á»›ng dáº«n viÃªn cho má»™t chuyáº¿n, sá»­a trong há»™p thoáº¡i riÃªng.
  const [guideDialogScheduleId, setGuideDialogScheduleId] = useState<number | null>(null);
  const [pendingGuideIds, setPendingGuideIds] = useState<number[]>([]);

  // BÃ n giao giá»¯a chá»«ng. TÃ¡ch khá»i phÃ¢n cÃ´ng vÃ¬ báº¯t buá»™c kÃ¨m lÃ½ do vÃ  tÃ¬nh tráº¡ng Ä‘oÃ n.
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
   * YÃªu cáº§u bÃ n giao Ä‘ang chá» â€” á»Ÿ Ä‘Ã¢y chá»‰ Ä‘á»ƒ bÃ¡o, khÃ´ng xá»­ lÃ½.
   *
   * Viá»‡c duyá»‡t náº±m trá»n á»Ÿ /admin/handovers. TrÆ°á»›c Ä‘Ã³ nÃ³ cÃ³ má»™t báº£n sao ngay trong trang nÃ y, tá»©c
   * hai chá»— dá»±ng cÃ¹ng má»™t há»™p thoáº¡i chá»n ngÆ°á»i thay; sá»­a luáº­t á»Ÿ má»™t chá»— mÃ  quÃªn chá»— kia lÃ  chuyá»‡n
   * sá»›m muá»™n.
   */
  const [handoverRequests, setHandoverRequests] = useState<PendingHandoverRequest[]>([]);

  // State Há»§y chuyáº¿n
  // K - Há»§y chuyáº¿n. Má»—i Ä‘Æ¡n Ä‘Ã£ thanh toÃ¡n pháº£i cÃ³ má»™t phÆ°Æ¡ng Ã¡n trÆ°á»›c khi há»§y Ä‘Æ°á»£c.
  const [cancellingScheduleId, setCancellingScheduleId] = useState<number | null>(null);
  const [cancelReasonInput, setCancelReasonInput] = useState("");
  const [cancelPreview, setCancelPreview] = useState<ScheduleCancelPreviewResponse | null>(null);
  const [cancelPreviewLoading, setCancelPreviewLoading] = useState(false);
  const [cancelPlans, setCancelPlans] = useState<Record<number, CancelPlan>>({});
  const [cancelSaving, setCancelSaving] = useState(false);
  const [cancelError, setCancelError] = useState("");

  // G05 - Kiá»ƒm tra danh sÃ¡ch Ä‘oÃ n trÆ°á»›c khi gá»­i nhÃ  cung cáº¥p
  const [manifestScheduleId, setManifestScheduleId] = useState<number | null>(null);
  const [manifest, setManifest] = useState<ScheduleManifestResponse | null>(null);
  const [manifestLoading, setManifestLoading] = useState(false);
  // NhÃ³m Ä‘ang má»Ÿ xem chi tiáº¿t. Má»Ÿ sáºµn táº¥t cáº£ thÃ¬ Ä‘oÃ n Ä‘Ã´ng thÃ nh má»™t bá»©c tÆ°á»ng chá»¯.
  const [openGroupIds, setOpenGroupIds] = useState<number[]>([]);

  // L03 - GhÃ©p chuyáº¿n
  const [mergeScheduleId, setMergeScheduleId] = useState<number | null>(null);
  const [mergeData, setMergeData] = useState<MergeCandidatesResponse | null>(null);
  const [mergeLoading, setMergeLoading] = useState(false);
  const [mergeTargetId, setMergeTargetId] = useState<number | null>(null);
  const [mergeReason, setMergeReason] = useState("");
  const [mergeSaving, setMergeSaving] = useState(false);
  const [mergeError, setMergeError] = useState("");

  // Dá»i háº¡n chá»‘t danh sÃ¡ch. Xem docs/nghiep-vu/16-sua-han-chot.md.
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
        message: "KhÃ´ng thá»ƒ táº£i dá»¯ liá»‡u quáº£n lÃ½ chuyáº¿n.",
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

  // LÃ m pháº³ng danh sÃ¡ch chuyáº¿n Ä‘i tá»« danh sÃ¡ch Tour
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

  // Bá»™ lá»c tÃ¬m kiáº¿m
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

  const loadHandoverRequests = useCallback(async () => {
    try {
      setHandoverRequests(await adminService.getPendingHandoverRequests());
    } catch (err) {
      console.error("Lá»—i táº£i yÃªu cáº§u bÃ n giao:", err);
    }
  }, []);

  useEffect(() => {
    loadHandoverRequests();
  }, [loadHandoverRequests]);

  /**
   * ÄoÃ n Ä‘ang trÃªn Ä‘Æ°á»ng mÃ  chá»‰ cÃ²n má»™t ngÆ°á»i phá»¥ trÃ¡ch.
   *
   * Khi Ä‘Ã³ chá»‰ nhá» Ä‘Æ°á»£c hÆ°á»›ng dáº«n viÃªn Ä‘ang dáº«n Ä‘oÃ n khÃ¡c â€” há» Ä‘Ã£ á»Ÿ ngoÃ i Ä‘Æ°á»ng, tá»›i Ä‘Æ°á»£c ngay.
   * NgÆ°á»i á»Ÿ nhÃ  cÃ¡ch Ä‘oÃ n nhiá»u giá», mÃ  Ä‘Ã³ Ä‘Ãºng lÃ  quÃ£ng Ä‘oÃ n khÃ´ng cÃ³ ai.
   */
  const canNhoTrongHo = handoverPanel?.needs_emergency_cover === true;

  /** Chá»‰ nhá»¯ng ngÆ°á»i nhá» Ä‘Æ°á»£c. LÃºc bÃ¬nh thÆ°á»ng thÃ¬ lÃ  táº¥t cáº£. */
  const nguoiThayChonDuoc = (handoverPanel?.available_guides ?? []).filter(
    (g) => !canNhoTrongHo || g.leading_other_group,
  );

  const khongCoAiNhoDuoc = canNhoTrongHo && nguoiThayChonDuoc.length === 0;

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
      console.error("Lá»—i láº¥y thÃ´ng tin bÃ n giao:", err);
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
      setHandoverError(response?.message || "KhÃ´ng bÃ n giao Ä‘Æ°á»£c.");
    } finally {
      setHandoverSaving(false);
    }
  };

  const openGuideDialog = (schedule: ExtendedSchedule) => {
    setGuideDialogScheduleId(schedule.id);
    setPendingGuideIds((schedule.guides ?? []).map((guide) => guide.id));
  };

  /**
   * Äáº·t láº¡i cáº£ danh sÃ¡ch má»™t láº§n.
   *
   * MÃ¡y chá»§ Ä‘Æ°á»£c Äƒn cáº£ ngÃ£ vá» khÃ´ng: má»™t ngÆ°á»i vÆ°á»›ng lá»‹ch thÃ¬ cáº£ láº§n phÃ¢n cÃ´ng bá»‹ tá»« chá»‘i, nÃªn
   * khÃ´ng cÃ³ tráº¡ng thÃ¡i ná»­a vá»i Ä‘á»ƒ xá»­ lÃ½ á»Ÿ Ä‘Ã¢y.
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
            ? "ÄÃ£ bá» phÃ¢n cÃ´ng hÆ°á»›ng dáº«n viÃªn."
            : `ÄÃ£ phÃ¢n cÃ´ng ${guideIds.length} hÆ°á»›ng dáº«n viÃªn.`,
        type: "success",
        isOpen: true,
      });
    } catch (error: unknown) {
      const message =
        (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
        "KhÃ´ng thá»ƒ phÃ¢n cÃ´ng hÆ°á»›ng dáº«n viÃªn.";
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
        message: `ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i chuyáº¿n khá»Ÿi hÃ nh thÃ nh "${statusLabel[updatedSchedule.status]}".`,
        type: "success",
        isOpen: true,
      });
    } catch (error: unknown) {
      const message =
        (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
        "KhÃ´ng thá»ƒ cáº­p nháº­t tráº¡ng thÃ¡i chuyáº¿n khá»Ÿi hÃ nh.";
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

      // Máº·c Ä‘á»‹nh hoÃ n Ä‘á»§ cho má»i Ä‘Æ¡n: Ä‘Ã³ lÃ  phÆ°Æ¡ng Ã¡n luÃ´n há»£p lá»‡, cÃ²n chuyá»ƒn chuyáº¿n thÃ¬ phá»¥
      // thuá»™c chuyáº¿n Ä‘Ã­ch cÃ³ chá»— hay khÃ´ng. Äiá»u hÃ nh Ä‘á»•i láº¡i tá»«ng Ä‘Æ¡n náº¿u muá»‘n.
      setCancelPlans(
        Object.fromEntries(
          (data?.impact.paid_bookings ?? []).map((don) => [
            don.booking_id,
            { booking_id: don.booking_id, action: "refund" as const },
          ]),
        ),
      );
    } catch (err) {
      console.error("Lá»—i láº¥y tÃ¡c Ä‘á»™ng há»§y chuyáº¿n:", err);
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
      console.error("Lá»—i láº¥y danh sÃ¡ch Ä‘oÃ n:", err);
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
      console.error("Lá»—i táº£i danh sÃ¡ch chuyáº¿n cÃ³ thá»ƒ ghÃ©p:", err);
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
      setMergeError(response?.message || "KhÃ´ng ghÃ©p Ä‘Æ°á»£c chuyáº¿n.");
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
   * TÃ¡c Ä‘á»™ng do mÃ¡y chá»§ tÃ­nh, láº¥y láº¡i má»—i khi ngÆ°á»i dÃ¹ng Ä‘á»•i ngÃ y.
   *
   * Chá» 400ms rá»“i má»›i gá»i: Ã´ datetime-local báº¯n sá»± kiá»‡n theo tá»«ng kÃ½ tá»±, gá»i ngay thÃ¬ gÃµ má»™t
   * chá»¯ sá»‘ lÃ  má»™t lÆ°á»£t gá»i máº¡ng. TÃ­nh á»Ÿ trÃ¬nh duyá»‡t cho nhanh thÃ¬ sá»›m muá»™n con sá»‘ hiá»‡n ra sáº½
   * lá»‡ch vá»›i luáº­t mÃ¡y chá»§ thá»±c sá»± Ã¡p.
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
      setDeadlineError(response?.message || "KhÃ´ng Ä‘á»•i Ä‘Æ°á»£c háº¡n chá»‘t.");
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
      setCancelError(response?.message || "KhÃ´ng há»§y Ä‘Æ°á»£c chuyáº¿n.");
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
            Quáº£n lÃ½ Chuyáº¿n khá»Ÿi hÃ nh
          </h1>
          <p className="text-sm text-gray-500">
            Quáº£n lÃ½ chi tiáº¿t vÃ²ng Ä‘á»i chuyáº¿n Ä‘i, theo dÃµi thá»i háº¡n Ä‘Äƒng kÃ½, chá»‘t chuyáº¿n cháº¡y vÃ  há»§y chuyáº¿n.
          </p>
        </div>
      </div>

      {/*
        YÃªu cáº§u bÃ n giao Ä‘ang chá» â€” Ä‘áº·t ngay dÆ°á»›i tiÃªu Ä‘á», trÃªn cáº£ bá»™ lá»c.

        HÆ°á»›ng dáº«n viÃªn gá»­i lÃªn Ä‘Ãºng lÃºc há» khÃ´ng dáº«n tiáº¿p Ä‘Æ°á»£c, mÃ  Ä‘oÃ n thÃ¬ Ä‘ang trÃªn Ä‘Æ°á»ng. Náº±m
        dÆ°á»›i báº£ng chuyáº¿n thÃ¬ pháº£i cuá»™n háº¿t trang má»›i tháº¥y, vÃ  thá»© nÃ y khÃ´ng chá» Ä‘Æ°á»£c.
      */}
      {handoverRequests.length > 0 && (
        <Link
          to="/admin/handovers"
          className="flex flex-wrap items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm shadow-sm hover:bg-amber-100/60 transition-colors"
        >
          <AlertTriangle className="h-4 w-4 text-amber-700" />
          <span className="font-bold text-amber-900">
            {handoverRequests.length} yÃªu cáº§u bÃ n giao Ä‘ang chá» báº¡n cá»­ ngÆ°á»i thay
          </span>
          <span className="text-xs text-amber-800">
            {handoverRequests[0].requester_name}
            {handoverRequests.length > 1 ? ` vÃ  ${handoverRequests.length - 1} ngÆ°á»i ná»¯a` : ""}
          </span>
          <span className="ml-auto text-xs font-semibold text-amber-900 underline">
            Xá»­ lÃ½ ngay
          </span>
        </Link>
      )}

      {/* FILTER & SEARCH */}
      <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <div className="relative w-full sm:max-w-xs">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="TÃ¬m theo ID, tÃªn tour..."
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
            <option value="all">Táº¥t cáº£ tráº¡ng thÃ¡i</option>
            <option value="open">Äang má»Ÿ bÃ¡n</option>
            <option value="closed">ÄÃ£ Ä‘Ã³ng bÃ¡n</option>
            <option value="confirmed">ÄÃ£ chá»‘t cháº¡y</option>
            <option value="in_progress">Äang di chuyá»ƒn</option>
            <option value="completed">ÄÃ£ hoÃ n thÃ nh</option>
            <option value="cancelled">ÄÃ£ há»§y</option>
          </select>

          {(searchQuery !== "" || statusFilter !== "all") && (
            <button
              type="button"
              title="Äáº·t láº¡i bá»™ lá»c"
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
                  <th className="py-4 px-5">MÃ£ chuyáº¿n</th>
                  <th className="py-4 px-5">Tour du lá»‹ch</th>
                  <th className="py-4 px-5">Thá»i gian khá»Ÿi hÃ nh</th>
                  <th className="py-4 px-5">Háº¡n Ä‘áº·t (Deadline)</th>
                  <th className="py-4 px-5">Sá»‘ khÃ¡ch (Min/Max)</th>
                  <th className="py-4 px-5">HÆ°á»›ng dáº«n viÃªn</th>
                  <th className="py-4 px-5">Tráº¡ng thÃ¡i</th>
                  <th className="py-4 px-5 text-right">Váº­n hÃ nh & Thao tÃ¡c</th>
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
                      {/* MÃ£ chuyáº¿n */}
                      <td className="py-4 px-5 font-bold text-primary-700 font-mono">
                        #{schedule.id}
                      </td>

                      {/* Tour du lá»‹ch */}
                      <td className="py-4 px-5 max-w-xs">
                        <Link
                          to={`/admin/tours/${schedule.tour_id}`}
                          className="font-bold text-gray-900 hover:text-primary-650 transition-colors line-clamp-2"
                        >
                          {schedule.tour_title}
                        </Link>
                      </td>

                      {/* Thá»i gian */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        <div className="flex items-center gap-1.5">
                          <CalendarDays className="h-3.5 w-3.5 text-gray-400" />
                          <div>
                            <p className="font-semibold text-gray-955">
                              {formatDateTime(schedule.start_date)}
                            </p>
                            <p className="text-xs text-gray-400 mt-0.5">
                              Äáº¿n: {getEndDate(schedule.start_date, schedule.number_of_days)}
                            </p>
                          </div>
                        </div>
                      </td>

                      {/* Háº¡n chá»‘t Ä‘áº·t */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        {deadline ? (
                          <div className="flex items-center gap-1.5">
                            <Clock className={`h-3.5 w-3.5 ${isOverdue && status === "open" ? "text-amber-500 animate-pulse" : "text-gray-400"}`} />
                            <div>
                              <p className={`font-semibold ${isOverdue && status === "open" ? "text-amber-600" : "text-gray-955"}`}>
                                {formatDateTime(deadline)}
                              </p>
                              {isOverdue && status === "open" && (
                                <span className="inline-block text-[10px] bg-amber-50 text-amber-700 px-1 py-0.5 rounded font-bold uppercase tracking-wider mt-0.5">QuÃ¡ háº¡n</span>
                              )}
                            </div>
                          </div>
                        ) : (
                          <span className="text-gray-400">KhÃ´ng giá»›i háº¡n</span>
                        )}
                      </td>

                      {/* Sá»‘ khÃ¡ch */}
                      <td className="py-4 px-5 whitespace-nowrap">
                        <div className="flex items-center gap-1.5">
                          <Users className="h-3.5 w-3.5 text-gray-400" />
                          <div>
                            <p className="font-bold text-gray-900">
                              {schedule.booked_people} / {schedule.max_people} khÃ¡ch
                            </p>
                            <p className="text-xs text-gray-400 mt-0.5">
                              Tá»‘i thiá»ƒu: {minPeople} khÃ¡ch
                            </p>
                          </div>
                        </div>
                      </td>

                      {/*
                        HÆ°á»›ng dáº«n viÃªn â€” nhiá»u ngÆ°á»i cho má»™t chuyáº¿n.

                        Hiá»‡n dáº¡ng tháº» rá»“i má»Ÿ há»™p thoáº¡i Ä‘á»ƒ sá»­a, vÃ¬ Ã´ chá»n má»™t dÃ²ng khÃ´ng chá»©a ná»•i
                        danh sÃ¡ch. Bao nhiÃªu ngÆ°á»i lÃ  Ä‘á»§ thÃ¬ Ä‘iá»u hÃ nh quyáº¿t, há»‡ thá»‘ng khÃ´ng
                        cáº£nh bÃ¡o theo sá»‘ khÃ¡ch.
                      */}
                      <td className="py-4 px-5">
                        <div className="flex flex-wrap items-center gap-1 min-w-44">
                          {(schedule.guides ?? []).length === 0 ? (
                            <span className="text-xs text-gray-400">ChÆ°a phÃ¢n cÃ´ng</span>
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
                            Sá»­a
                          </button>
                        </div>
                      </td>

                      {/* Tráº¡ng thÃ¡i */}
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
                              LÃ½ do: {schedule.cancelled_reason}
                            </span>
                          )}
                        </div>
                      </td>

                      {/* Váº­n hÃ nh & Thao tÃ¡c */}
                      <td className="py-4 px-5 text-right whitespace-nowrap">
                        <div className="flex flex-col gap-2 items-end">
                          <div className="flex flex-wrap gap-1 justify-end">
                            {/*
                              G05 - Danh sÃ¡ch Ä‘oÃ n, chia theo tá»«ng nhÃ³m. Tráº£ lá»i hai cÃ¢u á»Ÿ cÃ¹ng
                              má»™t chá»—: gá»­i cho nhÃ  cung cáº¥p Ä‘Æ°á»£c chÆ°a, vÃ  nhÃ³m nÃ y gá»“m nhá»¯ng ai.
                              Äáº·t ngay trÃªn hÃ ng chuyáº¿n vÃ¬ cáº£ hai cÃ¢u Ä‘á»u há»i lÃºc Ä‘ang nhÃ¬n chuyáº¿n,
                              khÃ´ng pháº£i lÃºc Ä‘ang má»Ÿ má»™t Ä‘Æ¡n.
                            */}
                            {status !== "cancelled" && (
                              <button
                                type="button"
                                onClick={() => openManifestCheck(schedule.id)}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Danh sÃ¡ch Ä‘oÃ n
                              </button>
                            )}

                            {/* Open/Close toggle */}
                            {status === "open" && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "closed")}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                ÄÃ³ng bÃ¡n
                              </button>
                            )}
                            {status === "closed" && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "open")}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-primary-655 hover:bg-primary-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Má»Ÿ bÃ¡n láº¡i
                              </button>
                            )}

                            {/* BÃ n giao giá»¯a chá»«ng: chá»‰ cÃ³ nghÄ©a khi Ä‘oÃ n Ä‘Ã£ hoáº·c sáº¯p lÃªn Ä‘Æ°á»ng
                                vÃ  Ä‘ang cÃ³ ngÆ°á»i phá»¥ trÃ¡ch Ä‘á»ƒ mÃ  giao. */}
                            {(status === "confirmed" || status === "in_progress") &&
                              (schedule.guides ?? []).length > 0 && (
                                <button
                                  type="button"
                                  onClick={() => openHandoverDialog(schedule.id)}
                                  className="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition-all active:scale-95 duration-150 cursor-pointer"
                                >
                                  BÃ n giao HDV
                                </button>
                              )}

                            {/* Dá»i háº¡n chá»‘t danh sÃ¡ch. Chuyáº¿n Ä‘Ã£ cháº¡y hoáº·c Ä‘Ã£ xong thÃ¬ má»‘c nÃ y
                                khÃ´ng cÃ²n nghÄ©a gÃ¬ nÃªn khÃ´ng cho sá»­a. */}
                            {(status === "open" || status === "closed" || status === "confirmed") && (
                              <button
                                type="button"
                                onClick={() => openDeadlineDialog(schedule)}
                                className="rounded border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Sá»­a háº¡n chá»‘t
                              </button>
                            )}

                            {/* L03 - GhÃ©p chuyáº¿n. Chá»‰ cÃ³ nghÄ©a khi chuyáº¿n chÆ°a khá»Ÿi hÃ nh vÃ 
                                Ä‘ang Ã­t khÃ¡ch, nÃªn Ä‘á»ƒ cáº¡nh nÃºt chá»‘t chuyáº¿n. */}
                            {(status === "open" || status === "closed" || status === "confirmed") && (
                              <button
                                type="button"
                                onClick={() => openMergeDialog(schedule.id)}
                                className="rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                GhÃ©p chuyáº¿n
                              </button>
                            )}

                            {/* Confirm action */}
                            {(status === "open" || status === "closed") && (
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(schedule.id, "confirmed")}
                                className="rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-700 shadow-sm transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Chá»‘t chuyáº¿n
                              </button>
                            )}

                            {/* Cancel action */}
                            {(status === "open" || status === "closed" || status === "confirmed") && (
                              <button
                                type="button"
                                onClick={() => openCancelDialog(schedule.id)}
                                className="rounded border border-rose-150 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition-all active:scale-95 duration-150 cursor-pointer"
                              >
                                Há»§y chuyáº¿n
                              </button>
                            )}

                            {/* Closed lifecycle states */}
                            {(status === "completed" || status === "cancelled") && (
                              <span className="text-xs text-gray-400 italic">
                                ÄÃ£ káº¿t thÃºc vÃ²ng Ä‘á»i
                              </span>
                            )}
                          </div>

                          <Link
                            to={`/admin/tour-schedules/${schedule.id}/attendance`}
                            className="text-xs font-semibold text-primary-600 hover:underline inline-flex items-center gap-0.5"
                          >
                            Xem Ä‘iá»ƒm danh â†’
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
              itemLabel="chuyáº¿n Ä‘i"
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
          KhÃ´ng tÃ¬m tháº¥y chuyáº¿n Ä‘i nÃ o khá»›p vá»›i bá»™ lá»c.
        </div>
      )}


      {/* BÃ n giao hÆ°á»›ng dáº«n viÃªn giá»¯a chá»«ng */}
      {handoverScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                BÃ n giao hÆ°á»›ng dáº«n viÃªn â€” chuyáº¿n #{handoverScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                NgÆ°á»i cÅ© máº¥t quyá»n ghi ngay khi lÆ°u. Dá»¯ liá»‡u há» Ä‘Ã£ ghi giá»¯ nguyÃªn, chá»‰ chuyá»ƒn
                quyá»n ghi tiáº¿p.
              </p>
            </div>

            {!handoverPanel && <p className="text-sm text-gray-500">Äang táº£i...</p>}

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
                    ? "ChÆ°a bÃ n giao Ä‘Æ°á»£c."
                    : "ÄoÃ n chá»‰ cÃ²n má»™t ngÆ°á»i â€” chá»‰ nhá» Ä‘Æ°á»£c Ä‘oÃ n khÃ¡c."}
                </p>
                <p className="text-xs mt-0.5">
                  {khongCoAiNhoDuoc ? (
                    <>
                      ÄoÃ n Ä‘ang trÃªn Ä‘Æ°á»ng vÃ  khÃ´ng cÃ³ hÆ°á»›ng dáº«n viÃªn nÃ o khÃ¡c Ä‘ang dáº«n Ä‘oÃ n cÃ¹ng
                      lÃºc Ä‘á»ƒ nhá». HÃ£y báº¥m <strong>Sá»­a</strong> á»Ÿ cá»™t hÆ°á»›ng dáº«n viÃªn phÃ¢n cÃ´ng thÃªm
                      má»™t ngÆ°á»i cho chuyáº¿n, rá»“i quay láº¡i Ä‘Ã¢y.
                    </>
                  ) : (
                    <>
                      Gá»¡ ngÆ°á»i dáº«n duy nháº¥t ra thÃ¬ Ä‘oÃ n khÃ´ng cÃ³ ai cho tá»›i khi ngÆ°á»i má»›i tá»›i nÆ¡i.
                      NÃªn chá»‰ chá»n Ä‘Æ°á»£c ngÆ°á»i <strong>Ä‘ang dáº«n má»™t Ä‘oÃ n khÃ¡c</strong> â€” há» Ä‘Ã£ á»Ÿ
                      ngoÃ i Ä‘Æ°á»ng. NgÆ°á»i Ä‘Ã³ sáº½ táº¡m giá»¯ hai Ä‘oÃ n, há»‡ thá»‘ng Ä‘Ã¡nh dáº¥u Ä‘á»ƒ báº¡n xá»­ lÃ½ tiáº¿p.
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
                      ? "KhÃ´ng cÃ³ hÆ°á»›ng dáº«n viÃªn nÃ o Ä‘ang dáº«n Ä‘oÃ n khÃ¡c Ä‘á»ƒ nhá»."
                      : "KhÃ´ng cÃ²n hÆ°á»›ng dáº«n viÃªn nÃ o khÃ¡c Ä‘ang hoáº¡t Ä‘á»™ng Ä‘á»ƒ nháº­n Ä‘oÃ n."}
                  </p>
                ) : (
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">NgÆ°á»i giao</label>
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
                      <label className="block text-xs font-bold text-gray-700 mb-1">NgÆ°á»i nháº­n</label>
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
                            {g.leading_other_group ? " â€” Ä‘ang dáº«n Ä‘oÃ n khÃ¡c" : ""}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    LÃ½ do thay <span className="text-rose-500">*</span>
                  </label>
                  <input
                    value={handoverForm.reason}
                    onChange={(e) => setHandoverForm((truoc) => ({ ...truoc, reason: e.target.value }))}
                    placeholder="VD: HÆ°á»›ng dáº«n viÃªn cÅ© bá»‹ sá»‘t cao, pháº£i vá» sá»›m..."
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-amber-400"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    TÃ¬nh tráº¡ng Ä‘oÃ n <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={3}
                    value={handoverForm.handover_note}
                    onChange={(e) =>
                      setHandoverForm((truoc) => ({ ...truoc, handover_note: e.target.value }))
                    }
                    placeholder="ÄoÃ n Ä‘ang á»Ÿ Ä‘Ã¢u, Ä‘Ã£ Ä‘iá»ƒm danh tá»›i cháº·ng nÃ o, khÃ¡ch nÃ o cáº§n Ä‘á»ƒ Ã½, viá»‡c gÃ¬ Ä‘ang dá»Ÿ..."
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-amber-400"
                  />
                  <p className="mt-1 text-[11px] text-gray-400">
                    Ãt nháº¥t 20 kÃ½ tá»±. NgÆ°á»i nháº­n chá»‰ cÃ³ Ä‘Ãºng Ä‘oáº¡n nÃ y Ä‘á»ƒ báº¯t nhá»‹p vá»›i Ä‘oÃ n.
                  </p>
                </div>

                {/* Lá»‹ch sá»­: chuyáº¿n Ä‘á»•i ngÆ°á»i nhiá»u láº§n thÃ¬ Ä‘Ã¢y lÃ  chá»— láº§n ra ai dáº«n lÃºc nÃ o */}
                {handoverPanel.handovers.length > 0 && (
                  <div className="space-y-1.5">
                    <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      ÄÃ£ bÃ n giao trÆ°á»›c Ä‘Ã³
                    </p>
                    {handoverPanel.handovers.map((bg) => (
                      <div key={bg.id} className="rounded-lg border border-gray-200 p-2.5 text-xs">
                        <p className="font-semibold text-gray-900">
                          {bg.from_guide?.name} â†’ {bg.to_guide?.name}
                          {bg.is_emergency_cover && (
                            <span className="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">
                              Nhá» trÃ´ng há»™
                            </span>
                          )}
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
                Quay láº¡i
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
                {handoverSaving ? "Äang lÆ°u..." : "XÃ¡c nháº­n bÃ n giao"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* PhÃ¢n cÃ´ng hÆ°á»›ng dáº«n viÃªn â€” nhiá»u ngÆ°á»i cho má»™t chuyáº¿n */}
      {guideDialogScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-md rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                HÆ°á»›ng dáº«n viÃªn â€” chuyáº¿n #{guideDialogScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Chá»n Ä‘Æ°á»£c nhiá»u ngÆ°á»i. ÄoÃ n Ä‘Ã´ng thÃ¬ cáº§n thÃªm ngÆ°á»i dáº«n, bao nhiÃªu lÃ  Ä‘á»§ do báº¡n
                quyáº¿t â€” há»‡ thá»‘ng khÃ´ng tÃ­nh há»™ theo sá»‘ khÃ¡ch.
              </p>
            </div>

            <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
              {guides.length === 0 && (
                <p className="px-1 py-2 text-xs text-gray-500">ChÆ°a cÃ³ hÆ°á»›ng dáº«n viÃªn nÃ o.</p>
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
              NgÆ°á»i Ä‘ang báº­n má»™t chuyáº¿n khÃ¡c trÃ¹ng ngÃ y sáº½ bá»‹ mÃ¡y chá»§ tá»« chá»‘i, vÃ  khi Ä‘Ã³ cáº£ láº§n
              phÃ¢n cÃ´ng nÃ y khÃ´ng Ä‘Æ°á»£c lÆ°u.
            </p>

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setGuideDialogScheduleId(null)}
                disabled={assigningScheduleId === guideDialogScheduleId}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Quay láº¡i
              </button>
              <button
                type="button"
                onClick={() => assignGuides(guideDialogScheduleId, pendingGuideIds)}
                disabled={assigningScheduleId === guideDialogScheduleId}
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-40"
              >
                {assigningScheduleId === guideDialogScheduleId ? "Äang lÆ°u..." : "LÆ°u phÃ¢n cÃ´ng"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Dá»i háº¡n chá»‘t danh sÃ¡ch, cÃ³ xem trÆ°á»›c tÃ¡c Ä‘á»™ng */}
      {deadlineScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Háº¡n chá»‘t danh sÃ¡ch â€” chuyáº¿n #{deadlineScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                ÄÃ¢y lÃ  má»‘c gá»­i danh sÃ¡ch khÃ¡ch cho khÃ¡ch sáº¡n vÃ  nhÃ  xe. Dá»i má»‘c nÃ y lÃ  dá»i cÃ¹ng
                lÃºc quyá»n bÃ¡n chá»—, sá»­a tÃªn hÃ nh khÃ¡ch, chuyá»ƒn chuyáº¿n vÃ  ghÃ©p chuyáº¿n.
              </p>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Háº¡n chá»‘t má»›i</label>
              <input
                type="datetime-local"
                value={deadlineValue}
                onChange={(e) => setDeadlineValue(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                Äá»ƒ trá»‘ng thÃ¬ chuyáº¿n dÃ¹ng má»‘c máº·c Ä‘á»‹nh cá»§a há»‡ thá»‘ng.
              </p>
            </div>

            {deadlineLoading && <p className="text-sm text-gray-500">Äang tÃ­nh tÃ¡c Ä‘á»™ng...</p>}

            {deadlineImpact && !deadlineImpact.impact.can_change && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {deadlineImpact.impact.blocked_reason}
              </div>
            )}

            {deadlineImpact && deadlineImpact.impact.can_change && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-2">
                <p className="text-xs font-bold uppercase tracking-wider text-amber-800">
                  {deadlineImpact.impact.direction === "unchanged"
                    ? "ChÆ°a cÃ³ thay Ä‘á»•i nÃ o"
                    : "LÆ°u xong sáº½ cÃ³ hiá»‡u lá»±c ngay"}
                </p>

                <ul className="space-y-1.5">
                  {deadlineImpact.impact.warnings.map((dong) => (
                    <li key={dong} className="text-xs text-amber-900 flex gap-2">
                      <span className="text-amber-500 shrink-0">â€¢</span>
                      <span>{dong}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">LÃ½ do dá»i háº¡n</label>
              <textarea
                rows={2}
                value={deadlineReason}
                onChange={(e) => setDeadlineReason(e.target.value)}
                placeholder="VD: KhÃ¡ch sáº¡n cho thÃªm 2 phÃ²ng, chá»‘t láº¡i ngÃ y 19/08..."
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                KhÃ´ng báº¯t buá»™c, nhÆ°ng cÃ³ thÃ¬ nháº­t kÃ½ vá» sau Ä‘á»c má»›i hiá»ƒu Ä‘Æ°á»£c vÃ¬ sao má»‘c bá»‹ dá»i.
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
                Quay láº¡i
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
                {deadlineSaving ? "Äang lÆ°u..." : "Äá»“ng Ã½, lÆ°u háº¡n chá»‘t má»›i"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* L03 - GhÃ©p chuyáº¿n */}
      {mergeScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-2xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                GhÃ©p chuyáº¿n #{mergeScheduleId} vÃ o chuyáº¿n khÃ¡c
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                ToÃ n bá»™ Ä‘Æ¡n Ä‘Ã£ thanh toÃ¡n sáº½ chuyá»ƒn sang chuyáº¿n Ä‘Ã­ch, giÃ¡ giá»¯ nguyÃªn. Chuyáº¿n nÃ y
                sau Ä‘Ã³ chuyá»ƒn thÃ nh Ä‘Ã£ há»§y.
              </p>
            </div>

            {mergeLoading && <p className="text-sm text-gray-500">Äang tÃ¬m chuyáº¿n phÃ¹ há»£p...</p>}

            {mergeData && mergeData.candidates.length === 0 && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-1">
                <p className="font-semibold">KhÃ´ng cÃ³ chuyáº¿n nÃ o ghÃ©p Ä‘Æ°á»£c.</p>
                <p className="text-xs">
                  Chuyáº¿n Ä‘Ã­ch pháº£i cÃ¹ng tour, cÃ²n Ä‘á»§ chá»—, lá»‡ch ngÃ y khÃ´ng quÃ¡ 2 ngÃ y, vÃ {" "}
                  <strong>cáº£ hai chuyáº¿n Ä‘á»u cÃ²n trÆ°á»›c háº¡n chá»‘t danh sÃ¡ch</strong> â€” vÃ¬ má»¥c Ä‘Ã­ch cá»§a
                  ghÃ©p lÃ  gá»­i má»™t danh sÃ¡ch Ä‘Ãºng cho nhÃ  cung cáº¥p, thay vÃ¬ gá»­i hai rá»“i Ä‘i vÃ¡.
                </p>
              </div>
            )}

            {/* MÃ¡y chá»§ Ä‘Ã£ loáº¡i chuyáº¿n khÃ´ng ghÃ©p Ä‘Æ°á»£c vÃ  tÃ­nh sáºµn tÃ¡c Ä‘á»™ng, nÃªn Ä‘Ã¢y chá»‰ hiá»ƒn thá»‹. */}
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
                        #{item.schedule_id} Â· {formatDateTime(item.start_date)}
                      </span>
                      <span className="text-[11px] text-gray-500">
                        {item.booked_people}/{item.max_people} chá»—
                      </span>
                    </div>
                    <p className="mt-1 text-xs text-gray-600">
                      Chuyá»ƒn {item.transferring} Ä‘Æ¡n ({item.transferring_guests} khÃ¡ch)
                      {item.cancelling > 0 && (
                        <span className="text-amber-800 font-semibold">
                          {" "}Â· há»§y {item.cancelling} Ä‘Æ¡n chÆ°a thanh toÃ¡n
                        </span>
                      )}
                    </p>
                  </button>
                );
              })}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                LÃ½ do ghÃ©p <span className="text-rose-500">*</span>
              </label>
              <textarea
                rows={2}
                value={mergeReason}
                onChange={(e) => setMergeReason(e.target.value)}
                placeholder="VD: Hai chuyáº¿n Ä‘á»u chÆ°a Ä‘á»§ khÃ¡ch tá»‘i thiá»ƒu nÃªn dá»“n vá» má»™t chuyáº¿n..."
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                KhÃ¡ch sáº½ Ä‘á»c Ä‘Æ°á»£c ná»™i dung nÃ y khi Ä‘Æ°á»£c thÃ´ng bÃ¡o Ä‘á»•i ngÃ y khá»Ÿi hÃ nh.
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
                KhÃ´ng ghÃ©p ná»¯a
              </button>
              <button
                type="button"
                onClick={confirmMerge}
                disabled={mergeSaving || !mergeTargetId || mergeReason.trim().length < 10}
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-40"
              >
                {mergeSaving ? "Äang ghÃ©p..." : "XÃ¡c nháº­n ghÃ©p"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* G05 - Kiá»ƒm tra danh sÃ¡ch Ä‘oÃ n trÆ°á»›c khi gá»­i nhÃ  cung cáº¥p */}
      {manifestScheduleId !== null && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-2xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Danh sÃ¡ch Ä‘oÃ n â€” chuyáº¿n #{manifestScheduleId}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                Má»—i Ä‘Æ¡n lÃ  má»™t nhÃ³m, thÆ°á»ng do má»™t ngÆ°á»i Ä‘á»©ng ra Ä‘Äƒng kÃ½ cho cáº£ nhÃ  hoáº·c cáº£ phÃ²ng
                ban. Báº¥m vÃ o nhÃ³m Ä‘á»ƒ xem nhÃ³m Ä‘Ã³ gá»“m nhá»¯ng ai.
              </p>
            </div>

            {manifestLoading && <p className="text-sm text-gray-500">Äang táº£i danh sÃ¡ch...</p>}

            {manifest && (
              <>
                {manifest.can_export_manifest ? (
                  <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                    {manifest.total_groups} nhÃ³m, {manifest.total_guests} khÃ¡ch, Ä‘Ã£ khai Ä‘á»§. Gá»­i
                    Ä‘Æ°á»£c cho khÃ¡ch sáº¡n vÃ  nhÃ  xe.
                  </div>
                ) : (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                    {manifest.total_groups} nhÃ³m, Ä‘Ã£ khai {manifest.total_declared} trÃªn{" "}
                    {manifest.total_guests} khÃ¡ch. ChÆ°a gá»­i Ä‘Æ°á»£c danh sÃ¡ch Ä‘oÃ n.
                  </div>
                )}

                {manifest.groups.length === 0 && (
                  <p className="text-sm text-gray-500">Chuyáº¿n nÃ y chÆ°a cÃ³ Ä‘Æ¡n nÃ o.</p>
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
                              BK-{nhom.booking_id} Â· {nhom.customer_name}
                            </span>
                            <span className="flex items-center gap-2">
                              <span
                                className={`font-mono ${
                                  nhom.missing > 0 ? "text-amber-700 font-bold" : "text-gray-500"
                                }`}
                              >
                                {nhom.declared}/{nhom.guests} ngÆ°á»i
                              </span>
                              <span className="text-gray-400">{dangMo ? "â–¾" : "â–¸"}</span>
                            </span>
                          </div>

                          <p className="mt-0.5 flex flex-wrap gap-x-3 text-gray-500">
                            {nhom.customer_phone && <span>{nhom.customer_phone}</span>}
                            {nguoiLienHe && <span>LiÃªn há»‡ Ä‘oÃ n: {nguoiLienHe.name}</span>}
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
                                NhÃ³m nÃ y chÆ°a khai tÃªn ngÆ°á»i nÃ o.
                              </p>
                            ) : (
                              <table className="w-full text-xs">
                                <thead>
                                  <tr className="text-left text-gray-500">
                                    <th className="pb-1 font-semibold">Há» tÃªn</th>
                                    <th className="pb-1 font-semibold">Loáº¡i</th>
                                    <th className="pb-1 font-semibold">NgÃ y sinh</th>
                                    <th className="pb-1 font-semibold">Giáº¥y tá»</th>
                                  </tr>
                                </thead>
                                <tbody className="align-top">
                                  {nhom.passengers.map((khach) => (
                                    <tr key={khach.id} className="border-t border-gray-200">
                                      <td className="py-1.5 pr-2 font-semibold text-gray-900">
                                        {khach.name}
                                        {khach.is_contact && (
                                          <span className="ml-1.5 rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary-700">
                                            LiÃªn há»‡
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
                                          ? "NgÆ°á»i lá»›n"
                                          : khach.type === "child"
                                            ? "Tráº» em"
                                            : "Em bÃ©"}
                                      </td>
                                      <td className="py-1.5 pr-2 text-gray-600">
                                        {khach.date_of_birth
                                          ? formatDateTime(khach.date_of_birth)
                                          : "â€”"}
                                      </td>
                                      <td className="py-1.5 font-mono text-gray-600">
                                        {khach.identity_number ?? "â€”"}
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
                ÄÃ³ng
              </button>
            </div>
          </div>
        </div>
      )}

      {/*
        K - Há»§y chuyáº¿n, ba bÆ°á»›c báº¯t buá»™c: xem tÃ¡c Ä‘á»™ng, gÃ¡n phÆ°Æ¡ng Ã¡n cho tá»«ng Ä‘Æ¡n Ä‘Ã£ thanh toÃ¡n,
        rá»“i má»›i xÃ¡c nháº­n. TrÆ°á»›c Ä‘Ã¢y chá»— nÃ y chá»‰ há»i lÃ½ do rá»“i Ä‘á»•i tráº¡ng thÃ¡i, cÃ²n Ä‘Æ¡n cá»§a khÃ¡ch
        thÃ¬ khÃ´ng ai Ä‘á»¥ng tá»›i.
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
                  Há»§y chuyáº¿n #{cancellingScheduleId}
                </h4>
                <p className="text-xs text-gray-500 mt-0.5">
                  Lá»—i khÃ´ng thuá»™c vá» khÃ¡ch, nÃªn má»—i Ä‘Æ¡n Ä‘Ã£ thanh toÃ¡n pháº£i Ä‘Æ°á»£c hoÃ n Ä‘á»§ 100% hoáº·c
                  chuyá»ƒn miá»…n phÃ­ sang chuyáº¿n khÃ¡c. KhÃ´ng Ã¡p báº£ng phÃ­ há»§y.
                </p>
              </div>
            </div>

            {cancelPreviewLoading && <p className="text-sm text-gray-500">Äang tÃ­nh tÃ¡c Ä‘á»™ng...</p>}

            {cancelPreview && !cancelPreview.impact.can_cancel && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {cancelPreview.impact.blocked_reason}
              </div>
            )}

            {cancelPreview && cancelPreview.impact.can_cancel && (
              <>
                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-1">
                  <p>
                    <strong>{cancelPreview.impact.total_paid_bookings} Ä‘Æ¡n Ä‘Ã£ thanh toÃ¡n</strong>{" "}
                    ({cancelPreview.impact.total_paid_guests} khÃ¡ch), tá»•ng Ä‘Ã£ thu{" "}
                    <strong>{formatPrice(cancelPreview.impact.total_refund_if_all_refunded)}</strong>.
                  </p>
                  {cancelPreview.impact.unpaid_bookings > 0 && (
                    <p className="text-xs">
                      NgoÃ i ra {cancelPreview.impact.unpaid_bookings} Ä‘Æ¡n chÆ°a thanh toÃ¡n (
                      {cancelPreview.impact.unpaid_guests} khÃ¡ch) sáº½ Ä‘Æ°á»£c há»§y tá»± Ä‘á»™ng, khÃ´ng cáº§n
                      chá»n phÆ°Æ¡ng Ã¡n.
                    </p>
                  )}
                </div>

                {cancelPreview.impact.paid_bookings.length === 0 ? (
                  <p className="text-sm text-gray-500">
                    Chuyáº¿n nÃ y chÆ°a cÃ³ Ä‘Æ¡n nÃ o Ä‘Ã£ thanh toÃ¡n.
                  </p>
                ) : (
                  <div className="space-y-2">
                    <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      PhÆ°Æ¡ng Ã¡n cho tá»«ng Ä‘Æ¡n
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
                              BK-{don.booking_id} Â· {don.customer_name}
                            </span>
                            <span className="text-gray-500">
                              {don.guests} khÃ¡ch Â· Ä‘Ã£ thu {formatPrice(don.paid_amount)}
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
                              HoÃ n Ä‘á»§ {formatPrice(don.paid_amount)}
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
                              Chuyá»ƒn sang chuyáº¿n khÃ¡c
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
                                    #{item.schedule_id} Â· {formatDateTime(item.start_date)} Â· cÃ²n{" "}
                                    {item.remaining_seats} chá»—
                                  </option>
                                ))}
                              </select>
                            )}
                          </div>

                          {cancelPreview.impact.transfer_options.length === 0 && (
                            <p className="text-[11px] text-gray-400">
                              KhÃ´ng cÃ³ chuyáº¿n nÃ o nháº­n Ä‘Æ°á»£c khÃ¡ch, nÃªn chá»‰ cÃ²n cÃ¡ch hoÃ n tiá»n.
                            </p>
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    LÃ½ do há»§y <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={2}
                    value={cancelReasonInput}
                    onChange={(e) => setCancelReasonInput(e.target.value)}
                    placeholder="VD: NhÃ  xe bÃ¡o há»ng xe, khÃ´ng thu xáº¿p Ä‘Æ°á»£c xe thay tháº¿..."
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
                  />
                  <p className="text-[11px] text-gray-400 mt-1">
                    KhÃ¡ch sáº½ Ä‘á»c Ä‘Æ°á»£c ná»™i dung nÃ y. Ãt nháº¥t 10 kÃ½ tá»±.
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
                KhÃ´ng há»§y ná»¯a
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
                {cancelSaving ? "Äang há»§y..." : "XÃ¡c nháº­n há»§y chuyáº¿n"}
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
