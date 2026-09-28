import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { Typography, Alert, Button, Card, Col, DatePicker, Form, Image, Input, Modal, Row, Select, Skeleton, Tag, Upload } from "antd";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, Camera, Clock, Plus } from "lucide-react";
import guideService, {
  INCIDENT_SEVERITIES,
  INCIDENT_TYPES,
} from "@/services/guideService";
import type { GuideIncident } from "@/services/guideService";
import type { Tour } from "@/types";
import { formatDateTime } from "@/utils/format";
import dayjs from "dayjs";

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

const severityColor: Record<string, string> = { low: "default", medium: "warning", high: "error" };

export default function GuideIncidents() {
  const feedback = useGuideFeedback();
  const [incidents, setIncidents] = useState<GuideIncident[]>([]);
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState(false);

  const [creating, setCreating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [uploadingId, setUploadingId] = useState<number | null>(null);

  const [form, setForm] = useState({
    tour_schedule_id: "",
    type: "weather",
    severity: "medium",
    occurred_at: toDateTimeLocal(new Date()),
    description: "",
  });

  const loadData = useCallback(() => {
    feedback.clearLoadError("Chưa tải được danh sách sự cố");
    return Promise.all([guideService.getMyIncidents(), guideService.getMyTours()])
      .then(result => {
        setIncidents(result[0]);
        setTours(result[1]);
        setPageError(false);
      })
      .catch(err => {
        setPageError(true);
        feedback.loadError(err, "Chưa tải được danh sách sự cố");
      })
      .finally(() => setLoading(false));
  }, [feedback]);

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
            (sc) => (sc.effective_status ?? sc.status) === "in_progress" || (sc.effective_status ?? sc.status) === "completed",
          )
          .map((sc) => ({
            id: sc.id,
            label: `#${sc.id} · ${tour.title} · ${formatDateTime(sc.start_date)}`,
          })),
      ),
    [tours],
  );

  const submit = async () => {
    if (saving || !form.tour_schedule_id || !form.occurred_at || form.description.trim().length < 20) return;
    if (dayjs(form.occurred_at).isAfter(dayjs())) { feedback.error(null, "Thời điểm xảy ra không được ở tương lai."); return; }

    setSaving(true);

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

      feedback.success(message);
      setCreating(false);
      setForm((truoc) => ({ ...truoc, description: "" }));
      await loadData();
    } catch (err) {
      feedback.error(err, "Chưa gửi được báo cáo sự cố. Nội dung đã nhập vẫn được giữ lại.");
    } finally {
      setSaving(false);
    }
  };

  const uploadPhoto = async (incidentId: number, file: File) => {
    if (uploadingId !== null) return;
    setUploadingId(incidentId);
    setPageError(false);
    try {
      await guideService.uploadIncidentPhoto(incidentId, file);
      feedback.success("Đã thêm ảnh hiện trường.");
      await loadData();
    } catch (err) {
      feedback.error(err, "Chưa tải được ảnh hiện trường. Vui lòng thử lại.");
    } finally {
      setUploadingId(null);
    }
  };

  return (
    <div className="space-y-6">
      {pageError && <Button onClick={() => { setLoading(true); void loadData(); }} loading={loading}>Tải lại dữ liệu</Button>}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <Typography.Title level={3} style={{ margin: 0 }}>
            Báo sự cố
          </Typography.Title>
          <p className="text-sm text-gray-500 mt-1">
            Gửi sự việc, ảnh hiện trường và theo dõi phản hồi của điều hành.
          </p>
        </div>

        {!creating && (
          <Button type="primary" danger onClick={() => { setCreating(true); }} disabled={chuyenDangDi.length === 0}>
            <Plus className="h-4 w-4" />
            Báo sự cố
          </Button>
        )}
      </div>

      {chuyenDangDi.length === 0 && !pageError && !loading && (
        <p className="rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-gray-500">
          Bạn không có chuyến đang đi hoặc đã kết thúc. Sự cố chỉ báo được khi đoàn
          đã lên đường; chuyến chưa đi mà có vấn đề thì báo điều hành để hủy
          hoặc dời lịch.
        </p>
      )}

      {/* Biểu mẫu báo cáo — không có ô tiền nào, và đó là chủ ý */}
      {creating && (
        <Modal open title="Báo sự cố với điều hành" onCancel={() => setCreating(false)} closable={!saving} keyboard={!saving} mask={{ closable: false }} cancelButtonProps={{ disabled: saving }} onOk={submit} confirmLoading={saving} okText="Gửi cho điều hành" cancelText="Bỏ qua" okButtonProps={{ disabled: !form.tour_schedule_id || !form.occurred_at || form.description.trim().length < 20 }} width={640}>
          <Form layout="vertical" disabled={saving}>
            <Form.Item htmlFor="GuideIncidents-field-1" label="Chuyến đang đi hoặc đã kết thúc" required><Select id="GuideIncidents-field-1" showSearch optionFilterProp="label" placeholder="Chọn chuyến" value={form.tour_schedule_id || undefined} onChange={value => setForm(prev => ({ ...prev, tour_schedule_id: value }))} options={chuyenDangDi.map(sc => ({ value: String(sc.id), label: sc.label }))} /></Form.Item>
            <Row gutter={16}><Col xs={24} sm={12}><Form.Item htmlFor="GuideIncidents-field-2" label="Loại sự cố" required><Select id="GuideIncidents-field-2" value={form.type} options={[...INCIDENT_TYPES]} onChange={value => setForm(prev => ({ ...prev, type: value }))} /></Form.Item></Col>
              <Col xs={24} sm={12}><Form.Item htmlFor="GuideIncidents-field-3" label="Mức nghiêm trọng" required><Select id="GuideIncidents-field-3" value={form.severity} options={[...INCIDENT_SEVERITIES]} onChange={value => setForm(prev => ({ ...prev, severity: value }))} /></Form.Item></Col></Row>
            <Form.Item htmlFor="GuideIncidents-field-4" label="Xảy ra lúc" required><DatePicker id="GuideIncidents-field-4" showTime={{ format: "HH:mm" }} format="DD/MM/YYYY HH:mm" maxDate={dayjs()} value={form.occurred_at ? dayjs(form.occurred_at) : null} onChange={value => setForm(prev => ({ ...prev, occurred_at: value ? value.format("YYYY-MM-DDTHH:mm") : "" }))} style={{ width: "100%" }} /></Form.Item>
            <Form.Item htmlFor="GuideIncidents-field-5" label="Diễn biến" required extra="Ít nhất 20 ký tự. Mô tả hiện trường và ảnh hưởng đến đoàn để điều hành quyết định phương án."><Input.TextArea id="GuideIncidents-field-5" rows={4} value={form.description} onChange={event => setForm(prev => ({ ...prev, description: event.target.value }))} placeholder="Ví dụ: Bão vào đất liền, tàu không ra đảo được, đoàn phải ở lại bờ thêm một đêm..." /></Form.Item>
          </Form>
        </Modal>
      )}

      {/* Danh sách đã báo */}
      <div className="space-y-3">
        {loading && <Skeleton active />}

        {!loading && !pageError && incidents.length === 0 && (
          <p className="text-sm text-gray-500">Chưa có sự cố nào được báo.</p>
        )}

        {incidents.map((sc) => (
          <Card key={sc.id}><div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <Tag color={severityColor[sc.severity] ?? "default"}>{sc.severity_label}</Tag>
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
              <p className="flex items-center gap-1 text-xs text-amber-700">
                <AlertTriangle className="h-3 w-3" />
                Ghi bù: báo muộn hơn 6 tiếng so với lúc xảy ra.
              </p>
            )}

            <p className="text-sm text-gray-700">{sc.description}</p>

            {/* Phương án của điều hành, chỉ đọc */}
            {sc.resolution ? (
              <Alert showIcon type="success" title="Phương án của điều hành — đọc cho khách" description={sc.resolution} />
            ) : (
              <p className="text-xs text-gray-400">{sc.status_label}</p>
            )}

            <div className="flex flex-wrap items-center gap-2">
              {sc.photos.map((anh) => (
                <Image key={anh.id} src={anh.image_path} alt={anh.caption ?? "Ảnh hiện trường"} width={80} height={80} style={{ objectFit: "cover", borderRadius: 8 }} />
              ))}

              <Upload accept="image/*" showUploadList={false} disabled={uploadingId !== null} beforeUpload={file => { void uploadPhoto(sc.id, file); return false; }}><Button icon={<Camera size={16} />} loading={uploadingId === sc.id} disabled={uploadingId !== null}>Thêm ảnh</Button></Upload>
            </div>
          </div></Card>
        ))}
      </div>
    </div>
  );
}
