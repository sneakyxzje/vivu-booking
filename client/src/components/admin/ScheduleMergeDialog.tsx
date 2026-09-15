import { Alert, Button, Card, Col, Descriptions, Flex, Form, Input, Modal, Row, Select, Statistic, Typography } from "antd";
import type { ExtendedSchedule } from "@/types";
import type { MergeCandidatesResponse } from "@/services/adminService";
import { formatDateTime } from "@/utils/format";

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
  const eligibleSources = schedules.filter((item) => ["open", "closed", "confirmed"].includes(item.status) || item.id === sourceId);
  const tourOptions = [...new Map(eligibleSources.map((item) => [item.tour_id, item.tour_title])).entries()];
  const sourceOptions = eligibleSources.filter((item) => item.tour_id === source?.tour_id);
  const preview = data?.schedule.id === sourceId && !loading ? data : null;
  const target = preview?.candidates.find((item) => item.schedule_id === targetId);
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
        <Form.Item label="Tour cần ghép chuyến" extra="Hai chuyến phải thuộc cùng một tour. Đổi tour hoặc chuyến nguồn sẽ xóa lựa chọn chuyến nhận.">
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
                  { key: "orders", label: "Đơn đã thanh toán sẽ chuyển", children: target ? `${target.transferring} đơn` : "—" },
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
                      value: item.schedule_id, label: `#${item.schedule_id} · ${formatDateTime(item.start_date)}`, disabled: !item.can_merge,
                    }))} />
                </Form.Item>
                <Descriptions column={1} size="small" items={[
                  { key: "date", label: "Khởi hành sau khi ghép", children: target ? formatDateTime(target.start_date) : "Chưa chọn chuyến nhận" },
                  { key: "seats", label: "Chỗ đã đặt / sức chứa", children: target ? `${target.booked_people} / ${target.max_people} ghế` : "—" },
                  { key: "remaining", label: "Chỗ còn trống hiện tại", children: target ? `${target.remaining_seats} ghế` : "—" },
                ]} />
                {preview?.candidates.length === 0 && <Alert type="warning" showIcon title="Không có chuyến phù hợp"
                  description="Hai chuyến phải cùng tour, chưa qua hạn chốt danh sách, lệch không quá 2 ngày và chuyến nhận còn đủ chỗ. Hãy chọn chuyến nguồn khác." />}
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
      <Alert type="warning" showIcon title="Hệ thống thông báo ngày mới cho khách đã thanh toán."
        description="Khách không đồng ý ngày mới có quyền yêu cầu hủy và hoàn toàn bộ số tiền đã trả, không tính phí hủy. Đơn chưa thanh toán bị hủy sẽ được thông báo và mời đặt lại." />
      <Form layout="vertical"><Form.Item label="Lý do ghép chuyến" required extra="Nhập 10–500 ký tự. Khách sẽ đọc được lý do này trong thông báo.">
        <Input.TextArea value={reason} disabled={saving} rows={3} maxLength={500} showCount onChange={(event) => onReasonChange(event.target.value)}
          placeholder="Ví dụ: Hai chuyến chưa đủ số khách tối thiểu, công ty sắp xếp ghép về một chuyến." />
      </Form.Item></Form>
      {error && <Alert type="error" showIcon title={error} action={!preview && !loading ? <Button disabled={saving} onClick={() => onSourceChange(sourceId)}>Thử lại</Button> : undefined} />}
    </Flex>
  </Modal>;
}
