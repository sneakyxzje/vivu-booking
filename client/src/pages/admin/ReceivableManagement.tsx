import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { AlertTriangle, Loader2, Search, Wallet } from "lucide-react";
import adminService from "@/services/adminService";
import type { ReceivableRow } from "@/services/adminService";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * Những đơn khách còn nợ công ty.
 *
 * Đây là chiều ngược lại của màn Hoàn tiền, và là nửa còn thiếu của câu hỏi "ai còn nợ ai". Trước
 * màn hình này, muốn biết một đơn còn thiếu bao nhiêu phải mở đúng đơn ấy ra xem — không có chỗ nào
 * trả lời được câu kế toán hỏi mỗi ngày: *hôm nay những đơn nào còn nợ, tổng bao nhiêu, đơn nào sắp
 * đi mà chưa thu đủ.*
 *
 * Xếp theo ngày khởi hành gần nhất trước, vì đó là tiền cần đòi gấp nhất: sau khi đoàn lên đường
 * thì đòi khó hơn nhiều.
 */

const KHOANG_NGAY = [
  { value: 0, label: "Tất cả" },
  { value: 7, label: "Đi trong 7 ngày" },
  { value: 30, label: "Đi trong 30 ngày" },
];

export default function ReceivableManagement() {
  const [rows, setRows] = useState<ReceivableRow[]>([]);
  const [outstandingTotal, setOutstandingTotal] = useState(0);
  const [total, setTotal] = useState(0);
  const [withinDays, setWithinDays] = useState(0);
  const [q, setQ] = useState("");
  const [tuKhoa, setTuKhoa] = useState("");
  const [loading, setLoading] = useState(true);

  const taiDanhSach = useCallback(async () => {
    setLoading(true);
    try {
      const result = await adminService.getReceivables({
        q: tuKhoa,
        withinDays: withinDays || undefined,
      });
      setRows(result?.data ?? []);
      setOutstandingTotal(result?.outstanding_total ?? 0);
      setTotal(result?.total ?? 0);
    } catch (err) {
      console.error("Lỗi tải công nợ phải thu:", err);
    } finally {
      setLoading(false);
    }
  }, [tuKhoa, withinDays]);

  useEffect(() => {
    taiDanhSach();
  }, [taiDanhSach]);

  const soDonQuaHan = rows.filter((r) => r.overdue).length;

  return (
    <div className="space-y-6">
      <p className="text-sm text-gray-500">
        Đơn đã vào danh sách đoàn nhưng khách chưa trả đủ. Đơn đang giữ chỗ không tính — nó tự hủy
        sau ít phút nếu không thanh toán.
      </p>

      {/* Hai con số đầu trang: tổng tiền, và số đơn đã quá hạn thu. */}
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-5">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-700">
            <Wallet className="h-4 w-4" />
            Tổng còn phải thu
          </div>
          <p className="mt-2 text-2xl font-bold text-amber-900">{formatPrice(outstandingTotal)}</p>
          <p className="mt-1 text-xs text-amber-800">
            Trên toàn bộ bộ lọc, không riêng trang đang xem · {total} đơn
          </p>
        </div>

        <div className="rounded-xl border border-gray-200 bg-white p-5">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
            <AlertTriangle className="h-4 w-4" />
            Đã quá hạn thu
          </div>
          <p className="mt-2 text-2xl font-bold text-gray-900">{soDonQuaHan} đơn</p>
          <p className="mt-1 text-xs text-gray-500">
            Quá hạn chốt danh sách — mốc công ty phải trả tiền cho khách sạn và nhà xe
          </p>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            setTuKhoa(q.trim());
          }}
          className="relative flex-1 min-w-[220px]"
        >
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Tên khách, email, số điện thoại, hoặc BK-12"
            className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm"
          />
        </form>

        <div className="flex gap-1.5">
          {KHOANG_NGAY.map((muc) => (
            <button
              key={muc.value}
              type="button"
              onClick={() => setWithinDays(muc.value)}
              className={`rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${
                withinDays === muc.value
                  ? "bg-primary-600 text-white"
                  : "border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
              }`}
            >
              {muc.label}
            </button>
          ))}
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center gap-2 py-16 text-sm text-gray-500">
          <Loader2 className="h-4 w-4 animate-spin" />
          Đang tải...
        </div>
      ) : rows.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-white py-16 text-center">
          <p className="text-sm font-semibold text-gray-700">Không có đơn nào còn nợ</p>
          <p className="mt-1 text-sm text-gray-500">
            Mọi đơn trong bộ lọc này đều đã thu đủ tiền.
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white">
          <table className="w-full min-w-[860px] text-sm">
            <thead>
              <tr className="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th className="px-4 py-3">Đơn</th>
                <th className="px-4 py-3">Khách hàng</th>
                <th className="px-4 py-3">Khởi hành</th>
                <th className="px-4 py-3 text-right">Giá trị đơn</th>
                <th className="px-4 py-3 text-right">Đã thu</th>
                <th className="px-4 py-3 text-right">Còn thiếu</th>
                <th className="px-4 py-3">Hạn thu</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-gray-100 last:border-0 hover:bg-gray-50/60">
                  <td className="px-4 py-3">
                    <Link
                      to={`/admin/bookings?q=BK-${row.id}`}
                      className="font-mono text-xs font-semibold text-primary-600 hover:underline"
                    >
                      BK-{row.id}
                    </Link>
                    <p className="mt-0.5 max-w-[200px] truncate text-xs text-gray-500">
                      {row.tour_title ?? "Tour đã xóa"}
                    </p>
                  </td>

                  <td className="px-4 py-3">
                    <p className="font-medium text-gray-900">{row.customer_name}</p>
                    <p className="text-xs text-gray-500">{row.customer_phone ?? row.customer_email}</p>
                  </td>

                  <td className="px-4 py-3 text-gray-600">
                    {row.start_date ? formatDateTime(row.start_date) : "—"}
                  </td>

                  <td className="px-4 py-3 text-right tabular-nums text-gray-600">
                    {formatPrice(row.total_amount)}
                  </td>

                  <td className="px-4 py-3 text-right tabular-nums text-gray-600">
                    {formatPrice(row.net_paid)}
                  </td>

                  <td className="px-4 py-3 text-right tabular-nums font-bold text-amber-700">
                    {formatPrice(row.balance_due)}
                  </td>

                  <td className="px-4 py-3">
                    {row.due_by ? (
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
                          row.overdue
                            ? "bg-rose-50 text-rose-700"
                            : "bg-gray-100 text-gray-600"
                        }`}
                      >
                        {row.overdue && <AlertTriangle className="h-3 w-3" />}
                        {formatDateTime(row.due_by)}
                      </span>
                    ) : (
                      <span className="text-xs text-gray-400">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
