import { Button, Card, Checkbox, Col, Flex, Form, Input, Popconfirm, Row, Tooltip, Typography } from "antd";
import { ArrowDown, ArrowUp } from "lucide-react";
import type { CheckpointItem } from "@/components/guide/tour-form/types";
import { checkpointRong } from "@/components/guide/tour-form/formHelpers";

interface Props {
  checkpoints: CheckpointItem[];
  onChange: (checkpoints: CheckpointItem[]) => void;
  labelClass?: string;
  fieldClass?: string;
}

export function CheckpointManager({ checkpoints, onChange }: Props) {
  const update = (index: number, changes: Partial<CheckpointItem>) =>
    onChange(checkpoints.map((item, position) => position === index ? { ...item, ...changes } : item));

  const move = (index: number, offset: number) => {
    const destination = index + offset;
    if (destination < 0 || destination >= checkpoints.length) return;
    const next = [...checkpoints];
    [next[index], next[destination]] = [next[destination], next[index]];
    onChange(next);
  };

  return <Flex vertical gap="middle">
    <Flex justify="space-between" align="center" gap="small" wrap>
      <Typography.Text type="secondary">{checkpoints.length ? `${checkpoints.length} điểm danh trong ngày` : "Chưa thêm điểm danh."}</Typography.Text>
      <Button onClick={() => onChange([...checkpoints, checkpointRong()])}>Thêm điểm danh</Button>
    </Flex>
    {checkpoints.map((point, index) => <Card key={index} size="small" title={`Điểm ${index + 1}`} extra={<Flex gap="small" align="center">
      <Tooltip title="Đưa lên trước"><Button size="small" icon={<ArrowUp size={14} />} aria-label={`Đưa điểm ${index + 1} lên trước`} disabled={index === 0} onClick={() => move(index, -1)} /></Tooltip>
      <Tooltip title="Đưa xuống sau"><Button size="small" icon={<ArrowDown size={14} />} aria-label={`Đưa điểm ${index + 1} xuống sau`} disabled={index === checkpoints.length - 1} onClick={() => move(index, 1)} /></Tooltip>
      <Popconfirm title={`Xóa điểm ${index + 1}?`} okText="Xóa" cancelText="Giữ lại" onConfirm={() => onChange(checkpoints.filter((_, position) => position !== index))}>
        <Button type="text" danger size="small">Xóa</Button>
      </Popconfirm>
    </Flex>}>
      <Form component={false} layout="vertical">
        <Row gutter={16}>
          <Col xs={24} md={12}><Form.Item label="Tên điểm danh" required style={{ marginBottom: 12 }}>
            <Input aria-label={`Tên điểm danh ${index + 1}`} maxLength={255} value={point.name} placeholder="Ví dụ: Cảng Tuần Châu" onChange={event => update(index, { name: event.target.value })} />
          </Form.Item></Col>
          <Col xs={24} md={12}><Form.Item label="Ghi chú cho hướng dẫn viên" style={{ marginBottom: 12 }}>
            <Input aria-label={`Ghi chú điểm danh ${index + 1}`} value={point.description} placeholder="Không bắt buộc" onChange={event => update(index, { description: event.target.value })} />
          </Form.Item></Col>
        </Row>
        <Checkbox checked={point.is_required_photo} onChange={event => update(index, { is_required_photo: event.target.checked })}>Yêu cầu chụp ảnh đoàn tại đây</Checkbox>
      </Form>
    </Card>)}
  </Flex>;
}

export default CheckpointManager;
