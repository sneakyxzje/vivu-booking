import React, { useState, useEffect } from "react";
import bookingService from "@/services/bookingService";
import type { CancelRequestPreview } from "@/services/bookingService";
import type { Booking } from "@/types";
import { formatDateTime } from "@/utils/format";
import { Modal } from "@/components/Modal";
import {
  Ticket,
  Search,
  Calendar,
  Users,
  MapPin,
  Clock,
  Phone,
  CheckCircle2,
  XCircle,
  Car,
  UserCheck,
  Star,
  CreditCard,
  Building2,
  Navigation
} from "lucide-react";

export type ExtendedBooking = Booking;

export const MyBookingsTab: React.FC = () => {
  const [bookings, setBookings] = useState<ExtendedBooking[]>([]);
  const [loadingBookings, setLoadingBookings] = useState(true);
  const [bookingFilter, setBookingFilter] = useState<string>("all");
  const [searchQuery, setSearchQuery] = useState("");

  // Modals state
  const [selectedBooking, setSelectedBooking] = useState<ExtendedBooking | null>(null);
  const [showReviewModal, setShowReviewModal] = useState<ExtendedBooking | null>(null);
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewComment, setReviewComment] = useState("");
  const [reviewSubmitted, setReviewSubmitted] = useState(false);

  // Hủy đơn chưa thanh toán
  const [cancelTarget, setCancelTarget] = useState<ExtendedBooking | null>(null);
  const [cancelReason, setCancelReason] = useState("");
  const [cancelLoading, setCancelLoading] = useState(false);
  const [cancelError, setCancelError] = useState("");

  // Yêu cầu hủy đơn đã thanh toán, phải chờ điều hành duyệt
  const [requestTarget, setRequestTarget] = useState<ExtendedBooking | null>(null);
  const [requestPreview, setRequestPreview] = useState<CancelRequestPreview | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [requestReason, setRequestReason] = useState("");
  const [requestLoading, setRequestLoading] = useState(false);
  const [requestError, setRequestError] = useState("");
  const [requestSent, setRequestSent] = useState(false);

  useEffect(() => {
    const fetchBookings = async () => {
      setLoadingBookings(true);
      try {
        const response = await bookingService.getMyBookings();
        if (response.data && Array.isArray(response.data.data)) {
          setBookings(response.data.data as Booking[]);
        } else {
          setBookings([]);
        }
      } catch (err) {
        console.error("Lỗi tải danh sách tour đã đặt:", err);
      } finally {
        setLoadingBookings(false);
      }
    };

    fetchBookings();
  }, []);

  const filteredBookings = bookings.filter((item) => {
    const matchesFilter =
      bookingFilter === "all" || item.status === bookingFilter;
    const matchesQuery =
      item.tour?.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      item.id.toString().includes(searchQuery);
    return matchesFilter && matchesQuery;
  });

  /**
   * Đơn chưa thanh toán thì khách tự hủy được.
   *
   * Đơn đã thanh toán đi đường khác: khách gửi yêu cầu, điều hành duyệt rồi mới hoàn tiền. Tiền
   * ra khỏi công ty thì phải có người chịu trách nhiệm, không để một cú bấm quyết định. Luồng đó
   * là nhóm F trong docs/nghiep-vu/11-backlog-trien-khai.md, chưa dựng.
   */
  const coTheTuHuy = (booking: ExtendedBooking) => booking.status === "pending";

  /** Đơn đã thu tiền thì không tự hủy, chỉ gửi yêu cầu cho điều hành duyệt. */
  const coTheGuiYeuCau = (booking: ExtendedBooking) => booking.status === "confirmed";

  const layLoiMayChu = (err: unknown, macDinh: string): string => {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
    return message || macDinh;
  };

  const moFormYeuCau = async (booking: ExtendedBooking) => {
    setRequestTarget(booking);
    setRequestReason("");
    setRequestError("");
    setRequestSent(false);
    setRequestPreview(null);
    setPreviewLoading(true);

    try {
      const response = await bookingService.getCancelRequestPreview(booking.id);
      setRequestPreview(response.data?.data ?? null);
    } catch (err) {
      setRequestError(layLoiMayChu(err, "Không lấy được mức hoàn dự kiến."));
    } finally {
      setPreviewLoading(false);
    }
  };

  const dongFormYeuCau = () => {
    setRequestTarget(null);
    setRequestPreview(null);
    setRequestReason("");
    setRequestError("");
    setRequestSent(false);
  };

  const guiYeuCauHuy = async () => {
    if (!requestTarget || requestReason.trim().length < 10) return;

    setRequestLoading(true);
    setRequestError("");

    try {
      await bookingService.requestCancellation(requestTarget.id, requestReason.trim());
      setRequestSent(true);
    } catch (err) {
      setRequestError(layLoiMayChu(err, "Không gửi được yêu cầu. Vui lòng thử lại."));
    } finally {
      setRequestLoading(false);
    }
  };

  const moFormHuy = (booking: ExtendedBooking) => {
    setCancelTarget(booking);
    setCancelReason("");
    setCancelError("");
  };

  const dongFormHuy = () => {
    setCancelTarget(null);
    setCancelReason("");
    setCancelError("");
  };

  const xacNhanHuy = async () => {
    if (!cancelTarget || !cancelReason.trim()) return;

    setCancelLoading(true);
    setCancelError("");

    try {
      await bookingService.cancelMyBooking(cancelTarget.id, cancelReason.trim());

      // Cập nhật tại chỗ thay vì tải lại cả danh sách: đơn vừa hủy phải đổi trạng thái ngay
      // trước mắt khách, còn các đơn khác không có lý do gì phải nhấp nháy theo.
      setBookings((prev) =>
        prev.map((item) =>
          item.id === cancelTarget.id ? { ...item, status: "cancelled" } : item,
        ),
      );

      setSelectedBooking((prev) =>
        prev && prev.id === cancelTarget.id ? { ...prev, status: "cancelled" } : prev,
      );

      dongFormHuy();
    } catch (err) {
      // Thông báo của máy chủ nói rõ vì sao, ví dụ chuyến đã khởi hành nên không hủy được nữa.
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setCancelError(message || "Không hủy được đơn. Vui lòng thử lại hoặc liên hệ tổng đài.");
    } finally {
      setCancelLoading(false);
    }
  };

  const renderStatusBadge = (status: string) => {
    switch (status) {
      case "confirmed":
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" /> Đã xác nhận
          </span>
        );
      case "pending":
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            <Clock className="w-3.5 h-3.5 text-amber-600" /> Chờ thanh toán
          </span>
        );
      case "cancelled":
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
            <XCircle className="w-3.5 h-3.5 text-rose-600" /> Đã hủy
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
            <CheckCircle2 className="w-3.5 h-3.5 text-blue-600" /> Hoàn thành
          </span>
        );
    }
  };

  return (
    <div className="space-y-6 font-inter">
      
      {/* Search & Filter Header Bar */}
      <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2 font-plus-jakarta">
            <Ticket className="w-5 h-5 text-primary-600" /> Quản lý tour đã đặt
          </h2>
          <p className="text-xs text-gray-500 mt-1">Theo dõi lịch trình, phương tiện xe đưa đón và hướng dẫn viên đoàn</p>
        </div>

        {/* Search input */}
        <div className="relative w-full sm:w-72">
          <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            placeholder="Tìm tên tour hoặc mã booking..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all"
          />
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
        {[
          { key: "all", label: "Tất cả tour" },
          { key: "confirmed", label: "Đã xác nhận" },
          { key: "pending", label: "Chờ thanh toán" },
          { key: "cancelled", label: "Đã hủy" },
        ].map((filter) => (
          <button
            key={filter.key}
            onClick={() => setBookingFilter(filter.key)}
            className={`px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${
              bookingFilter === filter.key
                ? "bg-primary-600 text-white shadow-sm"
                : "bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:text-gray-900"
            }`}
          >
            {filter.label}
          </button>
        ))}
      </div>

      {/* Bookings List */}
      {loadingBookings ? (
        <div className="bg-white p-12 rounded-lg border border-gray-100 text-center space-y-3 shadow-sm">
          <div className="w-8 h-8 border-3 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto" />
          <p className="text-xs text-gray-500 font-medium">Đang nạp dữ liệu các đơn đặt tour của bạn...</p>
        </div>
      ) : filteredBookings.length === 0 ? (
        <div className="bg-white p-12 rounded-lg border border-gray-100 text-center space-y-4 shadow-sm">
          <div className="w-16 h-16 bg-primary-50 text-primary-600 rounded-lg flex items-center justify-center mx-auto shadow-inner">
            <Ticket className="w-8 h-8" />
          </div>
          <div>
            <h3 className="text-base font-bold text-gray-900 font-plus-jakarta">Không tìm thấy đơn đặt tour nào</h3>
            <p className="text-xs text-gray-500 mt-1 max-w-md mx-auto">
              Bạn chưa có đơn đặt tour nào phù hợp với bộ lọc hiện tại. Hãy đặt chuyến du lịch tiếp theo ngay hôm nay!
            </p>
          </div>
          <a
            href="/tours"
            className="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-xl transition-colors shadow-sm inline-flex items-center gap-2"
          >
            Khám phá danh sách Tour
          </a>
        </div>
      ) : (
        <div className="space-y-4">
          {filteredBookings.map((item) => (
            <div
              key={item.id}
              className="bg-white rounded-lg p-5 border border-gray-200/80 shadow-sm hover:shadow-md hover:border-primary-200 transition-all flex flex-col md:flex-row gap-5"
            >
              {/* Tour Image */}
              <div className="w-full md:w-52 h-40 rounded-xl overflow-hidden relative shrink-0 bg-gray-100">
                <img
                  src={
                    item.tour?.thumbnail ||
                    "https://images.unsplash.com/photo-1507525428034-b723cf961d3e"
                  }
                  alt={item.tour?.title}
                  className="w-full h-full object-cover"
                />
                <div className="absolute top-2 left-2">
                  <span className="bg-gray-900/80 backdrop-blur-md text-white text-[10px] px-2.5 py-0.5 rounded-full font-semibold">
                    Mã đơn: #{item.id}
                  </span>
                </div>
              </div>

              {/* Booking Information */}
              <div className="flex-1 flex flex-col justify-between">
                <div>
                  <div className="flex items-start justify-between gap-3">
                    <h3 className="font-bold text-gray-900 text-base line-clamp-1 hover:text-primary-600 transition-colors font-plus-jakarta">
                      {item.tour?.title || `Tour #${item.tour_id}`}
                    </h3>
                    {renderStatusBadge(item.status)}
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-3 text-xs text-gray-600">
                    <div className="flex items-center gap-2">
                      <Calendar className="w-4 h-4 text-primary-600 shrink-0" />
                      <span>Khởi hành: <strong className="text-gray-900 font-semibold">{formatDateTime(item.departure_date)}</strong></span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Users className="w-4 h-4 text-primary-600 shrink-0" />
                      <span>Số lượng khách: <strong className="text-gray-900 font-semibold">{item.guests} hành khách</strong></span>
                    </div>
                    <div className="flex items-center gap-2">
                      <MapPin className="w-4 h-4 text-primary-600 shrink-0" />
                      <span>Khởi hành từ: <strong className="text-gray-900 font-semibold">{item.tour?.start_location || "TP.HCM"}</strong></span>
                    </div>
                    <div className="flex items-center gap-2">
                      <CreditCard className="w-4 h-4 text-primary-600 shrink-0" />
                      <span>Tổng số tiền: <strong className="text-primary-600 font-bold text-sm">{Number(item.total_amount).toLocaleString("vi-VN")} đ</strong></span>
                    </div>
                  </div>
                </div>

                {/* Card Action Footer */}
                <div className="flex items-center justify-between pt-4 mt-4 border-t border-gray-100">
                  <span className="text-[11px] text-gray-400">
                    Ngày đăng ký: {new Date(item.created_at).toLocaleDateString("vi-VN")}
                  </span>

                  <div className="flex items-center gap-2">
                    {coTheTuHuy(item) && (
                      <button
                        onClick={() => moFormHuy(item)}
                        className="px-3.5 py-1.5 text-xs font-semibold bg-white text-rose-600 hover:bg-rose-50 rounded-xl transition-colors flex items-center gap-1.5 border border-rose-200"
                      >
                        <XCircle className="w-3.5 h-3.5" />
                        Hủy đơn
                      </button>
                    )}
                    {coTheGuiYeuCau(item) && (
                      <button
                        onClick={() => moFormYeuCau(item)}
                        className="px-3.5 py-1.5 text-xs font-semibold bg-white text-rose-600 hover:bg-rose-50 rounded-xl transition-colors flex items-center gap-1.5 border border-rose-200"
                      >
                        <XCircle className="w-3.5 h-3.5" />
                        Yêu cầu hủy
                      </button>
                    )}
                    {item.status === "confirmed" && (
                      <button
                        onClick={() => setShowReviewModal(item)}
                        className="px-3.5 py-1.5 text-xs font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-xl transition-colors flex items-center gap-1.5 border border-amber-200"
                      >
                        <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                        Đánh giá
                      </button>
                    )}
                    <button
                      onClick={() => setSelectedBooking(item)}
                      className="px-4 py-2 text-xs font-semibold bg-primary-600 hover:bg-primary-700 text-white rounded-xl transition-colors shadow-sm flex items-center gap-1.5"
                    >
                      <Navigation className="w-3.5 h-3.5" />
                      Chi tiết & Theo dõi
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* REUSABLE COMMON MODAL: TRACKING CHI TIẾT TOUR & THÔNG TIN XE */}
      <Modal
        isOpen={!!selectedBooking}
        onClose={() => setSelectedBooking(null)}
        size="3xl"
        title={
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-xs font-bold text-primary-600 bg-primary-50 px-2.5 py-1 rounded-full uppercase tracking-wider border border-primary-200">
              Theo dõi hành trình tour #{selectedBooking?.id}
            </span>
            {selectedBooking && renderStatusBadge(selectedBooking.status)}
          </div>
        }
        subtitle={
          <span className="text-base font-bold text-gray-900 block mt-1 font-plus-jakarta">
            {selectedBooking?.tour?.title}
          </span>
        }
      >
        {selectedBooking && (
          <div className="space-y-6">
            
            {/* STEPPER TRACKING PROGRESS */}
            <div className="bg-slate-50/80 rounded-lg p-5 border border-slate-200/80">
              <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-5 flex items-center gap-2">
                <Navigation className="w-4 h-4 text-primary-600" /> Trạng thái tiến trình chuyến đi
              </h4>

              <div className="relative px-2 sm:px-4">
                {/* Progress line track - Positioned at top-4 (16px) passing directly through center of 32px circle nodes */}
                <div className="absolute left-8 right-8 top-4 -translate-y-1/2 h-1 bg-gray-200 z-0" />
                <div
                  className="absolute left-8 top-4 -translate-y-1/2 h-1 bg-primary-600 z-0 transition-all duration-500"
                  style={{
                    width:
                      selectedBooking.status === "confirmed"
                        ? "calc(100% - 64px)"
                        : "33%",
                  }}
                />

                {/* Nodes Row */}
                <div className="relative z-10 flex items-start justify-between">
                  {/* Step 1 */}
                  <div className="flex flex-col items-center w-24">
                    <div className="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center text-xs font-bold shadow-sm ring-4 ring-slate-50">
                      <CheckCircle2 className="w-4 h-4" />
                    </div>
                    <span className="text-[11px] font-bold text-primary-900 mt-2 text-center">Đặt tour</span>
                  </div>

                  {/* Step 2 */}
                  <div className="flex flex-col items-center w-24">
                    <div
                      className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-sm ring-4 ring-slate-50 ${
                        selectedBooking.status === "confirmed"
                          ? "bg-primary-600 text-white"
                          : "bg-amber-500 text-white"
                      }`}
                    >
                      <CreditCard className="w-4 h-4" />
                    </div>
                    <span className="text-[11px] font-semibold text-gray-800 mt-2 text-center">Thanh toán</span>
                  </div>

                  {/* Step 3 */}
                  <div className="flex flex-col items-center w-24">
                    <div
                      className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-sm ring-4 ring-slate-50 ${
                        selectedBooking.status === "confirmed"
                          ? "bg-primary-600 text-white"
                          : "bg-gray-200 text-gray-500"
                      }`}
                    >
                      <Car className="w-4 h-4" />
                    </div>
                    <span className="text-[11px] font-semibold text-gray-700 mt-2 text-center">Xe & HDV</span>
                  </div>

                  {/* Step 4 */}
                  <div className="flex flex-col items-center w-24">
                    <div className="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs font-bold ring-4 ring-slate-50">
                      <Building2 className="w-4 h-4" />
                    </div>
                    <span className="text-[11px] font-medium text-gray-400 mt-2 text-center">Khởi hành</span>
                  </div>
                </div>
              </div>
            </div>

            {/* VEHICLE INFO CARD (CHỨC NĂNG 4) */}
            <div className="bg-blue-50/50 rounded-lg p-5 border border-blue-100 space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                  <Car className="w-4 h-4 text-primary-600" /> Thông tin xe đi cùng tour
                </h4>
                <span className="text-[10px] font-bold bg-primary-600 text-white px-2.5 py-0.5 rounded-md shadow-xs">Xe du lịch đưa đón</span>
              </div>
              <div className="grid grid-cols-1 gap-3 text-xs text-gray-700 pt-1">
                <div>
                  <span className="text-gray-500 block">Phương tiện di chuyển:</span>
                  <strong className="text-gray-900 font-semibold">
                    {selectedBooking.tour?.vehicle_info || "Thông tin xe sẽ được cập nhật trước ngày khởi hành."}
                  </strong>
                </div>
                <div className="pt-2.5 border-t border-blue-100 flex items-start gap-2 text-xs">
                  <MapPin className="w-4 h-4 text-primary-600 shrink-0 mt-0.5" />
                  <div>
                    <span className="text-gray-500">Điểm đón & thời gian tập trung:</span>
                    <p className="font-semibold text-gray-900">
                      {formatDateTime(selectedBooking.departure_date)} —{" "}
                      {selectedBooking.tour?.pickup_location ||
                        `${selectedBooking.tour?.start_location ?? "Điểm khởi hành"} (chi tiết gửi qua email)`}
                    </p>
                    <p className="text-gray-500 mt-1">Vui lòng có mặt trước giờ khởi hành ít nhất 30 phút.</p>
                  </div>
                </div>
              </div>
            </div>

            {/* GUIDE INFO CARD */}
            <div className="bg-gray-50 rounded-lg p-4 border border-gray-200/70 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-base border-2 border-primary-200">
                  {selectedBooking.schedule?.guide?.name?.charAt(0)?.toUpperCase() ?? "?"}
                </div>
                <div>
                  <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Hướng dẫn viên</span>
                  <h5 className="font-bold text-gray-900 text-sm">
                    {selectedBooking.schedule?.guide?.name ?? "Đang sắp xếp hướng dẫn viên"}
                  </h5>
                  <p className="text-xs text-primary-600 font-semibold">
                    {selectedBooking.schedule?.guide?.phone ?? "Thông tin liên hệ sẽ gửi trước ngày đi"}
                  </p>
                </div>
              </div>
              {selectedBooking.schedule?.guide?.phone && (
                <a
                  href={`tel:${selectedBooking.schedule.guide.phone}`}
                  className="px-3.5 py-2 bg-primary-600 text-white rounded-xl text-xs font-semibold hover:bg-primary-700 transition-colors flex items-center gap-1.5 shadow-xs"
                >
                  <Phone className="w-3.5 h-3.5" /> Gọi HDV
                </a>
              )}
            </div>

            {/* THÔNG TIN ĐOÀN KHÁCH */}
            <div className="space-y-3">
              <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                <UserCheck className="w-4 h-4 text-primary-600" /> Đoàn khách ({selectedBooking.guests} người)
              </h4>
              <div className="bg-gray-50 rounded-lg p-4 border border-gray-200/70 text-xs text-gray-700 space-y-1.5">
                <p>
                  <span className="text-gray-500">Cơ cấu đoàn:</span>{" "}
                  <strong className="text-gray-900">
                    {selectedBooking.adult_count ?? 0} người lớn, {selectedBooking.child_count ?? 0} trẻ em, {selectedBooking.infant_count ?? 0} em bé
                  </strong>
                </p>
                <p>
                  <span className="text-gray-500">Người liên hệ:</span>{" "}
                  <strong className="text-gray-900">{selectedBooking.customer_name}</strong>
                  {selectedBooking.customer_phone ? ` — ${selectedBooking.customer_phone}` : ""}
                </p>
                {selectedBooking.passengers && selectedBooking.passengers.length > 0 && (
                  <div className="pt-2 mt-1 border-t border-gray-200/60 space-y-1.5">
                    {selectedBooking.passengers.map((passenger, idx) => (
                      <div key={passenger.id} className="flex items-center justify-between py-0.5">
                        <span className="flex items-center gap-2">
                          <span className="w-5 h-5 rounded-full bg-primary-100 text-primary-700 font-bold flex items-center justify-center text-[10px]">
                            {idx + 1}
                          </span>
                          <strong className="text-gray-800">{passenger.name}</strong>
                        </span>
                        <span className="text-gray-500">
                          {passenger.type === "adult" ? "Người lớn" : passenger.type === "child" ? "Trẻ em" : "Em bé"}
                          {passenger.note ? ` · ${passenger.note}` : ""}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

          </div>
        )}
      </Modal>

      {/* MODAL: YÊU CẦU HỦY ĐƠN ĐÃ THANH TOÁN, PHẢI CHỜ ĐIỀU HÀNH DUYỆT */}
      <Modal
        isOpen={!!requestTarget}
        onClose={dongFormYeuCau}
        size="lg"
        title={
          <span className="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full uppercase tracking-wider border border-rose-200">
            Yêu cầu hủy đơn #{requestTarget?.id}
          </span>
        }
        subtitle={
          <span className="text-base font-bold text-gray-900 block mt-1 font-plus-jakarta">
            {requestTarget?.tour?.title}
          </span>
        }
      >
        {requestTarget && (
          <div className="space-y-4">
            {requestSent ? (
              <div className="py-6 text-center space-y-3">
                <div className="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                  <CheckCircle2 className="w-8 h-8" />
                </div>
                <h4 className="text-base font-bold text-gray-900 font-plus-jakarta">
                  Đã gửi yêu cầu hủy
                </h4>
                <p className="text-xs text-gray-500 max-w-sm mx-auto">
                  Bộ phận điều hành sẽ xem xét và phản hồi. Đơn của bạn vẫn giữ nguyên cho tới khi
                  yêu cầu được duyệt, nên bạn vẫn đi được nếu đổi ý trước lúc đó.
                </p>
                <button
                  type="button"
                  onClick={dongFormYeuCau}
                  className="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-xl"
                >
                  Đóng
                </button>
              </div>
            ) : (
              <>
                {previewLoading && (
                  <p className="text-xs font-medium text-gray-500">Đang tính mức hoàn dự kiến...</p>
                )}

                {/* Đơn đã có yêu cầu chờ duyệt thì không gửi thêm được nữa. */}
                {requestPreview?.pending_request && (
                  <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 space-y-1">
                    <p className="font-bold">Đơn này đã có một yêu cầu hủy đang chờ duyệt.</p>
                    <p>
                      Gửi lúc {formatDateTime(requestPreview.pending_request.created_at)}. Vui lòng
                      đợi bộ phận điều hành phản hồi.
                    </p>
                  </div>
                )}

                {/*
                  Cho khách xác nhận con số TRƯỚC khi gửi là bước bắt buộc theo tài liệu 03
                  mục 5.2. Đây là cách duy nhất tránh khiếu nại "tôi tưởng được hoàn nhiều hơn".
                */}
                {requestPreview && !requestPreview.pending_request && (
                  <div className="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-gray-500">
                        Còn {Math.max(0, Math.round(requestPreview.hours_before ?? 0))} giờ tới khởi hành
                      </span>
                      <span className="font-bold text-gray-900">
                        Mức hoàn {requestPreview.refund_percent}%
                      </span>
                    </div>

                    <div className="grid grid-cols-3 gap-2 text-center">
                      <div className="rounded-md bg-gray-50 py-2">
                        <p className="text-[11px] text-gray-500">Bạn đã trả</p>
                        <p className="text-sm font-bold text-gray-900">
                          {Number(requestPreview.paid_amount).toLocaleString("vi-VN")} đ
                        </p>
                      </div>
                      <div className="rounded-md bg-amber-50 py-2">
                        <p className="text-[11px] text-amber-700">Phí hủy</p>
                        <p className="text-sm font-bold text-amber-800">
                          {Number(requestPreview.cancellation_fee).toLocaleString("vi-VN")} đ
                        </p>
                      </div>
                      <div className="rounded-md bg-emerald-50 py-2">
                        <p className="text-[11px] text-emerald-700">Bạn nhận lại</p>
                        <p className="text-sm font-bold text-emerald-800">
                          {Number(requestPreview.refund_amount).toLocaleString("vi-VN")} đ
                        </p>
                      </div>
                    </div>

                    <p className="text-[11px] text-gray-500">
                      Mức hoàn được chốt theo thời điểm bạn gửi yêu cầu, nên bạn không bị thiệt nếu
                      việc duyệt mất vài ngày.
                    </p>
                  </div>
                )}

                {requestPreview && !requestPreview.pending_request && (
                  <div>
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                      Lý do xin hủy <span className="text-rose-500">*</span>
                    </label>
                    <textarea
                      rows={3}
                      value={requestReason}
                      onChange={(e) => setRequestReason(e.target.value)}
                      placeholder="VD: Gia đình có việc đột xuất, không thu xếp được lịch..."
                      className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400"
                    />
                    <p
                      className={`text-[11px] mt-1 font-semibold ${
                        requestReason.trim().length >= 10 ? "text-emerald-700" : "text-gray-500"
                      }`}
                    >
                      {requestReason.trim().length}/10 ký tự tối thiểu
                    </p>
                  </div>
                )}

                {requestError && (
                  <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
                    {requestError}
                  </div>
                )}

                <div className="flex items-center justify-end gap-2 pt-1">
                  <button
                    type="button"
                    onClick={dongFormYeuCau}
                    disabled={requestLoading}
                    className="px-4 py-2.5 bg-white border border-gray-200 text-xs font-semibold rounded-xl text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Đóng
                  </button>
                  <button
                    type="button"
                    onClick={guiYeuCauHuy}
                    disabled={
                      requestLoading
                      || previewLoading
                      || !requestPreview
                      || !!requestPreview.pending_request
                      || requestReason.trim().length < 10
                    }
                    className="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm disabled:opacity-50"
                  >
                    {requestLoading ? "Đang gửi..." : "Gửi yêu cầu hủy"}
                  </button>
                </div>
              </>
            )}
          </div>
        )}
      </Modal>

      {/* MODAL: KHÁCH TỰ HỦY ĐƠN CHƯA THANH TOÁN */}
      <Modal
        isOpen={!!cancelTarget}
        onClose={dongFormHuy}
        size="lg"
        title={
          <span className="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full uppercase tracking-wider border border-rose-200">
            Hủy đơn đặt tour #{cancelTarget?.id}
          </span>
        }
        subtitle={
          <span className="text-base font-bold text-gray-900 block mt-1 font-plus-jakarta">
            {cancelTarget?.tour?.title}
          </span>
        }
      >
        {cancelTarget && (
          <div className="space-y-4">
            <div className="bg-gray-50 rounded-lg p-4 border border-gray-200/70 text-xs text-gray-700 space-y-1.5">
              <p>
                <span className="text-gray-500">Khởi hành:</span>{" "}
                <strong className="text-gray-900">{formatDateTime(cancelTarget.departure_date)}</strong>
              </p>
              <p>
                <span className="text-gray-500">Số khách:</span>{" "}
                <strong className="text-gray-900">{cancelTarget.guests} người</strong>
              </p>
              <p>
                <span className="text-gray-500">Giá trị đơn:</span>{" "}
                <strong className="text-gray-900">
                  {Number(cancelTarget.total_amount).toLocaleString("vi-VN")} đ
                </strong>
              </p>
            </div>

            {/* Đơn này chưa thu tiền nên không có gì để hoàn. Nói thẳng ra, đừng hiện bảng phí
                hủy với con số 0 đồng - khách sẽ tưởng mình vừa mất tiền. */}
            <div className="bg-emerald-50/70 rounded-lg p-4 border border-emerald-200 text-xs text-emerald-900 space-y-1">
              <p className="font-bold">Đơn chưa thanh toán nên không phát sinh phí hủy.</p>
              <p>
                Chỗ giữ sẽ được trả lại ngay cho chuyến, và mã giảm giá đã dùng (nếu có) được hoàn
                lượt để bạn dùng lần sau.
              </p>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                Lý do hủy <span className="text-rose-500">*</span>
              </label>
              <textarea
                rows={3}
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                placeholder="VD: Đổi lịch công tác, chưa sắp xếp được thời gian..."
                className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400"
              />
              <p className="text-[11px] text-gray-400 mt-1">
                Lý do giúp chúng tôi cải thiện dịch vụ. Hủy xong không khôi phục lại được, bạn cần
                đặt đơn mới nếu đổi ý.
              </p>
            </div>

            {cancelError && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
                {cancelError}
              </div>
            )}

            <div className="flex items-center justify-end gap-2 pt-1">
              <button
                type="button"
                onClick={dongFormHuy}
                disabled={cancelLoading}
                className="px-4 py-2.5 bg-white border border-gray-200 text-xs font-semibold rounded-xl text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50"
              >
                Không hủy nữa
              </button>
              <button
                type="button"
                onClick={xacNhanHuy}
                disabled={cancelLoading || !cancelReason.trim()}
                className="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors disabled:opacity-50"
              >
                {cancelLoading ? "Đang hủy..." : "Xác nhận hủy đơn"}
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* REUSABLE COMMON MODAL: ĐÁNH GIÁ TOUR */}
      <Modal
        isOpen={!!showReviewModal}
        onClose={() => { setShowReviewModal(null); setReviewSubmitted(false); }}
        size="lg"
        title={
          <span className="text-xs font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full uppercase tracking-wider border border-amber-200">
            Đánh giá & Nhận xét tour
          </span>
        }
        subtitle={
          <span className="text-base font-bold text-gray-900 block mt-1 font-plus-jakarta">
            {showReviewModal?.tour?.title}
          </span>
        }
      >
        {reviewSubmitted ? (
          <div className="py-8 text-center space-y-3">
            <div className="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
              <CheckCircle2 className="w-8 h-8" />
            </div>
            <h4 className="text-base font-bold text-gray-900 font-plus-jakarta">Cảm ơn bạn đã gửi đánh giá!</h4>
            <p className="text-xs text-gray-500">Ý kiến đóng góp của bạn giúp Vivu Booking nâng cao chất lượng dịch vụ hơn nữa.</p>
          </div>
        ) : (
          <form onSubmit={(e) => { e.preventDefault(); setReviewSubmitted(true); }} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Chấm điểm sao</label>
              <div className="flex items-center gap-2">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setReviewRating(star)}
                    className="p-1 transition-transform hover:scale-110"
                  >
                    <Star className={`w-8 h-8 ${star <= reviewRating ? 'fill-amber-400 text-amber-400' : 'text-gray-300'}`} />
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nội dung nhận xét</label>
              <textarea
                rows={4}
                placeholder="Hãy chia sẻ trải nghiệm thực tế của bạn về tour này..."
                value={reviewComment}
                onChange={(e) => setReviewComment(e.target.value)}
                className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                required
              />
            </div>

            <button
              type="submit"
              className="w-full py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs rounded-xl shadow-sm transition-colors"
            >
              Gửi đánh giá tour
            </button>
          </form>
        )}
      </Modal>

    </div>
  );
};

export default MyBookingsTab;
