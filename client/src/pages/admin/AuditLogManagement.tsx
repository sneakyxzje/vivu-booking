import { useCallback, useEffect, useMemo, useState } from "react";
import {
  CalendarDays,
  Filter,
  Coins,
  RotateCcw,
  Server,
  User,
} from "lucide-react";
import adminService from "@/services/adminService";
import type { AuditLogEntry, AuditLogResponse } from "@/services/adminService";
import { formatDateTime, formatPrice } from "@/utils/format";
import Pagination from "@/components/common/Pagination";
import { DateRangePicker } from "@/components/DateRangePicker";

/**
 * Nhật ký hệ thống.
 *
 * Nhật ký của từng đơn vẫn nằm trong hộp chi tiết đơn, và vẫn đúng chỗ: ở đó câu hỏi là "đơn này
 * đã trải qua những gì". Màn hình này phục vụ chiều tra cứu ngược lại — "hôm qua ai đụng vào
 * tiền", "tháng này có bao nhiêu lần mở lại chỗ", "ai dời hạn chốt các chuyến" — những câu không
 * trả lời được nếu phải mở từng đơn một.
 *
 * Xem docs/nghiep-vu/16-sua-han-chot.md mục 9.
 */

/** Tên tiếng Việt cho các khóa nằm trong old_values và new_values. */
const nhanTruong: Record<string, string> = {
  status: "Trạng thái",
  seats_released: "Chỗ trả về kho",
  refund_amount: "Tiền hoàn",
  refund_percent: "Phần trăm hoàn",
  estimated_refund: "Tiền hoàn dự kiến",
  cancellation_fee: "Phí hủy",
  total_amount: "Tổng tiền đơn",
  tour_schedule_id: "Chuyến khởi hành",
  booking_deadline: "Hạn chốt danh sách",
  request_id: "Mã yêu cầu",
  passengers: "Danh sách hành khách",
  guests: "Số khách",
  merged: "Do ghép chuyến",
  initiated_by: "Bên khởi xướng",
};

const nhanTrangThai: Record<string, string> = {
  pending: "Chờ thanh toán",
  confirmed: "Đã xác nhận",
  cancelled: "Đã hủy",
  completed: "Đã hoàn thành",
  no_show: "Không có mặt",
  transferred: "Đã chuyển chuyến",
};

const truongLaTien = new Set([
  "refund_amount",
  "estimated_refund",
  "cancellation_fee",
  "total_amount",
]);

const hienGiaTri = (khoa: string, giaTri: unknown): string => {
  if (giaTri === null || giaTri === undefined) return "—";
  if (typeof giaTri === "boolean") return giaTri ? "có" : "không";

  if (truongLaTien.has(khoa) && typeof giaTri === "number") {
    return formatPrice(giaTri);
  }

  if (khoa === "refund_percent") return `${giaTri}%`;
  if (khoa === "booking_deadline" && typeof giaTri === "string") {
    return formatDateTime(giaTri);
  }
  if (khoa === "status" && typeof giaTri === "string") {
    return nhanTrangThai[giaTri] ?? giaTri;
  }
  if (khoa === "initiated_by")
    return giaTri === "company" ? "Công ty" : "Khách";

  if (Array.isArray(giaTri)) return giaTri.join(", ");
  if (typeof giaTri === "object") return JSON.stringify(giaTri);

  return String(giaTri);
};

/**
 * Hai mảng giá trị gộp thành từng cặp trước → sau.
 *
 * Nhiều thao tác chỉ ghi new_values (tạo đơn, gửi yêu cầu hủy), nên không thể chỉ duyệt old_values.
 */
const ghepCapThayDoi = (entry: AuditLogEntry) => {
  const khoa = new Set([
    ...Object.keys(entry.old_values ?? {}),
    ...Object.keys(entry.new_values ?? {}),
  ]);

  return Array.from(khoa).map((ten) => ({
    ten,
    nhan: nhanTruong[ten] ?? ten,
    truoc: entry.old_values?.[ten],
    sau: entry.new_values?.[ten],
    coTruoc: Object.prototype.hasOwnProperty.call(entry.old_values ?? {}, ten),
  }));
};

export default function AuditLogManagement() {
  const [data, setData] = useState<AuditLogResponse | null>(null);
  const [loading, setLoading] = useState(true);

  const [scope, setScope] = useState<"all" | "booking" | "schedule">("all");
  const [action, setAction] = useState("");
  const [moneyOnly, setMoneyOnly] = useState(false);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      setData(
        await adminService.getAuditLogs({
          scope,
          action: action || undefined,
          money_only: moneyOnly,
          from: from || undefined,
          to: to || undefined,
          page,
          per_page: perPage,
        }),
      );
    } catch (err) {
      console.error("Lỗi lấy nhật ký hệ thống: ", err);
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [scope, action, moneyOnly, from, to, page, perPage]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Đổi bộ lọc thì quay về trang đầu, nếu không sẽ rơi vào trang trống.
  useEffect(() => {
    setPage(1);
  }, [scope, action, moneyOnly, from, to, perPage]);

  const danhSachThaoTac = useMemo(() => {
    if (!data) return [];

    if (scope === "booking") return data.filters.booking_actions;
    if (scope === "schedule") return data.filters.schedule_actions;

    return [...data.filters.booking_actions, ...data.filters.schedule_actions];
  }, [data, scope]);

  const datLai = () => {
    setScope("all");
    setAction("");
    setMoneyOnly(false);
    setFrom("");
    setTo("");
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
          Nhật ký hệ thống
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          Ghi lại mọi thao tác diễn ra trong hệ thống
        </p>
      </div>

      {/* BỘ LỌC */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">
              Nguồn
            </label>
            <select
              value={scope}
              onChange={(e) => setScope(e.target.value as typeof scope)}
              className="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
            >
              <option value="all">Tất cả</option>
              <option value="booking">Đơn hàng</option>
              <option value="schedule">Chuyến khởi hành</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">
              Thao tác
            </label>
            <select
              value={action}
              onChange={(e) => setAction(e.target.value)}
              className="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400 min-w-[200px]"
            >
              <option value="">Mọi thao tác</option>
              {danhSachThaoTac.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </select>
          </div>

          {/*
            Có chọn giờ: khi đối soát một sự việc, câu hỏi thường là "chiều qua từ 14 giờ ai đã
            đụng vào đơn này", không phải "cả ngày hôm qua".
          */}
          <div className="min-w-[260px]">
            <DateRangePicker
              label="Khoảng thời gian"
              withTime
              maxDate={new Date()}
              value={{ from, to }}
              onChange={(khoang) => {
                setFrom(khoang.from);
                setTo(khoang.to);
              }}
            />
          </div>

          {/* Câu hỏi hay gặp nhất lúc đối soát, nên để thành một nút bấm chứ không bắt chọn
              từng loại thao tác một. */}
          <button
            type="button"
            onClick={() => setMoneyOnly((truoc) => !truoc)}
            className={`flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors ${
              moneyOnly
                ? "border-amber-300 bg-amber-50 text-amber-800"
                : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
            }`}
          >
            <Coins className="h-3.5 w-3.5" />
            Chỉ những lần chạm tiền
          </button>

          <button
            type="button"
            onClick={datLai}
            className="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
          >
            <RotateCcw className="h-3.5 w-3.5" />
            Đặt lại
          </button>
        </div>

        {moneyOnly && (
          <p className="text-[11px] text-amber-700 flex items-center gap-1.5">
            <Filter className="h-3 w-3" />
            Đang lọc hủy đơn, duyệt hoàn, mở lại đơn và chuyển chuyến. Nhật ký
            chuyến không hiện ở đây vì dời hạn chốt không làm đổi tiền của đơn
            nào.
          </p>
        )}
      </div>

      {/* DÒNG THỜI GIAN */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-100">
        {loading && (
          <p className="p-6 text-sm text-gray-500">Đang tải nhật ký...</p>
        )}

        {!loading && data?.data.length === 0 && (
          <p className="p-6 text-sm text-gray-500">
            Không có bản ghi nào khớp bộ lọc đang chọn.
          </p>
        )}

        {!loading &&
          data?.data.map((entry) => {
            const thayDoi = ghepCapThayDoi(entry);

            return (
              <div
                key={entry.id}
                className="p-4 hover:bg-gray-50/60 transition-colors"
              >
                <div className="flex flex-wrap items-center gap-2">
                  <span
                    className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider ${
                      entry.source === "schedule"
                        ? "bg-indigo-50 text-indigo-700"
                        : "bg-slate-100 text-slate-700"
                    }`}
                  >
                    {entry.source === "schedule" ? (
                      <CalendarDays className="h-3 w-3" />
                    ) : (
                      <Server className="h-3 w-3" />
                    )}
                    {entry.subject_label}
                  </span>

                  <span className="text-sm font-bold text-gray-900">
                    {entry.action_label}
                  </span>

                  {entry.touches_money && (
                    <span className="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                      <Coins className="h-3 w-3" />
                      Tiền
                    </span>
                  )}

                  <span className="ml-auto text-xs text-gray-500">
                    {formatDateTime(entry.created_at)}
                  </span>
                </div>

                <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-500">
                  <span className="inline-flex items-center gap-1">
                    <User className="h-3 w-3" />
                    {/* Không có người thao tác nghĩa là tác vụ nền tự làm. */}
                    {entry.actor_name ?? "Hệ thống tự động"}
                    {entry.actor_role ? ` (${entry.actor_role})` : ""}
                  </span>
                  {entry.subject_note && <span>{entry.subject_note}</span>}
                  {entry.ip_address && <span>IP {entry.ip_address}</span>}
                </p>

                {thayDoi.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {thayDoi.map((item) => (
                      <span
                        key={item.ten}
                        className="rounded border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] text-gray-700"
                      >
                        <span className="font-semibold">{item.nhan}:</span>{" "}
                        {item.coTruoc && (
                          <>
                            <span className="text-gray-400 line-through">
                              {hienGiaTri(item.ten, item.truoc)}
                            </span>{" "}
                            <span className="text-gray-400">→</span>{" "}
                          </>
                        )}
                        <span className="font-semibold text-gray-900">
                          {hienGiaTri(item.ten, item.sau)}
                        </span>
                      </span>
                    ))}
                  </div>
                )}

                {entry.reason && (
                  <p className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
                    <span className="font-semibold">Lý do:</span> {entry.reason}
                  </p>
                )}
              </div>
            );
          })}
      </div>

      {data && data.meta.total > 0 && (
        <Pagination
          currentPage={data.meta.current_page}
          lastPage={data.meta.last_page}
          total={data.meta.total}
          perPage={data.meta.per_page}
          onPageChange={setPage}
          onPerPageChange={setPerPage}
          itemLabel="bản ghi"
        />
      )}
    </div>
  );
}
