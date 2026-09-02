import { useState, useEffect } from "react";
import type { Booking, BookingLedger } from "@/types";
import adminService from "@/services/adminService";
import type {
  BookingAuditEntry,
  BookingContractInfo,
  BookingListSummary,
  CancelPreview,
  ContactLog,
  TransferOption,
  TransferReasonCategory,
} from "@/services/adminService";
import {
  KENH_LIEN_HE,
  KET_QUA_LIEN_HE,
  NHOM_LY_DO_CHUYEN,
} from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";

/** Hình thức thu tiền khi xác nhận đơn bằng tay. */
type ConfirmMethod = "cash" | "bank_transfer" | "gateway";
import { StepperModal } from "@/components/admin/StepperModal";
import { formatDateTime, formatPrice } from "@/utils/format";

/** Nhãn tiếng Việt cho cột `method` của sổ giao dịch. `gateway` là khoản do VNPay báo về. */
const METHOD_LABEL: Record<string, string> = {
  bank_transfer: "Chuyển khoản",
  cash: "Tiền mặt",
  gateway: "Cổng thanh toán",
};

/*
 * Năm trạng thái mà luồng hiện tại sinh ra được, khớp với `BookingStatus::liveValues()`.
 *
 * Trước đây bảng chỉ vẽ ba trạng thái đầu, nên đơn của mọi chuyến đã đi xong hiện ra một cái nhãn
 * màu vàng rỗng không chữ — trông như dữ liệu hỏng, trong khi đơn hoàn toàn bình thường.
 */
const NHAN_TRANG_THAI: Record<string, string> = {
  pending: "Chờ xác nhận",
  confirmed: "Đã xác nhận",
  cancelled: "Đã hủy",
  completed: "Đã hoàn thành",
  no_show: "Không có mặt",
};

const MAU_TRANG_THAI: Record<string, string> = {
  pending: "bg-amber-50 text-amber-700 border-amber-200",
  confirmed: "bg-blue-50 text-blue-700 border-blue-200",
  cancelled: "bg-rose-50 text-rose-700 border-rose-200",
  completed: "bg-emerald-50 text-emerald-700 border-emerald-200",
  no_show: "bg-slate-100 text-slate-600 border-slate-300",
};

export default function BookingManagement() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalBookingsCount, setTotalBookingsCount] = useState(0);

  const [search, setSearch] = useState("");
  /**
   * Từ khóa đã ngừng gõ — đây mới là thứ gửi lên máy chủ.
   *
   * Tách khỏi `search` vì mỗi ký tự gõ vào là một lượt gọi mạng nếu không đợi. Ô nhập vẫn phản hồi
   * tức thì theo `search`, chỉ có truy vấn là chậm lại một nhịp.
   */
  const [tuKhoaTim, setTuKhoaTim] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [paymentFilter, setPaymentFilter] = useState<string>("all");
  const [sortBy, setSortBy] = useState<string>("latest");
  const [summary, setSummary] = useState<BookingListSummary | null>(null);

  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Sổ giao dịch của đơn đang mở, và biểu mẫu ghi một khoản thu ngoài cổng thanh toán.
  const [ledger, setLedger] = useState<BookingLedger | null>(null);
  const [showPaymentLogs, setShowPaymentLogs] = useState(false);
  const [paymentMode, setPaymentMode] = useState(false);
  const [paymentSaving, setPaymentSaving] = useState(false);
  const [paymentError, setPaymentError] = useState("");
  const [paymentForm, setPaymentForm] = useState({
    kind: "balance",
    amount: "",
    method: "bank_transfer",
    reference: "",
  });

  /** Đang mở form thu tiền của bước xác nhận đơn. */
  const [confirmMode, setConfirmMode] = useState(false);
  const [confirmForm, setConfirmForm] = useState<{ amount: string; method: ConfirmMethod }>({
    amount: "",
    method: "bank_transfer",
  });

  const [cancelMode, setCancelMode] = useState(false);
  /** Bước đang mở của hai luồng nhiều bước. Xem StepperModal. */
  const [buocHuy, setBuocHuy] = useState(0);
  const [buocChuyen, setBuocChuyen] = useState(0);
  const [cancelReason, setCancelReason] = useState("");
  const [cancelPreview, setCancelPreview] = useState<CancelPreview | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);

  // E04 - Dòng thời gian thay đổi của đơn
  const [history, setHistory] = useState<BookingAuditEntry[]>([]);
  const [showHistory, setShowHistory] = useState(false);

  // Q - Hợp đồng du lịch. `null` nghĩa là đơn này chưa được cấp hợp đồng.
  const [contract, setContract] = useState<BookingContractInfo | null>(null);
  const [contractBusy, setContractBusy] = useState(false);

  // Sửa thông tin liên hệ nhập nhầm. Không bị hạn chốt khóa, khác danh sách hành khách.
  const [editingContact, setEditingContact] = useState(false);
  const [contactForm, setContactForm] = useState({
    customer_name: "",
    customer_email: "",
    customer_phone: "",
  });
  const [contactSaving, setContactSaving] = useState(false);
  const [contactError, setContactError] = useState("");

  // I06 - Chuyển đơn sang chuyến khác
  const [transferMode, setTransferMode] = useState(false);
  const [transferOptions, setTransferOptions] = useState<TransferOption[]>([]);
  const [transferLoading, setTransferLoading] = useState(false);
  const [transferTargetId, setTransferTargetId] = useState<number | null>(null);
  const [transferReason, setTransferReason] = useState("");
  const [sameTourOnly, setSameTourOnly] = useState(true);
  // Khách gọi lên xin đổi thì vẫn là "customer", dù người bấm nút là điều hành. Chỉ chọn
  // "company" khi công ty tự chuyển vì lý do vận hành.
  const [initiatedBy, setInitiatedBy] = useState<"customer" | "company">("customer");

  /*
   * Chuyển chuyến phải dựa vào một cuộc trao đổi với khách.
   *
   * `canCuId` là bản ghi được chọn làm căn cứ. Máy chủ mới là chỗ quyết - nó từ chối nếu thiếu,
   * nếu bản ghi không phải "khách đồng ý", hoặc nếu nó đã dùng cho một lần chuyển trước. Ở đây chỉ
   * là để người dùng thấy trước, khỏi bấm rồi mới biết.
   */
  const [contactLogs, setContactLogs] = useState<ContactLog[]>([]);
  const [canCuId, setCanCuId] = useState<number | null>(null);
  const [nhomLyDo, setNhomLyDo] = useState<TransferReasonCategory>("customer_request");

  // Khung ghi nhanh một cuộc liên hệ, mở ngay trong bước chuyển chuyến.
  const [ghiLienHe, setGhiLienHe] = useState(false);
  const [kenhLienHe, setKenhLienHe] = useState<string>("phone");
  const [ketQuaLienHe, setKetQuaLienHe] = useState<string>("agreed");
  const [noiDungLienHe, setNoiDungLienHe] = useState("");

  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState("");

  /*
   * Đợi người dùng gõ xong rồi mới hỏi máy chủ.
   *
   * 350ms là khoảng giữa hai phím của người gõ bình thường: ngắn hơn thì mỗi ký tự một truy vấn,
   * dài hơn thì cảm giác như màn hình bị treo.
   */
  useEffect(() => {
    const hen = setTimeout(() => {
      setTuKhoaTim(search.trim());
      // Đổi từ khóa thì kết quả là một danh sách khác hẳn; đứng lại ở trang 5 của danh sách cũ chỉ
      // ra một trang trống.
      setCurrentPage(1);
    }, 350);

    return () => clearTimeout(hen);
  }, [search]);

  /*
   * Danh sách lấy từ máy chủ, kèm nguyên bộ lọc.
   *
   * Mọi tham số đều phải đi cùng nhau mỗi lần gọi, kể cả khi người dùng chỉ bấm sang trang khác:
   * trang 2 của "đơn đã hủy, giá giảm dần" không phải trang 2 của danh sách mặc định.
   */
  useEffect(() => {
    let daHuy = false;

    const tai = async () => {
      setLoading(true);
      try {
        const res = await adminService.getBookings({
          page: currentPage,
          q: tuKhoaTim,
          status: statusFilter,
          payment: paymentFilter,
          sort: sortBy,
        });

        // Người dùng đã gõ tiếp trong lúc chờ: kết quả này đã cũ, bỏ đi. Không có chốt này thì hai
        // lượt gọi về không đúng thứ tự sẽ dán kết quả của từ khóa cũ đè lên từ khóa mới.
        if (daHuy) return;

        if (res) {
          setBookings(res.data || []);
          setTotalPages(res.last_page || 1);
          setTotalBookingsCount(res.total || 0);
          setSummary(res.summary ?? null);
        }
      } catch (err) {
        if (!daHuy) console.error("Lỗi lấy danh sách đơn đặt: ", err);
      } finally {
        if (!daHuy) setLoading(false);
      }
    };

    tai();

    return () => {
      daHuy = true;
    };
  }, [currentPage, tuKhoaTim, statusFilter, paymentFilter, sortBy]);

  /** Đổi bộ lọc thì luôn quay về trang 1, vì số trang của danh sách mới khác hẳn. */
  const doiBoLoc = (dat: () => void) => {
    dat();
    setCurrentPage(1);
  };

  const xoaBoLoc = () => {
    setSearch("");
    setTuKhoaTim("");
    setStatusFilter("all");
    setPaymentFilter("all");
    setSortBy("latest");
    setCurrentPage(1);
  };

  /*
   * Số liệu do máy chủ tính trên toàn bộ bộ lọc.
   *
   * Trước đây phần này đếm trên mười dòng của trang đang xem, và nhãn trên ô ghi thẳng "(Trang
   * này)" — màn hình biết mình đang nói một con số vô nghĩa và chọn cách chú thích thay vì sửa.
   */
  const stats = summary ?? {
    total: totalBookingsCount,
    pending: 0,
    confirmed: 0,
    cancelled: 0,
    paid: 0,
    revenue: 0,
  };

  const dangLoc =
    tuKhoaTim !== "" || statusFilter !== "all" || paymentFilter !== "all";

  /** Chuyến đích đang chọn, để bước xác nhận nhắc lại đúng thứ sắp xảy ra. */
  const chuyenDich = transferOptions.find((o) => o.schedule_id === transferTargetId) ?? null;

  /**
   * Lý do chặn dùng chung cho MỌI chuyến đích, hoặc null.
   *
   * Phần lớn luật chặn thuộc về đơn chứ không về chuyến đích: quá hạn chốt ở chuyến gốc, hoặc
   * khách xin đổi khi còn dưới bảy ngày. Lúc ấy cả danh sách cùng đỏ vì một câu, và lặp câu đó
   * mười lần không nói thêm được gì.
   */
  const lyDoChanChung =
    transferOptions.length > 0 &&
    transferOptions.every(
      (o) => !o.can_transfer && o.blocked_reason === transferOptions[0].blocked_reason,
    )
      ? transferOptions[0].blocked_reason
      : null;

  // Xem chi tiết đơn hàng (Gọi API chi tiết để lấy thông tin sâu hơn như payment log)
  const openDetails = async (booking: Booking) => {
    setSelectedBooking(booking);
    setIsModalOpen(true);
    setHistory([]);
    setShowHistory(false);
    setEditingContact(false);
    setContract(null);
    setLedger(null);
    setPaymentMode(false);
    setShowPaymentLogs(false);

    adminService
      .getBookingLedger(booking.id)
      .then(setLedger)
      .catch((err) => console.error("Lỗi lấy sổ giao dịch:", err));

    // Hỏi luôn tình trạng hợp đồng, để nút hiện đúng chữ ngay lần vẽ đầu thay vì đổi sau một nhịp.
    adminService
      .getBookingContract(booking.id)
      .then(setContract)
      .catch((err) => console.error("Lỗi lấy tình trạng hợp đồng:", err));

    try {
      const detailed = await adminService.getBookingById(booking.id);
      if (detailed) {
        setSelectedBooking(detailed);
      }
    } catch (err) {
      console.error("Lỗi lấy chi tiết đơn đặt hàng: ", err);
    }

    // Tải nhật ký song song với chi tiết. Nó là thứ đầu tiên người ta mở khi có khiếu nại, nên
    // đừng bắt bấm thêm một lần nữa mới đi lấy.
    try {
      setHistory(await adminService.getBookingHistory(booking.id));
    } catch (err) {
      console.error("Lỗi lấy lịch sử đơn đặt hàng: ", err);
    }
  };

  /**
   * Ghi một khoản tiền nhận ngoài cổng thanh toán vào sổ.
   *
   * Tải lại cả sổ lẫn chi tiết đơn sau khi ghi: khoản này có thể vừa làm đơn đủ tiền, và khi đó
   * `paid_at` đóng lại — thứ quyết định các nút hủy và hoàn ở màn này hiện ra thế nào.
   */
  const ghiKhoanThu = async () => {
    if (!selectedBooking) return;

    setPaymentSaving(true);
    setPaymentError("");

    try {
      await adminService.recordBookingPayment(selectedBooking.id, {
        kind: paymentForm.kind,
        amount: Number(paymentForm.amount),
        method: paymentForm.method,
        reference: paymentForm.reference.trim() || undefined,
      });

      setPaymentMode(false);
      setLedger(await adminService.getBookingLedger(selectedBooking.id));

      const detailed = await adminService.getBookingById(selectedBooking.id);
      if (detailed) applyBookingUpdate(detailed);

      setHistory(await adminService.getBookingHistory(selectedBooking.id));
    } catch (err) {
      setPaymentError(
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
          "Không ghi được khoản thu.",
      );
    } finally {
      setPaymentSaving(false);
    }
  };

  const openContactEditor = (booking: Booking) => {
    setContactForm({
      customer_name: booking.customer_name ?? "",
      customer_email: booking.customer_email ?? "",
      customer_phone: booking.customer_phone ?? "",
    });
    setContactError("");
    setEditingContact(true);
  };

  const saveContact = async () => {
    if (!selectedBooking) return;

    setContactSaving(true);
    setContactError("");

    try {
      const moi = {
        customer_name: contactForm.customer_name.trim(),
        customer_email: contactForm.customer_email.trim(),
        customer_phone: contactForm.customer_phone.trim() || null,
      };

      await adminService.updateBookingContact(selectedBooking.id, moi);

      setSelectedBooking((truoc) => (truoc ? { ...truoc, ...moi } : truoc));

      // Cập nhật luôn dòng trong bảng, khỏi phải tải lại cả trang danh sách.
      setBookings((truoc) =>
        truoc.map((item) => (item.id === selectedBooking.id ? { ...item, ...moi } : item)),
      );

      setEditingContact(false);

      // Nhật ký vừa có thêm một dòng, lấy lại để màn lịch sử khớp ngay.
      setHistory(await adminService.getBookingHistory(selectedBooking.id));
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setContactError(response?.message || "Không lưu được thông tin liên hệ.");
    } finally {
      setContactSaving(false);
    }
  };

  const closeDetails = () => {
    setIsModalOpen(false);
    setSelectedBooking(null);
    setCancelMode(false);
    setCancelReason("");
    setCancelPreview(null);
    setActionError("");
    setContract(null);
  };

  /*
   * Q - Cấp hợp đồng rồi mở bản in.
   *
   * Gọi lại không sinh số mới, máy chủ trả lại đúng bản đã cấp — nên nút này bấm mấy lần cũng
   * an toàn, và người dùng không phải phân biệt "cấp" với "mở lại".
   *
   * Mở tab mới bằng liên kết có chữ ký: trang in là HTML, không phải JSON, và nó tự gọi hộp thoại
   * in khi tải xong.
   */
  const moHopDong = async () => {
    if (!selectedBooking) return;

    setContractBusy(true);
    setActionError("");

    try {
      const daCap = contract ?? (await adminService.issueBookingContract(selectedBooking.id));
      setContract(daCap);
      window.open(daCap.print_url, "_blank", "noopener");
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setActionError(response?.message || "Không cấp được hợp đồng cho đơn này.");
    } finally {
      setContractBusy(false);
    }
  };

  const ghiNhanDaKy = async () => {
    if (!contract) return;

    const ghiChu = window.prompt("Ghi chú về việc ký (không bắt buộc):", "");
    if (ghiChu === null) return;

    try {
      await adminService.markContractSigned(contract.id, ghiChu.trim() || undefined);
      setContract(await adminService.getBookingContract(contract.booking_id));
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setActionError(response?.message || "Không ghi nhận được.");
    }
  };

  /**
   * Mở form hủy và hỏi máy chủ trước xem hủy đơn này sẽ ra sao.
   *
   * Hỏi máy chủ chứ không tự tính ở đây: bảng phí đã sao chép vào từng đơn lúc đặt, và quy tắc
   * trả chỗ phụ thuộc hạn chốt danh sách của chuyến. Tính lại ở trình duyệt thì sớm muộn cũng
   * lệch với con số máy chủ thực sự áp dụng.
   */
  /*
   * Mở luồng chuyển chuyến từ đầu.
   *
   * Tách khỏi `openTransferForm` vì hàm kia còn được gọi lại mỗi lần đổi "ai yêu cầu" hay "nhóm lý
   * do" — để nó tự nhảy về bước 1 thì đổi một ô ở bước 2 là mất chỗ đang đứng.
   */
  const moChuyenChuyen = () => {
    setBuocChuyen(0);
    openTransferForm();
  };

  const openCancelForm = async () => {
    if (!selectedBooking) return;

    setBuocHuy(0);
    setCancelMode(true);
    setActionError("");
    setCancelPreview(null);
    setPreviewLoading(true);

    try {
      setCancelPreview(await adminService.getCancelPreview(selectedBooking.id));
    } catch (err) {
      setActionError(extractApiError(err, "Không lấy được dự báo hủy đơn."));
    } finally {
      setPreviewLoading(false);
    }
  };

  // Cập nhật đơn trong cả modal lẫn danh sách sau khi admin thao tác
  const applyBookingUpdate = (updated: Booking) => {
    setSelectedBooking(updated);
    setBookings((prev) => prev.map((b) => (b.id === updated.id ? { ...b, ...updated } : b)));
  };

  const extractApiError = (err: unknown, fallback: string) => {
    const response = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data;
    return response?.errors ? Object.values(response.errors).flat()[0] ?? fallback : response?.message ?? fallback;
  };

  /**
   * Mở form thu tiền trước khi xác nhận.
   *
   * Điền sẵn đúng số đơn còn thiếu — đó là con số đúng trong hầu hết trường hợp, và người bấm vẫn
   * sửa được khi khách chỉ đưa trước một phần.
   */
  const openConfirmForm = () => {
    if (!selectedBooking) return;

    const conThieu =
      Number(selectedBooking.balance_due ?? selectedBooking.total_amount ?? 0) || 0;

    setConfirmForm({ amount: conThieu > 0 ? String(conThieu) : "", method: "bank_transfer" });
    setActionError("");
    setConfirmMode(true);
  };

  const handleConfirm = async () => {
    if (!selectedBooking) return;
    setActionLoading(true);
    setActionError("");
    try {
      const soTien = Number(confirmForm.amount);

      const updated = await adminService.confirmBooking(
        selectedBooking.id,
        soTien > 0 ? { amount: soTien, method: confirmForm.method } : undefined,
      );

      if (updated) applyBookingUpdate(updated);
      setConfirmMode(false);
    } catch (err) {
      setActionError(extractApiError(err, "Không thể xác nhận đơn. Vui lòng thử lại."));
    } finally {
      setActionLoading(false);
    }
  };

  const openTransferForm = async (
    sameTour = sameTourOnly,
    khoiXuong: "customer" | "company" = initiatedBy,
    nhom: TransferReasonCategory = nhomLyDo,
  ) => {
    if (!selectedBooking) return;

    setTransferMode(true);
    setActionError("");
    setTransferTargetId(null);
    setTransferReason("");
    setTransferLoading(true);
    setSameTourOnly(sameTour);
    setInitiatedBy(khoiXuong);
    setNhomLyDo(nhom);

    try {
      const [result, logs] = await Promise.all([
        adminService.getTransferOptions(selectedBooking.id, sameTour, khoiXuong, nhom),
        adminService.getContactLogs(selectedBooking.id),
      ]);

      setTransferOptions(result?.options ?? []);
      setContactLogs(logs);

      // Chỉ có đúng một căn cứ dùng được thì chọn sẵn: không có gì để cân nhắc.
      const dungDuoc = logs.filter((l) => l.dung_lam_can_cu_duoc);
      setCanCuId(dungDuoc.length === 1 ? dungDuoc[0].id : null);
    } catch (err) {
      setActionError(extractApiError(err, "Không lấy được danh sách chuyến có thể chuyển."));
    } finally {
      setTransferLoading(false);
    }
  };

  const closeTransferForm = () => {
    setTransferMode(false);
    setTransferOptions([]);
    setTransferTargetId(null);
    setTransferReason("");
    setContactLogs([]);
    setCanCuId(null);
    setGhiLienHe(false);
    setNoiDungLienHe("");
  };

  /**
   * Ghi nhận một cuộc liên hệ, rồi nạp lại danh sách căn cứ.
   *
   * Đặt ngay trong khung chuyển chuyến chứ không bắt điều hành sang màn khác: cuộc gọi vừa xong,
   * họ đang cầm điện thoại, và bắt họ đi tìm chỗ ghi là cách nhanh nhất để không ai ghi.
   */
  const ghiNhanLienHe = async () => {
    if (!selectedBooking || noiDungLienHe.trim().length < 10) return;

    setActionLoading(true);
    setActionError("");

    try {
      await adminService.createContactLog(selectedBooking.id, {
        channel: kenhLienHe,
        purpose: "transfer",
        outcome: ketQuaLienHe,
        note: noiDungLienHe.trim(),
      });

      const logs = await adminService.getContactLogs(selectedBooking.id);
      setContactLogs(logs);

      const dungDuoc = logs.filter((l) => l.dung_lam_can_cu_duoc);
      if (dungDuoc.length > 0) setCanCuId(dungDuoc[0].id);

      setGhiLienHe(false);
      setNoiDungLienHe("");
    } catch (err) {
      setActionError(extractApiError(err, "Không ghi được cuộc liên hệ."));
    } finally {
      setActionLoading(false);
    }
  };

  const handleTransfer = async () => {
    if (!selectedBooking || !transferTargetId || !canCuId || transferReason.trim().length < 10) return;

    setActionLoading(true);
    setActionError("");

    try {
      await adminService.transferBooking(
        selectedBooking.id,
        transferTargetId,
        transferReason.trim(),
        canCuId,
        nhomLyDo,
        initiatedBy,
      );

      const detailed = await adminService.getBookingById(selectedBooking.id);
      if (detailed) applyBookingUpdate(detailed);

      setHistory(await adminService.getBookingHistory(selectedBooking.id));
      closeTransferForm();
    } catch (err) {
      setActionError(extractApiError(err, "Không chuyển được chuyến."));
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancel = async () => {
    if (!selectedBooking || !cancelReason.trim()) return;
    setActionLoading(true);
    setActionError("");
    try {
      const updated = await adminService.cancelBooking(selectedBooking.id, cancelReason.trim());
      if (updated) {
        applyBookingUpdate(updated);
        setCancelMode(false);
        setCancelReason("");
        setCancelPreview(null);
      }
    } catch (err) {
      setActionError(extractApiError(err, "Không thể hủy đơn. Vui lòng thử lại."));
    } finally {
      setActionLoading(false);
    }
  };

  /*
   * Không còn "mở lại đơn đã hủy".
   *
   * Hủy là trạng thái kết thúc. Chỗ đã trả về kho có thể đã bán cho người khác, thư báo hủy đã
   * gửi, tiền hoàn có thể đã chuyển — kéo đơn trở lại là dựng dậy một thứ mà phần còn lại đã đi
   * tiếp. Hủy nhầm thì đặt lại đơn mới, mất một phút và để lại đúng một dòng lịch sử.
   */

  return (
    <div className="space-y-6">
      {/* HEADER */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
            Xem thông tin đặt hàng
          </h1>
          <p className="text-sm text-gray-500">
            Xem thông tin chi tiết và thanh toán của các đơn đặt tour du lịch từ khách hàng
          </p>
        </div>
      </div>

      {/* KPI METRICS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Doanh thu */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-emerald-50 text-emerald-600 rounded-md group-hover:bg-emerald-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Doanh thu VNPAY (Trang này)</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">
              {stats.revenue.toLocaleString()}đ
            </h3>
          </div>
        </div>

        {/* Tổng số đơn */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-blue-50 text-blue-600 rounded-md group-hover:bg-blue-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">
              {dangLoc ? "Đơn khớp bộ lọc" : "Tổng đơn đặt"}
            </p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.total} đơn</h3>
          </div>
        </div>
        {/* Chờ xác nhận */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-amber-50 text-amber-600 rounded-md group-hover:bg-amber-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Chờ xác nhận</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.pending} đơn</h3>
          </div>
        </div>

        {/* Đã hủy */}
        <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs flex items-center gap-4 hover:shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 group">
          <div className="p-3.5 bg-rose-50 text-rose-600 rounded-md group-hover:bg-rose-100 transition-colors">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Đơn đã hủy</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.cancelled} đơn</h3>
          </div>
        </div>
      </div>

      {/* FILTER & SEARCH */}
      <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-xs space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
          {/* Thanh tìm kiếm */}
          <div className="relative md:col-span-4">
            <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>
            </span>
            <input
              type="text"
              placeholder="Tìm mã đơn (BK-19), tên khách, email, số điện thoại, tên tour..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50"
            />
          </div>

          {/* Lọc trạng thái đặt */}
          <div className="md:col-span-2">
            <select
              value={statusFilter}
              onChange={(e) => doiBoLoc(() => setStatusFilter(e.target.value))}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="all">Tất cả trạng thái duyệt</option>
              <option value="pending">Chờ xác nhận</option>
              <option value="confirmed">Đã xác nhận</option>
              <option value="cancelled">Đã hủy</option>
              {/* Hai trạng thái sau chuyến. Thiếu chúng thì lọc kiểu gì cũng không ra đơn của các
                  chuyến đã đi xong, mà đó lại là phần lớn dữ liệu của một công ty chạy lâu năm. */}
              <option value="completed">Đã hoàn thành</option>
              <option value="no_show">Khách không có mặt</option>
            </select>
          </div>

          {/* Lọc thanh toán */}
          <div className="md:col-span-2.5">
            <select
              value={paymentFilter}
              onChange={(e) => doiBoLoc(() => setPaymentFilter(e.target.value))}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="all">Tất cả thanh toán</option>
              <option value="paid">Đã thanh toán</option>
              <option value="unpaid">Chưa thanh toán</option>
            </select>
          </div>

          {/* Sắp xếp */}
          <div className="md:col-span-2">
            <select
              value={sortBy}
              onChange={(e) => doiBoLoc(() => setSortBy(e.target.value))}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="latest">Mới nhất trước</option>
              <option value="oldest">Cũ nhất trước</option>
              <option value="amount-desc">Tổng giá giảm dần</option>
              <option value="amount-asc">Tổng giá tăng dần</option>
              {/* Câu hỏi thường trực của điều hành: đoàn nào sắp đi. */}
              <option value="departure-asc">Ngày khởi hành gần nhất</option>
              <option value="departure-desc">Ngày khởi hành xa nhất</option>
            </select>
          </div>

          {/* Xóa lọc nhanh */}
          <div className="md:col-span-1.5 flex">
            <button
              onClick={xoaBoLoc}
              className="w-full py-2 text-sm text-gray-500 hover:text-primary-600 bg-gray-50 border border-gray-100 rounded-md font-medium hover:bg-primary-50 transition-colors cursor-pointer"
            >
              Xóa bộ lọc
            </button>
          </div>
        </div>

        {/* Nói rõ đang xem tập nào: người lọc xong hay quên mất là mình đang lọc. */}
        {dangLoc && (
          <p className="text-xs text-gray-500">
            Đang lọc — <span className="font-semibold text-gray-700">{totalBookingsCount} đơn</span> khớp
            {tuKhoaTim && (
              <>
                {" "}
                với từ khóa <span className="font-semibold text-gray-700">"{tuKhoaTim}"</span>
              </>
            )}
            . Mọi con số ở trên tính theo đúng bộ lọc này.
          </p>
        )}
      </div>

      {/* DATA TABLE */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-gray-500 font-medium">
            Đang tải dữ liệu đơn đặt hàng...
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-gray-200">
                  <th className="py-3.5 px-6 w-28">Mã đơn</th>
                  <th className="py-3.5 px-6 w-72">Khách hàng</th>
                  <th className="py-3.5 px-6 w-80">Thông tin Tour & Ngày đi</th>
                  <th className="py-3.5 text-right px-6">Khách</th>
                  <th className="py-3.5 text-right px-6">Tổng tiền</th>
                  <th className="py-3.5 text-center px-6">Thanh toán</th>
                  <th className="py-3.5 text-center px-6">Trạng thái duyệt</th>
                  <th className="py-3.5 text-center px-6">Hành động</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-sm">
                {bookings.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="p-12 text-center text-gray-400">
                      {dangLoc
                        ? "Không có đơn nào khớp bộ lọc. Thử xóa bớt điều kiện."
                        : "Chưa có đơn đặt tour nào."}
                    </td>
                  </tr>
                ) : (
                  bookings.map((booking) => {
                    // Đã thu tiền hay chưa đọc `paid_at`, không đọc mã cổng thanh toán: khách
                    // chuyển khoản rồi điều hành ghi nhận tay cũng là đã trả tiền.
                    const isPaid = booking.paid_at !== null;
                    return (
                      <tr key={booking.id} className="hover:bg-gray-50/50 transition-colors">
                        {/* Mã đơn */}
                        <td className="py-3.5 px-6 font-bold text-gray-700">
                          BK-{booking.id}
                        </td>

                        {/* Khách hàng */}
                        <td className="py-3.5 px-6">
                          <div>
                            <p className="font-semibold text-gray-900">{booking.customer_name}</p>
                            <p className="text-xs text-gray-400 mt-0.5">{booking.customer_email}</p>
                            {booking.customer_phone && (
                              <p className="text-xs text-gray-500 font-mono mt-0.5">{booking.customer_phone}</p>
                            )}
                          </div>
                        </td>

                        {/* Thông tin Tour */}
                        <td className="py-3.5 px-6">
                          <div className="max-w-xs">
                            <p className="font-medium text-gray-800 line-clamp-1">
                              {booking.tour?.title ?? "Tour du lịch"}
                            </p>
                            <div className="flex items-center gap-1.5 text-xs text-gray-400 mt-1">
                              <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                  strokeWidth={2}
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                              </svg>
                              <span>Khởi hành:</span>
                              <span className="font-semibold text-gray-600">
                                {formatDateTime(booking.departure_date)}
                              </span>
                            </div>
                          </div>
                        </td>

                        {/* Khách */}
                        <td className="py-3.5 px-6 text-right font-medium text-gray-700">
                          {booking.guests} khách
                        </td>

                        {/* Tổng tiền */}
                        <td className="py-3.5 px-6 text-right font-bold text-gray-900">
                          {Number(booking.total_amount).toLocaleString()}đ
                        </td>

                        {/* Thanh toán */}
                        <td className="py-3.5 px-6 text-center">
                          <span
                            className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-semibold border ${isPaid
                              ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                              : "bg-gray-50 text-gray-500 border-gray-200"
                              }`}
                          >
                            <span className={`w-1.5 h-1.5 rounded-full ${isPaid ? "bg-emerald-500" : "bg-gray-400"}`}></span>
                            {isPaid
                              ? booking.vnpay_transaction_no
                                ? "Đã trả qua VNPAY"
                                : "Đã trả, ghi nhận tay"
                              : "Chưa thanh toán"}
                          </span>
                        </td>

                        {/* Trạng thái duyệt */}
                        <td className="py-3.5 px-6 text-center">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border ${MAU_TRANG_THAI[booking.status] ?? MAU_TRANG_THAI.pending
                              }`}
                          >
                            {NHAN_TRANG_THAI[booking.status] ?? booking.status}
                          </span>
                        </td>

                        {/* Hành động */}
                        <td className="py-3.5 px-6 text-center">
                          <button
                            onClick={() => openDetails(booking)}
                            className="px-3 py-1 text-xs text-primary-600 bg-primary-50 rounded hover:bg-primary-100 font-medium transition-colors cursor-pointer"
                          >
                            Xem chi tiết
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* PAGINATION CONTROLS */}
        {!loading && totalPages > 1 && (
          <div className="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-100 sm:px-6">
            <div className="flex-1 flex justify-between sm:hidden">
              <button
                disabled={currentPage === 1}
                onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
                className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
              >
                Trước
              </button>
              <button
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
                className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
              >
                Sau
              </button>
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-xs text-gray-500">
                  Hiển thị trang <span className="font-semibold text-gray-700">{currentPage}</span> / <span className="font-semibold text-gray-700">{totalPages}</span> trang (Tổng <span className="font-semibold text-gray-700">{totalBookingsCount}</span> đơn)
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                  <button
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage(1)}
                    className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Đầu
                  </button>
                  <button
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
                    className="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Trước
                  </button>
                  <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-primary-50 text-sm font-semibold text-primary-600">
                    {currentPage}
                  </span>
                  <button
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage((p) => Math.min(p + 1, totalPages))}
                    className="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Sau
                  </button>
                  <button
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage(totalPages)}
                    className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Cuối
                  </button>
                </nav>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* DETAIL MODAL POPUP (XEM + XỬ LÝ ĐƠN) */}
      <Modal
        isOpen={isModalOpen && !!selectedBooking}
        onClose={closeDetails}
        title={`Chi tiết đơn đặt: BK-${selectedBooking?.id}`}
        subtitle={`Khởi tạo lúc: ${selectedBooking?.created_at}`}
        size="3xl"
        footer={
          <div className="flex items-center justify-end gap-2.5">
            {selectedBooking?.status === "pending" && !cancelMode && (
              <button
                onClick={confirmMode ? handleConfirm : openConfirmForm}
                disabled={actionLoading}
                className="px-4 py-2 bg-emerald-600 text-sm font-semibold rounded-md text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 cursor-pointer"
              >
                {actionLoading
                  ? "Đang xử lý..."
                  : confirmMode
                    ? "Ghi nhận & xác nhận"
                    : "Xác nhận đơn"}
              </button>
            )}
            {/* I06 - Chỉ đơn đã thanh toán mới chuyển; đơn chưa trả tiền thì hủy rồi đặt lại
                đơn giản hơn nhiều. */}
            {selectedBooking?.status === "confirmed" && !cancelMode && !transferMode && (
              <button
                onClick={moChuyenChuyen}
                disabled={actionLoading}
                className="px-4 py-2 bg-white border border-blue-200 text-sm font-semibold rounded-md text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50 cursor-pointer"
              >
                Chuyển chuyến
              </button>
            )}
            {(selectedBooking?.status === "pending" || selectedBooking?.status === "confirmed") && !cancelMode && !transferMode && (
              <button
                onClick={openCancelForm}
                disabled={actionLoading}
                className="px-4 py-2 bg-white border border-rose-200 text-sm font-semibold rounded-md text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-50 cursor-pointer"
              >
                Hủy đơn
              </button>
            )}
            {/*
              Q - Hợp đồng du lịch. Chỉ đơn đã thành giao dịch mới có gì để ký; đơn đang giữ chỗ
              thì máy chủ cũng từ chối, ẩn nút ở đây để khỏi bấm xong mới bị chặn.
            */}
            {selectedBooking && !["pending", "cancelled"].includes(String(selectedBooking.status)) && !cancelMode && !transferMode && (
              <>
                <button
                  onClick={moHopDong}
                  disabled={contractBusy}
                  className="px-4 py-2 bg-white border border-primary-200 text-sm font-semibold rounded-md text-primary-700 hover:bg-primary-50 transition-colors disabled:opacity-50 cursor-pointer"
                  title={contract ? `Hợp đồng ${contract.contract_number}` : "Cấp số hợp đồng và mở bản in"}
                >
                  {contractBusy
                    ? "Đang xử lý..."
                    : contract
                      ? `Mở hợp đồng ${contract.contract_number}`
                      : "Cấp hợp đồng"}
                </button>

                {contract && !contract.signed_at && (
                  <button
                    onClick={ghiNhanDaKy}
                    className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer"
                  >
                    Đã ký
                  </button>
                )}
              </>
            )}

            <button
              onClick={closeDetails}
              className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-md text-gray-700 hover:bg-gray-100 transition-colors focus:outline-none cursor-pointer"
            >
              Đóng
            </button>
          </div>
        }
      >
        {selectedBooking && (() => {
        // Nhật ký cổng thanh toán đi kèm chi tiết đơn, không phải một lượt gọi riêng.
        const paymentLogs = selectedBooking.payment_logs ?? [];

        return (
          <div className="space-y-5">
            {/*
              Xác nhận đơn là tuyên bố "khách này đã trả tiền", nên phải nói rõ đã thu bao nhiêu.
              Trước đây nút xác nhận chỉ đổi trạng thái: đơn vào danh sách đoàn và cộng vào doanh
              thu trong khi sổ giao dịch vẫn ghi 0 đồng, và hủy đơn thì khách được hoàn đúng 0.
            */}
            {confirmMode && (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50/60 p-4">
                <p className="text-sm font-semibold text-emerald-900">Ghi nhận khoản đã thu</p>
                <p className="mt-1 text-xs text-emerald-800">
                  Khoản này vào thẳng sổ giao dịch của đơn. Bỏ trống chỉ được khi kế toán đã ghi
                  nhận từ trước.
                </p>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  <label className="block">
                    <span className="text-xs font-medium text-gray-700">Số tiền đã thu</span>
                    <input
                      type="number"
                      min={0}
                      value={confirmForm.amount}
                      onChange={(e) => setConfirmForm((f) => ({ ...f, amount: e.target.value }))}
                      className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                  </label>
                  <label className="block">
                    <span className="text-xs font-medium text-gray-700">Hình thức</span>
                    <select
                      value={confirmForm.method}
                      onChange={(e) =>
                        setConfirmForm((f) => ({ ...f, method: e.target.value as ConfirmMethod }))
                      }
                      className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                      <option value="bank_transfer">Chuyển khoản</option>
                      <option value="cash">Tiền mặt</option>
                      <option value="gateway">Qua cổng thanh toán</option>
                    </select>
                  </label>
                </div>
              </div>
            )}

            {/* Tour info */}
            <div className="bg-primary-50/50 p-5 rounded-lg border border-primary-100/50">
              <p className="text-xs font-semibold text-primary-600 uppercase tracking-wider">Thông tin Tour đặt</p>
              <h4 className="font-bold text-gray-900 mt-1.5 text-base font-plus-jakarta">
                {selectedBooking.tour?.title}
              </h4>
              <div className="grid grid-cols-2 gap-y-3 gap-x-6 mt-4 text-sm font-inter">
                <div>
                  <span className="text-gray-400">Thời gian:</span>{" "}
                  <span className="font-semibold text-gray-800">
                    {selectedBooking.tour?.number_of_days} ngày {selectedBooking.tour?.number_of_nights} đêm
                  </span>
                </div>
                <div>
                  <span className="text-gray-400">Nơi đi:</span>{" "}
                  <span className="font-semibold text-gray-800">
                    {selectedBooking.tour?.start_location}
                  </span>
                </div>
                <div>
                  <span className="text-gray-400">Ngày đi:</span>{" "}
                  <span className="font-bold text-primary-600">
                    {formatDateTime(selectedBooking.departure_date)}
                  </span>
                </div>
                <div>
                  <span className="text-gray-400">Số khách:</span>{" "}
                  <span className="font-bold text-gray-800">
                    {selectedBooking.guests} người
                  </span>
                </div>
              </div>
            </div>

            {/* Customer & Payment info */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
              <div className="bg-gray-50/50 p-5 rounded-lg border border-gray-200">
                <div className="flex items-center justify-between gap-2">
                  <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Thông tin người đặt</h5>
                  {!editingContact && (
                    <button
                      type="button"
                      onClick={() => openContactEditor(selectedBooking)}
                      className="rounded border border-gray-200 bg-white px-2 py-0.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 transition-colors"
                    >
                      Sửa
                    </button>
                  )}
                </div>

                {/*
                  Sửa được cả sau hạn chốt và cả khi đoàn đang đi — khác hẳn danh sách hành khách.
                  Đây là số hướng dẫn viên gọi khách, sát ngày mới càng cần đúng.
                */}
                {editingContact ? (
                  <div className="mt-3.5 space-y-2">
                    <div>
                      <label className="block text-xs font-semibold text-gray-500 mb-1">Họ và tên</label>
                      <input
                        value={contactForm.customer_name}
                        onChange={(e) => setContactForm((truoc) => ({ ...truoc, customer_name: e.target.value }))}
                        className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm outline-none focus:border-primary-400"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-500 mb-1">Email</label>
                      <input
                        type="email"
                        value={contactForm.customer_email}
                        onChange={(e) => setContactForm((truoc) => ({ ...truoc, customer_email: e.target.value }))}
                        className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm outline-none focus:border-primary-400"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-500 mb-1">Số điện thoại</label>
                      <input
                        value={contactForm.customer_phone}
                        onChange={(e) => setContactForm((truoc) => ({ ...truoc, customer_phone: e.target.value }))}
                        className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-mono outline-none focus:border-primary-400"
                      />
                    </div>

                    {contactError && (
                      <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                        {contactError}
                      </p>
                    )}

                    <div className="flex justify-end gap-2 pt-1">
                      <button
                        type="button"
                        onClick={() => setEditingContact(false)}
                        disabled={contactSaving}
                        className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                      >
                        Bỏ qua
                      </button>
                      <button
                        type="button"
                        onClick={saveContact}
                        disabled={contactSaving}
                        className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-40"
                      >
                        {contactSaving ? "Đang lưu..." : "Lưu"}
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="mt-3.5 space-y-2 text-sm font-inter">
                    <p className="flex justify-between border-b border-gray-100 pb-1.5">
                      <span className="text-gray-400">Họ và tên:</span>{" "}
                      <span className="font-semibold text-gray-800">{selectedBooking.customer_name}</span>
                    </p>
                    <p className="flex justify-between border-b border-gray-100 pb-1.5">
                      <span className="text-gray-400">Email:</span>{" "}
                      <span className="font-semibold text-gray-800 font-mono text-xs">{selectedBooking.customer_email}</span>
                    </p>
                    <p className="flex justify-between">
                      <span className="text-gray-400">Số ĐT:</span>{" "}
                      <span className="font-semibold text-gray-800 font-mono">{selectedBooking.customer_phone ?? "Không có"}</span>
                    </p>
                  </div>
                )}
              </div>

              <div className="bg-gray-50/50 p-5 rounded-lg border border-gray-200">
                <div className="flex items-center justify-between gap-2">
                  <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Trạng thái thanh toán</h5>
                  {/*
                    Nút ghi khoản thu đặt ở đây, không ở mục sổ bên dưới: đây là chỗ người ta
                    đang nhìn khi tự hỏi "đơn này trả tiền chưa".
                  */}
                  {ledger && !["cancelled", "transferred"].includes(String(selectedBooking.status)) && (
                    <button
                      type="button"
                      onClick={() => {
                        setPaymentForm({
                          kind: "balance",
                          amount: String(Math.max(0, ledger.balance_due)),
                          method: "bank_transfer",
                          reference: "",
                        });
                        setPaymentError("");
                        setPaymentMode(true);
                      }}
                      className="rounded border border-gray-200 bg-white px-2 py-0.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 transition-colors"
                    >
                      Ghi khoản thu
                    </button>
                  )}
                </div>
                <div className="mt-3.5 space-y-2 text-sm font-inter">
                  <p className="flex justify-between border-b border-gray-100 pb-1.5">
                    <span className="text-gray-450">Giao dịch VNPAY:</span>{" "}
                    <span className="font-semibold text-gray-800 font-mono text-xs">
                      {selectedBooking.vnpay_transaction_no ?? "Chưa thanh toán"}
                    </span>
                  </p>
                  {selectedBooking.paid_at && (
                    <p className="flex justify-between border-b border-gray-100 pb-1.5">
                      <span className="text-gray-450">Thời gian:</span>{" "}
                      <span className="font-semibold text-gray-800 font-mono text-xs">{selectedBooking.paid_at}</span>
                    </p>
                  )}
                  <p className="flex justify-between items-baseline">
                    <span className="text-gray-450">Tổng tiền:</span>{" "}
                    <span className="font-bold text-primary-600 text-lg">
                      {Number(selectedBooking.total_amount).toLocaleString()}đ
                    </span>
                  </p>
                  {/*
                    Chỉ nói thêm khi số thu KHÁC tổng đơn. Trả đủ một lần thì hai dòng dưới đây
                    chỉ nhắc lại "Tổng tiền" ở trên bằng chữ khác.
                  */}
                  {ledger && ledger.balance_due > 0 && ledger.net_paid > 0 && (
                    <p className="flex justify-between items-baseline border-t border-gray-100 pt-1.5">
                      <span className="text-gray-450">Còn thiếu:</span>{" "}
                      <span className="font-bold text-amber-700">{formatPrice(ledger.balance_due)}</span>
                    </p>
                  )}
                </div>
              </div>
            </div>

            {/* Ghi một khoản tiền nhận ngoài cổng thanh toán. Mở từ nút ở khối bên trên. */}
            {paymentMode && (
              <div className="space-y-3 rounded-lg border border-primary-200 bg-primary-50/40 p-4">
                <p className="text-xs text-gray-600">
                  Khách chuyển khoản hoặc nộp tiền mặt tại quầy. Khoản trả qua VNPay tự vào sổ,
                  không cần ghi tay.
                </p>

                {paymentError && (
                  <p className="rounded border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700">
                    {paymentError}
                  </p>
                )}

                <div className="grid grid-cols-2 gap-3">
                  <label className="block">
                    <span className="text-[11px] font-semibold text-gray-600">Số tiền</span>
                    <input
                      type="number"
                      min={1}
                      value={paymentForm.amount}
                      onChange={(e) => setPaymentForm((cu) => ({ ...cu, amount: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm focus:border-primary-500 focus:outline-none"
                    />
                  </label>
                  <label className="block">
                    <span className="text-[11px] font-semibold text-gray-600">Hình thức</span>
                    <select
                      value={paymentForm.method}
                      onChange={(e) => setPaymentForm((cu) => ({ ...cu, method: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm focus:border-primary-500 focus:outline-none"
                    >
                      <option value="bank_transfer">Chuyển khoản</option>
                      <option value="cash">Tiền mặt</option>
                    </select>
                  </label>
                </div>

                <label className="block">
                  <span className="text-[11px] font-semibold text-gray-600">
                    Mã giao dịch / chứng từ{" "}
                    <span className="font-normal text-gray-400">(không bắt buộc)</span>
                  </span>
                  <input
                    value={paymentForm.reference}
                    onChange={(e) => setPaymentForm((cu) => ({ ...cu, reference: e.target.value }))}
                    placeholder={paymentForm.method === "cash" ? "Số phiếu thu" : "Mã giao dịch ngân hàng"}
                    className="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm focus:border-primary-500 focus:outline-none"
                  />
                  {/*
                    Nói rõ lấy ở đâu. Trước đây ô này chỉ có placeholder "FT26083012345" — trông
                    như một định dạng bắt buộc, trong khi máy chủ khai `nullable` và không ai biết
                    con số ấy đến từ đâu.
                  */}
                  <span className="mt-1 block text-[11px] text-gray-500">
                    {paymentForm.method === "cash"
                      ? "Số phiếu thu công ty tự ghi. Không có thì để trống."
                      : "Mã tham chiếu trong app ngân hàng hoặc trên sao kê. Không có thì để trống."}
                  </span>
                </label>

                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setPaymentMode(false)}
                    className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                  >
                    Hủy
                  </button>
                  <button
                    type="button"
                    onClick={ghiKhoanThu}
                    disabled={paymentSaving || Number(paymentForm.amount) <= 0}
                    className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-700 disabled:opacity-50"
                  >
                    {paymentSaving ? "Đang ghi..." : "Ghi vào sổ"}
                  </button>
                </div>
              </div>
            )}

            {/* Danh sách hành khách */}
            {selectedBooking.passengers && selectedBooking.passengers.length > 0 && (
              <div className="bg-gray-50/50 p-4 rounded-lg border border-gray-200">
                <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                  Danh sách hành khách ({selectedBooking.passengers.length})
                </h5>
                <div className="space-y-2">
                  {selectedBooking.passengers.map((passenger, idx) => (
                    <div key={passenger.id} className="flex items-center justify-between text-xs py-1.5 border-b border-gray-200/60 last:border-none">
                      <span className="flex items-center gap-2">
                        <span className="w-5 h-5 rounded-full bg-primary-100 text-primary-700 font-bold flex items-center justify-center text-[10px]">
                          {idx + 1}
                        </span>
                        <strong className="text-gray-800">{passenger.name}</strong>
                        <span className="text-gray-400">
                          ({passenger.type === "adult" ? "Người lớn" : passenger.type === "child" ? "Trẻ em" : "Em bé"})
                        </span>
                      </span>
                      <span className="text-gray-500 font-mono">
                        {passenger.identity_number ?? "—"}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Passenger note */}
            <div className="bg-gray-50/50 p-4 rounded-lg border border-gray-200">
              <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Ghi chú từ khách hàng</h5>
              <p className="text-sm text-gray-600 italic leading-relaxed">
                {selectedBooking.note || "Không có ghi chú thêm."}
              </p>
            </div>

            {/* Lý do hủy (nếu đơn đã hủy) */}
            {selectedBooking.status === "cancelled" && selectedBooking.cancel_reason && (
              <div className="bg-rose-50/60 p-4 rounded-lg border border-rose-200">
                <h5 className="text-xs font-semibold text-rose-500 uppercase tracking-wider mb-2">Lý do hủy đơn</h5>
                <p className="text-sm text-rose-800 leading-relaxed">{selectedBooking.cancel_reason}</p>
                {selectedBooking.cancelled_at && (
                  <p className="text-[11px] text-rose-600 mt-2 font-mono">
                    Thời gian hủy: {formatDateTime(selectedBooking.cancelled_at)}
                  </p>
                )}
              </div>
            )}

            {/*
              Không còn khối "lý do khôi phục đơn": không đơn nào khôi phục được nữa. Đơn cũ từng
              được mở lại thì dấu vết vẫn còn trong lịch sử thay đổi ngay bên dưới.
            */}

            {/*
              Sổ giao dịch — chỉ hiện khi có gì để hiện.

              Không dựng ba ô tổng ở đây: khối "Trạng thái thanh toán" phía trên đã nói tổng tiền
              và đã trả hay chưa. Lặp lại bằng chữ khác là ba con số cho một thông tin.

              Sổ chỉ thêm dòng, không sửa dòng cũ: ghi nhầm thì ghi một dòng điều chỉnh ngược lại.
            */}
            {ledger && ledger.entries.length > 0 && (
              <div className="pt-4 border-t border-gray-200 space-y-2">
                <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                  Sổ giao dịch ({ledger.entries.length})
                </span>
                <ul className="space-y-2">
                  {ledger.entries.map((entry) => (
                    <li
                      key={entry.id}
                      className="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-gray-200 bg-white p-3"
                    >
                      <div>
                        <span className="text-sm font-semibold text-gray-900">{entry.kind_label}</span>
                        <span className="ml-2 text-[11px] text-gray-500">
                          {entry.paid_at}
                          {entry.method && ` · ${METHOD_LABEL[entry.method] ?? entry.method}`}
                          {entry.reference && ` · ${entry.reference}`}
                          {entry.recorded_by && ` · ${entry.recorded_by}`}
                        </span>
                        {entry.note && (
                          <span className="mt-0.5 block text-[11px] text-gray-500">{entry.note}</span>
                        )}
                      </div>
                      <span
                        className={`font-mono text-sm font-bold ${
                          entry.kind === "refund" ? "text-rose-600" : "text-emerald-700"
                        }`}
                      >
                        {entry.kind === "refund" ? "−" : "+"}
                        {formatPrice(entry.amount)}
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Nghĩa vụ hoàn tiền — chỉ có nghĩa sau khi đơn đã hủy. */}
            {ledger && ledger.refund_due > 0 && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                Phải hoàn <b>{formatPrice(ledger.refund_due)}</b>, đã trả{" "}
                <b>{formatPrice(ledger.refunded)}</b>, còn nợ khách{" "}
                <b>{formatPrice(ledger.refund_outstanding)}</b>.
                {ledger.refund_bank && (
                  <span className="mt-1 block font-mono text-[11px]">
                    {ledger.refund_bank.account_number} · {ledger.refund_bank.bank_name} ·{" "}
                    {ledger.refund_bank.account_holder}
                  </span>
                )}
                <span className="mt-1 block">Ghi khoản đã chuyển ở màn Hoàn tiền.</span>
              </div>
            )}

            {/*
              Nhật ký cổng thanh toán.

              Dữ liệu này được nạp cùng chi tiết đơn từ trước nhưng chưa màn hình nào hiện ra. Nó
              ghi MỌI lượt VNPay trả về, kể cả lượt thất bại, kèm kết quả kiểm chữ ký — thứ trả lời
              câu "làm sao biết khoản thanh toán này là thật".
            */}
            {paymentLogs.length > 0 && (
              <div className="pt-4 border-t border-gray-200 space-y-3">
                <button
                  type="button"
                  onClick={() => setShowPaymentLogs((prev) => !prev)}
                  className="flex w-full items-center justify-between text-left"
                >
                  <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Nhật ký cổng thanh toán ({paymentLogs.length})
                  </span>
                  <span className="text-xs font-bold text-primary-600">
                    {showPaymentLogs ? "Thu gọn" : "Xem"}
                  </span>
                </button>

                {showPaymentLogs && (
                  <ul className="space-y-2">
                    {paymentLogs.map((log) => {
                      const thanhCong = log.response_code === "00" && log.transaction_status === "00";

                      return (
                        <li
                          key={log.id}
                          className={`rounded-lg border p-3 text-xs ${
                            thanhCong ? "border-emerald-200 bg-emerald-50/60" : "border-gray-200 bg-gray-50/60"
                          }`}
                        >
                          <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <span className="font-mono font-semibold text-gray-900">
                              {log.transaction_no ?? "Không có mã giao dịch"}
                            </span>
                            <span
                              className={`rounded-full border px-2 py-0.5 text-[10px] font-bold ${
                                thanhCong
                                  ? "border-emerald-300 bg-emerald-100 text-emerald-800"
                                  : "border-gray-300 bg-white text-gray-600"
                              }`}
                            >
                              {thanhCong ? "Thành công" : "Không thành công"}
                            </span>
                          </div>
                          <p className="mt-1 text-gray-600">
                            {log.amount != null && `${formatPrice(Number(log.amount))} · `}
                            {log.bank_code && `${log.bank_code} · `}
                            mã trả về {log.response_code ?? "—"}/{log.transaction_status ?? "—"}
                            {log.created_at && ` · ${formatDateTime(log.created_at)}`}
                          </p>
                          {/*
                            Chữ ký không hợp lệ nghĩa là dữ liệu trả về không do VNPay ký. Đây là
                            dòng đáng chú ý nhất trong cả nhật ký, nên nó phải nổi lên.
                          */}
                          {!log.is_valid_signature && (
                            <p className="mt-1.5 rounded border border-rose-200 bg-rose-50 px-2 py-1 font-semibold text-rose-700">
                              Chữ ký KHÔNG hợp lệ — lượt gọi này không được hệ thống công nhận.
                            </p>
                          )}
                        </li>
                      );
                    })}
                  </ul>
                )}
              </div>
            )}


            {/*
              E04 - Dòng thời gian thay đổi của đơn.
              Trước khi có nhật ký, dấu vết nằm rải rác ở cancelled_by, reviewed_by,
              seats_released_by, mỗi chỗ một kiểu và không ghép lại được theo thứ tự.
            */}
            {history.length > 0 && (
              <div className="pt-4 border-t border-gray-200 space-y-3">
                <button
                  type="button"
                  onClick={() => setShowHistory((prev) => !prev)}
                  className="flex w-full items-center justify-between text-left"
                >
                  <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Lịch sử thay đổi ({history.length})
                  </span>
                  <span className="text-xs font-bold text-primary-600">
                    {showHistory ? "Thu gọn" : "Xem"}
                  </span>
                </button>

                {showHistory && (
                  <ol className="space-y-3">
                    {history.map((entry) => (
                      <li
                        key={entry.id}
                        className={`rounded-lg border p-3 ${
                          entry.touches_money
                            ? "border-amber-200 bg-amber-50/60"
                            : "border-gray-200 bg-gray-50/60"
                        }`}
                      >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                          <span className="text-sm font-bold text-gray-900">
                            {entry.action_label}
                          </span>
                          <span className="font-mono text-[11px] text-gray-500">
                            {formatDateTime(entry.created_at)}
                          </span>
                        </div>

                        <p className="mt-0.5 text-xs text-gray-600">
                          {entry.actor_name
                            ? `${entry.actor_name}${entry.actor_role ? ` (${entry.actor_role})` : ""}`
                            : "Tác vụ nền tự động"}
                          {entry.ip_address ? ` · ${entry.ip_address}` : ""}
                        </p>

                        {typeof entry.old_values?.status === "string"
                          && typeof entry.new_values?.status === "string" && (
                          <p className="mt-1 font-mono text-[11px] text-gray-500">
                            {String(entry.old_values.status)} → {String(entry.new_values.status)}
                          </p>
                        )}

                        {typeof entry.new_values?.refund_amount !== "undefined" && (
                          <p className="mt-1 text-xs font-bold text-amber-800">
                            Hoàn khách {formatPrice(Number(entry.new_values.refund_amount))}
                          </p>
                        )}

                        {entry.new_values?.seats_released === false && (
                          <p className="mt-1 text-xs font-semibold text-rose-700">
                            Chỗ không quay lại kho, thành ghế chết.
                          </p>
                        )}

                        {entry.reason && (
                          <p className="mt-1 text-xs italic text-gray-700">“{entry.reason}”</p>
                        )}
                      </li>
                    ))}
                  </ol>
                )}
              </div>
            )}

            {/* Trạng thái duyệt của Admin */}
            <div className="pt-4 border-t border-gray-200 flex justify-between items-center">
              <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Trạng thái duyệt</span>
              <span
                className={`inline-flex items-center px-3.5 py-1 rounded text-xs font-bold border ${selectedBooking.status === "confirmed"
                  ? "bg-blue-50 text-blue-700 border-blue-200"
                  : selectedBooking.status === "cancelled"
                    ? "bg-rose-50 text-rose-700 border-rose-200"
                    : "bg-amber-50 text-amber-700 border-amber-200"
                  }`}
              >
                {selectedBooking.status === "confirmed" && "Đã xác nhận"}
                {selectedBooking.status === "cancelled" && "Đã hủy đơn"}
                {selectedBooking.status === "pending" && "Chờ xác nhận"}
              </span>
            </div>

            {actionError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {actionError}
              </div>
            )}

          </div>
        );
        })()}
      </Modal>

      {/*
        Hủy đơn — ba bước.

        Trước đây cả cụm này nằm ngay trong hộp chi tiết đơn, dưới hai chục dòng thông tin khác.
        Người bấm phải cuộn qua bảng dự báo để tới được cái nút, mà bảng dự báo mới đúng là thứ
        họ cần đọc kỹ nhất: hoàn bao nhiêu, và chỗ có quay lại kho không.
      */}
      {cancelMode && selectedBooking && (
        <StepperModal
          isOpen
          onClose={() => { setCancelMode(false); setCancelReason(""); setCancelPreview(null); }}
          title={`Hủy đơn BK-${selectedBooking.id}`}
          subtitle={`${selectedBooking.customer_name} · ${selectedBooking.guests} khách · khởi hành ${formatDateTime(selectedBooking.departure_date)}`}
          sacThai="nguy-hiem"
          hienTai={buocHuy}
          onDoiBuoc={setBuocHuy}
          nhanHoanTat="Xác nhận hủy đơn"
          onHoanTat={handleCancel}
          dangChay={actionLoading}
          buoc={[
            {
              ten: "Hậu quả",
              moTa: "Đọc hai con số này trước: khách nhận lại bao nhiêu, và chỗ có bán lại được không.",
              chuaXong: previewLoading
                ? "Đang tính mức hoàn..."
                : !cancelPreview
                  ? "Chưa lấy được dự báo."
                  : !cancelPreview.can_cancel
                    ? "Đơn này không hủy được."
                    : null,
              noiDung: (
                <>
                  {previewLoading && (
                    <p className="text-xs font-medium text-gray-500">
                      Đang tính mức hoàn và tình trạng chỗ...
                    </p>
                  )}

                  {cancelPreview && !cancelPreview.can_cancel && (
                    <div className="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2.5">
                      <p className="text-sm font-bold text-rose-700">Không hủy được đơn này</p>
                      <p className="text-xs text-rose-700 mt-0.5">{cancelPreview.blocked_reason}</p>
                    </div>
                  )}

                  {cancelPreview && cancelPreview.can_cancel && (
                    <div className="space-y-2">
                      <div className="flex items-center justify-between text-xs">
                        <span className="text-gray-500">
                          Còn {Math.max(0, Math.round(cancelPreview.hours_before ?? 0))} giờ tới khởi hành
                          {cancelPreview.policy_name ? ` · ${cancelPreview.policy_name}` : ""}
                        </span>
                        <span className="font-bold text-gray-900">
                          Mức hoàn {cancelPreview.refund_percent}%
                        </span>
                      </div>

                      {/* Vì sao mức hoàn là 100%. Không nói ra thì người bấm tưởng bảng phí hỏng. */}
                      {cancelPreview.moved_by_company && (
                        <p className="rounded-md bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">
                          Đơn này đang ở chuyến do công ty dời tới. Khách từ chối một thay đổi họ
                          không chọn nên không chịu phí hủy — hoàn đủ số đã thu.
                        </p>
                      )}

                      <div className="grid grid-cols-3 gap-2 text-center">
                        <div className="rounded-md bg-gray-50 py-2">
                          <p className="text-[11px] text-gray-500">Đã thu</p>
                          <p className="text-sm font-bold text-gray-900">{formatPrice(cancelPreview.paid_amount)}</p>
                        </div>
                        <div className="rounded-md bg-amber-50 py-2">
                          <p className="text-[11px] text-amber-700">Phí hủy</p>
                          <p className="text-sm font-bold text-amber-800">{formatPrice(cancelPreview.cancellation_fee)}</p>
                        </div>
                        <div className="rounded-md bg-emerald-50 py-2">
                          <p className="text-[11px] text-emerald-700">Hoàn khách</p>
                          <p className="text-sm font-bold text-emerald-800">{formatPrice(cancelPreview.refund_amount)}</p>
                        </div>
                      </div>

                      {/* Điểm dễ hiểu sai nhất: hủy sau hạn chốt thì chỗ ở lại với đơn, vì suất
                          đã cam kết với nhà cung cấp và không hủy được nữa. */}
                      {cancelPreview.seats_will_be_released ? (
                        <p className="text-xs text-gray-600">
                          Chỗ sẽ được trả về kho và lịch khởi hành bán tiếp được ngay.
                        </p>
                      ) : (
                        <p className="rounded-md bg-rose-100 px-3 py-2 text-xs font-semibold text-rose-800">
                          Đơn này đã qua hạn chốt danh sách. Hủy xong <strong>chỗ không quay lại kho</strong>,
                          nó thành ghế chết và chỉ mở bán lại được bằng tay ở mục Chỗ đã hủy chưa mở bán lại.
                        </p>
                      )}
                    </div>
                  )}
                </>
              ),
            },
            {
              ten: "Lý do",
              moTa: "Câu này vào nhật ký của đơn, và là thứ người sau đọc để hiểu vì sao đơn bị hủy.",
              chuaXong: cancelReason.trim() ? null : "Nhập lý do hủy để đi tiếp.",
              noiDung: (
                <>
                  <textarea
                    rows={3}
                    value={cancelReason}
                    onChange={(e) => setCancelReason(e.target.value)}
                    placeholder="VD: Khách yêu cầu hoàn do thay đổi lịch trình, tour bị hoãn..."
                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-rose-400"
                  />
                  <p className="text-xs text-gray-500">
                    Lượt mã giảm giá luôn được hoàn lại.
                    {selectedBooking.vnpay_transaction_no && " Đơn này ĐÃ thanh toán qua VNPay — cần chuyển tiền hoàn cho khách thủ công."}
                  </p>
                </>
              ),
            },
            {
              ten: "Xác nhận",
              moTa: "Bấm xong không lùi lại được.",
              chuaXong: null,
              noiDung: (
                <div className="space-y-2 text-sm">
                  <p className="text-gray-700">
                    Hủy đơn <b>BK-{selectedBooking.id}</b> của {selectedBooking.customer_name}.
                  </p>
                  {cancelPreview && (
                    <p className="text-gray-700">
                      Khách nhận lại <b>{formatPrice(cancelPreview.refund_amount)}</b>
                      {cancelPreview.cancellation_fee > 0 && ` (đã trừ phí hủy ${formatPrice(cancelPreview.cancellation_fee)})`}.
                      {" "}
                      {cancelPreview.seats_will_be_released
                        ? "Chỗ quay lại kho."
                        : "Chỗ KHÔNG quay lại kho — thành ghế chết."}
                    </p>
                  )}
                  <p className="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    <span className="font-semibold">Lý do đã ghi:</span> {cancelReason.trim()}
                  </p>
                </div>
              ),
            },
          ]}
        />
      )}

      {/*
        I06 - Chuyển chuyến, ba bước.

        Thứ tự các bước là thứ tự việc thật: gọi cho khách, thống nhất phương án, rồi mới đụng vào
        đơn. Không có căn cứ thì máy chủ từ chối ngay từ đầu, nên hỏi nó sau cùng chỉ khiến người
        ta điền xong hết mới biết mình thiếu.
      */}
      {transferMode && selectedBooking && (
        <StepperModal
          isOpen
          onClose={closeTransferForm}
          title={`Chuyển chuyến cho đơn BK-${selectedBooking.id}`}
          subtitle={`${selectedBooking.customer_name} · ${selectedBooking.guests} khách · đang ở chuyến ${formatDateTime(selectedBooking.departure_date)}`}
          size="2xl"
          hienTai={buocChuyen}
          onDoiBuoc={setBuocChuyen}
          nhanHoanTat="Xác nhận chuyển"
          onHoanTat={handleTransfer}
          dangChay={actionLoading}
          buoc={[
            {
              ten: "Trao đổi với khách",
              moTa: "Chuyển chuyến là đổi ngày đi của khách, nên phải hỏi họ trước.",
              chuaXong: canCuId
                ? null
                : "Cần một cuộc liên hệ có kết quả “khách đồng ý” và chưa dùng cho lần chuyển nào.",
              noiDung: (
                <>
                  {/* Ai khởi xướng quyết định hai luật: hạn báo trước 7 ngày và phí đổi lịch.
                      Khách gọi lên xin đổi thì vẫn là khách, dù người bấm nút là điều hành. */}
                  <div className="flex flex-wrap items-center gap-4 text-xs">
                    <span className="font-semibold text-gray-700">Ai yêu cầu:</span>
                    <label className="flex items-center gap-1.5 text-gray-700">
                      <input
                        type="radio"
                        name="transfer-initiator"
                        checked={initiatedBy === "customer"}
                        onChange={() => openTransferForm(sameTourOnly, "customer")}
                      />
                      Khách xin đổi
                    </label>
                    <label className="flex items-center gap-1.5 text-gray-700">
                      <input
                        type="radio"
                        name="transfer-initiator"
                        checked={initiatedBy === "company"}
                        onChange={() => openTransferForm(sameTourOnly, "company")}
                      />
                      Công ty chuyển
                    </label>
                  </div>

                  <p className="text-[11px] text-gray-500">
                    {initiatedBy === "customer"
                      ? "Khách xin đổi: từ lần thứ hai có phí đổi lịch. Đổi được tới hạn chốt danh sách, trừ khi công ty đặt hạn báo trước riêng."
                      : "Công ty chuyển: miễn phí đổi lịch. Vẫn không chuyển được sau hạn chốt danh sách."}
                  </p>

                  <div className="flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
                    <span className="text-xs font-bold text-gray-800">Căn cứ đã ghi nhận</span>
                    {!ghiLienHe && (
                      <button
                        type="button"
                        onClick={() => setGhiLienHe(true)}
                        className="rounded border border-gray-200 px-2.5 py-1 text-[11px] font-semibold text-primary-600 hover:bg-primary-50"
                      >
                        Ghi nhận cuộc liên hệ
                      </button>
                    )}
                  </div>

                  {contactLogs.length === 0 && !ghiLienHe && (
                    <p className="text-[11px] text-gray-500">
                      Chưa có cuộc liên hệ nào được ghi nhận cho đơn này.
                    </p>
                  )}

                  {contactLogs.length > 0 && (
                    <div className="space-y-1.5">
                      {contactLogs.map((log) => (
                        <label
                          key={log.id}
                          className={`flex gap-2 rounded border p-2 text-[11px] ${
                            log.dung_lam_can_cu_duoc
                              ? "cursor-pointer border-gray-200 hover:border-primary-400"
                              : "border-gray-100 bg-gray-50 text-gray-400"
                          } ${canCuId === log.id ? "border-primary-500 bg-primary-50/50" : ""}`}
                        >
                          <input
                            type="radio"
                            name="can-cu-chuyen-chuyen"
                            className="mt-0.5"
                            disabled={!log.dung_lam_can_cu_duoc}
                            checked={canCuId === log.id}
                            onChange={() => setCanCuId(log.id)}
                          />
                          <span className="min-w-0">
                            <span className="font-semibold">
                              {log.channel_label} · {log.outcome_label}
                            </span>
                            <span className="text-gray-400">
                              {" "}· {formatDateTime(log.contacted_at)}
                              {log.contacted_by ? ` · ${log.contacted_by}` : ""}
                            </span>
                            <span className="block text-gray-600">{log.note}</span>
                            {log.da_dung_lam_can_cu && (
                              <span className="block italic text-gray-400">
                                Đã dùng làm căn cứ cho một lần chuyển trước.
                              </span>
                            )}
                          </span>
                        </label>
                      ))}
                    </div>
                  )}

                  {ghiLienHe && (
                    <div className="space-y-2 rounded border border-gray-200 bg-gray-50 p-2.5">
                      <div className="flex gap-2">
                        <select
                          value={kenhLienHe}
                          onChange={(e) => setKenhLienHe(e.target.value)}
                          className="flex-1 rounded border border-gray-200 px-2 py-1.5 text-xs"
                        >
                          {KENH_LIEN_HE.map((k) => (
                            <option key={k.value} value={k.value}>{k.label}</option>
                          ))}
                        </select>
                        <select
                          value={ketQuaLienHe}
                          onChange={(e) => setKetQuaLienHe(e.target.value)}
                          className="flex-1 rounded border border-gray-200 px-2 py-1.5 text-xs"
                        >
                          {KET_QUA_LIEN_HE.map((k) => (
                            <option key={k.value} value={k.value}>{k.label}</option>
                          ))}
                        </select>
                      </div>

                      <textarea
                        rows={2}
                        value={noiDungLienHe}
                        onChange={(e) => setNoiDungLienHe(e.target.value)}
                        placeholder="Khách nói gì? VD: Đã gọi, khách đồng ý dời sang chuyến ngày 20/09."
                        className="w-full rounded border border-gray-200 px-2 py-1.5 text-xs"
                      />

                      <p className="text-[10px] text-gray-500">
                        Ghi rồi thì không sửa và không xóa được. Ghi cả những lần khách từ chối hoặc
                        không bắt máy — đó mới là thứ cần đến khi có tranh cãi.
                      </p>

                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => { setGhiLienHe(false); setNoiDungLienHe(""); }}
                          className="rounded px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-100"
                        >
                          Bỏ qua
                        </button>
                        <button
                          type="button"
                          onClick={ghiNhanLienHe}
                          disabled={actionLoading || noiDungLienHe.trim().length < 10}
                          className="rounded bg-primary-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                        >
                          Lưu cuộc liên hệ
                        </button>
                      </div>
                    </div>
                  )}
                </>
              ),
            },
            {
              ten: "Chuyến đích",
              moTa: "Máy chủ đã loại sẵn chuyến không chuyển được và tính sẵn chênh lệch cho từng lựa chọn.",
              chuaXong: transferTargetId ? null : "Chọn một chuyến để đi tiếp.",
              noiDung: (
                <>
                  {/*
                    Nhóm lý do đứng trên danh sách vì nó đổi con số phí: ba nhóm đầu là bất khả
                    kháng nên không thu phí đổi lịch, còn nhóm cuối thì có.
                  */}
                  <div className="space-y-1">
                    <span className="text-xs font-bold text-gray-800">Nhóm lý do</span>
                    <select
                      value={nhomLyDo}
                      onChange={(e) =>
                        openTransferForm(sameTourOnly, initiatedBy, e.target.value as TransferReasonCategory)
                      }
                      className="w-full rounded border border-gray-200 bg-white px-2 py-1.5 text-xs"
                    >
                      {NHOM_LY_DO_CHUYEN.map((n) => (
                        <option key={n.value} value={n.value}>{n.label}</option>
                      ))}
                    </select>
                    <p className="text-[11px] text-gray-500">
                      {nhomLyDo === "customer_request"
                        ? "Việc riêng của khách: áp quy tắc phí đổi lịch như thường."
                        : "Bất khả kháng: không thu phí đổi lịch của khách, dù đây là lần chuyển thứ mấy."}
                    </p>
                  </div>

                  <label className="flex items-center gap-1.5 text-xs font-medium text-gray-600">
                    <input
                      type="checkbox"
                      checked={sameTourOnly}
                      onChange={(e) => openTransferForm(e.target.checked)}
                    />
                    Chỉ trong cùng tour
                  </label>

                  {transferLoading && (
                    <p className="text-xs text-gray-500">Đang tìm chuyến phù hợp...</p>
                  )}

                  {!transferLoading && transferOptions.length === 0 && (
                    <p className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-600">
                      Tour này không còn chuyến nào đang mở bán và khởi hành sau hôm nay.
                      {sameTourOnly && " Bỏ tick “chỉ trong cùng tour” để tìm rộng hơn."}
                    </p>
                  )}

                  {/*
                    Mọi lựa chọn cùng bị chặn vì một lý do thì lý do ấy thuộc về ĐƠN, không thuộc
                    về chuyến nào - quá hạn chốt ở chuyến gốc, hoặc khách xin đổi khi còn dưới bảy
                    ngày. Nói một lần ở trên đầu, thay vì lặp lại y hệt trên từng dòng.
                  */}
                  {!transferLoading && lyDoChanChung && (
                    <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-medium text-amber-900">
                      Không chuyến nào chuyển sang được: {lyDoChanChung}
                    </p>
                  )}

                  <div className="space-y-2">
                    {transferOptions.map((option) => {
                      const chenh = option.price_difference + option.fee;
                      const dangChon = transferTargetId === option.schedule_id;

                      return (
                        <button
                          key={option.schedule_id}
                          type="button"
                          disabled={!option.can_transfer}
                          onClick={() => setTransferTargetId(option.schedule_id)}
                          className={`w-full text-left rounded-lg border p-3 transition-colors ${
                            !option.can_transfer
                              ? "border-gray-100 bg-gray-50 opacity-70 cursor-not-allowed"
                              : dangChon
                                ? "border-blue-500 bg-blue-50/50 ring-2 ring-blue-200"
                                : "border-gray-200 bg-white hover:bg-gray-50"
                          }`}
                        >
                          <div className="flex items-baseline justify-between gap-2">
                            <span className="text-sm font-bold text-gray-900">
                              {formatDateTime(option.start_date)}
                            </span>
                            <span className="text-[11px] text-gray-500">
                              còn {option.remaining_seats} chỗ
                            </span>
                          </div>

                          {!sameTourOnly && option.tour_title && (
                            <p className="text-xs text-gray-600 mt-0.5">{option.tour_title}</p>
                          )}

                          {option.can_transfer ? (
                            <p
                              className={`mt-1 text-xs font-semibold ${
                                chenh > 0
                                  ? "text-amber-800"
                                  : chenh < 0
                                    ? "text-emerald-800"
                                    : "text-gray-500"
                              }`}
                            >
                              {chenh > 0 && `Thu thêm ${formatPrice(chenh)}`}
                              {chenh < 0 && `Chuyến mới rẻ hơn ${formatPrice(Math.abs(chenh))}`}
                              {chenh === 0 && "Không chênh lệch"}
                              {option.fee > 0 && ` (gồm phí đổi lịch ${formatPrice(option.fee)})`}
                            </p>
                          ) : (
                            /* Nói đúng câu máy chủ sẽ trả lời nếu bấm, thay vì để chuyến biến mất
                               khỏi danh sách và người dùng tự đoán vì sao. */
                            <p className="mt-1 text-xs font-medium text-rose-700">
                              {option.blocked_reason}
                            </p>
                          )}
                        </button>
                      );
                    })}
                  </div>
                </>
              ),
            },
            {
              ten: "Lý do và xác nhận",
              moTa: "Nhóm ở bước trước nói loại căn cứ; ô này nói việc cụ thể đã xảy ra.",
              chuaXong:
                transferReason.trim().length < 10
                  ? "Ghi lại chuyện gì đã xảy ra, ít nhất 10 ký tự."
                  : null,
              noiDung: (
                <>
                  <textarea
                    rows={3}
                    value={transferReason}
                    onChange={(e) => setTransferReason(e.target.value)}
                    placeholder="VD: Bão số 9, cấm biển từ 12/09, không chạy tàu ra đảo."
                    className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400"
                  />
                  <p className="text-[11px] text-gray-500">
                    Câu này vào nhật ký của đơn và là thứ người sau đọc lại để hiểu vì sao đơn bị dời.
                  </p>

                  {chuyenDich && (
                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm space-y-1">
                      <p className="text-gray-700">
                        Chuyển sang chuyến <b>{formatDateTime(chuyenDich.start_date)}</b>
                        {!sameTourOnly && chuyenDich.tour_title ? ` · ${chuyenDich.tour_title}` : ""}.
                      </p>
                      <p className="text-gray-700">
                        {chuyenDich.price_difference + chuyenDich.fee > 0
                          ? `Khách trả thêm ${formatPrice(chuyenDich.price_difference + chuyenDich.fee)}.`
                          : chuyenDich.price_difference + chuyenDich.fee < 0
                            ? `Chuyến mới rẻ hơn ${formatPrice(Math.abs(chuyenDich.price_difference + chuyenDich.fee))}.`
                            : "Không có chênh lệch tiền."}
                        {chuyenDich.fee > 0 && ` Trong đó phí đổi lịch ${formatPrice(chuyenDich.fee)}.`}
                      </p>
                    </div>
                  )}
                </>
              ),
            },
          ]}
        />
      )}
    </div>
  );
}


