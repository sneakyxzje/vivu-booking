import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Download, Loader2, Search } from "lucide-react";
import adminService from "@/services/adminService";
import type { TransactionFilters, TransactionRow } from "@/services/adminService";
import { DateRangePicker } from "@/components/DateRangePicker";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * Sổ giao dịch tổng — mọi đồng tiền vào và ra, xếp theo thời gian.
 *
 * Sổ vốn chỉ mở được từ bên trong một đơn, tức chỉ trả lời được "khách này đã trả chưa". Kế toán
 * hỏi ngược lại mỗi ngày: hôm nay thu bao nhiêu, khoản trên sao kê này là của ai, tháng này tiền
 * mặt bao nhiêu. Không câu nào trả lời được bằng cách mở lần lượt từng đơn.
 *
 * Ba con số ở đầu trang tính trên TOÀN BỘ bộ lọc, không riêng trang đang xem — đó là con số đem
 * đi đối chiếu sao kê, và cộng nhầm hai mươi lăm dòng đầu vẫn ra một số trông hợp lý.
 */

const HINH_THUC = [
  { key: "", label: "Mọi hình thức" },
  { key: "bank_transfer", label: "Chuyển khoản" },
  { key: "cash", label: "Tiền mặt" },
  { key: "gateway", label: "Cổng thanh toán" },
];

const CHIEU = [
  { key: "", label: "Vào và ra" },
  { key: "in", label: "Tiền vào" },
  { key: "out", label: "Tiền hoàn ra" },
];

export default function TransactionRegister() {
  const [rows, setRows] = useState<TransactionRow[]>([]);
  const [totals, setTotals] = useState({ in: 0, out: 0, net: 0, count: 0 });
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState("");

  const [filters, setFilters] = useState<TransactionFilters>({
    from: "",
    to: "",
    direction: "",
    method: "",
    q: "",
  });

  /** Bỏ các trường rỗng: gửi `direction=""` lên là máy chủ từ chối vì không thuộc tập cho phép. */
  const thamSo = useCallback(
    () =>
      Object.fromEntries(
        Object.entries(filters).filter(([, v]) => String(v ?? "").trim() !== ""),
      ) as TransactionFilters,
    [filters],
  );

  const taiDanhSach = useCallback(async () => {
    setLoading(true);
    setError("");

    try {
      const result = await adminService.getTransactions({ ...thamSo(), page });
      setRows(result?.data ?? []);
      setLastPage(result?.last_page ?? 1);
      if (result?.totals) setTotals(result.totals);
    } catch (err) {
      setError(
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
          "Không tải được sổ giao dịch.",
      );
    } finally {
      setLoading(false);
    }
  }, [thamSo, page]);

  useEffect(() => {
    // Chờ một nhịp sau khi gõ, để mỗi phím không thành một lượt gọi máy chủ.
    const timer = setTimeout(taiDanhSach, 300);
    return () => clearTimeout(timer);
  }, [taiDanhSach]);

  // Đổi bộ lọc thì về trang 1: giữ nguyên trang 3 khi kết quả còn 8 dòng là hiện một trang trống.
  useEffect(() => {
    setPage(1);
  }, [filters]);

  const xuatCsv = async () => {
    setExporting(true);
    try {
      await adminService.exportTransactions(thamSo());
    } catch {
      setError("Không tải được tệp CSV.");
    } finally {
      setExporting(false);
    }
  };

  const datLai = () =>
    setFilters({ from: "", to: "", direction: "", method: "", q: "" });

  const dangLoc = Object.values(filters).some((v) => String(v ?? "").trim() !== "");

  const inputClass =
    "w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20";

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Sổ giao dịch</h1>
          <p className="mt-1 text-sm text-gray-500">
            Mọi khoản thu và hoàn của mọi đơn, xếp theo thời gian. Dùng để đối chiếu với sao kê
            ngân hàng.
          </p>
        </div>
        <button
          onClick={xuatCsv}
          disabled={exporting || totals.count === 0}
          className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-50"
        >
          <Download className="h-4 w-4" />
          {exporting ? "Đang tải..." : "Xuất CSV"}
        </button>
      </div>

      {/* Ba tổng của khoảng đang lọc. Tiền vào và ra khác màu vì đó là điều đầu tiên cần phân biệt. */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
          <p className="text-xs font-semibold uppercase tracking-wider text-emerald-700">Tiền vào</p>
          <p className="mt-1 text-2xl font-bold tabular-nums text-emerald-900">
            {formatPrice(totals.in)}
          </p>
        </div>
        <div className="rounded-xl border border-rose-200 bg-rose-50 p-5">
          <p className="text-xs font-semibold uppercase tracking-wider text-rose-700">Hoàn ra</p>
          <p className="mt-1 text-2xl font-bold tabular-nums text-rose-900">
            {formatPrice(totals.out)}
          </p>
        </div>
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">Thực còn</p>
          <p className="mt-1 text-2xl font-bold tabular-nums text-gray-900">
            {formatPrice(totals.net)}
          </p>
          <p className="text-xs text-gray-400">{totals.count} bút toán</p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm lg:grid-cols-5">
        {/*
          Bật chọn giờ ở đây: đối chiếu sao kê hay cần cắt theo ca, và máy chủ lọc tới giờ thật
          (xem trait LocKhoangThoiGian) chứ không cắt bỏ phần giờ.
        */}
        <div className="col-span-2">
          <DateRangePicker
            label="Khoảng thời gian"
            withTime
            maxDate={new Date()}
            value={{ from: filters.from ?? "", to: filters.to ?? "" }}
            onChange={(khoang) => setFilters((cu) => ({ ...cu, ...khoang }))}
          />
        </div>
        <label className="block">
          <span className="text-[11px] font-semibold text-gray-500">Chiều tiền</span>
          <select
            value={filters.direction ?? ""}
            onChange={(e) =>
              setFilters((cu) => ({ ...cu, direction: e.target.value as TransactionFilters["direction"] }))
            }
            className={`mt-1 ${inputClass}`}
          >
            {CHIEU.map((o) => (
              <option key={o.key} value={o.key}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="text-[11px] font-semibold text-gray-500">Hình thức</span>
          <select
            value={filters.method ?? ""}
            onChange={(e) =>
              setFilters((cu) => ({ ...cu, method: e.target.value as TransactionFilters["method"] }))
            }
            className={`mt-1 ${inputClass}`}
          >
            {HINH_THUC.map((o) => (
              <option key={o.key} value={o.key}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
        <label className="col-span-2 block lg:col-span-1">
          <span className="text-[11px] font-semibold text-gray-500">Mã chứng từ / tên khách</span>
          <div className="relative mt-1">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={filters.q ?? ""}
              onChange={(e) => setFilters((cu) => ({ ...cu, q: e.target.value }))}
              placeholder="FT2609..."
              className={`${inputClass} pl-9`}
            />
          </div>
        </label>
      </div>

      {dangLoc && (
        <button
          onClick={datLai}
          className="text-xs font-bold text-primary-600 underline hover:text-primary-700"
        >
          Xóa bộ lọc
        </button>
      )}

      {error && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      )}

      <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center gap-2 py-20 text-sm text-gray-500">
            <Loader2 className="h-4 w-4 animate-spin" /> Đang tải...
          </div>
        ) : rows.length === 0 ? (
          <div className="py-20 text-center text-sm text-gray-500">
            Không có bút toán nào khớp điều kiện lọc.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                <tr>
                  <th className="whitespace-nowrap px-5 py-3 font-semibold">Thời gian</th>
                  <th className="px-5 py-3 font-semibold">Đơn / khách</th>
                  <th className="px-5 py-3 font-semibold">Loại</th>
                  <th className="px-5 py-3 font-semibold">Hình thức</th>
                  <th className="px-5 py-3 font-semibold">Chứng từ</th>
                  <th className="whitespace-nowrap px-5 py-3 text-right font-semibold">Số tiền</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {rows.map((row) => (
                  <tr key={row.id} className="hover:bg-gray-50/60">
                    <td className="whitespace-nowrap px-5 py-3 text-xs tabular-nums text-gray-600">
                      {row.paid_at ? formatDateTime(row.paid_at) : "—"}
                    </td>
                    <td className="px-5 py-3">
                      {/*
                        Bấm sang đúng đơn: "khoản này của ai" mà trả lời xong vẫn phải tự đi tìm
                        đơn thì mới xong được một nửa.
                      */}
                      <Link
                        to="/admin/bookings"
                        className="font-mono text-xs font-bold text-primary-600 hover:underline"
                      >
                        BK-{row.booking_id}
                      </Link>
                      <p className="text-sm font-semibold text-gray-900">{row.customer_name ?? "—"}</p>
                      {row.tour_title && (
                        <p className="text-[11px] text-gray-400">{row.tour_title}</p>
                      )}
                    </td>
                    <td className="px-5 py-3">
                      <span
                        className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${
                          row.direction === "out"
                            ? "border-rose-200 bg-rose-50 text-rose-700"
                            : "border-emerald-200 bg-emerald-50 text-emerald-700"
                        }`}
                      >
                        {row.kind_label}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-xs text-gray-600">{row.method_label ?? "—"}</td>
                    <td className="px-5 py-3">
                      <p className="font-mono text-xs text-gray-700">{row.reference ?? "—"}</p>
                      {row.recorded_by ? (
                        <p className="text-[11px] text-gray-400">{row.recorded_by} ghi</p>
                      ) : (
                        // Không có người ghi nghĩa là cổng thanh toán tự vào sổ, không ai bấm nút.
                        <p className="text-[11px] text-gray-400">Hệ thống ghi</p>
                      )}
                    </td>
                    <td
                      className={`whitespace-nowrap px-5 py-3 text-right font-mono text-sm font-bold tabular-nums ${
                        row.direction === "out" ? "text-rose-600" : "text-emerald-700"
                      }`}
                    >
                      {row.direction === "out" ? "−" : "+"}
                      {formatPrice(row.amount)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {lastPage > 1 && (
        <nav className="flex items-center justify-center gap-2" aria-label="Phân trang sổ giao dịch">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1}
            className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Trước
          </button>
          <span className="px-2 text-sm text-gray-600 tabular-nums">
            Trang {page}/{lastPage}
          </span>
          <button
            onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
            disabled={page >= lastPage}
            className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Sau
          </button>
        </nav>
      )}
    </div>
  );
}
