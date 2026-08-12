import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { AlertTriangle, CalendarDays, RotateCcw, Users } from "lucide-react";
import adminService from "@/services/adminService";
import type { Booking } from "@/types";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * Màn hình ghế chết: các đơn đã hủy sau hạn chốt danh sách nên chỗ chưa được trả về kho.
 *
 * Chỗ trống về mặt vật lý nhưng chưa bán lại được, vì phòng, ghế và suất ăn đã chốt theo
 * danh sách đã gửi nhà cung cấp. Hệ thống cố ý không tự mở lại: chỉ điều hành mới biết có
 * xin thêm được suất hay không.
 */
export default function HeldSeatsManagement() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [totalHeldSeats, setTotalHeldSeats] = useState(0);
  const [loading, setLoading] = useState(true);
  const [releasingId, setReleasingId] = useState<number | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const [toast, setToast] = useState({
    message: "",
    type: "success" as "success" | "error" | "info",
    isOpen: false,
  });

  const load = useCallback(async (page: number) => {
    setLoading(true);
    try {
      const data = await adminService.getHeldSeats(page);
      setBookings(data?.bookings.data ?? []);
      setTotalHeldSeats(data?.total_held_seats ?? 0);
      setLastPage(data?.bookings.last_page ?? 1);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load(currentPage);
  }, [load, currentPage]);

  const handleRelease = async (booking: Booking) => {
    setReleasingId(booking.id);
    try {
      const updated = await adminService.releaseHeldSeats(booking.id);

      if (!updated) {
        setToast({
          message: "Không mở lại được chỗ của đơn này. Có thể chỗ đã được mở trước đó.",
          type: "error",
          isOpen: true,
        });
        return;
      }

      setToast({
        message: `Đã mở lại ${booking.guests} chỗ của đơn #${booking.id} để bán tiếp.`,
        type: "success",
        isOpen: true,
      });

      await load(currentPage);
    } finally {
      setReleasingId(null);
    }
  };

  return (
    <div className="space-y-5">
      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((t) => ({ ...t, isOpen: false }))}
      />

      <div>
        <h1 className="text-2xl font-bold text-gray-950">Chỗ chưa mở bán lại</h1>
        <p className="mt-1 text-sm text-gray-500">
          Đơn đã hủy sau hạn chốt danh sách. Chỗ còn trống nhưng chưa bán lại được vì phòng,
          ghế và suất ăn đã chốt với nhà cung cấp.
        </p>
      </div>

      <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div className="flex items-start gap-3">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
          <div className="text-sm text-amber-900">
            <p className="font-semibold">
              Đang giữ {totalHeldSeats} chỗ trên {bookings.length} đơn
            </p>
            <p className="mt-1 text-amber-800">
              Chỉ mở lại khi đã xin được thêm suất từ nhà cung cấp. Mở lại mà không có dịch vụ
              đi kèm nghĩa là bán ra một chỗ không phục vụ được.
            </p>
          </div>
        </div>
      </div>

      <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px]">
            <thead className="bg-gray-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500">
              <tr>
                <th className="px-5 py-3">Đơn</th>
                <th className="px-5 py-3">Tour và chuyến</th>
                <th className="px-5 py-3">Số chỗ</th>
                <th className="px-5 py-3">Hủy lúc</th>
                <th className="px-5 py-3">Lý do</th>
                <th className="px-5 py-3 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 text-sm text-gray-700">
              {loading && (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-gray-500">
                    Đang tải...
                  </td>
                </tr>
              )}

              {!loading && bookings.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-5 py-10 text-center text-gray-500">
                    Không có chỗ nào đang bị giữ. Mọi đơn đã hủy đều đã trả chỗ về kho.
                  </td>
                </tr>
              )}

              {!loading &&
                bookings.map((booking) => (
                  <tr key={booking.id} className="transition-colors hover:bg-slate-50/50">
                    <td className="px-5 py-4 font-mono font-bold text-primary-700">
                      #{booking.id}
                      <p className="mt-0.5 font-sans text-xs font-normal text-gray-500">
                        {booking.customer_name}
                      </p>
                    </td>

                    <td className="max-w-xs px-5 py-4">
                      <Link
                        to={`/admin/tours/${booking.tour_id}`}
                        className="font-bold text-gray-900 transition-colors hover:text-primary-650"
                      >
                        {booking.tour?.title ?? `Tour #${booking.tour_id}`}
                      </Link>
                      {booking.schedule && (
                        <p className="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                          <CalendarDays className="h-3.5 w-3.5" />
                          {formatDateTime(booking.schedule.start_date)}
                        </p>
                      )}
                    </td>

                    <td className="whitespace-nowrap px-5 py-4">
                      <span className="inline-flex items-center gap-1.5 rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700">
                        <Users className="h-3.5 w-3.5" />
                        {booking.guests} chỗ
                      </span>
                      <p className="mt-1 text-xs text-gray-500">
                        {formatPrice(Number(booking.total_amount))}
                      </p>
                    </td>

                    <td className="whitespace-nowrap px-5 py-4 text-xs">
                      {booking.cancelled_at ? formatDateTime(booking.cancelled_at) : "-"}
                    </td>

                    <td className="max-w-xs px-5 py-4 text-xs text-gray-600">
                      {booking.cancel_reason ?? "-"}
                    </td>

                    <td className="whitespace-nowrap px-5 py-4 text-right">
                      <button
                        type="button"
                        disabled={releasingId === booking.id}
                        onClick={() => handleRelease(booking)}
                        className="inline-flex cursor-pointer items-center gap-1.5 rounded border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 transition-all duration-150 hover:bg-primary-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        <RotateCcw className="h-3.5 w-3.5" />
                        {releasingId === booking.id ? "Đang mở..." : "Mở bán lại"}
                      </button>
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </div>

      {lastPage > 1 && (
        <div className="flex items-center justify-end gap-2">
          <button
            type="button"
            disabled={currentPage <= 1}
            onClick={() => setCurrentPage((p) => p - 1)}
            className="cursor-pointer rounded border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-40"
          >
            Trang trước
          </button>
          <span className="text-xs font-semibold text-gray-600">
            {currentPage} / {lastPage}
          </span>
          <button
            type="button"
            disabled={currentPage >= lastPage}
            onClick={() => setCurrentPage((p) => p + 1)}
            className="cursor-pointer rounded border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-40"
          >
            Trang sau
          </button>
        </div>
      )}
    </div>
  );
}
