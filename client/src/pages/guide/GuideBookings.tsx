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
  new Date(d.trim().replace(" ", "T")).toLocaleString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
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
  /** Đơn đang mở ô thu tiền. Xác nhận là khẳng định đã cầm tiền, nên phải khai số. */
  const [dangThu, setDangThu] = useState<GuideBooking | null>(null);
  const [soTien, setSoTien] = useState("");
  const [hinhThuc, setHinhThuc] = useState<"cash" | "bank_transfer">("cash");

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

  /**
   * Mở ô thu tiền cho một đơn.
   *
   * Xác nhận tại điểm tập trung nghĩa là hướng dẫn viên vừa cầm tiền của khách, nên phải khai đã
   * cầm bao nhiêu — máy chủ ghi thẳng vào sổ giao dịch. Điền sẵn đúng giá trị đơn vì đó là con số
   * đúng trong hầu hết trường hợp.
   */
  const moOThuTien = (b: GuideBooking) => {
    setDangThu(b);
    setSoTien(String(b.total_amount ?? ""));
    setHinhThuc("cash");
  };

  const handleConfirm = async () => {
    if (!dangThu) return;

    const id = dangThu.id;
    setConfirmingId(id);

    try {
      const so = Number(soTien);

      await guideService.confirmBooking(
        id,
        so > 0 ? { amount: so, method: hinhThuc } : undefined,
      );

      const updated = await guideService.getBookings();
      setBookings(updated);
      setDangThu(null);
      setToast("Đã xác nhận đặt chỗ và ghi khoản thu.");
    } catch (err) {
      const data = (
        err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      )?.response?.data;

      setToast(
        (data?.errors ? Object.values(data.errors).flat()[0] : null) ??
          data?.message ??
          "Không thể xác nhận đặt chỗ. Vui lòng thử lại.",
      );
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
        <div className="bg-white rounded-lg border border-gray-100 p-12 text-center text-gray-500">
          Không có đặt chỗ nào.
        </div>
      ) : (
        <div className="bg-white rounded-lg border border-gray-100 shadow-sm overflow-hidden">
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
                          onClick={() => moOThuTien(b)}
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

      {/*
        Ô thu tiền trước khi xác nhận.

        Xác nhận tại điểm tập trung là khẳng định "khách này đã trả tiền", nên số tiền đi cùng thao
        tác chứ không phải một việc riêng mà ai đó phải nhớ làm sau. Trước đây nút này chỉ đổi trạng
        thái, và sổ giao dịch vẫn ghi đơn ấy thu 0 đồng.
      */}
      {dangThu && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
          <div className="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
            <h3 className="text-base font-bold text-gray-900">
              Xác nhận đơn BK-{dangThu.id}
            </h3>
            <p className="mt-1 text-xs text-gray-500">
              {dangThu.customer_name} · {formatPrice(dangThu.total_amount)}
            </p>

            <label className="mt-4 block">
              <span className="text-xs font-medium text-gray-700">Số tiền vừa thu</span>
              <input
                type="number"
                min={0}
                inputMode="numeric"
                value={soTien}
                onChange={(e) => setSoTien(e.target.value)}
                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="mt-3 block">
              <span className="text-xs font-medium text-gray-700">Hình thức</span>
              <select
                value={hinhThuc}
                onChange={(e) => setHinhThuc(e.target.value as "cash" | "bank_transfer")}
                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              >
                <option value="cash">Tiền mặt</option>
                <option value="bank_transfer">Chuyển khoản</option>
              </select>
            </label>

            <p className="mt-3 text-xs text-gray-500">
              Để trống chỉ được khi văn phòng đã ghi nhận khoản thu từ trước.
            </p>

            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setDangThu(null)}
                className="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700"
              >
                Hủy
              </button>
              <button
                type="button"
                disabled={confirmingId === dangThu.id}
                onClick={handleConfirm}
                className="rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
              >
                {confirmingId === dangThu.id ? "Đang lưu..." : "Ghi nhận & xác nhận"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default GuideBookings;










