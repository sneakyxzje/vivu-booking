import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { Typography, Button, Card, Collapse, Empty, Flex, Form, Input, Modal, Pagination, Skeleton, Tabs, Tag } from "antd";
import { useCallback, useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import guideService, { type GuideAssignment } from "@/services/guideService";
import { formatDateTime, getEndDate } from "@/utils/format";
import { assignmentView, filterAssignments, type AssignmentView } from "@/utils/guideAssignments";

export default function GuideAssignments() {
  const feedback = useGuideFeedback();
  const [params, setParams] = useSearchParams();
  const view: AssignmentView = params.get("view") === "accepted" ? "accepted" : params.get("view") === "history" ? "history" : "pending";
  const [items, setItems] = useState<GuideAssignment[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState(false);
  const [acceptingId, setAcceptingId] = useState<number | null>(null);
  const [declining, setDeclining] = useState<GuideAssignment | null>(null);
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const busy = saving || acceptingId !== null;

  const loadData = useCallback(() => {
    feedback.clearLoadError("Chưa tải được chuyến được giao");
    return guideService.getMyAssignments()
      .then(result => { setItems(result); setPageError(false); })
      .catch(err => { setPageError(true); feedback.loadError(err, "Chưa tải được chuyến được giao"); })
      .finally(() => setLoading(false));
  }, [feedback]);

  useEffect(() => { void loadData(); }, [loadData]);

  const accept = async (scheduleId: number) => {
    if (busy) return;
    setAcceptingId(scheduleId);
    try {
      const message = await guideService.acceptAssignment(scheduleId);
      feedback.success(message);
      await loadData();
    } catch (err) {
      feedback.error(err, "Chưa nhận được chuyến. Vui lòng thử lại.");
    } finally { setAcceptingId(null); }
  };

  const decline = async () => {
    if (busy || !declining || reason.trim().length < 10) return;
    setSaving(true);
    try {
      feedback.success(await guideService.declineAssignment(declining.schedule_id, reason.trim()));
      setItems(previous => previous.filter(item => item.schedule_id !== declining.schedule_id));
      setDeclining(null);
      setReason("");
      await loadData();
    } catch (err) {
      feedback.error(err, "Chưa từ chối được chuyến. Vui lòng thử lại.");
    } finally { setSaving(false); }
  };

  const filtered = filterAssignments(items, view, query);
  const currentPage = Math.min(page, Math.max(1, Math.ceil(filtered.length / 8)));
  const counts = { pending: 0, accepted: 0, history: 0 };
  items.forEach(item => counts[assignmentView(item)]++);

  return <Flex vertical gap="large">
    <div>
      <Typography.Title level={3} style={{ margin: 0 }}>Chuyến được giao</Typography.Title>
      <Typography.Text type="secondary">Xác nhận các chuyến điều hành đã phân công cho bạn.</Typography.Text>
    </div>
    <Card styles={{ body: { paddingTop: 0 } }}>
      <Tabs activeKey={view} onChange={key => { setParams({ view: key }); setPage(1); }} items={[
        { key: "pending", label: `Chờ trả lời (${counts.pending})` },
        { key: "accepted", label: `Đã nhận (${counts.accepted})` },
        { key: "history", label: `Chuyến cũ (${counts.history})` },
      ]} />
      <Input.Search aria-label="Tìm chuyến được giao" placeholder="Tên tour, mã chuyến hoặc HDV cùng dẫn" allowClear value={query}
        onChange={event => { setQuery(event.target.value); setPage(1); }} style={{ maxWidth: 480 }} />
    </Card>
    {pageError && <Flex><Button loading={loading} disabled={busy} onClick={() => { setLoading(true); void loadData(); }}>Tải lại danh sách</Button></Flex>}
    {loading ? <Skeleton active paragraph={{ rows: 8 }} /> : <>
      {filtered.length === 0 ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE}
        description={pageError ? "Chưa tải được danh sách" : query ? "Không tìm thấy chuyến phù hợp" : view === "pending" ? "Không có chuyến chờ trả lời" : view === "accepted" ? "Chưa có chuyến đã nhận" : "Chưa có chuyến cũ"}>
        {query ? <Button onClick={() => { setQuery(""); setPage(1); }}>Xóa tìm kiếm</Button> : view === "pending" && counts.accepted > 0 ? <Button onClick={() => { setParams({ view: "accepted" }); setPage(1); }}>Xem chuyến đã nhận</Button> : null}
      </Empty> : filtered.slice((currentPage - 1) * 8, currentPage * 8).map(item => {
        const history = assignmentView(item) === "history";
        const running = item.status === "in_progress";
        return <Card key={item.schedule_id}>
          <Flex vertical gap="middle">
            <Flex justify="space-between" gap="small" wrap align="start">
              <div style={{ flex: "1 1 240px", minWidth: 0 }}>
                <Typography.Text type="secondary">Chuyến #{item.schedule_id}</Typography.Text>
                <Typography.Title level={4} style={{ margin: "4px 0 0", overflowWrap: "anywhere" }}>{item.tour_title ?? "Tour"}</Typography.Title>
              </div>
              <Tag color={running ? "processing" : item.status === "cancelled" ? "error" : "default"}>
                {running ? "Đang đi" : item.status === "completed" ? "Đã kết thúc" : item.status === "cancelled" ? "Đã hủy" : "Sắp đi"}
              </Tag>
            </Flex>
            <Flex gap="large" wrap>
              <Flex vertical style={{ flex: "1 1 200px" }}><Typography.Text type="secondary">Khởi hành</Typography.Text><Typography.Text strong>{formatDateTime(item.start_date)}</Typography.Text></Flex>
              <Flex vertical style={{ flex: "1 1 200px" }}><Typography.Text type="secondary">Về tới nơi</Typography.Text><Typography.Text strong>{item.end_date ? formatDateTime(item.end_date) : getEndDate(item.start_date, item.number_of_days)}</Typography.Text></Flex>
            </Flex>
            {(item.co_guides.length > 0 || item.arrival_at || item.return_departure_at || item.accepted_at) && <Collapse ghost size="small" items={[{
              key: "details", label: "Thông tin thêm", children: <Flex vertical gap="small">
                {item.co_guides.length > 0 && <Typography.Text>Cùng dẫn: {item.co_guides.join(", ")}</Typography.Text>}
                {item.arrival_at && <Typography.Text>Tới điểm đến: {formatDateTime(item.arrival_at)}</Typography.Text>}
                {item.return_departure_at && <Typography.Text>Rời điểm đến: {formatDateTime(item.return_departure_at)}</Typography.Text>}
                {item.accepted_at && <Typography.Text type="secondary">Đã nhận lúc {formatDateTime(item.accepted_at)}</Typography.Text>}
              </Flex>,
            }]} />}
            <Flex justify="space-between" gap="middle" wrap align="center">
              <Flex gap="small" wrap>
                {!history && !item.accepted_at && <>
                  <Button type="primary" loading={acceptingId === item.schedule_id} disabled={busy || pageError} onClick={() => void accept(item.schedule_id)}>Nhận chuyến</Button>
                  {item.can_decline && <Button danger disabled={busy || pageError} onClick={() => { setDeclining(item); setReason(""); }}>Từ chối</Button>}
                </>}
                <Link to={`/guide/attendance/${item.schedule_id}`}><Button disabled={busy} type={item.accepted_at && running ? "primary" : "default"}>{history ? "Xem lại chuyến" : running ? "Điểm danh" : "Xem chuyến"}</Button></Link>
              </Flex>
              {running && <Link to="/guide/handovers">Cần bàn giao đoàn</Link>}
              {!running && !history && item.accepted_at && <Tag color="success">Đã nhận chuyến</Tag>}
            </Flex>
          </Flex>
        </Card>;
      })}
      {filtered.length > 8 && <Pagination current={currentPage} pageSize={8} total={filtered.length} onChange={setPage} showSizeChanger={false} />}
    </>}
    <Modal open={declining !== null} title="Từ chối chuyến" okText="Từ chối chuyến" cancelText="Quay lại" confirmLoading={saving}
      okButtonProps={{ danger: true, disabled: reason.trim().length < 10 || busy }} cancelButtonProps={{ disabled: saving }}
      closable={!saving} mask={{ closable: false }} onCancel={() => { if (!saving) setDeclining(null); }} onOk={() => void decline()}>
      <Typography.Paragraph><strong>{declining?.tour_title}</strong><br />Chuyến #{declining?.schedule_id} · {declining ? formatDateTime(declining.start_date) : ""}</Typography.Paragraph>
      <Form layout="vertical" disabled={saving}>
        <Form.Item label="Lý do từ chối" htmlFor="assignment-reason" required extra="Tối thiểu 10 ký tự.">
          <Input.TextArea id="assignment-reason" autoFocus rows={4} maxLength={500} showCount value={reason} onChange={event => setReason(event.target.value)} placeholder="Ví dụ: Tôi có lịch cá nhân vào ngày khởi hành." />
        </Form.Item>
      </Form>
    </Modal>
  </Flex>;
}
