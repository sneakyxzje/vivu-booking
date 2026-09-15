import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Select as AntSelect,
  Table as AntTable,
} from "antd";
import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Download, Loader2, Search } from "lucide-react";
import adminService from "@/services/adminService";
import type { TransactionFilters, TransactionRow } from "@/services/adminService";
import { DateRangePicker } from "@/components/admin/AdminDateRangePicker";
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

/**
 * Loại bút toán, hẹp hơn chiều tiền.
 *
 * Hai nhóm cố ý không trộn: `deposit` và `balance` là tiền của GIÁ TOUR, còn `surcharge` là tiền
 * sinh ra từ sự cố dọc đường — một đêm phòng chạy bão chẳng hạn. Gộp chúng lại thì con số "đã thu
 * cho tour" sai, và bảng phí hủy sẽ đem hoàn cả đêm phòng khách đã ở thật.
 */
const LOAI_BUT_TOAN = [
  { key: "", label: "Mọi loại" },
  { key: "deposit", label: "Tiền cọc" },
  { key: "balance", label: "Thanh toán phần còn lại" },
  { key: "refund", label: "Hoàn tiền" },
  { key: "surcharge", label: "Thu phụ phí sự cố" },
  { key: "surcharge_refund", label: "Hoàn do sự cố" },
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
    kind: "",
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
    setFilters({ from: "", to: "", direction: "", kind: "", method: "", q: "" });

  const dangLoc = Object.values(filters).some((v) => String(v ?? "").trim() !== "");


  return (
    <UIFlex vertical gap="large" ><UIFlex   wrap align="end" justify="space-between" gap={16}><p className="max-w-xl text-sm text-gray-500">
          Mọi khoản thu và hoàn của mọi đơn, xếp theo thời gian. Dùng để đối chiếu với sao kê ngân
          hàng.
        </p><AntButton onClick={xuatCsv} disabled={exporting || totals.count === 0} type="primary" htmlType="button"><Download className="h-4 w-4" />{exporting ? "Đang tải..." : "Xuất CSV"}</AntButton></UIFlex>{/* Ba tổng của khoảng đang lọc. Tiền vào và ra khác màu vì đó là điều đầu tiên cần phân biệt. */}<div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
        <UICard  ><UIFlex vertical gap="middle"><p className="text-xs font-semibold uppercase tracking-wider text-gray-500">Thực còn</p><p className="mt-1 text-2xl font-bold tabular-nums text-gray-900">
            {formatPrice(totals.net)}
          </p><p className="text-xs text-gray-400">{totals.count} bút toán</p></UIFlex></UICard>
      </div><div className="grid grid-cols-2 gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm lg:grid-cols-5">
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
          <AntSelect showSearch={{ optionFilterProp: "label" }} value={String(filters.direction ?? "")} onChange={(e) =>
              setFilters((cu) => ({ ...cu, direction: e as TransactionFilters["direction"] }))} style={{ width: "100%" }} options={[CHIEU.map((o) => (
              { value: String(o.key), label: o.label, disabled: false }
            ))].flat().filter((option) => !!option)} />
        </label>
        {/*
          Loại bút toán — hẹp hơn chiều tiền.

          "Chiều tiền" trả lời vào hay ra; "loại" trả lời vào bằng đường nào. Tiền cọc khác thanh
          toán phần còn lại, và phụ thu sự cố lại là túi tiền khác hẳn giá tour. Không có ô này thì
          câu "tháng này thu được bao nhiêu tiền cọc" phải xuất CSV rồi lọc trong Excel.
        */}
        <label className="block">
          <span className="text-[11px] font-semibold text-gray-500">Loại</span>
          <AntSelect showSearch={{ optionFilterProp: "label" }} value={String(filters.kind ?? "")} onChange={(e) =>
              setFilters((cu) => ({ ...cu, kind: e as TransactionFilters["kind"] }))} style={{ width: "100%" }} options={[LOAI_BUT_TOAN.map((o) => (
              { value: String(o.key), label: o.label, disabled: false }
            ))].flat().filter((option) => !!option)} />
        </label>
        <label className="block">
          <span className="text-[11px] font-semibold text-gray-500">Hình thức</span>
          <AntSelect showSearch={{ optionFilterProp: "label" }} value={String(filters.method ?? "")} onChange={(e) =>
              setFilters((cu) => ({ ...cu, method: e as TransactionFilters["method"] }))} style={{ width: "100%" }} options={[HINH_THUC.map((o) => (
              { value: String(o.key), label: o.label, disabled: false }
            ))].flat().filter((option) => !!option)} />
        </label>
        <label className="col-span-2 block lg:col-span-1">
          <span className="text-[11px] font-semibold text-gray-500">Mã chứng từ / tên khách</span>
          <div  className="relative mt-1"><AntInput prefix={<Search size={16} />} value={filters.q ?? ""} onChange={(e) => setFilters((cu) => ({ ...cu, q: e.target.value }))} placeholder="FT2609..." style={{ width: "100%" }} /></div>
        </label>
      </div>{dangLoc && (
        <AntButton onClick={datLai} htmlType="button">Xóa bộ lọc
        </AntButton>
      )}{error && (
        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {error}
        </div>
      )}<div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
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
            <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={rows.map((row) => (
                  {key: row.id, cells: [<>
                      {row.paid_at ? formatDateTime(row.paid_at) : "—"}
                    </>,<>
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
                    </>,<>
                      <span
                        className={`rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${
                          row.direction === "out"
                            ? "border-rose-200 bg-rose-50 text-rose-700"
                            : "border-emerald-200 bg-emerald-50 text-emerald-700"
                        }`}
                      >
                        {row.kind_label}
                      </span>
                    </>,<>{row.method_label ?? "—"}</>,<>
                      <p className="font-mono text-xs text-gray-700">{row.reference ?? "—"}</p>
                      {row.recorded_by ? (
                        <p className="text-[11px] text-gray-400">{row.recorded_by} ghi</p>
                      ) : (
                        // Không có người ghi nghĩa là cổng thanh toán tự vào sổ, không ai bấm nút.
                        <p className="text-[11px] text-gray-400">Hệ thống ghi</p>
                      )}
                    </>,<>
                      {row.direction === "out" ? "−" : "+"}
                      {formatPrice(row.amount)}
                    </>], rowProps: {}}
                ))}
    columns={[{ key: "0", title: <>Thời gian</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Đơn / khách</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Loại</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Hình thức</>, align: "left", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Chứng từ</>, align: "left", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Số tiền</>, align: "right", render: (_value, record) => record.cells[5] }]}
    onRow={(record) => record.rowProps}
     />
          </div>
        )}
      </div>{lastPage > 1 && (
        <nav className="flex items-center justify-center gap-2" aria-label="Phân trang sổ giao dịch">
          <AntButton onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1} htmlType="button">Trước
          </AntButton>
          <span className="px-2 text-sm text-gray-600 tabular-nums">
            Trang {page}/{lastPage}
          </span>
          <AntButton onClick={() => setPage((p) => Math.min(lastPage, p + 1))} disabled={page >= lastPage} htmlType="button">Sau
          </AntButton>
        </nav>
      )}</UIFlex>
  );
}
