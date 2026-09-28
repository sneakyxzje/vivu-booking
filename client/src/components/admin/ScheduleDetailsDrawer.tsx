import { Alert, Button, Collapse, Descriptions, Divider, Drawer, Flex, Tag, Timeline, Typography } from "antd";
import { Link } from "react-router-dom";
import type { ExtendedSchedule } from "@/types";
import { formatDateTime, getEndDate } from "@/utils/format";
import { getScheduleDeadline, getScheduleUnavailableReason } from "@/utils/schedule";
import { scheduleStatusText, scheduleStatus } from "@/utils/scheduleList";
import { ScheduleLifecycleActions } from "./ScheduleLifecycleActions";

interface Props {
  schedule: ExtendedSchedule | null;
  canAdvanceTime: boolean;
  onClose: () => void;
  onReload: () => Promise<void>;
  onFeedback: (message: string, type: "success" | "info") => void;
  onGuides: (schedule: ExtendedSchedule) => void;
  onManifest: (id: number) => void;
  onAttendance: (id: number) => void;
  onDeadline: (schedule: ExtendedSchedule) => void;
  onPropose: (schedule: ExtendedSchedule) => void;
  onConfirm: (schedule: ExtendedSchedule) => void;
  onMerge: (id: number) => void;
  onHandover: (id: number) => void;
  onCancel: (id: number) => void;
}

export function ScheduleDetailsDrawer(props: Props) {
  const { schedule: s, onClose } = props;
  const status = s ? scheduleStatus(s) : undefined;
  const active = s && status !== "completed" && status !== "cancelled";
  const beforeDeparture = status === "open" || status === "confirmed";
  const deadline = s ? getScheduleDeadline(s) : null;
  const act = (callback: () => void) => { onClose(); callback(); };

  return <Drawer open={!!s} onClose={onClose} size={640} title={s ? `Chuyến #${s.id}` : "Chi tiết chuyến"}>
    {s && <Flex vertical gap="large">
      <div>
        <Typography.Title level={4} style={{ marginTop: 0 }}>{s.tour_title}</Typography.Title>
        <Tag>{scheduleStatusText[scheduleStatus(s)]}</Tag>
        {status === "open" && <Typography.Text type="secondary">{getScheduleUnavailableReason(s) || "Đang nhận đặt chỗ"}</Typography.Text>}
        <div style={{ marginTop: 12 }}><Link to={`/admin/tours/${s.tour_id}`}>Xem tour</Link></div>
      </div>

      {s.cancelled_reason && status === "cancelled" && <Alert type="info" showIcon title={`Lý do hủy: ${s.cancelled_reason}`} />}
      {s.merged_into_schedule_id && <Typography.Text>Khách đã được ghép sang chuyến #{s.merged_into_schedule_id}.</Typography.Text>}

      <section aria-label="Thời gian chuyến đi">
        <Typography.Title level={5}>Thời gian chuyến đi</Typography.Title>
        <Timeline items={[
          { title: "Khởi hành", content: formatDateTime(s.start_date) },
          ...(s.arrival_at ? [{ title: "Tới điểm đến", content: formatDateTime(s.arrival_at) }] : []),
          ...(s.return_departure_at ? [{ title: "Bắt đầu về", content: formatDateTime(s.return_departure_at) }] : []),
          { title: "Về tới nơi", content: s.end_date ? formatDateTime(s.end_date) : getEndDate(s.start_date, s.number_of_days) },
        ]} />
        <Descriptions size="small" column={1} items={[
          { key: "deadline", label: "Hạn chốt danh sách / trả đủ tiền", children: deadline ? formatDateTime(deadline.toISOString()) : "Chưa có" },
        ]} />
        {beforeDeparture && <Button style={{ marginTop: 12 }} onClick={() => act(() => props.onDeadline(s))}>Đổi hạn chốt</Button>}
      </section>

      <section aria-label="Khách và chỗ ngồi">
        <Typography.Title level={5}>Khách và chỗ ngồi</Typography.Title>
        <Descriptions column={1} size="small" items={[
          { key: "seats", label: "Chỗ đã đặt", children: `${s.booked_people} / ${s.max_people} chỗ` },
          { key: "available", label: "Chỗ còn lại", children: Math.max(0, s.max_people - s.booked_people) },
          { key: "paid", label: "Khách đã thanh toán", children: s.paid_people ?? "Chưa có dữ liệu" },
          { key: "target", label: "Số khách mục tiêu", children: s.min_people ?? "Chưa đặt" },
        ]} />
        <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>Ít hơn số khách mục tiêu vẫn tổ chức chuyến nếu có khách đã trả đủ.</Typography.Paragraph>
        <Flex gap="small" wrap>
          {status !== "cancelled" && <Button type="primary" onClick={() => act(() => props.onManifest(s.id))}>Danh sách khách</Button>}
          <Button onClick={() => act(() => props.onAttendance(s.id))}>Xem điểm danh</Button>
        </Flex>
      </section>

      <section aria-label="Hướng dẫn viên">
        <Typography.Title level={5}>Hướng dẫn viên</Typography.Title>
        {(s.guides ?? []).length === 0 ? <Typography.Paragraph type="secondary">Chưa phân công hướng dẫn viên.</Typography.Paragraph> :
          <Flex vertical gap="small" style={{ marginBottom: 12 }}>{s.guides?.map(guide => <Flex key={guide.id} justify="space-between" gap="small">
            <Typography.Text>{guide.name}</Typography.Text>
            <Tag color={guide.pivot?.accepted_at ? "success" : "warning"}>{guide.pivot?.accepted_at ? "Đã nhận chuyến" : "Chờ phản hồi"}</Tag>
          </Flex>)}</Flex>}
        {active && <Flex wrap gap="small">
          <Button onClick={() => act(() => props.onGuides(s))}>{s.guides?.length ? "Đổi phân công" : "Phân công hướng dẫn viên"}</Button>
          {(status === "confirmed" || status === "in_progress") && !!s.guides?.length && <Button onClick={() => act(() => props.onHandover(s.id))}>Bàn giao cho người khác</Button>}
        </Flex>}
      </section>

      {beforeDeparture && <section aria-label="Điều chỉnh chuyến">
        <Divider style={{ marginTop: 0 }} />
        <Typography.Title level={5}>Điều chỉnh chuyến</Typography.Title>
        <Flex gap="small" wrap>
          {status === "open" && <>
            <Button onClick={() => act(() => props.onConfirm(s))}>Chốt chuyến</Button>
            <Button onClick={() => act(() => props.onMerge(s.id))}>Ghép với chuyến khác</Button>
            <Button onClick={() => act(() => props.onPropose(s))}>Gửi đề xuất đổi lịch</Button>
          </>}
          <Button danger onClick={() => act(() => props.onCancel(s.id))}>Hủy chuyến</Button>
        </Flex>
      </section>}

      {props.canAdvanceTime && active && <Collapse size="small" items={[{
        key: "demo", label: "Chạy thử thời gian (demo)", children: <>
          <Typography.Paragraph type="secondary">Thay đổi thời gian của chuyến để chạy thử. Thao tác có thể tự hủy đơn quá hạn.</Typography.Paragraph>
          {s.demo_clock && <Typography.Paragraph>Thời gian đang dùng: {formatDateTime(s.demo_clock.now)}</Typography.Paragraph>}
          <ScheduleLifecycleActions schedule={s} onChanged={props.onReload} onFeedback={props.onFeedback} />
        </>,
      }]} />}
    </Flex>}
  </Drawer>;
}
