import { Link, useLocation } from "react-router-dom";
import {
  CreditCardIcon,
  ChevronRightIcon,
} from "@/components/Icons";

type Booking = {
  id: number;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  departure_date?: string;
  guests: number;
  total_amount: number;
  status: string;
  note?: string;
  payment_url?: string;
  created_at?: string;
  tour?: {
    id: number;
    title: string;
    thumbnail: string;
    price: number;
    discount_price?: number;
  };
  schedule?: {
    id: number;
    start_date: string;
  };
};

const formatCurrency = (value?: number) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));

const formatDateTime = (dateStr?: string) => {
  if (!dateStr) return "-";
  try {
    const d = new Date(dateStr);
    return new Intl.DateTimeFormat("vi-VN", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(d);
  } catch {
    return dateStr;
  }
};

const getStatusBadge = (status: string) => {
  const lowercaseStatus = status.toLowerCase();
  if (
    lowercaseStatus === "paid" ||
    lowercaseStatus === "completed" ||
    lowercaseStatus === "thành công" ||
    lowercaseStatus === "đã thanh toán"
  ) {
    return (
      <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold px-3 py-1 rounded-full">
        Đã thanh toán
      </span>
    );
  }
  if (
    lowercaseStatus === "pending" ||
    lowercaseStatus === "chờ thanh toán" ||
    lowercaseStatus === "chờ xử lý"
  ) {
    return (
      <span className="bg-amber-50 text-amber-750 border border-amber-200 text-xs font-bold px-3 py-1 rounded-full animate-pulse">
        Chờ thanh toán
      </span>
    );
  }
  return (
    <span className="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold px-3 py-1 rounded-full">
      {status}
    </span>
  );
};

export default function BookingSuccess() {
  const { state } = useLocation();
  const booking = state as Booking | null;

  if (!booking) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 font-inter">
        <div className="rounded-3xl bg-white p-10 shadow-[0_8px_30px_rgb(0,0,0,0.015)] border border-gray-100 text-center max-w-md mx-4">
          <div className="w-16 h-16 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-500 mx-auto mb-4">
            <svg
              className="w-8 h-8"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-gray-900 font-plus-jakarta">
            Không tìm thấy thông tin đặt tour
          </h2>
          <p className="mt-2 text-sm text-gray-500">
            Vui lòng quay lại danh sách hoặc chọn tour khác để thực hiện đặt chỗ.
          </p>
          <Link
            to="/tours"
            className="mt-6 inline-block w-full rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 text-sm shadow-md transition-all duration-300"
          >
            Xem danh sách Tour
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8 font-inter">
      <div className="mx-auto max-w-[1280px] px-4 sm:px-6">

        {/* Navigation path */}
        <nav className="flex items-center gap-2 text-xs md:text-sm text-gray-500 font-medium mb-6">
          <Link to="/" className="hover:text-primary-600 transition-colors">
            Trang chủ
          </Link>
          <ChevronRightIcon className="w-3.5 h-3.5 text-gray-300" />
          <span className="text-gray-900 font-medium">Đặt tour thành công</span>
        </nav>

        {/* Dynamic Success Top Banner */}
        <div className="bg-white rounded-3xl p-6 md:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.015)] mb-8 flex flex-col md:flex-row items-center gap-6">
          <div className="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
            <svg
              className="w-8 h-8"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2.5}
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>
          <div className="text-center md:text-left flex-1">
            <h1 className="text-2xl md:text-2xl font-extrabold text-gray-900 font-plus-jakarta tracking-tight">
              Đặt tour thành công!
            </h1>
            <p className="mt-1.5 text-sm text-gray-500 leading-relaxed max-w-2xl">
              Cảm ơn quý khách đã tin tưởng đồng hành cùng <strong>Vivu Booking</strong>. Chúng tôi đã gửi chi tiết phiếu xác nhận và hướng dẫn thanh toán về hòm thư điện tử <strong>{booking.customer_email}</strong>.
            </p>
          </div>
          <div className="text-center md:text-right shrink-0">
          </div>
        </div>

        {/* Main Grid */}
        <div className="grid gap-8 lg:grid-cols-12 items-start">

          {/* LEFT: customer info and details */}
          <div className="lg:col-span-8 space-y-8">

            {/* THÔNG TIN LIÊN LẠC */}
            <div className="rounded-3xl bg-white p-6 md:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.015)]">
              <h2 className="mb-6 text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
                Thông tin liên lạc
              </h2>

              <div className="grid gap-6 sm:grid-cols-3 text-sm">
                <div>
                  <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Họ và tên</p>
                  <p className="mt-1.5 text-base font-bold text-gray-800">
                    {booking.customer_name}
                  </p>
                </div>

                <div>
                  <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Email liên hệ</p>
                  <p className="mt-1.5 text-base font-semibold text-gray-800 break-all font-mono">
                    {booking.customer_email}
                  </p>
                </div>

                <div>
                  <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Số điện thoại</p>
                  <p className="mt-1.5 text-base font-bold text-gray-800 font-mono">
                    {booking.customer_phone || "Đang cập nhật"}
                  </p>
                </div>
              </div>

              <div className="mt-8 pt-6 border-t border-gray-100">
                <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Ghi chú yêu cầu</p>
                <p className="mt-1.5 text-sm text-gray-650 italic whitespace-pre-line leading-relaxed">
                  {booking.note || "Không có ghi chú đặc biệt kèm theo."}
                </p>
              </div>
            </div>

            {/* CHI TIẾT BOOKING */}
            <div className="rounded-3xl bg-white p-6 md:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.015)]">
              <h2 className="mb-6 text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
                Chi tiết hóa đơn đặt chỗ
              </h2>

              <div className="divide-y divide-gray-100 text-sm">
                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Mã đặt chỗ</span>
                  <span className="font-bold text-primary-600 tracking-wider">
                    BK{booking.id}
                  </span>
                </div>

                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Thời gian đặt tour</span>
                  <span className="font-semibold text-gray-800 font-mono">
                    {formatDateTime(booking.created_at)}
                  </span>
                </div>

                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Ngày khởi hành</span>
                  <span className="font-bold text-gray-800 font-mono">
                    {booking.departure_date || "-"}
                  </span>
                </div>

                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Số lượng hành khách</span>
                  <span className="font-bold text-gray-800">
                    {booking.guests} khách
                  </span>
                </div>

                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Trị giá booking trọn gói</span>
                  <span className="font-extrabold text-gray-900">
                    {formatCurrency(booking.total_amount)}
                  </span>
                </div>

                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Số tiền đã thanh toán</span>
                  <span className="font-extrabold text-emerald-600 font-mono">
                    0đ
                  </span>
                </div>

                <div className="flex justify-between py-4">
                  <span className="text-gray-500 font-medium">Số tiền cần thanh toán thêm</span>
                  <span className="font-black text-red-600 font-mono">
                    {formatCurrency(booking.total_amount)}
                  </span>
                </div>

                <div className="flex justify-between py-4 items-center">
                  <span className="text-gray-500 font-medium">Trạng thái đặt chỗ</span>
                  <span>
                    {getStatusBadge(booking.status)}
                  </span>
                </div>
              </div>

              {/* Payment Box integration */}
              {booking.payment_url && booking.status === "pending" && (
                <div className="mt-8 p-5 bg-emerald-50 border border-emerald-100 rounded-2xl space-y-4">
                  <div className="flex items-start gap-3.5">
                    <div className="p-2.5 bg-emerald-500 rounded-xl text-white shrink-0">
                      <CreditCardIcon className="w-5 h-5" />
                    </div>
                    <div>
                      <h4 className="font-bold text-emerald-900 text-sm">Thanh toán trực tuyến VNPay an toàn</h4>
                      <p className="text-xs text-emerald-700 leading-relaxed mt-0.5">
                        Để hoàn tất đặt tour và giữ chỗ chính thức, vui lòng nhấn nút bên dưới để tiến hành thanh toán trực tuyến qua cổng VNPay của chúng tôi.
                      </p>
                    </div>
                  </div>
                  <a
                    href={booking.payment_url}
                    className="block w-full bg-emerald-600 hover:bg-emerald-750 text-white font-bold py-4 text-center rounded-xl shadow-md hover:shadow-lg transition-all duration-300 text-sm cursor-pointer"
                  >
                    Thanh toán trực tuyến ngay
                  </a>
                </div>
              )}
            </div>
          </div>

          {/* RIGHT: confirmation voucher */}
          <div className="lg:col-span-4 lg:sticky lg:top-24">
            <div className="rounded-3xl bg-white p-6 md:p-7 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.025)] space-y-6">
              <h2 className="text-lg md:text-xl font-bold text-gray-900 font-plus-jakarta">
                Phiếu xác nhận booking
              </h2>

              <div className="rounded-2xl border border-gray-100 p-4 space-y-4 bg-gray-50/50">
                <div className="relative h-44 rounded-xl overflow-hidden border border-gray-200">
                  <img
                    src={booking.tour?.thumbnail || "https://placehold.co/600x400"}
                    alt={booking.tour?.title || "Tour image"}
                    className="h-full w-full object-cover"
                  />
                  {booking.tour?.discount_price && (
                    <div className="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                      SALE
                    </div>
                  )}
                </div>

                <div>
                  <h3 className="text-base font-extrabold text-gray-900 line-clamp-2 leading-tight">
                    {booking.tour?.title || `Booking Tour #${booking.id}`}
                  </h3>

                  <p className="mt-1.5 text-xs text-primary-600 font-bold">
                    Mã booking: BK{booking.id}
                  </p>
                </div>

                <hr className="border-gray-200/60" />

                <div className="space-y-3.5 text-xs text-gray-600">
                  <div className="flex justify-between items-center">
                    <span className="font-medium text-gray-400">Ngày khởi hành</span>
                    <span className="font-bold text-gray-800 font-mono">
                      {booking.departure_date || "-"}
                    </span>
                  </div>

                  <div className="flex justify-between items-center">
                    <span className="font-medium text-gray-400">Số khách đi cùng</span>
                    <span className="font-bold text-gray-800">
                      {booking.guests} khách
                    </span>
                  </div>

                  <div className="flex justify-between items-center">
                    <span className="font-medium text-gray-400">Trạng thái</span>
                    <span>{getStatusBadge(booking.status)}</span>
                  </div>

                  <div className="border-t border-gray-200/60 pt-4 flex justify-between items-baseline">
                    <span className="font-bold text-gray-800 text-sm">Tổng cộng</span>
                    <span className="text-xl font-black text-red-650 font-mono">
                      {formatCurrency(booking.total_amount)}
                    </span>
                  </div>
                </div>
              </div>

              {/* Go back action */}
              <Link
                to="/"
                className="block w-full rounded-xl border border-gray-250 hover:bg-gray-50 text-center font-semibold py-3 text-sm text-gray-700 transition-all duration-300 cursor-pointer"
              >
                Về trang chủ
              </Link>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}