import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, ArrowRight, Clock, Phone } from "lucide-react";
import adminService from "@/services/adminService";
import type {
  HandoverHistoryResponse,
  HandoverPanelResponse,
  PendingHandoverRequest,
} from "@/services/adminService";
import { Toast } from "@/components/admin/CustomAlert";
import { formatDateTime } from "@/utils/format";

/**
 * Bàn giao hướng dẫn viên — màn theo dõi và duyệt của điều hành.
 *
 * Trước đó việc duyệt nằm trong một dải báo ở trang quản lý chuyến. Dải đó chỉ hiện khi có yêu
 * cầu, nên lúc không có gì chờ thì không ai biết chức năng này tồn tại. Giờ dải đó chỉ còn dẫn
 * sang đây, và toàn bộ việc duyệt nằm ở một chỗ.
 *
 * Hai phần, theo hai câu hỏi khác nhau:
 *
 *   - **Đang chờ** — ai cần được thay ngay bây giờ. Đây là phần phải xử lý.
 *   - **Đã bàn giao** — gần đây đổi người bao nhiêu lần, và còn ai đang phải trông hai đoàn.
 */
export default function HandoverManagement() {
  const [requests, setRequests] = useState<PendingHandoverRequest[]>([]);
  const [history, setHistory] = useState<HandoverHistoryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [emergencyOnly, setEmergencyOnly] = useState(false);

  const [reviewing, setReviewing] = useState<PendingHandoverRequest | null>(null);
  const [panel, setPanel] = useState<HandoverPanelResponse | null>(null);
  const [guideId, setGuideId] = useState(0);
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const [toast, setToast] = useState({
    message: "",
    type: "success" as "success" | "error",
    isOpen: false,
  });

  const loadData = useCallback(async () => {
    setLoading(true);

    try {
      const [yeuCau, lichSu] = await Promise.all([
        adminService.getPendingHandoverRequests(),
        adminService.getHandoverHistory(emergencyOnly),
      ]);
      setRequests(yeuCau);
      setHistory(lichSu);
    } catch (err) {
      console.error("Lỗi tải bàn giao:", err);
    } finally {
      setLoading(false);
    }
  }, [emergencyOnly]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  /**
   * Đoàn đang trên đường mà chỉ còn một người: chỉ nhờ được hướng dẫn viên đang dẫn đoàn khác.
   * Người ở nhà cách đoàn nhiều giờ, mà đó đúng là quãng đoàn không có ai.
   */
  const canNhoTrongHo = panel?.needs_emergency_cover === true;

  const nguoiThayChonDuoc = (panel?.available_guides ?? []).filter(
    (g) => !canNhoTrongHo || g.leading_other_group,
  );

  const khongCoAiNhoDuoc = canNhoTrongHo && nguoiThayChonDuoc.length === 0;

  const openReview = async (yc: PendingHandoverRequest) => {
    setReviewing(yc);
    setPanel(null);
    setNote("");
    setError("");
    setGuideId(0);

    try {
      const data = await adminService.getHandoverPanel(yc.tour_schedule_id);
      setPanel(data);

      const nhoDuoc = (data?.available_guides ?? []).filter(
        (g) => !data?.needs_emergency_cover || g.leading_other_group,
      );
      setGuideId(nhoDuoc[0]?.id ?? 0);
    } catch (err) {
      console.error("Lỗi lấy danh sách người thay:", err);
    }
  };

  const duyet = async () => {
    if (!reviewing || !guideId) return;

    setSaving(true);
    setError("");

    try {
      const message = await adminService.approveHandoverRequest(
        reviewing.id,
        guideId,
        note.trim() || undefined,
      );

      setReviewing(null);
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không duyệt được.");
    } finally {
      setSaving(false);
    }
  };

  const tuChoi = async () => {
    if (!reviewing || note.trim().length < 10) return;

    setSaving(true);
    setError("");

    try {
      const message = await adminService.rejectHandoverRequest(reviewing.id, note.trim());

      setReviewing(null);
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không từ chối được.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Bàn giao hướng dẫn viên</h1>
        <p className="text-sm text-gray-500 mt-1">
          Hướng dẫn viên gửi yêu cầu khi không dẫn tiếp được; bạn cử người thay. Họ không tự chọn
          người thay, vì việc đó cần nhìn toàn bộ lịch công ty.
        </p>
      </div>

      {/* ĐANG CHỜ — phần phải xử lý */}
      <div className="space-y-2">
        <h2 className="text-sm font-bold text-gray-900">
          Đang chờ xử lý {requests.length > 0 && `(${requests.length})`}
        </h2>

        {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

        {!loading && requests.length === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
            Không có yêu cầu nào đang chờ.
          </p>
        )}

        {requests.map((yc) => (
          <button
            key={yc.id}
            type="button"
            onClick={() => openReview(yc)}
            className="w-full rounded-xl border border-amber-300 bg-amber-50 p-4 text-left hover:bg-amber-100/60 transition-colors"
          >
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <AlertTriangle className="h-4 w-4 text-amber-700" />
              <span className="font-bold text-gray-900">{yc.requester_name}</span>
              {yc.requester_phone && (
                <span className="flex items-center gap-1 text-xs text-gray-600">
                  <Phone className="h-3 w-3" />
                  {yc.requester_phone}
                </span>
              )}
              <span className="text-xs text-gray-500">
                {yc.tour_title} · chuyến #{yc.tour_schedule_id}
              </span>
              <span className="ml-auto text-xs text-gray-500">
                {formatDateTime(yc.created_at)}
              </span>
            </div>

            <p className="mt-1.5 text-sm font-semibold text-amber-900">{yc.reason}</p>
            <p className="mt-0.5 text-xs text-gray-700">{yc.group_state}</p>
          </button>
        ))}
      </div>

      {/* ĐÃ BÀN GIAO — phần theo dõi */}
      <div className="space-y-2">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-sm font-bold text-gray-900">Đã bàn giao</h2>

          {(history?.emergency_count ?? 0) > 0 && (
            <button
              type="button"
              onClick={() => setEmergencyOnly((truoc) => !truoc)}
              className={`rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors ${
                emergencyOnly
                  ? "border-amber-300 bg-amber-50 text-amber-800"
                  : "border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
              }`}
            >
              Chỉ lần nhờ trông hộ ({history?.emergency_count})
            </button>
          )}
        </div>

        {emergencyOnly && (
          <p className="rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
            Mỗi lần nhờ trông hộ là một người đang giữ hai đoàn cùng lúc — biện pháp chữa cháy,
            không phải cách vận hành bình thường. Thu xếp được người khác thì phân công lại.
          </p>
        )}

        {!loading && (history?.handovers.length ?? 0) === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
            Chưa có lần bàn giao nào.
          </p>
        )}

        {history?.handovers.map((bg) => (
          <div key={bg.id} className="rounded-xl border border-gray-200 bg-white p-4 space-y-1.5">
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-semibold text-gray-900">{bg.from_guide?.name}</span>
              <ArrowRight className="h-3.5 w-3.5 text-gray-400" />
              <span className="font-semibold text-gray-900">{bg.to_guide?.name}</span>

              {bg.is_emergency_cover && (
                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">
                  Nhờ trông hộ
                </span>
              )}

              <span className="ml-auto flex items-center gap-1 text-xs text-gray-500">
                <Clock className="h-3 w-3" />
                {formatDateTime(bg.handed_over_at)}
              </span>
            </div>

            <p className="text-xs text-gray-500">
              {bg.tour_title} · chuyến #{bg.tour_schedule_id}
              {bg.created_by_name ? ` · ${bg.created_by_name} thực hiện` : ""}
            </p>

            <p className="text-xs text-gray-700">
              <span className="font-semibold">Lý do:</span> {bg.reason}
            </p>

            <p className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
              {bg.handover_note}
            </p>
          </div>
        ))}
      </div>

      {/* Duyệt một yêu cầu */}
      {reviewing && (
        <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in">
          <div className="bg-white w-full max-w-xl rounded-xl shadow-2xl border border-gray-100 p-6 space-y-4 animate-scale-up max-h-[85vh] overflow-y-auto">
            <div>
              <h4 className="text-base font-bold text-gray-900">
                Yêu cầu bàn giao — chuyến #{reviewing.tour_schedule_id}
              </h4>
              <p className="text-xs text-gray-500 mt-0.5">
                {reviewing.requester_name} gửi lúc {formatDateTime(reviewing.created_at)}
              </p>
            </div>

            <div className="rounded-lg bg-gray-50 p-3 space-y-1.5 text-sm">
              <p>
                <span className="font-semibold">Lý do:</span> {reviewing.reason}
              </p>
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-gray-500">
                  Tình trạng đoàn
                </p>
                <p className="text-gray-800">{reviewing.group_state}</p>
              </div>
            </div>

            {canNhoTrongHo && (
              <div
                className={`rounded-lg border px-4 py-3 text-sm ${
                  khongCoAiNhoDuoc
                    ? "border-rose-200 bg-rose-50 text-rose-800"
                    : "border-amber-200 bg-amber-50 text-amber-900"
                }`}
              >
                <p className="font-semibold">
                  {khongCoAiNhoDuoc
                    ? "Chưa duyệt được yêu cầu này."
                    : "Chuyến chỉ có một người — chính người đang xin."}
                </p>
                <p className="text-xs mt-0.5">
                  {khongCoAiNhoDuoc ? (
                    <>
                      Không có hướng dẫn viên nào đang dẫn đoàn khác cùng lúc để nhờ. Sang trang
                      quản lý chuyến phân công thêm một người, rồi quay lại duyệt. Từ chối cũng
                      được, nhưng nhớ ghi lý do để họ biết đường xoay xở.
                    </>
                  ) : (
                    <>
                      Chỉ nhờ được người <strong>đang dẫn đoàn khác</strong>, vì họ đã ở ngoài
                      đường và tới được ngay. Người đó tạm giữ hai đoàn cho tới khi bạn thu xếp
                      được người khác.
                    </>
                  )}
                </p>
              </div>
            )}

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Cử ai thay <span className="text-rose-500">*</span>
              </label>

              {!panel ? (
                <p className="text-xs text-gray-500">Đang tìm người rảnh...</p>
              ) : nguoiThayChonDuoc.length === 0 ? (
                <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                  {canNhoTrongHo
                    ? "Không có hướng dẫn viên nào đang dẫn đoàn khác để nhờ."
                    : "Không còn hướng dẫn viên nào khác đang hoạt động."}{" "}
                  Từ chối kèm lý do để người xin biết đường xoay xở.
                </p>
              ) : (
                <select
                  value={guideId}
                  onChange={(e) => setGuideId(Number(e.target.value))}
                  className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
                >
                  {nguoiThayChonDuoc.map((g) => (
                    <option key={g.id} value={g.id}>
                      {g.name}
                      {g.leading_other_group ? " — đang dẫn đoàn khác" : ""}
                    </option>
                  ))}
                </select>
              )}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Ghi chú trả lời</label>
              <textarea
                rows={2}
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="Không bắt buộc khi duyệt. Bắt buộc khi từ chối, và người xin sẽ đọc được."
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-400"
              />
            </div>

            {error && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {error}
              </div>
            )}

            <div className="flex flex-wrap justify-end gap-2">
              <button
                type="button"
                onClick={() => setReviewing(null)}
                disabled={saving}
                className="px-4 py-2 text-xs font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl"
              >
                Để sau
              </button>
              <button
                type="button"
                onClick={tuChoi}
                disabled={saving || note.trim().length < 10}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 disabled:opacity-40"
              >
                Từ chối
              </button>
              <button
                type="button"
                onClick={duyet}
                disabled={saving || khongCoAiNhoDuoc || !guideId}
                className="px-4 py-2 text-xs font-semibold text-white rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-40"
              >
                {saving ? "Đang xử lý..." : "Duyệt và bàn giao"}
              </button>
            </div>
          </div>
        </div>
      )}

      <Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((truoc) => ({ ...truoc, isOpen: false }))}
      />
    </div>
  );
}
