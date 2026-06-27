import React, { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import guideService from "@/services/guideService";
import type { GuideBooking, BookingStatus } from "@/types/guide";
import { BookingStatusBadge } from "@/components/guide/GuideStatusBadge";

const formatPrice = (v: number) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(v);

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });

type StatusFilter = "all" | BookingStatus;

export const GuideBookings: React.FC = () => {
  const [searchParams] = useSearchParams();
  const initialStatus = (searchParams.get("status") as StatusFilter) || "all";

  const [bookings, setBookings] = useState<GuideBooking[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>(initialStatus);
  const [confirmingId, setConfirmingId] = useState<number | null>(null);
  const [toast, setToast] = useState("");

  useEffect(() => {
    guideService
      .getBookings()
      .then((data) => setBookings(data))
      .catch(() => setError("Không thể tải danh sách đặt chỗ."))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (toast) {
      const t = setTimeout(() => setToast(""), 3000);
      return () => clearTimeout(t);
    }
  }, [toast]);

  const filtered = useMemo(() => {
    if (statusFilter === "all") return bookings;
    return bookings.filter((b) => b.status === statusFilter);
  }, [bookings, statusFilter]);

  const handleConfirm = async (id: number) => {
    setConfirmingId(id);
    try {
      await guideService.confirmBooking(id);
      const updated = await guideService.getBookings();
      setBookings(updated);
      setToast("Đã xác nhận đặt chỗ.");
    } catch {
      setToast("Không thể xác nhận đặt chỗ. Vui lòng thử lại.");
    } finally {
      setConfirmingId(null);
    }
  };

  const tabs: { key: StatusFilter; label: string }[] = [
    { key: "all", label: "Tất cả" },
    { key: "pending", label: "Chờ xác nhận" },
    { key: "confirmed", label: "Đã xác nhận" },
    { key: "cancelled", label: "Đã hủy" },
  ];

  return (
    <div className="space-y-6 animate-fade-in">
      {toast && (
        <div className="fixed top-24 right-4 z-50 bg-emerald-600 text-white text-sm font-medium px-4 py-3 rounded-xl shadow-lg">
          {toast}
        </div>
      )}

      {error && (
        <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <div>
        <h1 className="text-2xl font-bold text-gray-900">Quản lý đặt chỗ</h1>
        <p className="text-gray-500 text-sm mt-1">
          Xem và xác nhận đặt tour từ khách hàng
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setStatusFilter(tab.key)}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
              statusFilter === tab.key
                ? "bg-primary-600 text-white"
                : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-16 text-gray-500">Đang tải...</div>
      ) : filtered.length === 0 ? (
        <div className="bg-white rounded-2xl border border-gray-100 p-12 text-center text-gray-500">
          Không có đặt chỗ nào.
        </div>
      ) : (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left text-gray-500 text-xs uppercase tracking-wide">
                  <th className="px-6 py-3 font-semibold">Mã</th>
                  <th className="px-4 py-3 font-semibold">Khách hàng</th>
                  <th className="px-4 py-3 font-semibold">Tour</th>
                  <th className="px-4 py-3 font-semibold">Ngày đi</th>
                  <th className="px-4 py-3 font-semibold">Số khách</th>
                  <th className="px-4 py-3 font-semibold">Tổng tiền</th>
                  <th className="px-4 py-3 font-semibold">Trạng thái</th>
                  <th className="px-6 py-3 font-semibold text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {filtered.map((b) => (
                  <tr key={b.id} className="hover:bg-gray-50/50">
                    <td className="px-6 py-4 font-mono text-xs text-gray-500">
                      #{b.id}
                    </td>
                    <td className="px-4 py-4">
                      <p className="font-medium text-gray-900">{b.customer_name}</p>
                      <p className="text-xs text-gray-500">{b.customer_phone}</p>
                    </td>
                    <td className="px-4 py-4 text-gray-700 max-w-[180px] truncate">
                      {b.tour_title}
                    </td>
                    <td className="px-4 py-4 text-gray-600">
                      {formatDate(b.departure_date)}
                    </td>
                    <td className="px-4 py-4 text-gray-600">{b.guests}</td>
                    <td className="px-4 py-4 font-medium text-gray-900">
                      {formatPrice(b.total_amount)}
                    </td>
                    <td className="px-4 py-4">
                      <BookingStatusBadge status={b.status} />
                    </td>
                    <td className="px-6 py-4 text-right">
                      {b.status === "pending" ? (
                        <button
                          type="button"
                          disabled={confirmingId === b.id}
                          onClick={() => handleConfirm(b.id)}
                          className="text-xs font-semibold bg-primary-600 text-white px-3 py-1.5 rounded-lg hover:bg-primary-700 disabled:opacity-50"
                        >
                          {confirmingId === b.id ? "..." : "Xác nhận"}
                        </button>
                      ) : (
                        <span className="text-xs text-gray-400">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

export default GuideBookings;










