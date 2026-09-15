import { useState } from "react";
import { Badge, Button, Divider, Empty, Flex, Popover, Spin, Typography } from "antd";
import { Bell, CheckCheck } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { useNotifications } from "@/hooks/useNotifications";
import { formatDateTime } from "@/utils/format";

export function AdminNotificationBell() {
  const { items, unread, loading, danhDauDaDoc, danhDauTatCa } = useNotifications();
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();
  const visible = items.slice(0, 6);

  return <Popover trigger="click" placement="bottomRight" open={open} onOpenChange={setOpen}
    content={<Flex vertical gap="small" style={{ width: "min(360px, calc(100vw - 64px))" }}>
      <Flex justify="space-between" align="center" gap="small" wrap>
        <Typography.Text strong>Thông báo {unread > 0 && <Badge count={unread} />}</Typography.Text>
        {unread > 0 && <Button type="text" size="small" icon={<CheckCheck size={15} />} onClick={danhDauTatCa}>Đọc tất cả</Button>}
      </Flex>
      <Divider style={{ marginBlock: 4 }} />
      <Spin spinning={loading && visible.length === 0}>
        <Flex vertical gap="small" style={{ maxHeight: "55vh", overflowY: "auto" }}>
          {!loading && visible.length === 0 && <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có thông báo" />}
          {visible.map((item) => <Button key={item.id} type="text" block
            style={{ height: "auto", padding: 10, textAlign: "left", whiteSpace: "normal" }}
            onClick={() => {
              if (!item.read_at) void danhDauDaDoc(item.id);
              setOpen(false);
              if (item.url) navigate(item.url);
            }}>
            <Flex vertical gap={4} style={{ width: "100%", minWidth: 0 }}>
              <Flex gap="small" align="baseline">
                {!item.read_at && <Badge status="processing" />}
                <Typography.Text strong={!item.read_at} ellipsis>{item.title}</Typography.Text>
              </Flex>
              <Typography.Paragraph type="secondary" ellipsis={{ rows: 2 }} style={{ margin: 0 }}>{item.body}</Typography.Paragraph>
              <Typography.Text type="secondary" style={{ fontSize: 12 }}>{formatDateTime(item.created_at)}</Typography.Text>
            </Flex>
          </Button>)}
        </Flex>
      </Spin>
      <Divider style={{ marginBlock: 4 }} />
      <Link to="/admin/notifications" onClick={() => setOpen(false)}>Xem tất cả thông báo</Link>
    </Flex>}>
    <Badge count={unread} size="small" offset={[-3, 3]}>
      <Button icon={<Bell size={18} />} aria-label={unread ? `Thông báo, ${unread} chưa đọc` : "Thông báo"} aria-expanded={open} />
    </Badge>
  </Popover>;
}
