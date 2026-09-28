import { Alert, Button, Dropdown, Flex, Modal, Typography } from "antd";
import { useRef, useState } from "react";
import api from "@/services/api";
import type { ExtendedSchedule } from "@/types";
import { scheduleStatusText as statusLabel } from "@/utils/scheduleList";

interface Milestone { key: string; label: string; at: string; blocked_reason: string | null }
interface Result { status: ExtendedSchedule["status"]; milestones: Milestone[]; output?: string }

const nextStep = {
  open: { status: "confirmed", label: "Tới hạn chốt", detail: "Chạy thử tại hạn chốt: hủy đơn chưa trả đủ và xác nhận chuyến có khách đã trả đủ." },
  confirmed: { status: "in_progress", label: "Tới giờ khởi hành", detail: "Chạy thử tại giờ khởi hành. Chuyến chuyển sang Đang diễn ra, hướng dẫn viên có thể điểm danh." },
  in_progress: { status: "completed", label: "Tới giờ kết thúc", detail: "Chạy thử sau giờ kết thúc và hoàn tất các đơn. Hãy lưu điểm danh trước khi thực hiện." },
} as const;

export function ScheduleLifecycleActions({ schedule, onChanged, onFeedback }: {
  schedule: ExtendedSchedule;
  onChanged: () => Promise<void>;
  onFeedback: (message: string, type: "success" | "info") => void;
}) {
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(false);
  const [milestones, setMilestones] = useState<Milestone[]>([]);
  const [error, setError] = useState("");
  const [pending, setPending] = useState<{ label: string; detail: string; status?: string; milestone?: string }>();
  const inFlight = useRef(false);
  if (schedule.status === "completed" || schedule.status === "cancelled") return null;
  const next = nextStep[schedule.status];

  async function loadMilestones(open: boolean) {
    if (!open) return;
    setLoading(true);
    setError("");
    try {
      const response = await api.get(`/admin/tour-schedules/${schedule.id}/demo`);
      setMilestones((response.data.data as Result).milestones);
    } catch { setError("Không tải được các mốc thời gian."); }
    finally { setLoading(false); }
  }

  async function run() {
    if (!pending || inFlight.current) return;
    inFlight.current = true;
    setBusy(true);
    setError("");
    try {
      const response = await api.post(`/admin/tour-schedules/${schedule.id}/demo/${pending.status ? "status" : "milestone"}`,
        pending.status ? { status: pending.status } : { milestone: pending.milestone });
      const result: Result = response.data.data;
      if (pending.status && result.status !== pending.status) {
        onFeedback(`Chuyến #${schedule.id}: ${statusLabel[result.status]}. Chưa thể chốt chuyến; kiểm tra số khách đã thanh toán và các đơn quá hạn.`, "info");
      } else {
        onFeedback(`Chuyến #${schedule.id}: ${statusLabel[result.status]}.`, "success");
      }
      setPending(undefined);
      await onChanged();
    } catch (err) {
      setError((err as { response?: { data?: { message?: string } } }).response?.data?.message || "Không chuyển được trạng thái chuyến.");
    } finally { inFlight.current = false; setBusy(false); }
  }

  return <Flex vertical gap={4} style={{ maxWidth: 300 }}>
    <Flex gap="small" wrap>
      <Button size="small" type="primary" disabled={busy} onClick={() => { setError(""); setPending(next); }}>{next.label}</Button>
      <Dropdown trigger={["click"]} onOpenChange={loadMilestones} menu={{
        items: loading ? [{ key: "loading", label: "Đang tải…", disabled: true }] : [
          ...milestones.filter(m => !["booking_deadline", "departure", "completion"].includes(m.key)).map(m => ({
            key: m.key, label: m.label, disabled: !!m.blocked_reason, title: m.blocked_reason || new Date(m.at).toLocaleString("vi-VN"),
          })),
          { key: "process", label: "Xử lý các đơn đến hạn" },
        ],
        onClick: ({ key }) => {
          const milestone = milestones.find(m => m.key === key);
          setError("");
          setPending({ milestone: key, label: milestone?.label || "Xử lý các đơn đến hạn",
            detail: milestone ? `Chuyển thời gian của chuyến tới ${new Date(milestone.at).toLocaleString("vi-VN")} và xử lý các đơn đến hạn.` : "Chạy xử lý các đơn tại thời gian hiện tại của chuyến." });
        },
      }}><Button size="small" disabled={busy}>Chọn mốc khác</Button></Dropdown>
    </Flex>
    {error && !pending && <Typography.Text type="danger">{error}</Typography.Text>}
    <Modal title={`Chạy thử: ${pending?.label ?? "Chuyển thời gian"} · #${schedule.id}`} open={!!pending}
      onOk={run} onCancel={() => { if (!busy) { setPending(undefined); setError(""); } }} confirmLoading={busy}
      okText={pending?.label} cancelText="Để sau" cancelButtonProps={{ disabled: busy }} closable={!busy} maskClosable={!busy}>
      <Typography.Paragraph>{pending?.detail}</Typography.Paragraph>
      {pending?.status && <Typography.Text strong>Sau khi chạy thử: {statusLabel[pending.status as ExtendedSchedule["status"]]}</Typography.Text>}
      {error && <Alert type="error" showIcon title={error} />}
    </Modal>
  </Flex>;
}
