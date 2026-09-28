import { useRef, useState } from "react";
import { Alert, Button, Card, Col, Collapse, Empty, Flex, Form, Input, Popconfirm, Row, Tabs, Typography } from "antd";
import type { ItineraryFormItem } from "./types";
import { danhSoLai } from "./formHelpers";
import { fillItineraryDays } from "./itineraryValidation";
import { CheckpointManager } from "../../admin/CheckpointManager";

interface Props {
  labelClass: string;
  fieldClass: string;
  items: ItineraryFormItem[];
  maxDays: number;
  onChange: (next: ItineraryFormItem[]) => void;
}

const complete = (item: ItineraryFormItem) => Boolean(item.title.trim() && item.content.trim());

export function TourFormItinerarySection({ items: savedItems, maxDays, onChange }: Props) {
  const [activeDay, setActiveDay] = useState(0);
  const editorTop = useRef<HTMLDivElement>(null);
  const dayLimit = Number.isInteger(maxDays) && maxDays > 0 ? maxDays : 1;
  // An already-open form may still contain only day 1 when its duration is longer.
  // Navigation and editing must use the full set of days, including blank ones.
  const items = fillItineraryDays(savedItems, dayLimit);
  const activeIndex = Math.min(activeDay, Math.max(0, items.length - 1));
  const current = items[activeIndex];
  const writtenDays = items.filter(complete).length;

  const update = (changes: Partial<ItineraryFormItem>) =>
    onChange(items.map((item, index) => index === activeIndex ? { ...item, ...changes } : item));

  const goToDay = (index: number) => {
    setActiveDay(Math.max(0, Math.min(index, items.length - 1)));
    editorTop.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const remove = () => {
    onChange(danhSoLai(items.filter((_, index) => index !== activeIndex)));
    setActiveDay(Math.max(0, activeIndex - 1));
  };

  return <Flex ref={editorTop} vertical gap="middle" style={{ scrollMarginTop: 80 }}>
    <Flex justify="space-between" align="center" wrap gap="middle">
      <div>
        <Typography.Title level={4} style={{ margin: 0 }}>Lịch trình từng ngày</Typography.Title>
        <Typography.Text type="secondary">Tour {dayLimit} ngày · Đã viết {writtenDays}/{dayLimit} ngày</Typography.Text>
      </div>
    </Flex>

    {items.length > dayLimit && <Alert type="warning" showIcon title={`Lịch trình có ${items.length} ngày, nhưng tour đang đặt ${dayLimit} ngày. Xóa ngày thừa hoặc sửa thời lượng tour.`} />}

    {current ? <>
      <Tabs activeKey={String(activeIndex)} onChange={key => goToDay(Number(key))} type="card"
        items={items.map((item, index) => ({
          key: String(index),
          label: <span>Ngày {index + 1}{!complete(item) && <Typography.Text type="secondary"> · Chưa viết đủ</Typography.Text>}</span>,
        }))} style={{ marginBottom: -16 }} />

      <Card key={current.id ?? `day-${activeIndex}`} title={`Ngày ${activeIndex + 1}`} extra={items.length > dayLimit &&
        <Popconfirm title={`Xóa ngày ${activeIndex + 1} khỏi lịch trình?`} description="Nội dung và điểm danh của ngày này sẽ được bỏ khỏi biểu mẫu." okText="Xóa ngày" cancelText="Giữ lại" onConfirm={remove}>
          <Button danger type="text">Xóa ngày</Button>
        </Popconfirm>
      }>
        <Form component={false} layout="vertical">
          <Form.Item label="Tiêu đề ngày" htmlFor={`itinerary-${activeIndex}-title`} required>
            <Input id={`itinerary-${activeIndex}-title`} maxLength={255} value={current.title} onChange={event => update({ title: event.target.value })}
              placeholder="Ví dụ: Hà Nội – tham quan vịnh Hạ Long" />
          </Form.Item>
          <Form.Item label="Hoạt động trong ngày" htmlFor={`itinerary-${activeIndex}-content`} required>
            <Input.TextArea id={`itinerary-${activeIndex}-content`} value={current.content} onChange={event => update({ content: event.target.value })}
              autoSize={{ minRows: 7, maxRows: 18 }} placeholder={"Sáng: Đón khách và khởi hành.\nTrưa: Ăn trưa tại nhà hàng.\nChiều: Tham quan các điểm trong chương trình.\nTối: Ăn tối, nhận phòng và nghỉ ngơi."} />
          </Form.Item>
        </Form>

        <Collapse key={activeIndex} defaultActiveKey={[
          ...(current.start_point || current.end_point || current.route_points.length || current.rest_stops ? ["route"] : []),
          ...(current.checkpoints?.length ? ["checkpoints"] : []),
        ]} items={[
          { key: "route", label: "Di chuyển và nghỉ chân", extra: <Typography.Text type="secondary">Không bắt buộc</Typography.Text>,
            children: <Form component={false} layout="vertical">
              <Row gutter={16}>
                <Col xs={24} sm={12}><Form.Item label="Điểm xuất phát trong ngày" htmlFor={`itinerary-${activeIndex}-start`}>
                  <Input id={`itinerary-${activeIndex}-start`} maxLength={255} value={current.start_point} onChange={event => update({ start_point: event.target.value })} placeholder="Ví dụ: Hà Nội" />
                </Form.Item></Col>
                <Col xs={24} sm={12}><Form.Item label="Điểm kết thúc trong ngày" htmlFor={`itinerary-${activeIndex}-end`}>
                  <Input id={`itinerary-${activeIndex}-end`} maxLength={255} value={current.end_point} onChange={event => update({ end_point: event.target.value })} placeholder="Ví dụ: Hạ Long" />
                </Form.Item></Col>
              </Row>
              <Form.Item label="Các nơi đi qua" htmlFor={`itinerary-${activeIndex}-route`} tooltip="Mỗi dòng một địa điểm, theo thứ tự di chuyển.">
                <Input.TextArea id={`itinerary-${activeIndex}-route`} autoSize={{ minRows: 2, maxRows: 6 }} value={current.route_points.join("\n")}
                  onChange={event => update({ route_points: event.target.value ? event.target.value.split("\n") : [] })} placeholder={"Hải Dương\nUông Bí\nBãi Cháy"} />
              </Form.Item>
              <Form.Item label="Nơi nghỉ chân" htmlFor={`itinerary-${activeIndex}-rest`} style={{ marginBottom: 0 }}>
                <Input.TextArea id={`itinerary-${activeIndex}-rest`} rows={2} value={current.rest_stops} onChange={event => update({ rest_stops: event.target.value })} placeholder="Ví dụ: Trạm dừng Sao Đỏ" />
              </Form.Item>
            </Form> },
          { key: "checkpoints", label: `Điểm danh cho hướng dẫn viên${current.checkpoints?.length ? ` (${current.checkpoints.length} điểm)` : ""}`,
            extra: <Typography.Text type="secondary">Không bắt buộc</Typography.Text>,
            children: <CheckpointManager checkpoints={current.checkpoints ?? []} onChange={checkpoints => update({ checkpoints })} /> },
        ]} />

        <Flex justify="space-between" wrap gap="small" style={{ marginTop: 24 }}>
          <Button htmlType="button" disabled={activeIndex === 0} onClick={() => goToDay(activeIndex - 1)}>Ngày trước</Button>
          <Typography.Text type="secondary">Ngày {activeIndex + 1} / {items.length}</Typography.Text>
          <Button htmlType="button" disabled={activeIndex >= items.length - 1} onClick={() => goToDay(activeIndex + 1)}>Ngày tiếp theo</Button>
        </Flex>
      </Card>
    </> : <Card><Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Nhập số ngày của tour ở bước Thông tin & giá để tạo lịch trình." /></Card>}
  </Flex>;
}
