import { useState, useEffect, useRef } from "react";
import type { Booking, BookingLedger } from "@/types";
import adminService from "@/services/adminService";
import type {
  BookingAuditEntry,
  BookingContractInfo,
  BookingListSummary,
  CancelPreview,
  CancelType,
  ContactLog,
  TransferOption,
  TransferReasonCategory,
} from "@/services/adminService";
import {
  KENH_LIEN_HE,
  KET_QUA_LIEN_HE,
  NHOM_LY_DO_CHUYEN,
} from "@/services/adminService";
import { Alert, Button, Card, Checkbox, Col, Descriptions, Empty, Flex, Form, Input, InputNumber, Modal, Radio, Row, Select, Spin, Statistic, Table, Tabs, Tag, Timeline, Typography } from "antd";
import type { TableColumnsType } from "antd";
import { Link } from "react-router-dom";

/** Hình thức thu tiền khi xác nhận đơn bằng tay. */
type ConfirmMethod = "cash" | "bank_transfer" | "gateway";
import { AntStepperModal } from "@/components/admin/AntStepperModal";
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
  pending: "Chờ thanh toán / xác nhận",
  confirmed: "Đã xác nhận",
  cancelled: "Đã hủy",
  completed: "Đã hoàn thành",
  no_show: "Không có mặt",
};

const MAU_TRANG_THAI: Record<string, string> = {
  pending: "warning",
  confirmed: "processing",
  cancelled: "error",
  completed: "success",
  no_show: "default",
};

export default function BookingManagement() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [listError, setListError] = useState("");
  const [reloadKey, setReloadKey] = useState(0);
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
  const detailRequestId = useRef(0);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState("");

  // Sổ giao dịch của đơn đang mở, và biểu mẫu ghi một khoản thu ngoài cổng thanh toán.
  const [ledger, setLedger] = useState<BookingLedger | null>(null);
  const [detailTab, setDetailTab] = useState("overview");
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
  /** Ai hủy — quyết định có áp bảng phí hay hoàn đủ. Xem `CancelType`. */
  const [loaiHuy, setLoaiHuy] = useState<CancelType>("by_customer");
  const [previewLoading, setPreviewLoading] = useState(false);

  // E04 - Dòng thời gian thay đổi của đơn
  const [history, setHistory] = useState<BookingAuditEntry[]>([]);
  const [signingContract, setSigningContract] = useState(false);
  const [signatureNote, setSignatureNote] = useState("");

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
      setListError("");
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
          if (currentPage > (res.last_page || 1)) {
            setCurrentPage(res.last_page || 1);
            return;
          }
          setBookings(res.data || []);
          setPageSize(res.per_page || 10);
          setTotalBookingsCount(res.total || 0);
          setSummary(res.summary ?? null);
        } else {
          setListError("Không tải được danh sách đơn. Vui lòng thử lại.");
        }
      } catch (err) {
        if (!daHuy) {
          console.error("Lỗi lấy danh sách đơn đặt: ", err);
          setListError("Không tải được danh sách đơn. Vui lòng thử lại.");
        }
      } finally {
        if (!daHuy) setLoading(false);
      }
    };

    tai();

    return () => {
      daHuy = true;
    };
  }, [currentPage, tuKhoaTim, statusFilter, paymentFilter, sortBy, reloadKey]);

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
    const requestId = ++detailRequestId.current;
    setSelectedBooking(booking);
    setIsModalOpen(true);
    setHistory([]);
    setDetailTab("overview");
    setConfirmMode(false);
    setCancelMode(false);
    setTransferMode(false);
    setSigningContract(false);
    setActionError("");
    setDetailError("");
    setDetailLoading(true);
    setEditingContact(false);
    setContract(null);
    setLedger(null);
    setPaymentMode(false);

    const results = await Promise.allSettled([
      adminService.getBookingById(booking.id),
      adminService.getBookingLedger(booking.id),
      adminService.getBookingContract(booking.id),
      adminService.getBookingHistory(booking.id),
    ]);
    if (requestId !== detailRequestId.current) return;
    const [details, money, document, audit] = results;
    if (details.status === "fulfilled" && details.value) setSelectedBooking(details.value);
    if (money.status === "fulfilled") setLedger(money.value);
    if (document.status === "fulfilled") setContract(document.value);
    if (audit.status === "fulfilled") setHistory(audit.value);
    if (results.some((result) => result.status === "rejected") ||
      (details.status === "fulfilled" && !details.value) ||
      (money.status === "fulfilled" && !money.value)) {
      setDetailError("Chưa tải đủ thông tin đơn. Hãy tải lại trước khi xử lý.");
    }
    setDetailLoading(false);
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
    ++detailRequestId.current;
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
    setContractBusy(true);
    try {
      await adminService.markContractSigned(contract.id, signatureNote.trim() || undefined);
      setContract(await adminService.getBookingContract(contract.booking_id));
      setSigningContract(false);
      setSignatureNote("");
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setActionError(response?.message || "Không ghi nhận được.");
    } finally {
      setContractBusy(false);
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

  /**
   * Tải lại dự báo theo đúng loại hủy đang chọn.
   *
   * Khách đổi ý thì áp bảng phí; công ty đơn phương hủy thì hoàn đủ số đã thu. Hai con số khác
   * nhau, nên đổi lựa chọn phải hỏi lại máy chủ — không thì số hiện ra và số thực chi lệch nhau.
   */
  const taiDuBaoHuy = async (loai: CancelType) => {
    if (!selectedBooking) return;

    setPreviewLoading(true);

    try {
      setCancelPreview(await adminService.getCancelPreview(selectedBooking.id, loai));
    } catch (err) {
      setActionError(extractApiError(err, "Không lấy được dự báo hủy đơn."));
    } finally {
      setPreviewLoading(false);
    }
  };

  const doiLoaiHuy = async (loai: CancelType) => {
    setLoaiHuy(loai);
    await taiDuBaoHuy(loai);
  };

  const openCancelForm = async () => {
    if (!selectedBooking) return;

    setBuocHuy(0);
    setCancelMode(true);
    setActionError("");
    setCancelPreview(null);
    // Mặc định là khách đổi ý — trường hợp thường gặp, và là lựa chọn không đụng tới tiền của
    // công ty. Nhánh hoàn đủ phải được chọn tường minh.
    setLoaiHuy("by_customer");

    await taiDuBaoHuy("by_customer");
  };

  // Cập nhật đơn trong cả modal lẫn danh sách sau khi admin thao tác
  const applyBookingUpdate = (updated: Booking) => {
    setSelectedBooking(updated);
    setBookings((prev) => prev.map((b) => (b.id === updated.id ? { ...b, ...updated } : b)));
    setReloadKey((key) => key + 1);
    const requestId = detailRequestId.current;
    adminService.getBookingLedger(updated.id).then((money) => {
      if (requestId === detailRequestId.current) setLedger(money);
    }).catch(() => {
      if (requestId === detailRequestId.current) setDetailError("Không tải lại được sổ tiền sau thao tác. Vui lòng tải lại.");
    });
    adminService.getBookingHistory(updated.id).then((entries) => {
      if (requestId === detailRequestId.current) setHistory(entries);
    }).catch(() => {
      if (requestId === detailRequestId.current) setDetailError("Không tải lại được lịch sử sau thao tác. Vui lòng tải lại.");
    });
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
      const updated = await adminService.cancelBooking(
        selectedBooking.id,
        cancelReason.trim(),
        loaiHuy,
      );
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

  const { Text, Title, Paragraph } = Typography;
  const busy = actionLoading || paymentSaving || contactSaving || contractBusy;
  const canCollect = !!selectedBooking && !["cancelled", "transferred"].includes(selectedBooking.status);
  const openPaymentForm = () => {
    if (!ledger) return;
    setPaymentForm({ kind: "balance", amount: String(ledger.balance_due), method: "bank_transfer", reference: "" });
    setPaymentError("");
    setPaymentMode(true);
  };
  const columns: TableColumnsType<Booking> = [
    {
      title: "Đơn / khách hàng", key: "customer", width: 250,
      render: (_, booking) => <Flex vertical gap={4}>
        <Button type="link" onClick={() => openDetails(booking)} style={{ padding: 0, justifyContent: "flex-start" }}>{"BK-" + booking.id}</Button>
        <Text strong>{booking.customer_name}</Text>
        <Text type="secondary">{booking.customer_email}</Text>
        {booking.customer_phone && <Text>{booking.customer_phone}</Text>}
      </Flex>,
    },
    {
      title: "Tour / khởi hành", key: "tour", width: 270,
      render: (_, booking) => <Flex vertical gap={4}>
        <Text strong>{booking.tour?.title ?? "Tour du lịch"}</Text>
        <Text>{formatDateTime(booking.departure_date)}</Text>
        <Text type="secondary">{booking.guests} khách</Text>
      </Flex>,
    },
    {
      title: "Giá trị đơn", key: "total", width: 145, align: "right",
      render: (_, booking) => <Text strong>{formatPrice(Number(booking.total_amount))}</Text>,
    },
    {
      title: "Thu / hoàn tiền", key: "money", width: 235,
      render: (_, booking) => <Flex vertical gap={4}>
        <Text>Đã thu (trừ hoàn): {booking.net_paid == null ? "Chưa có số liệu" : formatPrice(Number(booking.net_paid))}</Text>
        {booking.status !== "cancelled" && booking.balance_due != null && (
          <Text type={Number(booking.balance_due) > 0 ? "warning" : "success"}>
            {Number(booking.balance_due) > 0 ? "Còn thiếu: " + formatPrice(Number(booking.balance_due)) : "Đã thu đủ"}
          </Text>
        )}
        {Number(booking.refund_amount ?? 0) > 0 && <Text type="warning">Nghĩa vụ hoàn: {formatPrice(Number(booking.refund_amount))}</Text>}
        {booking.balance_overdue && booking.status !== "cancelled" && <Tag color="error">Quá hạn trả nốt</Tag>}
      </Flex>,
    },
    {
      title: "Trạng thái đơn", key: "status", width: 185,
      render: (_, booking) => <Tag color={MAU_TRANG_THAI[booking.status]}>{NHAN_TRANG_THAI[booking.status] ?? booking.status}</Tag>,
    },
    {
      title: "Thao tác", key: "actions", width: 145, fixed: "right",
      render: (_, booking) => <Button onClick={() => openDetails(booking)}>Xem và xử lý</Button>,
    },
  ];

  const moneyFields = (mode: "confirm" | "payment") => {
    const value = mode === "confirm" ? confirmForm : paymentForm;
    return <Row gutter={16}>
      <Col xs={24} sm={12}>
        <Form.Item label="Số tiền đã nhận (đ)" required>
          <InputNumber min={1} precision={0} value={value.amount ? Number(value.amount) : null}
            style={{ width: "100%" }} controls={false}
            formatter={(amount) => String(amount ?? "").replace(/\B(?=(\d{3})+(?!\d))/g, ".")}
            parser={(amount) => Number((amount ?? "").replace(/\./g, ""))}
            onChange={(amount) => mode === "confirm"
              ? setConfirmForm((old) => ({ ...old, amount: amount == null ? "" : String(amount) }))
              : setPaymentForm((old) => ({ ...old, amount: amount == null ? "" : String(amount) }))} />
        </Form.Item>
      </Col>
      <Col xs={24} sm={12}>
        <Form.Item label="Hình thức thu" required>
          <Select<ConfirmMethod> value={value.method as ConfirmMethod}
            options={Object.entries(METHOD_LABEL).filter(([key]) => mode === "confirm" || key !== "gateway").map(([value, label]) => ({ value, label }))}
            onChange={(method: ConfirmMethod) => mode === "confirm"
              ? setConfirmForm((old) => ({ ...old, method }))
              : setPaymentForm((old) => ({ ...old, method }))} />
        </Form.Item>
      </Col>
    </Row>;
  };

  return (
    <Flex vertical gap="large">
      <Flex justify="space-between" align="start" wrap gap="middle">
        <div>
          <Title level={3}>Đơn đặt tour</Title>
          <Text type="secondary">Theo dõi đơn, số tiền còn thiếu và các công việc cần xử lý.</Text>
        </div>
        <Flex gap="small" wrap>
          <Link to="/admin/change-requests"><Button>Yêu cầu hủy đang chờ</Button></Link>
          <Link to="/admin/refunds"><Button>Quản lý hoàn tiền</Button></Link>
        </Flex>
      </Flex>

      <Row gutter={[16, 16]}>
        {[
          { title: "Tổng đơn", value: stats.total, suffix: "đơn" },
          { title: "Chờ thanh toán / xác nhận", value: stats.pending, suffix: "đơn" },
          { title: "Đơn đã hủy", value: stats.cancelled, suffix: "đơn" },
          { title: "Tiền thu ròng của nhóm đơn tính doanh thu", value: stats.revenue, suffix: "đ" },
        ].map((stat) => <Col xs={24} sm={12} xl={6} key={stat.title}>
          <Card size="small"><Statistic title={stat.title} value={stat.value} suffix={stat.suffix} groupSeparator="." loading={loading} /></Card>
        </Col>)}
      </Row>

      <Card>
        <Form layout="vertical">
          <Row gutter={16}>
            <Col xs={24} lg={8}>
              <Form.Item label="Tìm đơn">
                <Input.Search allowClear value={search} onChange={(event) => setSearch(event.target.value)}
                  placeholder="Mã đơn, tên khách, email, điện thoại hoặc tour" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} lg={5}>
              <Form.Item label="Trạng thái đơn">
                <Select value={statusFilter} onChange={(value) => doiBoLoc(() => setStatusFilter(value))}
                  options={[{ value: "all", label: "Tất cả trạng thái" }, ...Object.entries(NHAN_TRANG_THAI).map(([value, label]) => ({ value, label }))]} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} lg={5}>
              <Form.Item label="Thanh toán">
                <Select value={paymentFilter} onChange={(value) => doiBoLoc(() => setPaymentFilter(value))}
                  options={[{ value: "all", label: "Tất cả thanh toán" }, { value: "paid", label: "Đã ghi nhận thu đủ" }, { value: "unpaid", label: "Chưa ghi nhận thu đủ" }]} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12} lg={6}>
              <Form.Item label="Sắp xếp">
                <Select value={sortBy} onChange={(value) => doiBoLoc(() => setSortBy(value))}
                  options={[
                    { value: "latest", label: "Đơn mới nhất" }, { value: "oldest", label: "Đơn cũ nhất" },
                    { value: "amount-desc", label: "Giá trị cao đến thấp" }, { value: "amount-asc", label: "Giá trị thấp đến cao" },
                    { value: "departure-asc", label: "Khởi hành gần nhất" }, { value: "departure-desc", label: "Khởi hành xa nhất" },
                  ]} />
              </Form.Item>
            </Col>
          </Row>
        </Form>
        <Flex justify="space-between" align="center" wrap gap="small">
          <Text type="secondary">{dangLoc ? "Đang áp dụng bộ lọc. " : ""}Số liệu tổng quan tính trên toàn bộ kết quả phù hợp.</Text>
          <Button onClick={xoaBoLoc}>Xóa bộ lọc</Button>
        </Flex>
      </Card>

      {listError && <Alert type="error" showIcon title={listError} action={<Button onClick={() => setReloadKey((key) => key + 1)}>Thử lại</Button>} />}
      <Table<Booking> rowKey="id" columns={columns} dataSource={bookings} loading={loading}
        scroll={{ x: 1230 }} locale={{ emptyText: <Empty description="Không có đơn phù hợp với bộ lọc" /> }}
        pagination={{ current: currentPage, pageSize, total: totalBookingsCount, showSizeChanger: false,
          onChange: setCurrentPage, showTotal: (total) => "Tổng " + total + " đơn" }} />

      <Modal open={isModalOpen && !!selectedBooking} title={"Đơn BK-" + selectedBooking?.id} width={1050}
        onCancel={() => { if (!busy) closeDetails(); }} keyboard={!busy} closable={!busy}
        mask={{ closable: false }} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}
        footer={<Button onClick={closeDetails} disabled={busy}>Đóng</Button>}>
        {selectedBooking && <Flex vertical gap="middle">
          {detailLoading && <Alert type="info" showIcon title="Đang tải thông tin đơn…" />}
          {detailError && <Alert type="error" showIcon title={detailError} action={<Button onClick={() => openDetails(selectedBooking)}>Tải lại</Button>} />}
          <Flex align="center" justify="space-between" wrap gap="small">
            <Tag color={MAU_TRANG_THAI[selectedBooking.status]}>{NHAN_TRANG_THAI[selectedBooking.status]}</Tag>
            <Text type="secondary">Tạo lúc {formatDateTime(selectedBooking.created_at)}</Text>
          </Flex>
          {actionError && !cancelMode && !transferMode && !confirmMode && <Alert type="error" showIcon title={actionError} />}
          <Tabs activeKey={detailTab} onChange={setDetailTab} items={[
            {
              key: "overview", label: "Thông tin đơn",
              children: <Flex vertical gap="middle">
                <Descriptions title={selectedBooking.tour?.title ?? "Thông tin chuyến"} bordered column={{ xs: 1, sm: 2 }}>
                  <Descriptions.Item label="Khởi hành">{formatDateTime(selectedBooking.departure_date)}</Descriptions.Item>
                  <Descriptions.Item label="Số khách">{selectedBooking.guests}</Descriptions.Item>
                  <Descriptions.Item label="Thời gian">{selectedBooking.tour?.number_of_days} ngày {selectedBooking.tour?.number_of_nights} đêm</Descriptions.Item>
                  <Descriptions.Item label="Nơi đi">{selectedBooking.tour?.start_location ?? "—"}</Descriptions.Item>
                </Descriptions>
                <Descriptions title="Người đặt" bordered column={1}
                  extra={<Button disabled={detailLoading || !!detailError} onClick={() => openContactEditor(selectedBooking)}>Sửa liên hệ</Button>}>
                  <Descriptions.Item label="Họ tên">{selectedBooking.customer_name}</Descriptions.Item>
                  <Descriptions.Item label="Email">{selectedBooking.customer_email}</Descriptions.Item>
                  <Descriptions.Item label="Điện thoại">{selectedBooking.customer_phone ?? "Chưa cung cấp"}</Descriptions.Item>
                  <Descriptions.Item label="Ghi chú">{selectedBooking.note || "Không có"}</Descriptions.Item>
                </Descriptions>
                {selectedBooking.status === "cancelled" && <Alert type="warning" showIcon title="Đơn đã hủy"
                  description={<>{selectedBooking.cancel_reason}{selectedBooking.cancelled_at && <Paragraph>Hủy lúc {formatDateTime(selectedBooking.cancelled_at)}</Paragraph>}</>} />}
                <Flex gap="small" wrap>
                  {selectedBooking.status === "pending" && <Button disabled={detailLoading || !!detailError} type="primary" onClick={openConfirmForm}>Ghi nhận thanh toán và xác nhận đơn</Button>}
                  {selectedBooking.status === "confirmed" && <Button disabled={detailLoading || !!detailError} onClick={moChuyenChuyen}>Chuyển chuyến</Button>}
                  {["pending", "confirmed"].includes(selectedBooking.status) && <Button disabled={detailLoading || !!detailError} danger onClick={openCancelForm}>Hủy đơn</Button>}
                </Flex>
                {!["pending", "cancelled"].includes(selectedBooking.status) && <Card size="small" title="Hợp đồng">
                  <Flex vertical gap="small">
                    <Text>{contract ? contract.contract_number : "Chưa cấp hợp đồng"}</Text>
                    {contract?.signed_at && <Tag color="success">Đã ghi nhận ký hợp đồng</Tag>}
                    <Flex gap="small" wrap>
                      <Button disabled={detailLoading || !!detailError} onClick={moHopDong} loading={contractBusy}>{contract ? "Mở bản in hợp đồng" : "Cấp hợp đồng"}</Button>
                      {contract && !contract.signed_at && <Button onClick={() => { setSignatureNote(""); setSigningContract(true); }}>Ghi nhận đã ký</Button>}
                    </Flex>
                  </Flex>
                </Card>}
              </Flex>,
            },
            {
              key: "money", label: "Thanh toán và hoàn tiền",
              children: ledger ? <Flex vertical gap="middle">
                <Row gutter={[16, 16]}>
                  <Col xs={24} sm={8}><Statistic title="Giá trị đơn" value={ledger.total_amount} suffix="đ" groupSeparator="." /></Col>
                  <Col xs={24} sm={8}><Statistic title="Đã thu (trừ hoàn)" value={ledger.net_paid} suffix="đ" groupSeparator="." /></Col>
                  <Col xs={24} sm={8}><Statistic title={selectedBooking.status === "cancelled" ? "Còn phải hoàn khách" : "Còn thiếu"} value={selectedBooking.status === "cancelled" ? ledger.refund_outstanding : ledger.balance_due} suffix="đ" groupSeparator="." /></Col>
                </Row>
                {selectedBooking.balance_due_at && selectedBooking.status !== "cancelled" && <Alert type={selectedBooking.balance_overdue ? "warning" : "info"} showIcon
                  title={"Hạn trả nốt: " + formatDateTime(selectedBooking.balance_due_at)} />}
                {canCollect && ledger.balance_due > 0 && <Flex>
                  <Button disabled={detailLoading || !!detailError} type="primary" onClick={selectedBooking.status === "pending" ? openConfirmForm : openPaymentForm}>Ghi nhận thanh toán</Button>
                </Flex>}
                {ledger.refund_due > 0 && <Alert type="warning" showIcon title={"Còn phải hoàn: " + formatPrice(ledger.refund_outstanding)}
                  description={<Flex vertical gap="small">
                    <Text>Nghĩa vụ hoàn: {formatPrice(ledger.refund_due)} · Đã hoàn: {formatPrice(ledger.refunded)}</Text>
                    {ledger.refund_bank && <Text>{ledger.refund_bank.bank_name} · {ledger.refund_bank.account_number} · {ledger.refund_bank.account_holder}</Text>}
                    <Link to="/admin/refunds">Mở quản lý hoàn tiền</Link>
                  </Flex>} />}
                <Table rowKey="id" size="small" dataSource={ledger.entries} pagination={false} scroll={{ x: 650 }}
                  columns={[
                    { title: "Giao dịch", dataIndex: "kind_label" },
                    { title: "Số tiền", key: "amount", align: "right", render: (_, entry) => <Text type={entry.kind === "refund" ? "danger" : "success"}>{(entry.kind === "refund" ? "−" : "+") + formatPrice(entry.amount)}</Text> },
                    { title: "Hình thức", key: "method", render: (_, entry) => entry.method ? METHOD_LABEL[entry.method] ?? entry.method : "—" },
                    { title: "Thời gian", key: "time", render: (_, entry) => formatDateTime(entry.paid_at) },
                    { title: "Chứng từ / người ghi", key: "reference", render: (_, entry) => <Flex vertical><Text>{entry.reference ?? "—"}</Text><Text type="secondary">{entry.recorded_by ?? "Hệ thống"}</Text>{entry.note && <Text>{entry.note}</Text>}</Flex> },
                  ]} />
                <Typography.Title level={5}>Nhật ký cổng thanh toán</Typography.Title>
                <Table rowKey="id" size="small" dataSource={selectedBooking.payment_logs ?? []} pagination={{ pageSize: 5, showSizeChanger: false }} scroll={{ x: 600 }}
                  columns={[
                    { title: "Mã giao dịch", dataIndex: "transaction_no", render: (value) => value ?? "—" },
                    { title: "Số tiền", dataIndex: "amount", render: (value) => value == null ? "—" : formatPrice(Number(value)) },
                    { title: "Ngân hàng", dataIndex: "bank_code" },
                    { title: "Kết quả", key: "result", render: (_, log) => !log.is_valid_signature ? <Tag color="error">Chữ ký không hợp lệ</Tag> : <Tag color={log.response_code === "00" && log.transaction_status === "00" ? "success" : "default"}>{log.response_code === "00" && log.transaction_status === "00" ? "Thành công" : "Không thành công"}{" (" + (log.response_code ?? "—") + "/" + (log.transaction_status ?? "—") + ")"}</Tag> },
                    { title: "Thời gian", dataIndex: "created_at", render: (value) => formatDateTime(value) },
                  ]} />
              </Flex> : detailLoading ? <Spin tip="Đang tải sổ giao dịch"><div style={{ minHeight: 100 }} /></Spin> : <Empty description="Chưa tải được sổ giao dịch. Bấm Tải lại ở thông báo phía trên." />,
            },
            {
              key: "passengers", label: "Hành khách",
              children: <Table rowKey="id" dataSource={selectedBooking.passengers ?? []} pagination={false} scroll={{ x: 550 }}
                locale={{ emptyText: "Chưa có danh sách hành khách" }}
                columns={[
                  { title: "Họ tên", dataIndex: "name" },
                  { title: "Loại khách", dataIndex: "type", render: (value) => ({ adult: "Người lớn", child: "Trẻ em", infant: "Em bé" })[value as "adult" | "child" | "infant"] ?? value },
                  { title: "Giấy tờ", dataIndex: "identity_number", render: (value) => value ?? "Chưa cung cấp" },
                  { title: "Yêu cầu riêng", dataIndex: "special_request", render: (value) => value ?? "—" },
                ]} />,
            },
            {
              key: "history", label: "Lịch sử xử lý",
              children: history.length === 0 ? <Empty description="Chưa có lịch sử xử lý" /> : <Timeline items={history.map((entry) => ({
                color: entry.touches_money ? "orange" : "blue",
                content: <Flex vertical gap={4}>
                  <Text strong>{entry.action_label}</Text>
                  <Text type="secondary">{formatDateTime(entry.created_at)} · {entry.actor_name ?? "Tác vụ tự động"}{entry.actor_role ? " (" + entry.actor_role + ")" : ""}</Text>
                  {typeof entry.old_values?.status === "string" && typeof entry.new_values?.status === "string" && <Text>{NHAN_TRANG_THAI[entry.old_values.status] ?? entry.old_values.status} → {NHAN_TRANG_THAI[entry.new_values.status] ?? entry.new_values.status}</Text>}
                  {entry.new_values?.refund_amount !== undefined && <Text>Nghĩa vụ hoàn: {formatPrice(Number(entry.new_values.refund_amount))}</Text>}
                  {entry.new_values?.seats_released === false && <Text type="warning">Chỗ chưa được mở bán lại.</Text>}
                  {entry.reason && <Text>{entry.reason}</Text>}
                  {entry.ip_address && <Text type="secondary">IP: {entry.ip_address}</Text>}
                </Flex>,
              }))} />,
            },
          ]} />
        </Flex>}
      </Modal>

      <Modal open={editingContact && isModalOpen} title="Sửa thông tin liên hệ" onCancel={() => setEditingContact(false)}
        closable={!contactSaving} keyboard={!contactSaving} mask={{ closable: false }}
        footer={<Flex justify="end" gap="small"><Button disabled={contactSaving} onClick={() => setEditingContact(false)}>Bỏ qua</Button><Button type="primary" htmlType="submit" form="booking-contact-form" loading={contactSaving}>Lưu liên hệ</Button></Flex>}>
        <Form id="booking-contact-form" layout="vertical" onFinish={saveContact} disabled={contactSaving}>
          {contactError && <Alert type="error" showIcon title={contactError} />}
          <Form.Item label="Họ tên" required><Input required value={contactForm.customer_name} onChange={(event) => setContactForm((old) => ({ ...old, customer_name: event.target.value }))} /></Form.Item>
          <Form.Item label="Email" required><Input required type="email" value={contactForm.customer_email} onChange={(event) => setContactForm((old) => ({ ...old, customer_email: event.target.value }))} /></Form.Item>
          <Form.Item label="Điện thoại"><Input value={contactForm.customer_phone} onChange={(event) => setContactForm((old) => ({ ...old, customer_phone: event.target.value }))} /></Form.Item>
        </Form>
      </Modal>

      <Modal open={confirmMode && isModalOpen} title="Ghi nhận thanh toán và xác nhận đơn" onCancel={() => setConfirmMode(false)}
        closable={!actionLoading} keyboard={!actionLoading} mask={{ closable: false }} confirmLoading={actionLoading}
        okText="Ghi nhận và xác nhận" cancelText="Bỏ qua" cancelButtonProps={{ disabled: actionLoading }}
        okButtonProps={{ disabled: Number(confirmForm.amount) <= 0 && Number(selectedBooking?.net_paid ?? 0) <= 0 }} onOk={handleConfirm}>
        <Flex vertical gap="middle">
          <Alert type="info" showIcon title="Chỉ ghi nhận khoản tiền đã thực nhận."
            description="Có thể để trống số tiền nếu khoản thu đã được ghi trước đó. Thanh toán VNPay được tự động ghi nhận." />
          {actionError && <Alert type="error" showIcon title={actionError} />}
          <Form layout="vertical" disabled={actionLoading}>{moneyFields("confirm")}</Form>
        </Flex>
      </Modal>

      <Modal open={paymentMode && isModalOpen} title="Ghi nhận khoản thanh toán" onCancel={() => setPaymentMode(false)}
        closable={!paymentSaving} keyboard={!paymentSaving} mask={{ closable: false }} confirmLoading={paymentSaving}
        okText="Ghi vào sổ" cancelText="Bỏ qua" cancelButtonProps={{ disabled: paymentSaving }}
        okButtonProps={{ disabled: Number(paymentForm.amount) <= 0 }} onOk={ghiKhoanThu}>
        <Flex vertical gap="middle">
          <Alert type="info" showIcon title="Ghi tiền mặt hoặc chuyển khoản đã nhận. Khoản qua VNPay tự vào sổ." />
          {paymentError && <Alert type="error" showIcon title={paymentError} />}
          <Form layout="vertical" disabled={paymentSaving}>
            {moneyFields("payment")}
            <Form.Item label={paymentForm.method === "cash" ? "Số phiếu thu" : "Mã tham chiếu ngân hàng"} extra="Không có thì để trống.">
              <Input value={paymentForm.reference} onChange={(event) => setPaymentForm((old) => ({ ...old, reference: event.target.value }))} />
            </Form.Item>
          </Form>
        </Flex>
      </Modal>

      <Modal open={signingContract && isModalOpen} title="Ghi nhận hợp đồng đã ký" onCancel={() => setSigningContract(false)}
        closable={!contractBusy} keyboard={!contractBusy} mask={{ closable: false }} confirmLoading={contractBusy}
        okText="Ghi nhận đã ký" cancelText="Bỏ qua" cancelButtonProps={{ disabled: contractBusy }} onOk={ghiNhanDaKy}>
        <Form layout="vertical" disabled={contractBusy}>
          <Form.Item label="Ghi chú việc ký (không bắt buộc)">
            <Input.TextArea rows={3} value={signatureNote} onChange={(event) => setSignatureNote(event.target.value)} />
          </Form.Item>
        </Form>
        {actionError && <Alert type="error" showIcon title={actionError} />}
      </Modal>

      {cancelMode && selectedBooking && <AntStepperModal
        title={"Hủy đơn BK-" + selectedBooking.id} subtitle={selectedBooking.customer_name + " · " + formatDateTime(selectedBooking.departure_date)}
        onClose={() => { setCancelMode(false); setCancelReason(""); setCancelPreview(null); }}
        hienTai={buocHuy} onDoiBuoc={setBuocHuy} nhanHoanTat="Xác nhận hủy đơn" onHoanTat={handleCancel}
        dangChay={actionLoading} error={actionError} danger buoc={[
          {
            ten: "Kiểm tra tiền và chỗ",
            chuaXong: previewLoading ? "Đang tính mức hoàn…" : !cancelPreview ? "Chưa tải được dự báo hủy." : !cancelPreview.can_cancel ? cancelPreview.blocked_reason ?? "Đơn không hủy được." : null,
            noiDung: <>
              <Form layout="vertical">
                <Form.Item label="Ai yêu cầu hủy?">
                  <Radio.Group value={loaiHuy} disabled={previewLoading || actionLoading} onChange={(event) => doiLoaiHuy(event.target.value)}
                    options={[{ value: "by_customer", label: "Khách yêu cầu hủy" }, { value: "by_company", label: "Công ty hủy" }]} />
                </Form.Item>
              </Form>
              <Spin spinning={previewLoading}>
                {cancelPreview ? <Flex vertical gap="middle">
                  {!cancelPreview.can_cancel && <Alert type="error" showIcon title={cancelPreview.blocked_reason} />}
                  <Descriptions bordered column={1}>
                    <Descriptions.Item label="Giá trị đơn">{formatPrice(cancelPreview.total_amount)}</Descriptions.Item>
                    <Descriptions.Item label="Đã thanh toán">{formatPrice(cancelPreview.paid_amount)}</Descriptions.Item>
                    <Descriptions.Item label="Phí hủy">{formatPrice(cancelPreview.cancellation_fee)}</Descriptions.Item>
                    <Descriptions.Item label="Khách được hoàn"><Text strong>{formatPrice(cancelPreview.refund_amount)}</Text></Descriptions.Item>
                    <Descriptions.Item label="Chỗ sau khi hủy">{cancelPreview.seats_will_be_released ? "Được mở bán lại" : "Chưa được mở bán lại"}</Descriptions.Item>
                  </Descriptions>
                  {cancelPreview.fee_waived && <Alert type="info" showIcon title="Miễn phí hủy do thay đổi từ phía công ty." />}
                  {cancelPreview.policy_name && <Text type="secondary">{cancelPreview.policy_name}</Text>}
                </Flex> : <Button onClick={() => taiDuBaoHuy(loaiHuy)} disabled={previewLoading}>Tải lại dự báo</Button>}
              </Spin>
            </>,
          },
          {
            ten: "Ghi lý do", chuaXong: cancelReason.trim().length < 10 ? "Nhập lý do ít nhất 10 ký tự." : null,
            noiDung: <Form layout="vertical"><Form.Item label="Lý do hủy" required extra="Nội dung này được lưu và gửi cho khách.">
              <Input.TextArea rows={4} maxLength={500} showCount value={cancelReason} disabled={actionLoading} onChange={(event) => setCancelReason(event.target.value)} />
            </Form.Item></Form>,
          },
          {
            ten: "Xác nhận",
            noiDung: <Alert type="warning" showIcon title="Kiểm tra trước khi hủy"
              description={<Flex vertical gap="small">
                <Text>{"Đơn BK-" + selectedBooking.id + " · " + (loaiHuy === "by_company" ? "Công ty hủy" : "Khách yêu cầu hủy")}</Text>
                <Text>Khách được hoàn: {formatPrice(cancelPreview?.refund_amount ?? 0)}. Khoản hoàn cần được chi và ghi nhận riêng.</Text>
                <Text>Lý do: {cancelReason}</Text>
                <Text>Đơn đã hủy không được mở lại.</Text>
              </Flex>} />,
          },
        ]} />}

      {transferMode && selectedBooking && <AntStepperModal
        title={"Chuyển chuyến cho đơn BK-" + selectedBooking.id} subtitle={selectedBooking.customer_name + " · Chuyến hiện tại " + formatDateTime(selectedBooking.departure_date)}
        onClose={closeTransferForm} hienTai={buocChuyen} onDoiBuoc={setBuocChuyen}
        nhanHoanTat="Xác nhận chuyển chuyến" onHoanTat={handleTransfer} dangChay={actionLoading} error={actionError}
        buoc={[
          {
            ten: "Trao đổi với khách",
            chuaXong: transferLoading ? "Đang tải dữ liệu…" : !canCuId ? "Chọn cuộc liên hệ có kết quả đồng ý và chưa dùng cho lần chuyển khác." : null,
            noiDung: <>
              <Form layout="vertical">
                <Form.Item label="Ai yêu cầu chuyển?">
                  <Radio.Group disabled={actionLoading || transferLoading} value={initiatedBy} onChange={(event) => openTransferForm(sameTourOnly, event.target.value)}
                    options={[{ value: "customer", label: "Khách xin đổi" }, { value: "company", label: "Công ty chuyển" }]} />
                </Form.Item>
              </Form>
              <Text type="secondary">{initiatedBy === "customer" ? "Phí chuyển được tính theo chính sách và lịch sử đổi của đơn." : "Công ty chuyển: miễn phí đổi lịch, vẫn phải đáp ứng điều kiện thời gian và chỗ."}</Text>
              <Table rowKey="id" size="small" loading={transferLoading} dataSource={contactLogs} pagination={{ pageSize: 5 }} scroll={{ x: 500 }}
                rowSelection={{ type: "radio", selectedRowKeys: canCuId ? [canCuId] : [], onChange: (keys) => setCanCuId(Number(keys[0])),
                  getCheckboxProps: (log) => ({ disabled: !log.dung_lam_can_cu_duoc || actionLoading || transferLoading }) }}
                columns={[
                  { title: "Cuộc liên hệ", key: "contact", render: (_, log) => <Flex vertical><Text strong>{log.channel_label + " · " + log.outcome_label}</Text><Text type="secondary">{formatDateTime(log.contacted_at)}{log.contacted_by ? " · " + log.contacted_by : ""}</Text></Flex> },
                  { title: "Nội dung", key: "note", render: (_, log) => <Flex vertical><Text>{log.note}</Text>{log.da_dung_lam_can_cu && <Tag>Đã dùng cho lần chuyển trước</Tag>}</Flex> },
                ]} />
              {!ghiLienHe ? <Button onClick={() => setGhiLienHe(true)}>Ghi nhận cuộc liên hệ</Button> : <Card size="small" title="Cuộc liên hệ mới">
                <Form layout="vertical" disabled={actionLoading}>
                  <Row gutter={16}>
                    <Col xs={24} sm={12}><Form.Item label="Kênh liên hệ"><Select value={kenhLienHe} onChange={setKenhLienHe} options={[...KENH_LIEN_HE]} /></Form.Item></Col>
                    <Col xs={24} sm={12}><Form.Item label="Kết quả"><Select value={ketQuaLienHe} onChange={setKetQuaLienHe} options={[...KET_QUA_LIEN_HE]} /></Form.Item></Col>
                  </Row>
                  <Form.Item label="Nội dung trao đổi" required extra="Ghi tối thiểu 10 ký tự. Bản ghi đã lưu không sửa hoặc xóa.">
                    <Input.TextArea rows={3} value={noiDungLienHe} onChange={(event) => setNoiDungLienHe(event.target.value)} />
                  </Form.Item>
                  <Flex justify="end" gap="small">
                    <Button onClick={() => { setGhiLienHe(false); setNoiDungLienHe(""); }}>Bỏ qua</Button>
                    <Button type="primary" onClick={ghiNhanLienHe} loading={actionLoading} disabled={noiDungLienHe.trim().length < 10}>Lưu cuộc liên hệ</Button>
                  </Flex>
                </Form>
              </Card>}
            </>,
          },
          {
            ten: "Chọn chuyến",
            chuaXong: transferLoading ? "Đang tải chuyến…" : !chuyenDich?.can_transfer ? "Chọn một chuyến đủ điều kiện." : null,
            noiDung: <>
              <Form layout="vertical">
                <Form.Item label="Nhóm lý do"><Select disabled={actionLoading || transferLoading} value={nhomLyDo} options={[...NHOM_LY_DO_CHUYEN]}
                  onChange={(value) => openTransferForm(sameTourOnly, initiatedBy, value)} /></Form.Item>
                <Form.Item><Checkbox disabled={actionLoading || transferLoading} checked={sameTourOnly} onChange={(event) => openTransferForm(event.target.checked)}>Chỉ tìm trong cùng tour</Checkbox></Form.Item>
              </Form>
              {lyDoChanChung && <Alert type="warning" showIcon title={lyDoChanChung} />}
              <Table rowKey="schedule_id" size="small" loading={transferLoading} dataSource={transferOptions} pagination={{ pageSize: 5 }} scroll={{ x: 600 }}
                rowSelection={{ type: "radio", selectedRowKeys: transferTargetId ? [transferTargetId] : [], onChange: (keys) => setTransferTargetId(Number(keys[0])),
                  getCheckboxProps: (option) => ({ disabled: !option.can_transfer || transferLoading || actionLoading }) }}
                columns={[
                  { title: "Chuyến nhận", key: "trip", render: (_, option) => <Flex vertical><Text strong>{"#" + option.schedule_id + " · " + formatDateTime(option.start_date)}</Text><Text>{option.tour_title}</Text><Text type="secondary">Còn {option.remaining_seats} chỗ</Text></Flex> },
                  { title: "Chênh lệch / điều kiện", key: "difference", render: (_, option) => option.can_transfer ? <Flex vertical>
                    <Text>{option.price_difference + option.fee > 0 ? "Giá đơn tăng " + formatPrice(option.price_difference + option.fee) : option.price_difference + option.fee < 0 ? "Giá đơn giảm " + formatPrice(Math.abs(option.price_difference + option.fee)) : "Giá đơn không đổi"}</Text>
                    <Text type="secondary">Phí đổi: {formatPrice(option.fee)}</Text>
                  </Flex> : <Text type="danger">{option.blocked_reason}</Text> },
                ]} />
            </>,
          },
          {
            ten: "Kiểm tra và xác nhận", chuaXong: transferReason.trim().length < 10 ? "Nhập lý do ít nhất 10 ký tự." : null,
            noiDung: <>
              {chuyenDich && <Descriptions bordered column={1}>
                <Descriptions.Item label="Chuyến mới">{"#" + chuyenDich.schedule_id + " · " + chuyenDich.tour_title}</Descriptions.Item>
                <Descriptions.Item label="Khởi hành">{formatDateTime(chuyenDich.start_date)}</Descriptions.Item>
                <Descriptions.Item label="Giá trị đơn sau chuyển">{formatPrice(chuyenDich.new_total)}</Descriptions.Item>
                <Descriptions.Item label="Phí đổi lịch">{formatPrice(chuyenDich.fee)}</Descriptions.Item>
                <Descriptions.Item label="Còn thiếu sau chuyển">{formatPrice(chuyenDich.balance_due)}</Descriptions.Item>
                <Descriptions.Item label="Hạn trả nốt">{chuyenDich.balance_due_at ? formatDateTime(chuyenDich.balance_due_at) : "—"}</Descriptions.Item>
              </Descriptions>}
              {chuyenDich?.balance_overdue_after && <Alert type="warning" showIcon title="Đơn sẽ quá hạn trả nốt ngay sau khi chuyển"
                description={chuyenDich.auto_collect_too_late ? "Không còn đủ thời gian cho quy trình nhắc tự động. Điều hành cần liên hệ khách để xử lý khoản còn thiếu." : "Hãy thống nhất với khách về khoản còn thiếu và hạn thanh toán mới."} />}
              <Form layout="vertical"><Form.Item label="Lý do chuyển cụ thể" required extra="Nội dung được lưu trong lịch sử đơn.">
                <Input.TextArea disabled={actionLoading} rows={3} maxLength={500} showCount value={transferReason} onChange={(event) => setTransferReason(event.target.value)} />
              </Form.Item></Form>
            </>,
          },
        ]} />}
    </Flex>
  );
}


