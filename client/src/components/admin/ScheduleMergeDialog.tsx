import { Alert, Button, Card, Col, Descriptions, Flex, Form, Input, Modal, Row, Select, Statistic, Typography } from "antd";
import type { ExtendedSchedule } from "@/types";
import type { MergeCandidatesResponse } from "@/services/adminService";
import { formatDateTime } from "@/utils/format";
import dayjs from "dayjs";

interface Props {
  schedules: ExtendedSchedule[];
  sourceId: number;
  data: MergeCandidatesResponse | null;
  loading: boolean;
  targetId: number | null;
  reason: string;
  saving: boolean;
  error: string;
  onSourceChange: (id: number) => void;
  onTargetChange: (id: number | null) => void;
  onReasonChange: (value: string) => void;
  onClose: () => void;
  onConfirm: () => void;
}


export function ScheduleMergeDialog({
  schedules, sourceId, data, loading, targetId, reason, saving, error,
  onSourceChange, onTargetChange, onReasonChange, onClose, onConfirm,
}: Props) {
  const source = schedules.find((item) => item.id === sourceId);
  const eligibleSources = schedules.filter((item) => item.status === "open" || item.id === sourceId);
  const tourOptions = [...new Map(eligibleSources.map((item) => [item.tour_id, item.tour_title])).entries()];
  const sourceOptions = eligibleSources.filter((item) => item.tour_id === source?.tour_id);
  const preview = data?.schedule.id === sourceId && !loading ? data : null;
  const target = preview?.candidates.find((item) => item.schedule_id === targetId);
  const isSameDay = !!target && dayjs(target.start_date).isSame(preview?.schedule.start_date, "day");
  const canConfirm = !!target?.can_merge && !loading && !saving && reason.trim().length >= 10 && reason.trim().length <= 500;

  return <Modal open title="Ghép chuyến" width={1080} onCancel={onClose} closable={!saving} keyboard={!saving}
    mask={{ closable: false }} styles={{ body: { maxHeight: "72vh", overflowY: "auto" } }}
    footer={<Flex justify="space-between" align="center" wrap gap="small">
      <Typography.Text type="secondary">{!target ? "Chọn chuyến nhận để tiếp tục." : reason.trim().length < 10 ? "Nhập lý do ít nhất 10 ký tự." : "Kiểm tra ngày khởi hành và các đơn bị ảnh hưởng."}</Typography.Text>
      <Flex gap="small"><Button disabled={saving} onClick={onClose}>Đóng</Button><Button type="primary" loading={saving} disabled={!canConfirm} onClick={onConfirm}>Xác nhận ghép</Button></Flex>
    </Flex>}>
    <Flex vertical gap="middle">
      <Typography.Paragraph>Chọn chuyến chuyển khách đi và chuyến nhận khách, rồi kiểm tra kết quả trước khi xác nhận.</Typography.Paragraph>
      <Form layout="vertical">
        <Form.Item label="Tour của chuyến nguồn" extra="Có thể ghép khác tour khi trùng thời điểm khởi hành. Đổi tour hoặc chuyến nguồn sẽ xóa lựa chọn chuyến nhận.">
          <Select showSearch optionFilterProp="label" value={source?.tour_id} disabled={saving}
            options={tourOptions.map(([value, label]) => ({ value, label }))}
            onChange={(id) => { const next = eligibleSources.find((item) => item.tour_id === id); if (next) onSourceChange(next.id); }} />
        </Form.Item>
        <Row gutter={[16, 16]}>
          <Col xs={24} md={12}>
            <Card title="1. Chuyến chuyển khách đi">
              <Flex vertical gap="middle">
                <Form.Item label="Chuyến nguồn">
                  <Select value={sourceId} disabled={saving} onChange={onSourceChange}
                    options={sourceOptions.map((item) => ({ value: item.id, label: `#${item.id} · ${formatDateTime(item.start_date)}` }))} />
                </Form.Item>
                <Descriptions column={1} size="small" items={[
                  { key: "date", label: "Khởi hành hiện tại", children: source ? formatDateTime(source.start_date) : "Chưa có thông tin" },
                  { key: "guests", label: "Số khách sẽ chuyển", children: target ? `${target.transferring_guests} khách` : "—" },
                  { key: "orders", label: "Đơn sẽ chuyển", children: target ? `${target.transferring} đơn` : "—" },
                ]} />
                <Alert type="warning" showIcon title="Sau khi ghép, chuyến nguồn chuyển thành Đã hủy." />
              </Flex>
            </Card>
          </Col>
          <Col xs={24} md={12}>
            <Card title="2. Chuyến nhận khách" loading={loading}>
              <Flex vertical gap="middle">
                <Form.Item label="Chuyến đích">
                  <Select value={target?.schedule_id} placeholder="Chọn chuyến nhận khách" disabled={saving || loading || !preview?.candidates.length}
                    onChange={onTargetChange} options={preview?.candidates.map((item) => ({
                      value: item.schedule_id, label: `#${item.schedule_id} · ${formatDateTime(item.start_date)}${item.tour_id !== source?.tour_id ? ` · Khác tour: ${item.tour_title}` : ""}`, disabled: !item.can_merge,
                    }))} />
                </Form.Item>
                <Descriptions column={1} size="small" items={[
                  { key: "date", label: "Khởi hành sau khi ghép", children: target ? formatDateTime(target.start_date) : "Chưa chọn chuyến nhận" },
                  { key: "seats", label: "Chỗ đã đặt / sức chứa", children: target ? `${target.booked_people} / ${target.max_people} ghế` : "—" },
                  { key: "remaining", label: "Chỗ còn trống hiện tại", children: target ? `${target.remaining_seats} ghế` : "—" },
                ]} />
                {preview?.candidates.length === 0 && <Alert type="warning" showIcon title="Không có chuyến phù hợp"
                  description="Hai chuyến phải đang mở bán, chưa qua hạn chốt danh sách và chuyến nhận còn đủ chỗ. Cùng tour được lệch tối đa 2 ngày; khác tour phải trùng thời điểm khởi hành. Hãy chọn chuyến nguồn khác." />}
              </Flex>
            </Card>
          </Col>
        </Row>
      </Form>
      <Card title="Kết quả dự kiến sau khi ghép" aria-live="polite">
        {target ? <Flex vertical gap="middle">
          <Typography.Text>Chuyến #{sourceId} → Chuyến #{target.schedule_id}. Giá của các đơn được chuyển giữ nguyên.</Typography.Text>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={8}><Statistic title="Khách chuyển sang" value={target.transferring_guests} suffix="khách" /><Typography.Text type="secondary">{target.transferring} đơn · {target.transferring_seats} ghế</Typography.Text></Col>
            <Col xs={24} sm={8}><Statistic title="Chỗ trống sau khi nhận khách" value={target.remaining_seats_after} suffix="ghế" /></Col>
            <Col xs={24} sm={8}><Statistic title="Đơn chưa thanh toán bị hủy" value={target.cancelling} suffix="đơn" /></Col>
          </Row>
        </Flex> : <Typography.Text type="secondary">{loading ? "Đang tải thông tin ghép chuyến…" : "Chọn chuyến nhận để xem số đơn, số khách và số ghế sau khi ghép."}</Typography.Text>}
      </Card>
      {target && (isSameDay
        ? <Alert type="info" showIcon title="Ghép chuyến cùng ngày"
            description="Các đơn đã thanh toán và đang chờ thanh toán đều được chuyển. Hệ thống không tự động gửi email; điều hành chịu trách nhiệm thông báo và bảo đảm dịch vụ đã cam kết." />
        : <Alert type="warning" showIcon title="Ghép đổi ngày: chỉ chuyển các đơn đã thanh toán."
            description="Hệ thống gửi email thông báo ngày mới. Khách không đồng ý có quyền yêu cầu hủy và hoàn toàn bộ số tiền đã trả, không tính phí hủy. Đơn đang chờ thanh toán bị hủy sẽ được thông báo và mời đặt lại." />)}
      <Form layout="vertical"><Form.Item label="Lý do ghép chuyến" required extra="Nhập 10–500 ký tự. Khi ghép đổi ngày, lý do này được gửi cho khách trong email thông báo.">
        <Input.TextArea value={reason} disabled={saving} rows={3} maxLength={500} showCount onChange={(event) => onReasonChange(event.target.value)}
          placeholder="Ví dụ: Hai chuyến chưa đủ số khách tối thiểu, công ty sắp xếp ghép về một chuyến." />
      </Form.Item></Form>
      {error && <Alert type="error" showIcon title={error} action={!preview && !loading ? <Button disabled={saving} onClick={() => onSourceChange(sourceId)}>Thử lại</Button> : undefined} />}
    </Flex>
  </Modal>;
}
