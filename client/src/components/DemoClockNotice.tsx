import { Alert } from "antd";
import { useEffect, useState } from "react";
import { businessNow, type DemoClockValue } from "@/utils/demoClock";

export function DemoClockNotice({ clock }: { clock?: DemoClockValue | null }) {
  const [, tick] = useState(0);
  useEffect(() => {
    if (!clock) return;
    const timer = window.setInterval(() => tick(value => value + 1), 1000);
    return () => window.clearInterval(timer);
  }, [clock]);
  if (!clock) return null;
  return <Alert showIcon type="info" title={`Thời gian của chuyến · ${new Date(businessNow({ demo_clock: clock })).toLocaleString("vi-VN")}`}
    description="Sau khi chuyển trạng thái hoặc mốc thời gian ở trang quản trị, hãy tải lại màn hình để cập nhật." />;
}
