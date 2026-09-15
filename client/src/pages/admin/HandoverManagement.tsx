import {
  Button as AntButton,
  Card as UICard,
  Flex as UIFlex,
  Input as AntInput,
  Modal as AntModal,
  Select as AntSelect,
  Typography as AntTypography,
} from "antd";
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
        adminService.getHandoverHistory(),
      ]);
      setRequests(yeuCau);
      setHistory(lichSu);
    } catch (err) {
      console.error("Lỗi tải bàn giao:", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  /**
   * Đoàn đang trên đường mà chỉ còn một người: chưa bàn giao được.
   *
   * Phải phân công thêm người cho chuyến trước. Không còn lối tắt "nhờ hướng dẫn viên đoàn khác
   * trông hộ" — lối ấy cho một người giữ hai đoàn cùng lúc, phá chính luật trùng lịch hệ thống
   * chặn ở mọi chỗ khác.
   */
  const chuaBanGiaoDuoc = panel?.blocked_needs_second_guide === true;

  const nguoiThayChonDuoc = panel?.available_guides ?? [];

  /*
   * Tách hai nhóm để người chọn thấy được khoảng cách.
   *
   * Đoàn đang ở Hạ Long mà cử người đang rảnh ở Hà Nội thì họ phải đi mấy tiếng mới tới. Vẫn
   * làm được nếu chuyến còn dài, nhưng không phải thứ chọn khi cần người ngay — và màn hình
   * không nói ra thì nhìn vào tưởng chọn ai cũng như nhau.
   */
  const dangNgoaiDuong = nguoiThayChonDuoc.filter((g) => g.leading_other_group);
  const dangRanh = nguoiThayChonDuoc.filter((g) => !g.leading_other_group);

  const dangChay = panel?.schedule.status === "in_progress";

  const openReview = async (yc: PendingHandoverRequest) => {
    setReviewing(yc);
    setPanel(null);
    setNote("");
    setError("");
    setGuideId(0);

    try {
      const data = await adminService.getHandoverPanel(yc.tour_schedule_id);
      setPanel(data);
      setGuideId(data?.available_guides?.[0]?.id ?? 0);
    } catch (err) {
      console.error("Lỗi lấy danh sách người thay:", err);
    }
  };

  /** Chỉ định người mới. Máy chủ đi qua đúng đường bàn giao chung. */
  const banGiao = async () => {
    if (!reviewing || !guideId) return;

    setSaving(true);
    setError("");

    try {
      const message = await adminService.resolveHandoverRequest(
        reviewing.id,
        guideId,
        note.trim() || undefined,
      );

      setReviewing(null);
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không bàn giao được.");
    } finally {
      setSaving(false);
    }
  };

  /** Đóng phiếu mà không đổi ai — gộp cả "không đồng ý" lẫn "hướng dẫn viên đỡ rồi". */
  const dongPhieu = async () => {
    if (!reviewing || note.trim().length < 10) return;

    setSaving(true);
    setError("");

    try {
      const message = await adminService.closeHandoverRequest(reviewing.id, note.trim());

      setReviewing(null);
      setToast({ message, type: "success", isOpen: true });
      loadData();
    } catch (err) {
      const response = (err as { response?: { data?: { message?: string } } })?.response?.data;
      setError(response?.message || "Không đóng phiếu được.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <UIFlex vertical gap="large" ><div>
        <AntTypography.Title level={3} >Bàn giao hướng dẫn viên</AntTypography.Title>
        <p className="text-sm text-gray-500 mt-1">
          Hướng dẫn viên gửi yêu cầu khi không dẫn tiếp được; bạn cử người thay. Họ không tự chọn
          người thay, vì việc đó cần nhìn toàn bộ lịch công ty.
        </p>
      </div>{/* ĐANG CHỜ — phần phải xử lý */}<UIFlex vertical gap={8} ><h2 className="text-sm font-bold text-gray-900">
          Đang chờ xử lý {requests.length > 0 && `(${requests.length})`}
        </h2>{loading && <p className="text-sm text-gray-500">Đang tải...</p>}{!loading && requests.length === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
            Không có yêu cầu nào đang chờ.
          </p>
        )}{requests.map((yc) => (
          <UICard key={yc.id} size="small">
            <UIFlex vertical gap="small">
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
            </div><p className="mt-1.5 text-sm font-semibold text-amber-900">{yc.reason}</p><p className="mt-0.5 text-xs text-gray-700">{yc.group_state}</p>
              <AntButton onClick={() => openReview(yc)}>Xem và xử lý yêu cầu</AntButton>
            </UIFlex>
          </UICard>
        ))}</UIFlex>{/* ĐÃ BÀN GIAO — phần theo dõi */}<UIFlex vertical gap={8} ><h2 className="text-sm font-bold text-gray-900">Đã bàn giao</h2>{!loading && (history?.handovers.length ?? 0) === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
            Chưa có lần bàn giao nào.
          </p>
        )}{history?.handovers.map((bg) => (
          <UICard key={bg.id} ><UIFlex vertical gap="middle"><div className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-semibold text-gray-900">{bg.from_guide?.name}</span>
              <ArrowRight className="h-3.5 w-3.5 text-gray-400" />
              <span className="font-semibold text-gray-900">{bg.to_guide?.name}</span>

              <span className="ml-auto flex items-center gap-1 text-xs text-gray-500">
                <Clock className="h-3 w-3" />
                {formatDateTime(bg.handed_over_at)}
              </span>
            </div><p className="text-xs text-gray-500">
              {bg.tour_title} · chuyến #{bg.tour_schedule_id}
              {bg.created_by_name ? ` · ${bg.created_by_name} thực hiện` : ""}
              {bg.to_guide?.phone && (
                <span className="ml-1">
                  · {bg.to_guide.name}: {bg.to_guide.phone}
                </span>
              )}
            </p><p className="text-xs text-gray-700">
              <span className="font-semibold">Lý do:</span> {bg.reason}
            </p><p className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
              {bg.handover_note}
            </p></UIFlex></UICard>
        ))}</UIFlex>{/* Duyệt một yêu cầu */}{reviewing && (
        <AntModal open title={<>
                Yêu cầu bàn giao — chuyến #{reviewing.tour_schedule_id}
              </>} width={720} onCancel={() => setReviewing(null)} closable={!(saving)} keyboard={!(saving)} mask={{ closable: false }} footer={null} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}><UIFlex vertical gap="middle">
            <div>

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

            {chuaBanGiaoDuoc && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p className="font-semibold">Chưa bàn giao được.</p>
                <p className="text-xs mt-0.5">
                  Đoàn đang trên đường và chuyến này chỉ có một hướng dẫn viên — chính người đang
                  xin. Gỡ họ ra thì đoàn không có ai cho tới khi người mới tới nơi. Sang trang quản
                  lý chuyến <strong>phân công thêm một người</strong>, rồi quay lại đây. Đóng phiếu
                  cũng được, nhưng nhớ ghi lý do để họ biết đường xoay xở.
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
                  Không còn hướng dẫn viên nào khác đang hoạt động. Đóng phiếu kèm lý do để người
                  xin biết đường xoay xở.
                </p>
              ) : (
                <>
                  <AntSelect showSearch={{ optionFilterProp: "label" }} value={String(guideId ?? "")} onChange={(id) => setGuideId(Number(id))} style={{ width: "100%" }}
                    options={dangChay ? [
                      { label: "Đang dẫn đoàn khác — tới ngay được", options: dangNgoaiDuong.map((g) => ({ value: String(g.id), label: g.name })) },
                      { label: "Đang rảnh — phải di chuyển tới chỗ đoàn", options: dangRanh.map((g) => ({ value: String(g.id), label: g.name })) },
                    ].filter((group) => group.options.length > 0) : [{ label: "Hướng dẫn viên có thể thay", options: nguoiThayChonDuoc.map((g) => ({ value: String(g.id), label: g.name })) }]} />

                  {/* Chọn người đang rảnh cho đoàn đang đi: nói rõ họ chưa có mặt */}
                  {dangChay && dangRanh.some((g) => g.id === guideId) && (
                    <p className="mt-1.5 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
                      Người này không dẫn đoàn nào lúc này, nên có thể đang ở xa và phải di chuyển
                      tới chỗ đoàn.
                      {panel?.hours_remaining !== null && panel?.hours_remaining !== undefined && (
                        <> Đoàn còn khoảng {panel.hours_remaining} giờ nữa là kết thúc.</>
                      )}{" "}
                      Cần người có mặt ngay thì chọn nhóm trên.
                    </p>
                  )}
                </>
              )}
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Ghi chú trả lời</label>
              <AntInput.TextArea rows={2} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Không bắt buộc khi duyệt. Bắt buộc khi từ chối, và người xin sẽ đọc được." style={{ width: "100%" }} />
            </div>

            {error && (
              <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {error}
              </div>
            )}

            <UIFlex   wrap  justify="end" gap={8}><AntButton htmlType="button" onClick={() => setReviewing(null)} disabled={saving}>Để sau
              </AntButton><AntButton htmlType="button" onClick={dongPhieu} disabled={saving || note.trim().length < 10} danger>Từ chối
              </AntButton><AntButton htmlType="button" onClick={banGiao} disabled={saving || chuaBanGiaoDuoc || !guideId} type="primary">{saving ? "Đang xử lý..." : "Bàn giao"}</AntButton></UIFlex>
          </UIFlex></AntModal>
      )}<Toast
        message={toast.message}
        type={toast.type}
        isOpen={toast.isOpen}
        onClose={() => setToast((truoc) => ({ ...truoc, isOpen: false }))}
      /></UIFlex>
  );
}
