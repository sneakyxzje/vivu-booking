import { Link, useLocation } from "react-router-dom";

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
};

const formatCurrency = (value?: number) =>
  `${(value ?? 0).toLocaleString("vi-VN")}đ`;

export default function BookingSuccess() {
  const { state } = useLocation();

  const booking = state as Booking | null;

  if (!booking) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="rounded-3xl bg-white p-10 shadow-lg text-center">
          <h2 className="text-2xl font-bold text-red-500">
            Không có thông tin booking
          </h2>

          <p className="mt-3 text-gray-500">
            Vui lòng quay lại và thực hiện đặt tour.
          </p>

          <Link
            to="/"
            className="mt-6 inline-block rounded-xl bg-blue-600 px-6 py-3 text-white font-semibold"
          >
            Về trang chủ
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#f5f6f8] py-8">
      <div className="mx-auto max-w-7xl px-4">
        <div className="grid gap-6 lg:grid-cols-3">
          {/* LEFT */}
          <div className="lg:col-span-2 space-y-6">
            {/* THÔNG TIN LIÊN LẠC */}
            <div className="rounded-3xl bg-white p-8 shadow-sm">
              <h2 className="mb-8 text-4xl font-bold text-slate-900">
                Thông tin liên lạc
              </h2>

              <div className="grid gap-8 md:grid-cols-3">
                <div>
                  <p className="text-gray-500">Họ tên:</p>
                  <p className="mt-2 text-2xl font-semibold">
                    {booking.customer_name}
                  </p>
                </div>

                <div>
                  <p className="text-gray-500">Email:</p>
                  <p className="mt-2 text-xl font-semibold break-all">
                    {booking.customer_email}
                  </p>
                </div>

                <div>
                  <p className="text-gray-500">Điện thoại:</p>
                  <p className="mt-2 text-xl font-semibold">
                    {booking.customer_phone}
                  </p>
                </div>
              </div>

              <div className="mt-10">
                <p className="text-gray-500">Ghi chú:</p>

                <p className="mt-2 text-lg font-semibold">
                  {booking.note || "Không có ghi chú"}
                </p>
              </div>
            </div>

            {/* CHI TIẾT BOOKING */}
            <div className="rounded-3xl bg-white p-8 shadow-sm">
              <h2 className="mb-8 text-4xl font-bold text-slate-900">
                Chi tiết booking
              </h2>

              <div className="space-y-5 text-lg">
                <div className="flex justify-between">
                  <span className="text-gray-500">Mã đặt chỗ:</span>

                  <span className="font-bold text-orange-500">
                    BK{booking.id}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">Ngày tạo:</span>

                  <span className="font-semibold">
                    {booking.created_at || "-"}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">Ngày khởi hành:</span>

                  <span className="font-semibold">
                    {booking.departure_date || "-"}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">Số khách:</span>

                  <span className="font-semibold">
                    {booking.guests} người
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">Trị giá booking:</span>

                  <span className="font-bold">
                    {formatCurrency(booking.total_amount)}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">
                    Số tiền đã thanh toán:
                  </span>

                  <span className="font-bold">
                    0đ
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">
                    Số tiền còn lại:
                  </span>

                  <span className="font-bold">
                    {formatCurrency(booking.total_amount)}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className="text-gray-500">Tình trạng:</span>

                  <span className="font-bold text-orange-500">
                    {booking.status}
                  </span>
                </div>
              </div>

              {booking.payment_url && (
                <a
                  href={booking.payment_url}
                  className="mt-8 block rounded-2xl bg-blue-600 py-4 text-center text-lg font-bold text-white hover:bg-blue-700"
                >
                  Thanh toán VNPay
                </a>
              )}
            </div>
          </div>

          {/* RIGHT */}
          <div>
            <div className="sticky top-6 rounded-3xl bg-white p-6 shadow-sm">
              <h2 className="mb-6 text-3xl font-bold">
                Phiếu xác nhận booking
              </h2>

              <div className="rounded-2xl border p-4">
                <img
                  src="https://placehold.co/600x400"
                  alt="tour"
                  className="h-48 w-full rounded-2xl object-cover"
                />

                <h3 className="mt-4 text-xl font-bold">
                  Booking Tour #{booking.id}
                </h3>

                <p className="mt-1 text-blue-600">
                  Mã booking: BK{booking.id}
                </p>

                <hr className="my-5" />

                <div className="space-y-4">
                  <div className="flex justify-between">
                    <span>Ngày khởi hành</span>
                    <span>{booking.departure_date || "-"}</span>
                  </div>

                  <div className="flex justify-between">
                    <span>Số khách</span>
                    <span>{booking.guests}</span>
                  </div>

                  <div className="flex justify-between">
                    <span>Trạng thái</span>

                    <span className="font-semibold text-orange-500">
                      {booking.status}
                    </span>
                  </div>

                  <div className="border-t pt-4 flex justify-between text-xl font-bold">
                    <span>Tổng cộng</span>

                    <span className="text-red-500">
                      {formatCurrency(booking.total_amount)}
                    </span>
                  </div>
                </div>
              </div>

              <Link
                to="/"
                className="mt-6 block rounded-2xl border py-3 text-center font-semibold hover:bg-gray-100"
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