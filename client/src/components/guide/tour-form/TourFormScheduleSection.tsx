import { useMemo, useState } from "react";
import { Alert, Button, Card, Col, DatePicker, Empty, Flex, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Table, Tag, TimePicker, Typography } from "antd";
import dayjs from "dayjs";
import type { Dayjs } from "dayjs";
import type { Guide } from "@/types";
import type { ScheduleFormItem } from "./types";
import { GIO_MAC_DINH, GIO_VE_MAC_DINH, SO_KHACH_TOI_DA_MAC_DINH, SO_KHACH_TOI_THIEU_MAC_DINH, daDoiHanChot, hanChotMacDinh, ketThucMacDinh, taoChuyen } from "./formHelpers";
import { scheduleErrors } from "./scheduleValidation";
import { hienThiNgay } from "@/components/date/dateHelpers";

interface Props {
  fieldClass: string;
  items: ScheduleFormItem[];
  numberOfDays: number;
  guidesByUid: Record<string, Guide[]>;
  availabilityLoading: boolean;
  onChange: (next: ScheduleFormItem[]) => void;
}

const dateFormat = "DD/MM/YYYY HH:mm";
const localValue = (date: Dayjs | null) => date?.format("YYYY-MM-DDTHH:mm") ?? "";
const timeValue = (value: string) => value ? dayjs(`2000-01-01T${value}`) : null;
const locked = (item: ScheduleFormItem) => ["in_progress", "completed", "cancelled"].includes(item.status);
const statusText: Record<string, string> = { open: "Chưa chốt", confirmed: "Đã chốt", in_progress: "Đang diễn ra", completed: "Đã kết thúc", cancelled: "Đã hủy" };

function shiftDeparture(item: ScheduleFormItem, start: string, days: number): ScheduleFormItem {
  const endTime = item.end_date?.slice(11, 16) || GIO_VE_MAC_DINH;
  return {
    ...item,
    start_date: start,
    booking_deadline: !item.booking_deadline || item.booking_deadline === hanChotMacDinh(item.start_date)
      ? hanChotMacDinh(start) : item.booking_deadline,
    end_date: !item.end_date || item.end_date === ketThucMacDinh(item.start_date, days, endTime)
      ? ketThucMacDinh(start, days, endTime) : item.end_date,
  };
}

export function TourFormScheduleSection({ items, numberOfDays, guidesByUid, availabilityLoading, onChange }: Props) {
  const [dates, setDates] = useState<Dayjs[]>([]);
  const [startTime, setStartTime] = useState(GIO_MAC_DINH);
  const [endTime, setEndTime] = useState(GIO_VE_MAC_DINH);
  const [target, setTarget] = useState(SO_KHACH_TOI_THIEU_MAC_DINH);
  const [capacity, setCapacity] = useState(SO_KHACH_TOI_DA_MAC_DINH);
  const [expanded, setExpanded] = useState<string[]>([]);
  const [selected, setSelected] = useState<string[]>([]);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [bulk, setBulk] = useState({ start: "", end: "", target: "", capacity: "" });
  const [bulkError, setBulkError] = useState("");
  const [notice, setNotice] = useState("");

  const sorted = useMemo(() => [...items].sort((a, b) => Number(locked(a)) - Number(locked(b)) || a.start_date.localeCompare(b.start_date)), [items]);
  const today = dayjs().startOf("day");
  const existing = new Set(items.map(item => item.start_date));
  const starts = [...new Set(dates.map(date => `${date.format("YYYY-MM-DD")}T${startTime}`))].sort();
  const newStarts = starts.filter(start => !existing.has(start));
  const duplicateCount = starts.length - newStarts.length;
  const newItems = newStarts.map(start => taoChuyen(start, { soNgay: numberOfDays, gioVe: endTime, toiThieu: target, toiDa: capacity }));
  const creationError = !startTime || !endTime ? "Nhập giờ khởi hành và giờ về." :
    !Number.isInteger(numberOfDays) || numberOfDays < 1 ? "Điền số ngày của tour ở bước Thông tin & giá trước." :
    newItems.flatMap(item => Object.values(scheduleErrors(item)))[0];
  const selectedItems = items.filter(item => selected.includes(item.uid) && !locked(item));

  const update = (item: ScheduleFormItem, changes: Partial<ScheduleFormItem>) => {
    if (!locked(item)) onChange(items.map(row => row.uid === item.uid ? { ...row, ...changes } : row));
  };
  const remove = (uids: string[]) => {
    onChange(items.filter(item => !uids.includes(item.uid) || locked(item)));
    setSelected(previous => previous.filter(uid => !uids.includes(uid)));
    setExpanded(previous => previous.filter(uid => !uids.includes(uid)));
  };
  const add = () => {
    if (!newItems.length || creationError) return;
    onChange([...items, ...newItems]);
    setExpanded(newItems.length === 1 ? [newItems[0].uid] : []);
    setNotice(`Đã thêm ${newItems.length} chuyến vào lịch.`);
    setDates([]);
  };
  const applyBulk = () => {
    const next = items.map(item => {
      if (!selected.includes(item.uid) || locked(item)) return item;
      let value = bulk.start ? shiftDeparture(item, `${item.start_date.slice(0, 10)}T${bulk.start}`, numberOfDays) : item;
      if (bulk.end) value = { ...value, end_date: `${value.end_date.slice(0, 10)}T${bulk.end}` };
      return { ...value, ...(bulk.target ? { min_people: bulk.target } : {}), ...(bulk.capacity ? { max_people: bulk.capacity } : {}) };
    });
    const error = next.filter(item => selected.includes(item.uid)).flatMap(item =>
      Object.entries(scheduleErrors(item, next)).filter(([field]) => field !== "booking_deadline_reason").map(([, message]) => `${hienThiNgay(item.start_date, true)}: ${message}`))[0];
    if (error) { setBulkError(error); return; }
    onChange(next);
    setBulkOpen(false);
    const needsReason = next.find(item => selected.includes(item.uid) && daDoiHanChot(item));
    if (needsReason) setExpanded([needsReason.uid]);
    setNotice(`Đã cập nhật ${selectedItems.length} chuyến.`);
  };

  const availableFor = (item: ScheduleFormItem) => (guidesByUid[item.uid] ?? []).filter(guide =>
    !items.some(other => {
      if (other.uid === item.uid || other.status === "cancelled" || !other.guide_ids.includes(String(guide.id))) return false;
      const start = dayjs(item.start_date).startOf("day");
      const end = start.add(Math.max(0, numberOfDays - 1), "day");
      const otherStart = dayjs(other.start_date).startOf("day");
      const otherEnd = otherStart.add(Math.max(0, numberOfDays - 1), "day");
      return !start.isAfter(otherEnd) && !end.isBefore(otherStart);
    }));

  const renderDetails = (item: ScheduleFormItem) => {
    const errors = scheduleErrors(item, items);
    const readOnly = locked(item);
    const guides = availableFor(item);
    const unavailable = item.guide_ids.filter(id => !guides.some(guide => String(guide.id) === id));
    const field = (key: keyof ScheduleFormItem) => ({
      validateStatus: errors[key] ? "error" as const : undefined,
      help: errors[key],
    });
    const datetime = (key: "start_date" | "end_date" | "arrival_at" | "return_departure_at" | "booking_deadline", label: string, required = false) =>
      <Form.Item label={label} required={required} {...field(key)}>
        <DatePicker aria-label={label} showTime={{ format: "HH:mm" }} format={dateFormat} value={item[key] ? dayjs(item[key]) : null}
          style={{ width: "100%" }} placeholder="Chọn ngày và giờ" disabled={readOnly} allowClear={!required}
          minDate={key === "start_date" && !item.id ? today : undefined}
          onChange={date => key === "start_date" ? update(item, shiftDeparture(item, localValue(date), numberOfDays)) : update(item, { [key]: localValue(date) })} />
      </Form.Item>;

    return <Form component={false} layout="vertical">
      {readOnly && <Alert type="info" showIcon title="Chuyến này chỉ được xem." style={{ marginBottom: 16 }} />}
      <Row gutter={[20, 0]}>
        <Col xs={24} md={12}>{datetime("start_date", "Khởi hành", true)}</Col>
        <Col xs={24} md={12}>{datetime("arrival_at", "Tới điểm đến (không bắt buộc)")}</Col>
        <Col xs={24} md={12}>{datetime("return_departure_at", "Khởi hành về (không bắt buộc)")}</Col>
        <Col xs={24} md={12}>{datetime("end_date", "Về tới nơi", true)}</Col>
      </Row>
      <Row gutter={[20, 0]}>
        <Col xs={24} md={12}>
          <Form.Item label="Hạn chốt danh sách" tooltip="Khách cần khai hành khách và thanh toán đủ trước mốc này." {...field("booking_deadline")}>
            <DatePicker showTime={{ format: "HH:mm" }} format={dateFormat} value={item.booking_deadline ? dayjs(item.booking_deadline) : null} disabled={readOnly}
              placeholder="Mặc định trước ngày đi 3 ngày" style={{ width: "100%" }} onChange={date => update(item, { booking_deadline: localValue(date) })} />
          </Form.Item>
        </Col>
        <Col xs={12} md={6}><Form.Item label="Số chỗ tối đa" required {...field("max_people")}><InputNumber min={1} precision={0} style={{ width: "100%" }} value={item.max_people ? Number(item.max_people) : null} disabled={readOnly} onChange={value => update(item, { max_people: value === null ? "" : String(value) })} /></Form.Item></Col>
        <Col xs={12} md={6}><Form.Item label="Số khách mục tiêu" tooltip="Mức dự kiến để tổ chức tour. Ít hơn mức này vẫn có thể khởi hành." required {...field("min_people")}><InputNumber min={1} precision={0} style={{ width: "100%" }} value={item.min_people ? Number(item.min_people) : null} disabled={readOnly} onChange={value => update(item, { min_people: value === null ? "" : String(value) })} /></Form.Item></Col>
      </Row>
      {daDoiHanChot(item) && <Form.Item label="Lý do đổi hạn chốt" required {...field("booking_deadline_reason")}>
        <Input.TextArea rows={2} maxLength={500} value={item.booking_deadline_reason ?? ""} disabled={readOnly} placeholder="Nhập lý do, ít nhất 10 ký tự" onChange={event => update(item, { booking_deadline_reason: event.target.value })} />
      </Form.Item>}
      <Form.Item label="Hướng dẫn viên" tooltip="Có thể phân công sau ở màn quản lý lịch khởi hành.">
        <Select mode="multiple" showSearch optionFilterProp="label" value={item.guide_ids} disabled={readOnly || !item.start_date || availabilityLoading}
          loading={availabilityLoading} placeholder="Chọn hướng dẫn viên (không bắt buộc)" notFoundContent="Không có hướng dẫn viên trống lịch."
          options={[...guides.map(guide => ({ value: String(guide.id), label: guide.name })),
            ...unavailable.map(id => ({ value: id, label: `Hướng dẫn viên #${id} · Cần kiểm tra lịch` }))]}
          onChange={ids => update(item, { guide_ids: ids })} />
      </Form.Item>
      {!!unavailable.length && !availabilityLoading && !readOnly && <Alert type="warning" showIcon title="Có hướng dẫn viên cần kiểm tra lại lịch. Bạn có thể bỏ chọn và phân công sau." />}
    </Form>;
  };

  return <Flex vertical gap="large">
    <Card title="Thêm ngày khởi hành">
      <Form component={false} layout="vertical">
        <Form.Item label="Ngày đi" tooltip="Chọn một hoặc nhiều ngày, sau đó bấm Thêm chuyến.">
          <DatePicker aria-label="Chọn các ngày khởi hành" multiple value={dates} onChange={value => setDates(value ?? [])} minDate={today} format="DD/MM/YYYY" placeholder="Chọn các ngày khởi hành" style={{ width: "100%" }} />
        </Form.Item>
        <Row gutter={[16, 0]}>
          <Col xs={12} md={6}><Form.Item label="Giờ khởi hành"><TimePicker value={timeValue(startTime)} format="HH:mm" style={{ width: "100%" }} allowClear={false} onChange={time => setStartTime(time?.format("HH:mm") ?? "")} /></Form.Item></Col>
          <Col xs={12} md={6}><Form.Item label="Giờ về tới nơi" tooltip={`Ngày về được điền theo tour ${numberOfDays} ngày; có thể sửa riêng từng chuyến.`}><TimePicker value={timeValue(endTime)} format="HH:mm" style={{ width: "100%" }} allowClear={false} onChange={time => setEndTime(time?.format("HH:mm") ?? "")} /></Form.Item></Col>
          <Col xs={12} md={6}><Form.Item label="Số chỗ tối đa"><InputNumber value={capacity ? Number(capacity) : null} min={1} precision={0} style={{ width: "100%" }} onChange={value => setCapacity(value === null ? "" : String(value))} /></Form.Item></Col>
          <Col xs={12} md={6}><Form.Item label="Số khách mục tiêu" tooltip="Mức dự kiến; không phải điều kiện bắt buộc để khởi hành."><InputNumber value={target ? Number(target) : null} min={1} precision={0} style={{ width: "100%" }} onChange={value => setTarget(value === null ? "" : String(value))} /></Form.Item></Col>
        </Row>
      </Form>
      <Flex gap="middle" align="center" wrap style={{ marginTop: 16 }}>
        <Button type="primary" onClick={add} disabled={!newItems.length || !!creationError}>{newItems.length ? `Thêm ${newItems.length} chuyến` : "Thêm chuyến"}</Button>
        {duplicateCount > 0 && <Typography.Text type="secondary">{duplicateCount} ngày đã có chuyến vào giờ này.</Typography.Text>}
        {!!newItems.length && creationError && <Typography.Text type="danger" role="alert">{creationError}</Typography.Text>}
      </Flex>
    </Card>

    <Flex justify="space-between" align="center" wrap gap="small">
      <Typography.Title level={5} style={{ margin: 0 }}>Các chuyến đã thêm ({items.length})</Typography.Title>
      {!!selectedItems.length && <Flex align="center" gap="small" wrap>
        <Typography.Text type="secondary">Đã chọn {selectedItems.length} chuyến</Typography.Text>
        <Button onClick={() => { setBulk({ start: "", end: "", target: "", capacity: "" }); setBulkError(""); setBulkOpen(true); }}>Sửa các chuyến đã chọn</Button>
        <Popconfirm title={`Xóa ${selectedItems.length} chuyến khỏi lịch?`} okText="Xóa" cancelText="Giữ lại" onConfirm={() => remove(selectedItems.map(item => item.uid))}>
          <Button danger>Xóa</Button>
        </Popconfirm>
        <Button type="text" onClick={() => setSelected([])}>Bỏ chọn</Button>
      </Flex>}
    </Flex>
    {notice && <Typography.Text type="secondary" role="status">{notice}</Typography.Text>}
    <Table<ScheduleFormItem> rowKey="uid" dataSource={sorted} size="middle" pagination={false} scroll={{ x: 1000 }}
      locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có ngày khởi hành. Chọn ngày ở phía trên để thêm chuyến." /> }}
      rowSelection={{ selectedRowKeys: selected, onChange: keys => setSelected(keys.map(String)), getCheckboxProps: item => ({ disabled: locked(item) }) }}
      expandable={{ expandedRowKeys: expanded, showExpandColumn: false,
        expandedRowRender: item => <div id={`schedule-details-${item.uid}`}>{renderDetails(item)}</div> }}
      columns={[
        { key: "start", title: "Khởi hành", width: 190, render: (_, item) => <Flex vertical gap={4}><Typography.Text strong>{hienThiNgay(item.start_date, true) || "Chưa chọn ngày"}</Typography.Text>{item.id && <Tag>{statusText[item.status] ?? item.status}</Tag>}{!locked(item) && Object.keys(scheduleErrors(item, items)).length > 0 && <Typography.Text type="danger">Cần kiểm tra ngày giờ / thông tin</Typography.Text>}</Flex> },
        { key: "end", title: "Về tới nơi", width: 175, render: (_, item) => hienThiNgay(item.end_date, true) || "Chưa nhập" },
        { key: "deadline", title: "Hạn chốt", width: 175, render: (_, item) => hienThiNgay(item.booking_deadline || hanChotMacDinh(item.start_date), true) },
        { key: "seats", title: "Số chỗ", width: 100, render: (_, item) => item.max_people },
        { key: "guide", title: "Hướng dẫn viên", width: 140, render: (_, item) => item.guide_ids.length ? `${item.guide_ids.length} người` : "Chưa phân công" },
        { key: "actions", title: "Thao tác", width: 170, fixed: "right", render: (_, item) => <Flex gap="small" align="center">
          <Button size="small" aria-expanded={expanded.includes(item.uid)} aria-controls={`schedule-details-${item.uid}`}
            onClick={() => setExpanded(current => current.includes(item.uid) ? [] : [item.uid])}>
            {expanded.includes(item.uid) ? "Thu gọn" : locked(item) ? "Xem" : "Sửa"}
          </Button>
          {!locked(item) && <Popconfirm title="Xóa chuyến khỏi lịch?" okText="Xóa" cancelText="Giữ lại" onConfirm={() => remove([item.uid])}>
            <Button size="small" type="text" danger>Xóa</Button>
          </Popconfirm>}
        </Flex> },
      ]}
      summary={() => {
        const invalid = items.filter(item => !locked(item) && Object.keys(scheduleErrors(item, items)).length);
        return invalid.length ? <Table.Summary.Row><Table.Summary.Cell index={0} colSpan={7}><Typography.Text type="danger">{invalid.length} chuyến cần kiểm tra lại. Bấm Sửa để xem lỗi tại từng ô.</Typography.Text></Table.Summary.Cell></Table.Summary.Row> : null;
      }}
    />

    <Modal title={`Sửa ${selectedItems.length} chuyến đã chọn`} open={bulkOpen} onCancel={() => setBulkOpen(false)} onOk={applyBulk} okText="Áp dụng" cancelText="Đóng" okButtonProps={{ disabled: !Object.values(bulk).some(Boolean) }}>
      <Typography.Paragraph type="secondary">Chỉ những ô được điền mới áp dụng cho các chuyến đã chọn.</Typography.Paragraph>
      <Form layout="vertical">
        <Row gutter={16}>
          <Col span={12}><Form.Item label="Giờ khởi hành"><TimePicker format="HH:mm" value={timeValue(bulk.start)} style={{ width: "100%" }} onChange={time => setBulk(value => ({ ...value, start: time?.format("HH:mm") ?? "" }))} /></Form.Item></Col>
          <Col span={12}><Form.Item label="Giờ về tới nơi"><TimePicker format="HH:mm" value={timeValue(bulk.end)} style={{ width: "100%" }} onChange={time => setBulk(value => ({ ...value, end: time?.format("HH:mm") ?? "" }))} /></Form.Item></Col>
          <Col span={12}><Form.Item label="Số chỗ tối đa"><InputNumber min={1} precision={0} value={bulk.capacity ? Number(bulk.capacity) : null} style={{ width: "100%" }} onChange={number => setBulk(value => ({ ...value, capacity: number === null ? "" : String(number) }))} /></Form.Item></Col>
          <Col span={12}><Form.Item label="Số khách mục tiêu"><InputNumber min={1} precision={0} value={bulk.target ? Number(bulk.target) : null} style={{ width: "100%" }} onChange={number => setBulk(value => ({ ...value, target: number === null ? "" : String(number) }))} /></Form.Item></Col>
        </Row>
      </Form>
      {bulkError && <Alert type="error" showIcon title={bulkError} />}
    </Modal>
  </Flex>;
}
