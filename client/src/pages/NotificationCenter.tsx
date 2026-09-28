import { Badge, Button, Card, Empty, Flex, Skeleton, Typography } from "antd";
import { useState } from "react";
import { Link } from "react-router-dom";
import { useNotifications } from "@/hooks/useNotifications";
import { formatDateTime } from "@/utils/format";

export default function NotificationCenter() {
  const { items, unread, loading, danhDauDaDoc, danhDauTatCa } = useNotifications();
  const [markingAll, setMarkingAll] = useState(false);
  const markAll = async () => {
    setMarkingAll(true);
    try { await danhDauTatCa(); }
    finally { setMarkingAll(false); }
  };

  return <Flex vertical gap="middle" style={{ maxWidth: 960 }}>
    <Flex justify="space-between" align="center" gap="middle" wrap>
      <Typography.Title level={3} style={{ margin: 0 }}>Thông báo</Typography.Title>
      {unread > 0 && <Button onClick={markAll} loading={markingAll}>Đánh dấu tất cả đã đọc ({unread})</Button>}
    </Flex>
    {loading && <Skeleton active />}
    {!loading && items.length === 0 && <Empty description="Chưa có thông báo nào." />}
    {items.map(tb => {
      const content = <Flex vertical gap="small">
        <Flex align="start" justify="space-between" gap="small" wrap>
          <Typography.Text strong={!tb.read_at}>{tb.title}</Typography.Text>
          {!tb.read_at && <Badge status="processing" text="Chưa đọc" />}
        </Flex>
        <Typography.Text type="secondary">{formatDateTime(tb.created_at)}</Typography.Text>
        <Typography.Paragraph style={{ margin: 0, whiteSpace: "pre-wrap" }}>{tb.body}</Typography.Paragraph>
      </Flex>;
      return tb.url ? <Link key={tb.id} to={tb.url} onClick={() => { if (!tb.read_at) void danhDauDaDoc(tb.id); }}>
        <Card hoverable>{content}</Card>
      </Link> : <Card key={tb.id}>
        <Flex vertical gap="middle" align="start">{content}
          {!tb.read_at && <Button onClick={() => danhDauDaDoc(tb.id)}>Đánh dấu đã đọc</Button>}
        </Flex>
      </Card>;
    })}
  </Flex>;
}
