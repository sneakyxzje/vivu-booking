import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Table as AntTable,
} from "antd";
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
    <UIFlex vertical gap="large" ><p className="text-sm text-gray-500">
        Đơn đã vào danh sách đoàn nhưng khách chưa trả đủ. Đơn đang giữ chỗ không tính — nó tự hủy
        sau ít phút nếu không thanh toán.
      </p>{/* Hai con số đầu trang: tổng tiền, và số đơn đã quá hạn thu. */}<div className="grid gap-4 sm:grid-cols-2">
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

        <UICard  ><UIFlex vertical gap="middle"><div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
            <AlertTriangle className="h-4 w-4" />
            Đã quá hạn thu
          </div><p className="mt-2 text-2xl font-bold text-gray-900">{soDonQuaHan} đơn</p><p className="mt-1 text-xs text-gray-500">
            Quá hạn chốt danh sách — mốc công ty phải trả tiền cho khách sạn và nhà xe
          </p></UIFlex></UICard>
      </div><UIFlex   wrap align="center"  gap={12}><form
          onSubmit={(e) => {
            e.preventDefault();
            setTuKhoa(q.trim());
          }}
          className="relative flex-1 min-w-[220px]"
        >
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <AntInput value={q} onChange={(e) => setQ(e.target.value)} placeholder="Tên khách, email, số điện thoại, hoặc BK-12" style={{ width: "100%" }} />
        </form><UIFlex      gap={6}>{KHOANG_NGAY.map((muc) => (
            <AntButton type={(withinDays === muc.value) ? "primary" : "default"} key={muc.value} htmlType="button" onClick={() => setWithinDays(muc.value)}>{muc.label}</AntButton>
          ))}</UIFlex></UIFlex>{loading ? (
        <div className="flex items-center justify-center gap-2 py-16 text-sm text-gray-500">
          <Loader2 className="h-4 w-4 animate-spin" />
          Đang tải...
        </div>
      ) : rows.length === 0 ? (
        <UICard  ><UIFlex vertical gap="middle"><p className="text-sm font-semibold text-gray-700">Không có đơn nào còn nợ</p><p className="mt-1 text-sm text-gray-500">
            Mọi đơn trong bộ lọc này đều đã thu đủ tiền.
          </p></UIFlex></UICard>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white">
          <AntTable rowKey="key" pagination={false} scroll={{ x: "max-content" }}
    dataSource={rows.map((row) => (
                {key: row.id, cells: [<>
                    <Link
                      to={`/admin/bookings?q=BK-${row.id}`}
                      className="font-mono text-xs font-semibold text-primary-600 hover:underline"
                    >
                      BK-{row.id}
                    </Link>
                    <p className="mt-0.5 max-w-[200px] truncate text-xs text-gray-500">
                      {row.tour_title ?? "Tour đã xóa"}
                    </p>
                  </>,<>
                    <p className="font-medium text-gray-900">{row.customer_name}</p>
                    <p className="text-xs text-gray-500">{row.customer_phone ?? row.customer_email}</p>
                  </>,<>
                    {row.start_date ? formatDateTime(row.start_date) : "—"}
                  </>,<>
                    {formatPrice(row.total_amount)}
                  </>,<>
                    {formatPrice(row.net_paid)}
                  </>,<>
                    {formatPrice(row.balance_due)}
                  </>,<>
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
                  </>], rowProps: {}}
              ))}
    columns={[{ key: "0", title: <>Đơn</>, align: "left", render: (_value, record) => record.cells[0] },{ key: "1", title: <>Khách hàng</>, align: "left", render: (_value, record) => record.cells[1] },{ key: "2", title: <>Khởi hành</>, align: "left", render: (_value, record) => record.cells[2] },{ key: "3", title: <>Giá trị đơn</>, align: "right", render: (_value, record) => record.cells[3] },{ key: "4", title: <>Đã thu</>, align: "right", render: (_value, record) => record.cells[4] },{ key: "5", title: <>Còn thiếu</>, align: "right", render: (_value, record) => record.cells[5] },{ key: "6", title: <>Hạn thu</>, align: "left", render: (_value, record) => record.cells[6] }]}
    onRow={(record) => record.rowProps}
     />
        </div>
      )}</UIFlex>
  );
}
