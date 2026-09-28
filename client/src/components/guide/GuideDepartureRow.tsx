import { Button, Flex, Tag, Typography } from "antd";
import { Link } from "react-router-dom";
import type { GuideTrip } from "@/utils/guideWork";
import { formatDateTime } from "@/utils/format";

const labels = { open: "Sắp đi", confirmed: "Đã chốt", in_progress: "Đang đi", completed: "Đã kết thúc", cancelled: "Đã hủy" };

export function GuideDepartureRow({ trip }: { trip: GuideTrip }) {
  const { schedule, status } = trip;
  const running = status === "in_progress";
  const history = status === "completed" || status === "cancelled";
  return <Flex justify="space-between" align="center" gap="middle" wrap style={{ paddingBlock: 12 }}>
    <Flex vertical gap={6} style={{ minWidth: 0, flex: "1 1 220px" }}>
      <Flex gap="small" align="center" wrap>
        <Tag color={running ? "processing" : status === "cancelled" ? "error" : "default"}>{labels[status]}</Tag>
        <Typography.Text type="secondary">Chuyến #{schedule.id}</Typography.Text>
      </Flex>
      <Typography.Text strong>Đi: {formatDateTime(schedule.start_date)}</Typography.Text>
      {schedule.end_date && <Typography.Text type="secondary">Về: {formatDateTime(schedule.end_date)}</Typography.Text>}
    </Flex>
    <Link to={`/guide/attendance/${schedule.id}`} aria-label={`${running ? "Điểm danh" : "Xem"} chuyến ${schedule.id}`}>
      <Button type={running ? "primary" : "default"}>{running ? "Điểm danh" : history ? "Xem lại" : "Xem chuyến"}</Button>
    </Link>
  </Flex>;
}
