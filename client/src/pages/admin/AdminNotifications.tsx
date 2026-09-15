import { Button, Card, Empty, Flex, Spin, Tag, Typography } from "antd";
import { Link } from "react-router-dom";
import { useNotifications } from "@/hooks/useNotifications";
import { formatDateTime } from "@/utils/format";

export default function AdminNotifications() {
  const { items, unread, loading, danhDauDaDoc, danhDauTatCa } = useNotifications();
  return <Flex vertical gap="middle">
    <Flex justify="space-between" align="center" wrap gap="small">
      <Typography.Title level={3}>Thông báo</Typography.Title>
      {unread > 0 && <Button onClick={danhDauTatCa}>Đánh dấu tất cả đã đọc ({unread})</Button>}
    </Flex>
    <Spin spinning={loading}>
      <Flex vertical gap="middle">
        {!loading && items.length === 0 && <Empty description="Chưa có thông báo nào." />}
        {items.map((item) => <Card key={item.id} size="small" title={<Flex gap="small" wrap><Typography.Text strong={!item.read_at}>{item.title}</Typography.Text>{!item.read_at && <Tag color="blue">Chưa đọc</Tag>}</Flex>}
          extra={<Typography.Text type="secondary">{formatDateTime(item.created_at)}</Typography.Text>}>
          <Flex vertical gap="small">
            <Typography.Paragraph>{item.body}</Typography.Paragraph>
            {item.url ? <Link to={item.url} onClick={() => danhDauDaDoc(item.id)}>Xem và xử lý</Link>
              : !item.read_at && <Button onClick={() => danhDauDaDoc(item.id)}>Đánh dấu đã đọc</Button>}
          </Flex>
        </Card>)}
      </Flex>
    </Spin>
  </Flex>;
}
