import React, { useState, useMemo } from "react";
import type { Booking, BookingStatus } from "@/types";

const INITIAL_BOOKINGS: Booking[] = [
  {
    id: 10,
    tour_id: 1,
    customer_id: 3,
    guest_id: null,
    tour_schedule_id: 5,
    customer_name: "Nguyễn Văn A",
    customer_email: "nguyenvana@gmail.com",
    customer_phone: "0912345678",
    departure_date: "2026-07-15",
    guests: 4,
    total_amount: 14000000,
    status: "confirmed",
    note: "Xin phòng gia đình có ban công hướng biển.",
    vnpay_transaction_no: "VNP14890283",
    paid_at: "2026-07-01 10:30:00",
    confirmed_at: "2026-07-01 14:00:00",
    created_at: "2026-07-01 09:15:00",
    updated_at: "2026-07-01 14:00:00",
    tour: {
      id: 1,
      title: "Hạ Long Kỳ Vĩ - Du thuyền 5 sao Tuần Châu",
      slug: "ha-long-ky-vi",
      description: "Trải nghiệm kỳ quan thiên nhiên thế giới bằng du thuyền sang trọng 3 ngày 2 đêm.",
      price: 3500000,
      discount_price: null,
      thumbnail: null,
      number_of_days: 3,
      number_of_nights: 2,
      start_location: "Hà Nội",
      end_location: "Hạ Long",
      is_featured: true,
      status: "active",
    },
  },
  {
    id: 11,
    tour_id: 2,
    customer_id: 4,
    guest_id: null,
    tour_schedule_id: 6,
    customer_name: "Trần Thị B",
    customer_email: "tranthib@gmail.com",
    customer_phone: "0987654321",
    departure_date: "2026-08-01",
    guests: 2,
    total_amount: 8400000,
    status: "pending",
    note: "Đoàn có 1 người ăn chay.",
    vnpay_transaction_no: null,
    paid_at: null,
    confirmed_at: null,
    created_at: "2026-07-02 08:30:00",
    updated_at: "2026-07-02 08:30:00",
    tour: {
      id: 2,
      title: "Khám Phá Đà Nẵng - Hội An - Bà Nà Hills",
      slug: "kham-pha-da-nang-hoi-an-ba-na-hills",
      description: "Hành trình di sản văn hóa miền Trung 4 ngày 3 đêm.",
      price: 4200000,
      discount_price: null,
      thumbnail: null,
      number_of_days: 4,
      number_of_nights: 3,
      start_location: "Đà Nẵng",
      end_location: "Đà Nẵng",
      is_featured: true,
      status: "active",
    },
  },
  {
    id: 12,
    tour_id: 3,
    customer_id: null,
    guest_id: "guest-uuid-123",
    tour_schedule_id: 8,
    customer_name: "Lê Hoàng C",
    customer_email: "lehoangc@gmail.com",
    customer_phone: "0905556677",
    departure_date: "2026-07-20",
    guests: 1,
    total_amount: 1500000,
    status: "cancelled",
    note: "Khách hủy lịch trình do có việc gia đình đột xuất.",
    vnpay_transaction_no: null,
    paid_at: null,
    confirmed_at: null,
    created_at: "2026-06-30 18:22:00",
    updated_at: "2026-07-01 09:00:00",
    tour: {
      id: 3,
      title: "City Tour Hà Nội - Khám phá Thủ đô 1 ngày",
      slug: "city-tour-ha-noi-kham-pha-thu-do-1-ngay",
      description: "Thăm lăng Bác, Văn Miếu Quốc Tử Giám, Hồ Hoàn Kiếm.",
      price: 1500000,
      discount_price: null,
      thumbnail: null,
      number_of_days: 1,
      number_of_nights: 0,
      start_location: "Hà Nội",
      end_location: "Hà Nội",
      is_featured: false,
      status: "active",
    },
  },
];

export default function BookingManagement() {
  const [bookings, setBookings] = useState<Booking[]>(INITIAL_BOOKINGS);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [paymentFilter, setPaymentFilter] = useState<string>("all");
  const [sortBy, setSortBy] = useState<string>("latest");
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Tính toán số liệu thống kê từ danh sách bookings (Reactive)
  const stats = useMemo(() => {
    const total = bookings.length;
    const pending = bookings.filter((b) => b.status === "pending").length;
    const confirmed = bookings.filter((b) => b.status === "confirmed").length;
    const cancelled = bookings.filter((b) => b.status === "cancelled").length;
    const paid = bookings.filter((b) => b.vnpay_transaction_no !== null).length;
    const revenue = bookings
      .filter((b) => b.status !== "cancelled" && b.vnpay_transaction_no !== null)
      .reduce((sum, b) => sum + Number(b.total_amount), 0);

    return { total, pending, confirmed, cancelled, paid, revenue };
  }, [bookings]);

  // Bộ lọc và tìm kiếm
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

  // Xử lý cập nhật nhanh trạng thái
  const handleUpdateStatus = (bookingId: number, nextStatus: BookingStatus) => {
    setBookings((prev) =>
      prev.map((b) => {
        if (b.id === bookingId) {
          const nowStr = new Date().toISOString().replace("T", " ").substring(0, 19);
          return {
            ...b,
            status: nextStatus,
            confirmed_at: nextStatus === "confirmed" ? nowStr : b.confirmed_at,
            updated_at: nowStr,
          };
        }
        return b;
      })
    );

    // Cập nhật chi tiết trong modal nếu đang mở đúng đơn này
    if (selectedBooking && selectedBooking.id === bookingId) {
      setSelectedBooking((prev) => {
        if (!prev) return null;
        const nowStr = new Date().toISOString().replace("T", " ").substring(0, 19);
        return {
          ...prev,
          status: nextStatus,
          confirmed_at: nextStatus === "confirmed" ? nowStr : prev.confirmed_at,
          updated_at: nowStr,
        };
      });
    }
  };

  // Xử lý cập nhật thanh toán nhanh giả lập
  const handleTogglePayment = (bookingId: number) => {
    setBookings((prev) =>
      prev.map((b) => {
        if (b.id === bookingId) {
          const isPaid = b.vnpay_transaction_no !== null;
          const nowStr = new Date().toISOString().replace("T", " ").substring(0, 19);
          return {
            ...b,
            vnpay_transaction_no: isPaid ? null : `MOCK-${Math.floor(100000 + Math.random() * 900000)}`,
            paid_at: isPaid ? null : nowStr,
            updated_at: nowStr,
          };
        }
        return b;
      })
    );

    if (selectedBooking && selectedBooking.id === bookingId) {
      setSelectedBooking((prev) => {
        if (!prev) return null;
        const isPaid = prev.vnpay_transaction_no !== null;
        const nowStr = new Date().toISOString().replace("T", " ").substring(0, 19);
        return {
          ...prev,
          vnpay_transaction_no: isPaid ? null : `MOCK-${Math.floor(100000 + Math.random() * 900000)}`,
          paid_at: isPaid ? null : nowStr,
          updated_at: nowStr,
        };
      });
    }
  };

  const openDetails = (booking: Booking) => {
    setSelectedBooking(booking);
    setIsModalOpen(true);
  };

  const closeDetails = () => {
    setIsModalOpen(false);
    setSelectedBooking(null);
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
            Quản lý và cập nhật các đơn đặt tour du lịch từ khách hàng
          </p>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-xs bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-full font-medium border border-indigo-200">
            Chế độ Mock Data (Độ trung thực cao)
          </span>
        </div>
      </div>

      {/* KPI METRICS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Doanh thu */}
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-emerald-50 text-emerald-600 rounded-xl group-hover:bg-emerald-100 transition-colors">
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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Doanh thu VNPAY</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">
              {stats.revenue.toLocaleString()}đ
            </h3>
          </div>
        </div>

        {/* Tổng số đơn */}
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-blue-50 text-blue-600 rounded-xl group-hover:bg-blue-100 transition-colors">
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
            <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Tổng Đơn Đặt</p>
            <h3 className="text-xl font-bold text-gray-900 mt-1">{stats.total} đơn</h3>
          </div>
        </div>

        {/* Chờ duyệt */}
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-amber-50 text-amber-600 rounded-xl group-hover:bg-amber-100 transition-colors">
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
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 group">
          <div className="p-3.5 bg-rose-50 text-rose-600 rounded-xl group-hover:bg-rose-100 transition-colors">
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
      <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm space-y-4">
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
              className="w-full pl-10 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50/50"
            />
          </div>

          {/* Lọc trạng thái đặt */}
          <div className="md:col-span-2">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white"
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
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white"
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
              className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white"
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
              className="w-full py-2 text-sm text-gray-500 hover:text-indigo-600 bg-gray-50 border border-gray-100 rounded-xl font-medium hover:bg-indigo-50 transition-colors"
            >
              Xóa bộ lọc
            </button>
          </div>
        </div>
      </div>

      {/* DATA TABLE */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-gray-50 text-gray-500 text-xs font-semibold uppercase tracking-wider border-b border-gray-100">
                <th className="p-4 w-28">Mã đơn</th>
                <th className="p-4 w-72">Khách hàng</th>
                <th className="p-4 w-80">Thông tin Tour & Ngày đi</th>
                <th className="p-4 text-right">Khách</th>
                <th className="p-4 text-right">Tổng tiền</th>
                <th className="p-4 text-center">Thanh toán</th>
                <th className="p-4 text-center">Trạng thái duyệt</th>
                <th className="p-4 text-center">Hành động</th>
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
                      <td className="p-4 font-bold text-gray-700">
                        BK-{booking.id}
                      </td>

                      {/* Khách hàng */}
                      <td className="p-4">
                        <div>
                          <p className="font-semibold text-gray-900">{booking.customer_name}</p>
                          <p className="text-xs text-gray-400 mt-0.5">{booking.customer_email}</p>
                          {booking.customer_phone && (
                            <p className="text-xs text-gray-500 font-mono mt-0.5">{booking.customer_phone}</p>
                          )}
                        </div>
                      </td>

                      {/* Thông tin Tour */}
                      <td className="p-4">
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
                              {booking.departure_date}
                            </span>
                          </div>
                        </div>
                      </td>

                      {/* Khách */}
                      <td className="p-4 text-right font-medium text-gray-700">
                        {booking.guests} khách
                      </td>

                      {/* Tổng tiền */}
                      <td className="p-4 text-right font-bold text-gray-900">
                        {Number(booking.total_amount).toLocaleString()}đ
                      </td>

                      {/* Thanh toán */}
                      <td className="p-4 text-center">
                        <button
                          onClick={() => handleTogglePayment(booking.id)}
                          title="Click để đổi nhanh trạng thái thanh toán (Mô phỏng)"
                          className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border ${isPaid
                              ? "bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100"
                              : "bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100"
                            } transition-all duration-200`}
                        >
                          <span className={`w-1.5 h-1.5 rounded-full ${isPaid ? "bg-emerald-500" : "bg-gray-400"}`}></span>
                          {isPaid ? "Đã trả qua VNPAY" : "Chưa thanh toán"}
                        </button>
                      </td>

                      {/* Trạng thái duyệt */}
                      <td className="p-4 text-center">
                        <span
                          className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${booking.status === "confirmed"
                              ? "bg-blue-50 text-blue-700 border border-blue-200"
                              : booking.status === "cancelled"
                                ? "bg-rose-50 text-rose-700 border border-rose-200"
                                : "bg-amber-50 text-amber-700 border border-amber-200"
                            }`}
                        >
                          {booking.status === "confirmed" && "Đã xác nhận"}
                          {booking.status === "cancelled" && "Đã hủy"}
                          {booking.status === "pending" && "Chờ xác nhận"}
                        </span>
                      </td>

                      {/* Hành động */}
                      <td className="p-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => openDetails(booking)}
                            className="px-2.5 py-1.5 text-xs text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 font-medium transition-colors"
                          >
                            Chi tiết
                          </button>
                          {booking.status === "pending" && (
                            <>
                              <button
                                onClick={() => handleUpdateStatus(booking.id, "confirmed")}
                                className="px-2.5 py-1.5 text-xs text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 font-medium transition-colors"
                              >
                                Duyệt
                              </button>
                              <button
                                onClick={() => handleUpdateStatus(booking.id, "cancelled")}
                                className="px-2.5 py-1.5 text-xs text-rose-600 bg-rose-50 rounded-lg hover:bg-rose-100 font-medium transition-colors"
                              >
                                Hủy
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* DETAIL MODAL POPUP */}
      {isModalOpen && selectedBooking && (
        <div className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center bg-black/50 p-4 animate-fade-in">
          <div className="relative bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-gray-100 overflow-hidden transform transition-all duration-300 scale-100">
            {/* Modal Header */}
            <div className="bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white flex justify-between items-center">
              <div>
                <h3 className="text-lg font-bold">Chi tiết đơn đặt: BK-{selectedBooking.id}</h3>
                <p className="text-xs text-indigo-100 mt-1">Khởi tạo lúc: {selectedBooking.created_at}</p>
              </div>
              <button
                onClick={closeDetails}
                className="p-1.5 bg-white/10 hover:bg-white/20 rounded-full transition-colors focus:outline-none"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
              {/* Tour info */}
              <div className="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Thông tin Tour đặt</p>
                <h4 className="font-bold text-gray-900 mt-1 text-base">
                  {selectedBooking.tour?.title}
                </h4>
                <div className="grid grid-cols-2 gap-4 mt-3 text-sm">
                  <div>
                    <span className="text-gray-400">Thời gian:</span>{" "}
                    <span className="font-medium text-gray-800">
                      {selectedBooking.tour?.number_of_days} ngày {selectedBooking.tour?.number_of_nights} đêm
                    </span>
                  </div>
                  <div>
                    <span className="text-gray-400">Nơi đi:</span>{" "}
                    <span className="font-medium text-gray-800">
                      {selectedBooking.tour?.start_location}
                    </span>
                  </div>
                  <div>
                    <span className="text-gray-400">Ngày đi:</span>{" "}
                    <span className="font-semibold text-indigo-600">
                      {selectedBooking.departure_date}
                    </span>
                  </div>
                  <div>
                    <span className="text-gray-400">Số khách:</span>{" "}
                    <span className="font-semibold text-gray-800">
                      {selectedBooking.guests} người
                    </span>
                  </div>
                </div>
              </div>

              {/* Customer info */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Thông tin người đặt</h5>
                  <div className="mt-2.5 space-y-1.5 text-sm">
                    <p>
                      <span className="text-gray-400">Họ và tên:</span>{" "}
                      <span className="font-medium text-gray-800">{selectedBooking.customer_name}</span>
                    </p>
                    <p>
                      <span className="text-gray-400">Email:</span>{" "}
                      <span className="font-medium text-gray-800 font-mono text-xs">{selectedBooking.customer_email}</span>
                    </p>
                    <p>
                      <span className="text-gray-400">Số ĐT:</span>{" "}
                      <span className="font-medium text-gray-800 font-mono">{selectedBooking.customer_phone ?? "Không có"}</span>
                    </p>
                  </div>
                </div>

                <div>
                  <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Trạng thái thanh toán</h5>
                  <div className="mt-2.5 space-y-1.5 text-sm">
                    <p>
                      <span className="text-gray-400">Giao dịch VNPAY:</span>{" "}
                      <span className="font-semibold text-gray-800 font-mono">
                        {selectedBooking.vnpay_transaction_no ?? "Chưa thanh toán"}
                      </span>
                    </p>
                    {selectedBooking.paid_at && (
                      <p>
                        <span className="text-gray-400">Ngày thanh toán:</span>{" "}
                        <span className="font-medium text-gray-800 font-mono text-xs">{selectedBooking.paid_at}</span>
                      </p>
                    )}
                    <p>
                      <span className="text-gray-400">Tổng thanh toán:</span>{" "}
                      <span className="font-bold text-gray-900 text-base">
                        {Number(selectedBooking.total_amount).toLocaleString()}đ
                      </span>
                    </p>
                  </div>
                </div>
              </div>

              {/* Passenger note */}
              <div>
                <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ghi chú từ khách hàng</h5>
                <p className="mt-2 p-3 bg-gray-50 text-sm text-gray-600 rounded-xl italic border-l-2 border-indigo-500">
                  {selectedBooking.note || "Không có ghi chú thêm."}
                </p>
              </div>

              {/* Status Update Section */}
              <div className="pt-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                  <h5 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Cập nhật nhanh trạng thái duyệt</h5>
                  <div className="flex gap-2 mt-2">
                    <button
                      onClick={() => handleUpdateStatus(selectedBooking.id, "confirmed")}
                      disabled={selectedBooking.status === "confirmed"}
                      className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors ${selectedBooking.status === "confirmed"
                          ? "bg-gray-100 text-gray-400 cursor-not-allowed"
                          : "bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                        }`}
                    >
                      Duyệt (Xác nhận)
                    </button>
                    <button
                      onClick={() => handleUpdateStatus(selectedBooking.id, "cancelled")}
                      disabled={selectedBooking.status === "cancelled"}
                      className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors ${selectedBooking.status === "cancelled"
                          ? "bg-gray-100 text-gray-400 cursor-not-allowed"
                          : "bg-rose-50 text-rose-700 hover:bg-rose-100"
                        }`}
                    >
                      Hủy đơn đặt
                    </button>
                  </div>
                </div>

                <div className="flex flex-col items-end">
                  <span className="text-xs text-gray-400">Trạng thái hiện tại:</span>
                  <span
                    className={`mt-1 inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ${selectedBooking.status === "confirmed"
                        ? "bg-blue-100 text-blue-800"
                        : selectedBooking.status === "cancelled"
                          ? "bg-rose-100 text-rose-800"
                          : "bg-amber-100 text-amber-800"
                      }`}
                  >
                    {selectedBooking.status === "confirmed" && "Đã duyệt / Xác nhận"}
                    {selectedBooking.status === "cancelled" && "Đã hủy đơn"}
                    {selectedBooking.status === "pending" && "Chờ xác nhận duyệt"}
                  </span>
                </div>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="bg-gray-50 px-6 py-4 flex justify-end gap-2 border-t border-gray-100">
              <button
                onClick={closeDetails}
                className="px-4 py-2 bg-white border border-gray-200 text-sm font-semibold rounded-xl text-gray-700 hover:bg-gray-100 transition-colors"
              >
                Đóng
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
