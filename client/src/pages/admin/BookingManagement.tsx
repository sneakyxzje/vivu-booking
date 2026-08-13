import { useState, useEffect, useMemo } from "react";
import type { Booking } from "@/types";
import adminService from "@/services/adminService";
import type { CancelPreview } from "@/services/adminService";
import { Modal } from "@/components/admin/Modal";
import { formatDateTime, formatPrice } from "@/utils/format";

export default function BookingManagement() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalBookingsCount, setTotalBookingsCount] = useState(0);

  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [paymentFilter, setPaymentFilter] = useState<string>("all");
  const [sortBy, setSortBy] = useState<string>("latest");

  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  const [cancelMode, setCancelMode] = useState(false);
  const [cancelReason, setCancelReason] = useState("");
  const [cancelPreview, setCancelPreview] = useState<CancelPreview | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [reopenMode, setReopenMode] = useState(false);
  const [reopenReason, setReopenReason] = useState("");
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState("");

  // Fetch dữ liệu từ Backend API (Có phân trang)
  useEffect(() => {
    const fetchBookings = async () => {
      setLoading(true);
      try {
        const res = await adminService.getBookings(currentPage);
        if (res) {
          setBookings(res.data || []);
          setTotalPages(res.last_page || 1);
          setTotalBookingsCount(res.total || 0);
        }
      } catch (err) {
        console.error("Lỗi lấy danh sách đơn đặt: ", err);
      } finally {
        setLoading(false);
      }
    };
    fetchBookings();
  }, [currentPage]);

  // Tính toán số liệu thống kê nhanh trên trang hiện tại
  const stats = useMemo(() => {
    const total = totalBookingsCount;
    const pending = bookings.filter((b) => b.status === "pending").length;
    const confirmed = bookings.filter((b) => b.status === "confirmed").length;
    const cancelled = bookings.filter((b) => b.status === "cancelled").length;
    const paid = bookings.filter((b) => b.vnpay_transaction_no !== null).length;
    // Doanh thu = tổng giá trị các đơn đã xác nhận (thống nhất với dashboard admin & guide)
    const revenue = bookings
      .filter((b) => b.status === "confirmed")
      .reduce((sum, b) => sum + Number(b.total_amount), 0);

    return { total, pending, confirmed, cancelled, paid, revenue };
  }, [bookings, totalBookingsCount]);

  // Bộ lọc và tìm kiếm trên danh sách hiện tại
  const filteredBookings = useMemo(() => {
    let result = [...bookings];

    // Tìm kiếm
    if (search.trim()) {
      const q = search.toLowerCase();
      result = result.filter(
        (b) =>
          `BK-${b.id}`.toLowerCase().includes(q) ||
          b.customer_name.toLowerCase().includes(q) ||
          b.customer_email.toLowerCase().includes(q) ||
          (b.customer_phone && b.customer_phone.includes(q)) ||
          (b.tour && b.tour.title.toLowerCase().includes(q))
      );
    }

    // Lọc theo trạng thái đặt
    if (statusFilter !== "all") {
      result = result.filter((b) => b.status === statusFilter);
    }

    // Lọc theo trạng thái thanh toán
    if (paymentFilter !== "all") {
      if (paymentFilter === "paid") {
        result = result.filter((b) => b.vnpay_transaction_no !== null);
      } else {
        result = result.filter((b) => b.vnpay_transaction_no === null);
      }
    }

    // Sắp xếp
    if (sortBy === "latest") {
      result.sort((a, b) => b.id - a.id);
    } else if (sortBy === "oldest") {
      result.sort((a, b) => a.id - b.id);
    } else if (sortBy === "amount-desc") {
      result.sort((a, b) => Number(b.total_amount) - Number(a.total_amount));
    } else if (sortBy === "amount-asc") {
      result.sort((a, b) => Number(a.total_amount) - Number(b.total_amount));
    }

    return result;
  }, [bookings, search, statusFilter, paymentFilter, sortBy]);

  // Xem chi tiết đơn hàng (Gọi API chi tiết để lấy thông tin sâu hơn như payment log)
  const openDetails = async (booking: Booking) => {
    setSelectedBooking(booking);
    setIsModalOpen(true);
    try {
      const detailed = await adminService.getBookingById(booking.id);
      if (detailed) {
        setSelectedBooking(detailed);
      }
    } catch (err) {
      console.error("Lỗi lấy chi tiết đơn đặt hàng: ", err);
    }
  };

  const closeDetails = () => {
    setIsModalOpen(false);
    setSelectedBooking(null);
    setCancelMode(false);
    setCancelReason("");
    setCancelPreview(null);
    setActionError("");
  };

  /**
   * Mở form hủy và hỏi máy chủ trước xem hủy đơn này sẽ ra sao.
   *
   * Hỏi máy chủ chứ không tự tính ở đây: bảng phí đã sao chép vào từng đơn lúc đặt, và quy tắc
   * trả chỗ phụ thuộc hạn chốt danh sách của chuyến. Tính lại ở trình duyệt thì sớm muộn cũng
   * lệch với con số máy chủ thực sự áp dụng.
   */
  const openCancelForm = async () => {
    if (!selectedBooking) return;

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

  const handleConfirm = async () => {
    if (!selectedBooking) return;
    setActionLoading(true);
    setActionError("");
    try {
      const updated = await adminService.confirmBooking(selectedBooking.id);
      if (updated) applyBookingUpdate(updated);
    } catch (err) {
      setActionError(extractApiError(err, "Không thể xác nhận đơn. Vui lòng thử lại."));
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

  // Kiểm tra đơn có đủ điều kiện mở lại trong vòng 24 giờ không (Edge Case C06)
  const canReopen = (booking: Booking | null) => {
    if (!booking || booking.status !== "cancelled" || !booking.cancelled_at) return false;
    const cancelledTime = new Date(booking.cancelled_at).getTime();
    const now = new Date().getTime();
    const diffInHours = (now - cancelledTime) / (1000 * 60 * 60);
    return diffInHours <= 24;
  };

  const handleReopen = async () => {
    if (!selectedBooking || !reopenReason.trim()) return;
    setActionLoading(true);
    setActionError("");
    try {
      const updated = await adminService.reopenBooking(selectedBooking.id, reopenReason.trim());
      if (updated) {
        applyBookingUpdate(updated);
        setReopenMode(false);
        setReopenReason("");
      }
    } catch (err) {
      setActionError(extractApiError(err, "Không thể mở lại đơn đặt tour này. Vui lòng kiểm tra số chỗ trống trên chuyến."));
    } finally {
      setActionLoading(false);
    }
  };

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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Tổng Đơn Đặt (Tất cả)</p>
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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Chờ xác nhận (Trang này)</p>
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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Đơn đã hủy (Trang này)</p>
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
              placeholder="Tìm mã đơn, tên khách hàng, số điện thoại, tour..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-gray-50/50"
            />
          </div>

          {/* Lọc trạng thái đặt */}
          <div className="md:col-span-2">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="all">Tất cả trạng thái duyệt</option>
              <option value="pending">Chờ xác nhận</option>
              <option value="confirmed">Đã xác nhận</option>
              <option value="cancelled">Đã hủy</option>
            </select>
          </div>

          {/* Lọc thanh toán */}
          <div className="md:col-span-2.5">
            <select
              value={paymentFilter}
              onChange={(e) => setPaymentFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="all">Tất cả thanh toán</option>
              <option value="paid">Đã thanh toán (Qua VNPAY)</option>
              <option value="unpaid">Chưa thanh toán</option>
            </select>
          </div>

          {/* Sắp xếp */}
          <div className="md:col-span-2">
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white cursor-pointer"
            >
              <option value="latest">Mới nhất trước</option>
              <option value="oldest">Cũ nhất trước</option>
              <option value="amount-desc">Tổng giá giảm dần</option>
              <option value="amount-asc">Tổng giá tăng dần</option>
            </select>
          </div>

          {/* Xóa lọc nhanh */}
          <div className="md:col-span-1.5 flex">
            <button
              onClick={() => {
                setSearch("");
                setStatusFilter("all");
                setPaymentFilter("all");
                setSortBy("latest");
              }}
              className="w-full py-2 text-sm text-gray-500 hover:text-primary-600 bg-gray-50 border border-gray-100 rounded-md font-medium hover:bg-primary-50 transition-colors cursor-pointer"
            >
              Xóa bộ lọc
            </button>
          </div>
        </div>
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
                {filteredBookings.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="p-12 text-center text-gray-400">
                      Không tìm thấy đơn đặt hàng nào phù hợp với bộ lọc.
                    </td>
                  </tr>
                ) : (
                  filteredBookings.map((booking) => {
                    const isPaid = booking.vnpay_transaction_no !== null;
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
                            {isPaid ? "Đã trả qua VNPAY" : "Chưa thanh toán"}
                          </span>
                        </td>

                        {/* Trạng thái duyệt */}
                        <td className="py-3.5 px-6 text-center">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border ${booking.status === "confirmed"
                              ? "bg-blue-50 text-blue-700 border-blue-200"
                              : booking.status === "cancelled"
                                ? "bg-rose-50 text-rose-700 border-rose-200"
                                : "bg-amber-50 text-amber-700 border-amber-200"
                              }`}
                          >
                            {booking.status === "confirmed" && "Đã xác nhận"}
                            {booking.status === "cancelled" && "Đã hủy"}
                            {booking.status === "pending" && "Chờ xác nhận"}
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
                onClick={handleConfirm}
                disabled={actionLoading}
                className="px-4 py-2 bg-emerald-600 text-sm font-semibold rounded-md text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 cursor-pointer"
              >
                {actionLoading ? "Đang xử lý..." : "Xác nhận đơn"}
              </button>
            )}
            {(selectedBooking?.status === "pending" || selectedBooking?.status === "confirmed") && !cancelMode && !reopenMode && (
              <button
                onClick={openCancelForm}
                disabled={actionLoading}
                className="px-4 py-2 bg-white border border-rose-200 text-sm font-semibold rounded-md text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-50 cursor-pointer"
              >
                Hủy đơn
              </button>
            )}
            {selectedBooking?.status === "cancelled" && canReopen(selectedBooking) && !cancelMode && !reopenMode && (
              <button
                onClick={() => { setReopenMode(true); setActionError(""); }}
                disabled={actionLoading}
                className="px-4 py-2 bg-blue-600 text-sm font-semibold rounded-md text-white hover:bg-blue-700 transition-colors disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
              >
                🔄 Mở lại đơn đã hủy
              </button>
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
        {selectedBooking && (
          <div className="space-y-5">
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
                <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Thông tin người đặt</h5>
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
              </div>

              <div className="bg-gray-50/50 p-5 rounded-lg border border-gray-200">
                <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Trạng thái thanh toán</h5>
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
                </div>
              </div>
            </div>

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
                    {canReopen(selectedBooking) ? " (Trong thời hạn 24h — Có thể mở lại)" : " (Đã quá 24h — Không thể mở lại)"}
                  </p>
                )}
              </div>
            )}

            {/* Lý do khôi phục đơn (nếu đơn từng được khôi phục) */}
            {selectedBooking.reopen_reason && (
              <div className="bg-blue-50/60 p-4 rounded-lg border border-blue-200">
                <h5 className="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-2">Lý do khôi phục đơn</h5>
                <p className="text-sm text-blue-800 leading-relaxed">{selectedBooking.reopen_reason}</p>
                {selectedBooking.reopened_at && (
                  <p className="text-[11px] text-blue-600 mt-2 font-mono">
                    Khôi phục lúc: {formatDateTime(selectedBooking.reopened_at)}
                  </p>
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

            {/* Form nhập lý do hủy */}
            {cancelMode && (
              <div className="rounded-lg border border-rose-200 bg-rose-50/50 p-4 space-y-3">
                {previewLoading && (
                  <p className="text-xs font-medium text-gray-500">Đang tính mức hoàn và tình trạng chỗ...</p>
                )}

                {/* Chuyến đang chạy hoặc đã kết thúc thì không hủy được. Nói ngay ở đây thay vì
                    để người ta gõ xong lý do rồi mới nhận lỗi. */}
                {cancelPreview && !cancelPreview.can_cancel && (
                  <div className="rounded-lg border border-rose-300 bg-white px-3 py-2.5">
                    <p className="text-sm font-bold text-rose-700">Không hủy được đơn này</p>
                    <p className="text-xs text-rose-700 mt-0.5">{cancelPreview.blocked_reason}</p>
                  </div>
                )}

                {cancelPreview && cancelPreview.can_cancel && (
                  <div className="rounded-lg border border-gray-200 bg-white p-3 space-y-2">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-gray-500">
                        Còn {Math.max(0, Math.round(cancelPreview.hours_before ?? 0))} giờ tới khởi hành
                        {cancelPreview.policy_name ? ` · ${cancelPreview.policy_name}` : ""}
                      </span>
                      <span className="font-bold text-gray-900">
                        Mức hoàn {cancelPreview.refund_percent}%
                      </span>
                    </div>

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

                    {/* Điểm dễ hiểu sai nhất: hủy sau hạn chốt danh sách thì chỗ ở lại với đơn,
                        vì suất đã cam kết với nhà cung cấp và không hủy được nữa. */}
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

                <label className="block text-sm font-semibold text-rose-800">
                  Lý do hủy đơn <span className="text-rose-500">*</span>
                </label>
                <textarea
                  rows={2}
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  placeholder="VD: Khách yêu cầu hoàn do thay đổi lịch trình, tour bị hoãn..."
                  className="w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm outline-none focus:border-rose-400"
                />
                <p className="text-xs text-rose-600">
                  Lượt mã giảm giá luôn được hoàn lại.
                  {selectedBooking.vnpay_transaction_no && " Đơn này ĐÃ thanh toán qua VNPay — cần chuyển tiền hoàn cho khách thủ công."}
                </p>
                <div className="flex justify-end gap-2">
                  <button
                    onClick={() => { setCancelMode(false); setCancelReason(""); setCancelPreview(null); }}
                    disabled={actionLoading}
                    className="px-3.5 py-2 bg-white border border-gray-200 text-xs font-semibold rounded-md text-gray-600 hover:bg-gray-100 cursor-pointer"
                  >
                    Không hủy nữa
                  </button>
                  <button
                    onClick={handleCancel}
                    disabled={
                      actionLoading
                      || previewLoading
                      || !cancelReason.trim()
                      || cancelPreview?.can_cancel === false
                    }
                    className="px-3.5 py-2 bg-rose-600 text-xs font-semibold rounded-md text-white hover:bg-rose-700 disabled:opacity-50 cursor-pointer"
                  >
                    {actionLoading ? "Đang hủy..." : "Xác nhận hủy đơn"}
                  </button>
                </div>
              </div>
            )}

            {/* Form nhập lý do mở lại đơn */}
            {reopenMode && (
              <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 space-y-3">
                <label className="block text-sm font-semibold text-blue-800">
                  Lý do mở lại đơn đã hủy <span className="text-blue-500">*</span>
                </label>
                <textarea
                  rows={2}
                  value={reopenReason}
                  onChange={(e) => setReopenReason(e.target.value)}
                  placeholder="VD: Quản trị viên hủy nhầm đơn khách đã thanh toán, khách muốn tiếp tục đi tour..."
                  className="w-full rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400"
                />
                <p className="text-xs text-blue-600">
                  Mở lại đơn sẽ cộng lại số chỗ vào chuyến khởi hành và khôi phục trạng thái đơn về {selectedBooking.vnpay_transaction_no ? "Đã xác nhận (Confirmed)" : "Chờ xác nhận (Pending)"}.
                </p>
                <div className="flex justify-end gap-2">
                  <button
                    onClick={() => { setReopenMode(false); setReopenReason(""); }}
                    disabled={actionLoading}
                    className="px-3.5 py-2 bg-white border border-gray-200 text-xs font-semibold rounded-md text-gray-600 hover:bg-gray-100 cursor-pointer"
                  >
                    Không mở nữa
                  </button>
                  <button
                    onClick={handleReopen}
                    disabled={actionLoading || !reopenReason.trim()}
                    className="px-3.5 py-2 bg-blue-600 text-xs font-semibold rounded-md text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                  >
                    {actionLoading ? "Đang khôi phục..." : "Xác nhận khôi phục đơn"}
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}


