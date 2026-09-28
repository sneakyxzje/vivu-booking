import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { Typography, Alert, Button, Card, Form, Input, Modal, Select, Skeleton, Tag } from "antd";
import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowRight, Clock, Phone, Plus } from "lucide-react";
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
  const feedback = useGuideFeedback();
  const [notes, setNotes] = useState<GuideHandoverNote[]>([]);
  const [requests, setRequests] = useState<GuideHandoverRequestRow[]>([]);
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState(false);

  const [creating, setCreating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ tour_schedule_id: "", reason: "", group_state: "" });

  const loadData = useCallback(() => {
    feedback.clearLoadError("Chưa tải được bàn giao đoàn");
    return Promise.all([guideService.getMyHandovers(), guideService.getMyHandoverRequests(), guideService.getMyTours()])
      .then(result => {
        setNotes(result[0]);
        setRequests(result[1]);
        setTours(result[2]);
        setPageError(false);
      })
      .catch(err => {
        setPageError(true);
        feedback.loadError(err, "Chưa tải được bàn giao đoàn");
      })
      .finally(() => setLoading(false));
  }, [feedback]);

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
    if (saving || !form.tour_schedule_id || form.reason.trim().length < 10 || form.group_state.trim().length < 20) return;

    setSaving(true);

    try {
      const message = await guideService.requestHandover(Number(form.tour_schedule_id), {
        reason: form.reason.trim(),
        group_state: form.group_state.trim(),
      });

      feedback.success(message);
      setCreating(false);
      setForm({ tour_schedule_id: "", reason: "", group_state: "" });
      await loadData();
    } catch (err) {
      feedback.error(err, "Chưa gửi được yêu cầu bàn giao. Nội dung đã nhập vẫn được giữ lại.");
    } finally {
      setSaving(false);
    }
  };

  /*
   * Không còn nút "đã đọc, tôi tiếp nhận" và nút "rút lại yêu cầu".
   *
   * Nút thứ nhất không chặn gì — đoàn đã thuộc về bạn từ lúc điều hành bấm. Nút thứ hai thì đỡ
   * rồi gọi cho điều hành một câu là xong, họ đóng phiếu kèm ghi chú.
   */

  const nhan = notes.filter((n) => n.direction === "received");
  const giao = notes.filter((n) => n.direction === "given");

  const the = (note: GuideHandoverNote, nhanDoan: boolean) => (
    <Card key={note.id}><div className="space-y-3">
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

      <Card ><div>
        <p className="text-xs font-bold uppercase tracking-wider text-gray-500">
          Tình trạng đoàn lúc bàn giao
        </p>
        <p className="mt-1 text-sm text-gray-800">{note.handover_note}</p>
      </div></Card>

      {nhanDoan && (
        <p className="text-xs text-gray-500">
          Đoàn đã thuộc về bạn kể từ thời điểm ghi ở trên.
        </p>
      )}
    </div></Card>
  );

  return (
    <div className="space-y-6">
      {pageError && <Button onClick={() => { setLoading(true); void loadData(); }} loading={loading}>Tải lại dữ liệu</Button>}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <Typography.Title level={3} style={{ margin: 0 }}>Bàn giao đoàn</Typography.Title>
          <p className="text-sm text-gray-500 mt-1">
            Xin được thay khi bạn không dẫn tiếp được, và đọc lại các lần bàn giao. Bạn không chọn
            người thay — điều hành cử, vì việc đó cần nhìn toàn bộ lịch công ty.
          </p>
        </div>

        {!creating && (
          <Button type="primary" onClick={() => { setCreating(true); }} disabled={chuyenCoThe.length === 0}>
            <Plus className="h-4 w-4" />
            Xin bàn giao
          </Button>
        )}
      </div>

      {/* Gửi yêu cầu — không có ô chọn người thay, và đó là chủ ý */}
      {creating && (
        <Modal open title="Yêu cầu bàn giao đoàn" onCancel={() => setCreating(false)} closable={!saving} keyboard={!saving} mask={{ closable: false }} cancelButtonProps={{ disabled: saving }} onOk={guiYeuCau} confirmLoading={saving} okText="Gửi cho điều hành" cancelText="Bỏ qua" okButtonProps={{ disabled: !form.tour_schedule_id || form.reason.trim().length < 10 || form.group_state.trim().length < 20 }}>
          <Form layout="vertical" disabled={saving}>
            <Form.Item htmlFor="GuideHandovers-field-1" label="Chuyến" required><Select id="GuideHandovers-field-1" showSearch optionFilterProp="label" placeholder="Chọn chuyến" value={form.tour_schedule_id || undefined} onChange={value => setForm(prev => ({ ...prev, tour_schedule_id: value }))} options={chuyenCoThe.map(sc => ({ value: String(sc.id), label: sc.label }))} /></Form.Item>
            <Form.Item htmlFor="GuideHandovers-field-2" label="Vì sao bạn cần được thay" required extra="Ít nhất 10 ký tự để điều hành hiểu lý do."><Input id="GuideHandovers-field-2" value={form.reason} onChange={event => setForm(prev => ({ ...prev, reason: event.target.value }))} placeholder="Ví dụ: Tôi bị sốt cao từ sáng, không dẫn tiếp được..." /></Form.Item>
            <Form.Item htmlFor="GuideHandovers-field-3" label="Tình trạng đoàn" required extra="Ít nhất 20 ký tự. Nêu vị trí đoàn, điểm đã điểm danh, khách cần chú ý và lịch trình còn lại."><Input.TextArea id="GuideHandovers-field-3" rows={4} value={form.group_state} onChange={event => setForm(prev => ({ ...prev, group_state: event.target.value }))} /></Form.Item>
          </Form>
          <Alert showIcon type="info" title="Bạn vẫn phụ trách đoàn cho tới khi điều hành duyệt và cử người thay." />
        </Modal>
      )}

      {/* Yêu cầu của mình */}
      {requests.length > 0 && (
        <div className="space-y-2">
          <Typography.Title level={4} style={{ margin: 0 }}>Yêu cầu của bạn</Typography.Title>
          {requests.map((yc) => (
            <Card key={yc.id}><div className="space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-bold text-gray-900">
                  {yc.tour_title} · chuyến #{yc.tour_schedule_id}
                </span>
                {/* Hai trạng thái: đang chờ, hoặc đã xử lý. */}
                <Tag color={yc.status === "pending" ? "warning" : "default"}>{yc.status_label}</Tag>

                {yc.status === "pending" && (
                  <span className="ml-auto text-xs text-gray-500">
                    Đỡ rồi thì gọi điều hành để đóng phiếu.
                  </span>
                )}
              </div>

              <p className="text-gray-600">{yc.reason}</p>

              {yc.review_note && (
                <p className="rounded bg-gray-50 px-2 py-1.5 text-gray-700">
                  <span className="font-semibold">Điều hành trả lời:</span> {yc.review_note}
                </p>
              )}
            </div></Card>
          ))}
        </div>
      )}

      {loading && <Skeleton active />}

      {!loading && !pageError && notes.length === 0 && requests.length === 0 && (
        <p className="rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-500">
          Chưa có bàn giao nào.
        </p>
      )}

      {nhan.length > 0 && (
        <div className="space-y-3">
          <Typography.Title level={4} style={{ margin: 0 }}>Đoàn bạn nhận ({nhan.length})</Typography.Title>
          {nhan.map((note) => the(note, true))}
        </div>
      )}

      {giao.length > 0 && (
        <div className="space-y-3">
          <Typography.Title level={4} style={{ margin: 0 }}>Đoàn bạn đã giao ({giao.length})</Typography.Title>
          <p className="text-xs text-gray-500">
            Bạn không ghi tiếp được vào những chuyến này, nhưng vẫn xem lại được nội dung đã bàn giao.
          </p>
          {giao.map((note) => the(note, false))}
        </div>
      )}
    </div>
  );
}
