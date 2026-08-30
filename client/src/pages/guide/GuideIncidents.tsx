import { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, Camera, Clock, Plus } from "lucide-react";
import guideService, {
  INCIDENT_SEVERITIES,
  INCIDENT_TYPES,
} from "@/services/guideService";
import type { GuideIncident } from "@/services/guideService";
import type { Tour } from "@/types";
import { formatDateTime } from "@/utils/format";
import { DateTimePicker } from "@/components/DateTimePicker";

/**
 * O - Hướng dẫn viên báo cáo sự cố tại hiện trường.
 *
 * Màn này **cố ý không có ô nhập tiền nào**. Không phải quên: người đang đứng giữa đoàn khách mệt
 * và bực không nên là người quyết mức thu, và cũng không nên là người phải nói con số đó ra. Điều
 * hành quyết ở màn riêng, rồi phương án hiện ngược lại ở đây để hướng dẫn viên đọc cho khách.
 *
 * Xem docs/nghiep-vu/04-luong-dieu-hanh.md mục 6.
 */

const toDateTimeLocal = (d: Date) => {
  const p = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
};

const severityClass: Record<string, string> = {
  low: "bg-gray-100 text-gray-700",
  medium: "bg-amber-50 text-amber-700",
  high: "bg-rose-50 text-rose-700",
};

export default function GuideIncidents() {
  const [incidents, setIncidents] = useState<GuideIncident[]>([]);
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);

  const [creating, setCreating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const [form, setForm] = useState({
    tour_schedule_id: "",
    type: "weather",
    severity: "medium",
    occurred_at: toDateTimeLocal(new Date()),
    description: "",
  });

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      const [ds, myTours] = await Promise.all([
        guideService.getMyIncidents(),
        guideService.getMyTours(),
      ]);
      setIncidents(ds);
      setTours(myTours);
    } catch (err) {
      console.error("Lỗi tải sự cố:", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  /**
   * Chỉ chuyến đã lên đường mới báo được sự cố dọc đường.
   *
   * Chuyến chưa đi mà có vấn đề thì đó là chuyện của luồng hủy chuyến hoặc dời lịch. Máy chủ cũng
   * chặn, nhưng lọc sẵn ở đây thì người dùng khỏi chọn rồi mới bị từ chối.
   */
  const chuyenDangDi = useMemo(
    () =>
      tours.flatMap((tour) =>
        (tour.schedules ?? [])
          .filter(
            (sc) => sc.status === "in_progress" || sc.status === "completed",
          )
          .map((sc) => ({
            id: sc.id,
            label: `#${sc.id} · ${tour.title} · ${formatDateTime(sc.start_date)}`,
          })),
      ),
    [tours],
  );

  const submit = async () => {
    if (!form.tour_schedule_id) return;

    setSaving(true);
    setError("");

    try {
      const { message } = await guideService.reportIncident(
        Number(form.tour_schedule_id),
        {
          type: form.type,
          severity: form.severity,
          occurred_at: form.occurred_at.replace("T", " ") + ":00",
          description: form.description.trim(),
        },
      );

      setNotice(message);
      setCreating(false);
      setForm((truoc) => ({ ...truoc, description: "" }));
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })
        ?.response?.data;
      setError(response?.message || "Không gửi được báo cáo.");
    } finally {
      setSaving(false);
    }
  };

  const uploadPhoto = async (incidentId: number, file: File) => {
    try {
      await guideService.uploadIncidentPhoto(incidentId, file);
      loadData();
    } catch (err) {
      console.error("Lỗi tải ảnh:", err);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
            Chi phí phát sinh
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Báo lại những gì đang xảy ra với đoàn. Bạn không cần và không được
            quyết mức tiền — điều hành sẽ đưa phương án về đây cho bạn đọc cho
            khách.
          </p>
        </div>

        {!creating && (
          <button
            type="button"
            onClick={() => setCreating(true)}
            disabled={chuyenDangDi.length === 0}
            className="flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-40 shrink-0"
          >
            <Plus className="h-4 w-4" />
            Báo sự cố
          </button>
        )}
      </div>

      {notice && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {notice}
        </div>
      )}

      {chuyenDangDi.length === 0 && !loading && (
        <p className="rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-gray-500">
          Bạn không có chuyến nào đang đi. Sự cố dọc đường chỉ báo được khi đoàn
          đã lên đường; chuyến chưa đi mà có vấn đề thì báo điều hành để hủy
          hoặc dời lịch.
        </p>
      )}

      {/* Biểu mẫu báo cáo — không có ô tiền nào, và đó là chủ ý */}
      {creating && (
        <div className="rounded-xl border border-gray-200 bg-white p-5 space-y-3 shadow-sm">
          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">
              Chuyến đang đi
            </label>
            <select
              value={form.tour_schedule_id}
              onChange={(e) =>
                setForm((truoc) => ({
                  ...truoc,
                  tour_schedule_id: e.target.value,
                }))
              }
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
            >
              <option value="">Chọn chuyến</option>
              {chuyenDangDi.map((sc) => (
                <option key={sc.id} value={sc.id}>
                  {sc.label}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Loại sự cố
              </label>
              <select
                value={form.type}
                onChange={(e) =>
                  setForm((truoc) => ({ ...truoc, type: e.target.value }))
                }
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
              >
                {INCIDENT_TYPES.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Mức nghiêm trọng
              </label>
              <select
                value={form.severity}
                onChange={(e) =>
                  setForm((truoc) => ({ ...truoc, severity: e.target.value }))
                }
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
              >
                {INCIDENT_SEVERITIES.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Xảy ra lúc
              </label>
              {/* `maxDate` là hôm nay: sự cố đã xảy ra rồi mới có người ngồi báo cáo nó. */}
              <DateTimePicker
                withTime
                maxDate={new Date()}
                value={form.occurred_at}
                onChange={(giaTri) =>
                  setForm((truoc) => ({
                    ...truoc,
                    occurred_at: giaTri,
                  }))
                }
                placeholder="Chọn thời điểm xảy ra"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">
              Diễn biến
            </label>
            <textarea
              rows={3}
              value={form.description}
              onChange={(e) =>
                setForm((truoc) => ({ ...truoc, description: e.target.value }))
              }
              placeholder="VD: Bão vào đất liền, tàu không ra đảo được, đoàn phải ở lại bờ thêm một đêm..."
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-rose-400"
            />
            <p className="mt-1 text-[11px] text-gray-400">
              Ít nhất 20 ký tự. Điều hành ở xa và chỉ có mô tả này để quyết
              phương án — viết như đang kể cho người không nhìn thấy hiện
              trường.
            </p>
          </div>

          {error && (
            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
              {error}
            </p>
          )}

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
              onClick={submit}
              disabled={
                saving ||
                !form.tour_schedule_id ||
                form.description.trim().length < 20
              }
              className="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-40"
            >
              {saving ? "Đang gửi..." : "Gửi cho điều hành"}
            </button>
          </div>
        </div>
      )}

      {/* Danh sách đã báo */}
      <div className="space-y-3">
        {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

        {!loading && incidents.length === 0 && (
          <p className="text-sm text-gray-500">Chưa có sự cố nào được báo.</p>
        )}

        {incidents.map((sc) => (
          <div
            key={sc.id}
            className="rounded-xl border border-gray-200 bg-white p-4 space-y-2"
          >
            <div className="flex flex-wrap items-center gap-2">
              <span
                className={`rounded px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider ${
                  severityClass[sc.severity] ?? severityClass.low
                }`}
              >
                {sc.severity_label}
              </span>
              <span className="text-sm font-bold text-gray-900">
                {sc.type_label}
              </span>
              <span className="text-xs text-gray-500">{sc.tour_title}</span>

              <span className="ml-auto flex items-center gap-1 text-xs text-gray-500">
                <Clock className="h-3 w-3" />
                {formatDateTime(sc.occurred_at)}
              </span>
            </div>

            {sc.reported_late && (
              <p className="flex items-center gap-1 text-[11px] text-amber-700">
                <AlertTriangle className="h-3 w-3" />
                Ghi bù: báo muộn hơn 6 tiếng so với lúc xảy ra.
              </p>
            )}

            <p className="text-sm text-gray-700">{sc.description}</p>

            {/* Phương án của điều hành, chỉ đọc */}
            {sc.resolution ? (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                <p className="text-[11px] font-bold uppercase tracking-wider text-emerald-800">
                  Phương án của điều hành — đọc cho khách
                </p>
                <p className="mt-1 text-sm text-emerald-900">{sc.resolution}</p>
              </div>
            ) : (
              <p className="text-xs text-gray-400">{sc.status_label}</p>
            )}

            <div className="flex flex-wrap items-center gap-2">
              {sc.photos.map((anh) => (
                <img
                  key={anh.id}
                  src={anh.image_path}
                  alt={anh.caption ?? "Ảnh hiện trường"}
                  className="h-16 w-16 rounded-lg border border-gray-200 object-cover"
                />
              ))}

              <label className="flex h-16 w-16 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-400 hover:border-rose-300 hover:text-rose-500">
                <Camera className="h-4 w-4" />
                <span className="text-[10px]">Thêm ảnh</span>
                <input
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) uploadPhoto(sc.id, file);
                  }}
                />
              </label>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
