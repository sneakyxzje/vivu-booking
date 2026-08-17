import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowRight, Check, Clock, Phone, Plus } from "lucide-react";
import guideService from "@/services/guideService";
import type { GuideHandoverNote, GuideHandoverRequestRow } from "@/services/guideService";
import type { Tour } from "@/types";
import { formatDateTime } from "@/utils/format";

/**
 * Biên bản bàn giao đoàn.
 *
 * Hai chiều, và mỗi chiều phục vụ một việc khác nhau:
 *
 *   - **Nhận** — đoàn đang ở đâu, đã điểm danh tới đâu, khách nào cần để ý. Đây là thứ duy nhất
 *     người mới có để bắt nhịp, nên hiển thị nổi nhất.
 *   - **Giao** — người cũ mất quyền ghi nhưng vẫn đọc được mình đã giao gì, lúc nào. Không phải
 *     để can thiệp tiếp, mà để còn đối chiếu khi có khiếu nại về chặng mình từng dẫn.
 */
export default function GuideHandovers() {
  const [notes, setNotes] = useState<GuideHandoverNote[]>([]);
  const [requests, setRequests] = useState<GuideHandoverRequestRow[]>([]);
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);

  const [creating, setCreating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [form, setForm] = useState({ tour_schedule_id: "", reason: "", group_state: "" });

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      const [bienBan, yeuCau, myTours] = await Promise.all([
        guideService.getMyHandovers(),
        guideService.getMyHandoverRequests(),
        guideService.getMyTours(),
      ]);
      setNotes(bienBan);
      setRequests(yeuCau);
      setTours(myTours);
    } catch (err) {
      console.error("Lỗi tải bàn giao:", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  /** Chỉ chuyến đang đi hoặc đã chốt mới có đoàn để bàn giao. */
  const chuyenCoThe = useMemo(
    () =>
      tours.flatMap((tour) =>
        (tour.schedules ?? [])
          .filter((sc) => sc.status === "in_progress" || sc.status === "confirmed")
          .map((sc) => ({
            id: sc.id,
            label: `#${sc.id} · ${tour.title} · ${formatDateTime(sc.start_date)}`,
          })),
      ),
    [tours],
  );

  const guiYeuCau = async () => {
    if (!form.tour_schedule_id) return;

    setSaving(true);
    setError("");

    try {
      const message = await guideService.requestHandover(Number(form.tour_schedule_id), {
        reason: form.reason.trim(),
        group_state: form.group_state.trim(),
      });

      setNotice(message);
      setCreating(false);
      setForm({ tour_schedule_id: "", reason: "", group_state: "" });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không gửi được yêu cầu.");
    } finally {
      setSaving(false);
    }
  };

  const xacNhan = async (id: number) => {
    try {
      await guideService.acknowledgeHandover(id);
      loadData();
    } catch (err) {
      console.error("Lỗi xác nhận bàn giao:", err);
    }
  };

  const rutLai = async (id: number) => {
    try {
      await guideService.withdrawHandoverRequest(id);
      loadData();
    } catch (err) {
      console.error("Lỗi rút yêu cầu:", err);
    }
  };

  const nhan = notes.filter((n) => n.direction === "received");
  const giao = notes.filter((n) => n.direction === "given");

  const the = (note: GuideHandoverNote, nhanDoan: boolean) => (
    <div
      key={note.id}
      className={`rounded-xl border p-4 space-y-2 ${
        nhanDoan ? "border-primary-200 bg-primary-50/40" : "border-gray-200 bg-white"
      }`}
    >
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-bold text-gray-900">{note.tour_title}</span>
        <span className="text-xs text-gray-500">
          chuyến #{note.tour_schedule_id} · khởi hành {formatDateTime(note.start_date)}
        </span>
        <span className="ml-auto flex items-center gap-1 text-xs text-gray-500">
          <Clock className="h-3 w-3" />
          {formatDateTime(note.handed_over_at)}
        </span>
      </div>

      <p className="flex flex-wrap items-center gap-1.5 text-xs text-gray-600">
        <span className="font-semibold">{note.from_guide_name}</span>
        <ArrowRight className="h-3 w-3 text-gray-400" />
        <span className="font-semibold">{note.to_guide_name}</span>
        {!nhanDoan && note.to_guide_phone && (
          <span className="flex items-center gap-1 text-gray-500">
            <Phone className="h-3 w-3" />
            {note.to_guide_phone}
          </span>
        )}
      </p>

      <p className="text-xs text-gray-600">
        <span className="font-semibold">Lý do:</span> {note.reason}
      </p>

      <div className="rounded-lg bg-white/80 p-3 border border-gray-200">
        <p className="text-[11px] font-bold uppercase tracking-wider text-gray-500">
          Tình trạng đoàn lúc bàn giao
        </p>
        <p className="mt-1 text-sm text-gray-800">{note.handover_note}</p>
      </div>

      {/*
        Xác nhận chỉ có ở chiều nhận, và chỉ là bằng chứng đã đọc — đoàn đã thuộc về bạn từ lúc
        điều hành bấm, không chờ nút này. Không kham nổi thì gửi yêu cầu bàn giao của chính mình.
      */}
      {nhanDoan &&
        (note.acknowledged_at ? (
          <p className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
            <Check className="h-3.5 w-3.5" />
            Đã xác nhận lúc {formatDateTime(note.acknowledged_at)}
          </p>
        ) : (
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => xacNhan(note.id)}
              className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700"
            >
              Đã đọc, tôi tiếp nhận
            </button>
            <span className="text-[11px] text-gray-500">
              Đoàn đã thuộc về bạn rồi. Bấm để điều hành biết bạn đã nắm được tình hình.
            </span>
          </div>
        ))}
    </div>
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Bàn giao đoàn</h1>
          <p className="text-sm text-gray-500 mt-1">
            Xin được thay khi bạn không dẫn tiếp được, và đọc lại các lần bàn giao. Bạn không chọn
            người thay — điều hành cử, vì việc đó cần nhìn toàn bộ lịch công ty.
          </p>
        </div>

        {!creating && (
          <button
            type="button"
            onClick={() => setCreating(true)}
            disabled={chuyenCoThe.length === 0}
            className="flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-40 shrink-0"
          >
            <Plus className="h-4 w-4" />
            Xin bàn giao
          </button>
        )}
      </div>

      {notice && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {notice}
        </div>
      )}

      {/* Gửi yêu cầu — không có ô chọn người thay, và đó là chủ ý */}
      {creating && (
        <div className="rounded-xl border border-gray-200 bg-white p-5 space-y-3 shadow-sm">
          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">Chuyến</label>
            <select
              value={form.tour_schedule_id}
              onChange={(e) => setForm((truoc) => ({ ...truoc, tour_schedule_id: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
            >
              <option value="">Chọn chuyến</option>
              {chuyenCoThe.map((sc) => (
                <option key={sc.id} value={sc.id}>
                  {sc.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">
              Vì sao bạn cần được thay
            </label>
            <input
              value={form.reason}
              onChange={(e) => setForm((truoc) => ({ ...truoc, reason: e.target.value }))}
              placeholder="VD: Tôi bị sốt cao từ sáng, không dẫn tiếp được..."
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
            />
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">Tình trạng đoàn</label>
            <textarea
              rows={3}
              value={form.group_state}
              onChange={(e) => setForm((truoc) => ({ ...truoc, group_state: e.target.value }))}
              placeholder="Đoàn đang ở đâu, đã điểm danh tới chặng nào, khách nào cần để ý, chiều còn lịch gì..."
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
            />
            <p className="mt-1 text-[11px] text-gray-400">
              Ít nhất 20 ký tự. Chỉ bạn biết những điều này, và người nhận đoàn chỉ có đúng đoạn
              bạn viết để bắt nhịp.
            </p>
          </div>

          {error && (
            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{error}</p>
          )}

          <p className="text-[11px] text-gray-500">
            Bạn vẫn phụ trách đoàn cho tới khi điều hành duyệt và cử người thay.
          </p>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => setCreating(false)}
              disabled={saving}
              className="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
              Bỏ qua
            </button>
            <button
              type="button"
              onClick={guiYeuCau}
              disabled={
                saving ||
                !form.tour_schedule_id ||
                form.reason.trim().length < 10 ||
                form.group_state.trim().length < 20
              }
              className="rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-40"
            >
              {saving ? "Đang gửi..." : "Gửi cho điều hành"}
            </button>
          </div>
        </div>
      )}

      {/* Yêu cầu của mình */}
      {requests.length > 0 && (
        <div className="space-y-2">
          <h2 className="text-sm font-bold text-gray-900">Yêu cầu của bạn</h2>
          {requests.map((yc) => (
            <div key={yc.id} className="rounded-xl border border-gray-200 bg-white p-3 text-xs space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-bold text-gray-900">
                  {yc.tour_title} · chuyến #{yc.tour_schedule_id}
                </span>
                <span
                  className={`rounded px-2 py-0.5 font-semibold ${
                    yc.status === "pending"
                      ? "bg-amber-50 text-amber-700"
                      : yc.status === "approved"
                        ? "bg-emerald-50 text-emerald-700"
                        : "bg-gray-100 text-gray-600"
                  }`}
                >
                  {yc.status_label}
                </span>

                {yc.status === "pending" && (
                  <button
                    type="button"
                    onClick={() => rutLai(yc.id)}
                    className="ml-auto rounded border border-gray-200 px-2 py-0.5 font-semibold text-gray-700 hover:bg-gray-50"
                  >
                    Rút lại
                  </button>
                )}
              </div>

              <p className="text-gray-600">{yc.reason}</p>

              {yc.review_note && (
                <p className="rounded bg-gray-50 px-2 py-1.5 text-gray-700">
                  <span className="font-semibold">Điều hành trả lời:</span> {yc.review_note}
                </p>
              )}
            </div>
          ))}
        </div>
      )}

      {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

      {!loading && notes.length === 0 && requests.length === 0 && (
        <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
          Chưa có bàn giao nào.
        </p>
      )}

      {nhan.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900">Đoàn bạn nhận ({nhan.length})</h2>
          {nhan.map((note) => the(note, true))}
        </div>
      )}

      {giao.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-bold text-gray-900">Đoàn bạn đã giao ({giao.length})</h2>
          <p className="text-xs text-gray-500">
            Bạn không ghi tiếp được vào những chuyến này, nhưng vẫn xem lại được nội dung đã bàn giao.
          </p>
          {giao.map((note) => the(note, false))}
        </div>
      )}
    </div>
  );
}
