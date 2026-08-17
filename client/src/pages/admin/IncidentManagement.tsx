import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, Clock, User } from "lucide-react";
import adminService from "@/services/adminService";
import type {
  AdminIncident,
  IncidentChargeInput,
  IncidentDetailResponse,
  IncidentListResponse,
} from "@/services/adminService";
import { formatDateTime, formatPrice } from "@/utils/format";

/**
 * O - Điều hành xử lý sự cố và phân bổ chi phí.
 *
 * Đây là nơi duy nhất quyết được tiền của một sự cố. Hướng dẫn viên báo cáo ở màn riêng và không
 * có đường nào chạm tới các ô ở đây — đó là chủ ý, không phải giới hạn kỹ thuật.
 *
 * Nguyên tắc phân bổ (tài liệu 04 mục 6.3): **hãng chịu chi phí thuộc nghĩa vụ tổ chức, khách
 * chịu chi phí thuộc tiêu dùng cá nhân thực tế phát sinh.** Xe hỏng phải thuê xe khác thì hãng
 * chịu; kẹt lại một đêm phải ở thêm phòng thì khách chịu. Cùng một cơn bão sinh ra cả hai loại.
 */

const severityClass: Record<string, string> = {
  low: "bg-gray-100 text-gray-700",
  medium: "bg-amber-50 text-amber-700",
  high: "bg-rose-50 text-rose-700",
};

export default function IncidentManagement() {
  const [data, setData] = useState<IncidentListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("");

  const [detail, setDetail] = useState<IncidentDetailResponse | null>(null);
  const [resolution, setResolution] = useState("");
  const [costDelta, setCostDelta] = useState("");
  const [whoBears, setWhoBears] = useState("");
  const [charges, setCharges] = useState<IncidentChargeInput[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      setData(await adminService.getIncidents(statusFilter || undefined));
    } catch (err) {
      console.error("Lỗi tải danh sách sự cố:", err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const openDetail = async (sc: AdminIncident) => {
    setError("");
    setResolution(sc.resolution ?? "");
    setCostDelta(sc.cost_delta !== null ? String(sc.cost_delta) : "");
    setWhoBears(sc.who_bears ?? "");
    setCharges([]);

    try {
      setDetail(await adminService.getIncident(sc.id));
    } catch (err) {
      console.error("Lỗi tải chi tiết sự cố:", err);
    }
  };

  const bearerHienTai = data?.options.bearers.find((b) => b.value === whoBears);
  // Đã xác định hãng chịu thì không lập khoản thu của khách — máy chủ cũng từ chối, khóa ở đây
  // để người dùng khỏi nhập xong mới bị chặn.
  const khachPhaiTra = !whoBears || bearerHienTai?.customer_pays === true;

  const themKhoan = () => {
    const dauTien = detail?.bookings[0];
    if (!dauTien) return;

    setCharges((truoc) => [
      ...truoc,
      {
        booking_id: dauTien.booking_id,
        kind: khachPhaiTra ? "surcharge" : "refund",
        amount: 0,
        reason: "",
      },
    ]);
  };

  const suaKhoan = (index: number, thayDoi: Partial<IncidentChargeInput>) =>
    setCharges((truoc) => truoc.map((k, i) => (i === index ? { ...k, ...thayDoi } : k)));

  const luuPhuongAn = async () => {
    if (!detail) return;

    setSaving(true);
    setError("");

    try {
      await adminService.resolveIncident(detail.incident.id, {
        resolution: resolution.trim(),
        cost_delta: costDelta ? Number(costDelta) : null,
        who_bears: whoBears || null,
        charges: charges.map((k) => ({ ...k, amount: Number(k.amount) })),
      });

      setDetail(null);
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không lưu được phương án.");
    } finally {
      setSaving(false);
    }
  };

  const duyetKhoan = async (id: number) => {
    await adminService.approveSurcharge(id);
    if (detail) setDetail(await adminService.getIncident(detail.incident.id));
    loadData();
  };

  const mienKhoan = async (id: number) => {
    const lyDo = window.prompt("Lý do miễn khoản này (ít nhất 10 ký tự):");
    if (!lyDo || lyDo.trim().length < 10) return;

    await adminService.waiveSurcharge(id, lyDo.trim());
    if (detail) setDetail(await adminService.getIncident(detail.incident.id));
    loadData();
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Sự cố dọc đường</h1>
        <p className="text-sm text-gray-500 mt-1">
          Hướng dẫn viên báo lại những gì xảy ra với đoàn; ở đây quyết phương án và ai trả bao nhiêu.
          Nguyên tắc: hãng chịu chi phí thuộc nghĩa vụ tổ chức, khách chịu chi phí tiêu dùng cá nhân.
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {[
          { value: "", label: "Tất cả" },
          { value: "reported", label: "Chờ xử lý" },
          { value: "reviewed", label: "Đã có phương án" },
          { value: "resolved", label: "Đã đóng" },
        ].map((item) => (
          <button
            key={item.value}
            type="button"
            onClick={() => setStatusFilter(item.value)}
            className={`rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors ${
              statusFilter === item.value
                ? "border-primary-300 bg-primary-50 text-primary-700"
                : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      <div className="space-y-3">
        {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

        {!loading && (data?.incidents.length ?? 0) === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
            Không có sự cố nào.
          </p>
        )}

        {data?.incidents.map((sc) => (
          <button
            key={sc.id}
            type="button"
            onClick={() => openDetail(sc)}
            className={`w-full rounded-xl border bg-white p-4 text-left transition-colors hover:bg-gray-50 ${
              sc.needs_attention ? "border-rose-300 ring-1 ring-rose-100" : "border-gray-200"
            }`}
          >
            <div className="flex flex-wrap items-center gap-2">
              <span
                className={`rounded px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider ${
                  severityClass[sc.severity] ?? severityClass.low
                }`}
              >
                {sc.severity_label}
              </span>
              <span className="text-sm font-bold text-gray-900">{sc.type_label}</span>
              <span className="text-xs text-gray-500">
                #{sc.tour_schedule_id} · {sc.tour_title}
              </span>

              <span className="ml-auto flex items-center gap-3 text-xs text-gray-500">
                <span className="flex items-center gap-1">
                  <Clock className="h-3 w-3" />
                  {formatDateTime(sc.occurred_at)}
                </span>
                <span className="rounded bg-gray-100 px-2 py-0.5 font-semibold text-gray-700">
                  {sc.status_label}
                </span>
              </span>
            </div>

            <p className="mt-1.5 line-clamp-2 text-sm text-gray-700">{sc.description}</p>

            <p className="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-gray-500">
              <span className="flex items-center gap-1">
                <User className="h-3 w-3" />
                {sc.reporter_name ?? "Không rõ"}
              </span>
              {sc.reported_late && (
                <span className="flex items-center gap-1 text-amber-700">
                  <AlertTriangle className="h-3 w-3" />
                  Ghi bù
                </span>
              )}
              {sc.surcharges.length > 0 && (
                <span>
                  {sc.surcharges.length} khoản ·{" "}
                  {sc.surcharges.filter((k) => k.in_effect).length} đã có hiệu lực
                </span>
              )}
            </p>
          </button>
        ))}
      </div>

      {/* Chi tiết và phân bổ chi phí */}
      {detail && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-3xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[88vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                {detail.incident.type_label} — chuyến #{detail.incident.tour_schedule_id}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                {detail.incident.reporter_name} báo lúc {formatDateTime(detail.incident.occurred_at)}
                {detail.incident.reported_late ? " (ghi bù)" : ""}
              </p>
            </div>

            <p className="rounded-lg bg-gray-50 p-3 text-sm text-gray-800">
              {detail.incident.description}
            </p>

            {detail.incident.photos.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {detail.incident.photos.map((anh) => (
                  <img
                    key={anh.id}
                    src={anh.image_path}
                    alt={anh.caption ?? "Ảnh hiện trường"}
                    className="h-20 w-20 rounded-lg border border-gray-200 object-cover"
                  />
                ))}
              </div>
            )}

            {/* Khoản đã lập trước đó */}
            {detail.incident.surcharges.length > 0 && (
              <div className="space-y-1.5">
                <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                  Các khoản đã lập
                </p>
                {detail.incident.surcharges.map((kh) => (
                  <div
                    key={kh.id}
                    className="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 p-2.5 text-xs"
                  >
                    <span className="font-bold text-gray-900">BK-{kh.booking_id}</span>
                    <span className="text-gray-600">{kh.customer_name}</span>
                    <span
                      className={`font-semibold ${
                        kh.kind === "refund" ? "text-emerald-700" : "text-rose-700"
                      }`}
                    >
                      {kh.kind_label} {formatPrice(kh.amount)}
                    </span>
                    <span className="text-gray-500">{kh.reason}</span>

                    <span className="ml-auto flex items-center gap-2">
                      <span
                        className={`rounded px-2 py-0.5 font-semibold ${
                          kh.in_effect ? "bg-emerald-50 text-emerald-700" : "bg-gray-100 text-gray-600"
                        }`}
                      >
                        {kh.status_label}
                      </span>
                      {kh.status === "pending" && (
                        <>
                          <button
                            type="button"
                            onClick={() => duyetKhoan(kh.id)}
                            className="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700 hover:bg-emerald-100"
                          >
                            Duyệt
                          </button>
                          <button
                            type="button"
                            onClick={() => mienKhoan(kh.id)}
                            className="rounded border border-gray-200 px-2 py-0.5 font-semibold text-gray-700 hover:bg-gray-50"
                          >
                            Miễn
                          </button>
                        </>
                      )}
                    </span>
                  </div>
                ))}
              </div>
            )}

            <div className="border-t border-gray-100 pt-4 space-y-3">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Phương án xử lý <span className="text-rose-500">*</span>
                </label>
                <textarea
                  rows={3}
                  value={resolution}
                  onChange={(e) => setResolution(e.target.value)}
                  placeholder="VD: Đổi sang chương trình tham quan trong bờ, ở thêm một đêm tại khách sạn cũ..."
                  className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
                />
                <p className="mt-1 text-[11px] text-gray-400">
                  Hướng dẫn viên sẽ đọc đúng đoạn này cho khách, và đây là căn cứ khi có khiếu nại.
                </p>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Chênh lệch chi phí (không bắt buộc)
                  </label>
                  <input
                    type="number"
                    value={costDelta}
                    onChange={(e) => setCostDelta(e.target.value)}
                    placeholder="0"
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">Ai chịu</label>
                  <select
                    value={whoBears}
                    onChange={(e) => setWhoBears(e.target.value)}
                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
                  >
                    <option value="">Chưa xác định</option>
                    {data?.options.bearers.map((b) => (
                      <option key={b.value} value={b.value}>
                        {b.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {!khachPhaiTra && (
                <p className="rounded-lg bg-gray-50 px-3 py-2 text-[11px] text-gray-600">
                  Phương án ghi <strong>{bearerHienTai?.label}</strong>, nên chỉ lập được khoản hoàn
                  cho khách, không lập được khoản thu thêm.
                </p>
              )}

              {/* Phân bổ cho từng đơn */}
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <p className="text-xs font-bold uppercase tracking-wider text-gray-700">
                    Phân bổ cho từng đơn
                  </p>
                  <button
                    type="button"
                    onClick={themKhoan}
                    className="rounded border border-gray-200 px-2 py-1 text-xs font-semibold text-primary-600 hover:bg-primary-50"
                  >
                    + Thêm khoản
                  </button>
                </div>

                {charges.length === 0 && (
                  <p className="text-[11px] text-gray-400">
                    Không lập khoản nào cũng được: sự cố mà hãng chịu toàn bộ thì chỉ cần ghi phương án.
                  </p>
                )}

                {charges.map((khoan, index) => (
                  <div key={index} className="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 p-2.5 sm:grid-cols-12">
                    <select
                      value={khoan.booking_id}
                      onChange={(e) => suaKhoan(index, { booking_id: Number(e.target.value) })}
                      className="rounded border border-gray-200 px-2 py-1 text-xs sm:col-span-3"
                    >
                      {detail.bookings.map((don) => (
                        <option key={don.booking_id} value={don.booking_id}>
                          BK-{don.booking_id} · {don.customer_name}
                        </option>
                      ))}
                    </select>

                    <select
                      value={khoan.kind}
                      onChange={(e) =>
                        suaKhoan(index, { kind: e.target.value as "surcharge" | "refund" })
                      }
                      className="rounded border border-gray-200 px-2 py-1 text-xs sm:col-span-2"
                    >
                      {data?.options.kinds
                        .filter((k) => k.value === "refund" || khachPhaiTra)
                        .map((k) => (
                          <option key={k.value} value={k.value}>
                            {k.label}
                          </option>
                        ))}
                    </select>

                    <input
                      type="number"
                      value={khoan.amount || ""}
                      onChange={(e) => suaKhoan(index, { amount: Number(e.target.value) })}
                      placeholder="Số tiền"
                      className="rounded border border-gray-200 px-2 py-1 text-xs sm:col-span-2"
                    />

                    <input
                      value={khoan.reason}
                      onChange={(e) => suaKhoan(index, { reason: e.target.value })}
                      placeholder="Diễn giải cho khách"
                      className="rounded border border-gray-200 px-2 py-1 text-xs sm:col-span-4"
                    />

                    <button
                      type="button"
                      onClick={() => setCharges((truoc) => truoc.filter((_, i) => i !== index))}
                      className="rounded px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 sm:col-span-1"
                    >
                      Xóa
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {error && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {error}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setDetail(null)}
                disabled={saving}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Đóng
              </button>
              <button
                type="button"
                onClick={luuPhuongAn}
                disabled={saving || resolution.trim().length < 20}
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-40"
              >
                {saving ? "Đang lưu..." : "Lưu phương án"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
