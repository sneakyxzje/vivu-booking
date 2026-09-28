import { useCallback, useEffect, useRef, useState } from "react";
import { Button, Card, Col, Empty, Flex, Row, Skeleton, Statistic, Tag, Typography } from "antd";
import { Link } from "react-router-dom";
import guideService, { type GuideAssignment } from "@/services/guideService";
import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { GuideDepartureRow } from "@/components/guide/GuideDepartureRow";
import { DemoClockNotice } from "@/components/DemoClockNotice";
import type { Tour } from "@/types";
import type { AttendanceData } from "@/types/guide";
import { guideTrips, isTripHistory, isTripToday, todayAttendanceProgress } from "@/utils/guideWork";
import { formatDateTime } from "@/utils/format";

export const GuideDashboard = () => {
  const feedback = useGuideFeedback();
  const [tours, setTours] = useState<Tour[]>([]);
  const [assignments, setAssignments] = useState<GuideAssignment[]>([]);
  const [attendance, setAttendance] = useState<Record<number, AttendanceData | null>>({});
  const [loading, setLoading] = useState(true);
  const [progressLoading, setProgressLoading] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const [snapshotTime, setSnapshotTime] = useState(() => Date.now());
  const requestVersion = useRef(0);
  const cancelRequests = useCallback(() => { requestVersion.current++; }, []);

  const loadData = useCallback(() => {
    const version = ++requestVersion.current;
    feedback.clearLoadError("Chưa tải được công việc hôm nay");
    return Promise.all([guideService.getMyTours(), guideService.getMyAssignments()])
      .then(async ([myTours, myAssignments]) => {
        if (version !== requestVersion.current) return;
        setTours(myTours);
        setAssignments(myAssignments);
        setSnapshotTime(Date.now());
        setLoadFailed(false);
        setLoading(false);
        setAttendance({});
        const running = guideTrips(myTours).filter(trip => trip.status === "in_progress");
        setProgressLoading(running.length > 0);
        const results = await Promise.allSettled(running.map(trip => guideService.getAttendance(trip.schedule.id)));
        if (version !== requestVersion.current) return;
        setAttendance(Object.fromEntries(results.map((result, index) => [running[index].schedule.id, result.status === "fulfilled" ? result.value : null])));
        setProgressLoading(false);
      })
      .catch(err => {
        if (version !== requestVersion.current) return;
        setLoadFailed(true);
        feedback.loadError(err, "Chưa tải được công việc hôm nay");
      })
      .finally(() => { if (version === requestVersion.current) setLoading(false); });
  }, [feedback]);

  useEffect(() => {
    void loadData();
    const refresh = () => { if (document.visibilityState !== "hidden") void loadData(); };
    window.addEventListener("focus", refresh);
    const timer = window.setInterval(refresh, 60000);
    return () => { cancelRequests(); window.removeEventListener("focus", refresh); window.clearInterval(timer); };
  }, [loadData, cancelRequests]);

  const trips = guideTrips(tours);
  const running = trips.filter(trip => trip.status === "in_progress");
  const today = trips.filter(trip => isTripToday(trip, snapshotTime)).sort((a, b) =>
    Number(b.status === "in_progress") - Number(a.status === "in_progress") || Date.parse(a.schedule.start_date) - Date.parse(b.schedule.start_date));
  const upcoming = trips.filter(trip => !isTripHistory(trip) && trip.status !== "in_progress")
    .sort((a, b) => Date.parse(a.schedule.start_date) - Date.parse(b.schedule.start_date));
  const pending = assignments.filter(item => !item.accepted_at && item.status !== "completed" && item.status !== "cancelled");
  const progressKnown = !progressLoading && running.every(trip => attendance[trip.schedule.id]);
  const remaining = progressKnown ? running.reduce((sum, trip) => sum + todayAttendanceProgress(attendance[trip.schedule.id]!).remaining, 0) : null;

  if (loading) return <Skeleton active paragraph={{ rows: 8 }} />;
  if (loadFailed) return <Empty description="Chưa tải được công việc hôm nay"><Button onClick={() => { setLoading(true); void loadData(); }}>Tải lại</Button></Empty>;

  return <Flex vertical gap="large">
    <Flex justify="space-between" align="center" gap="small" wrap>
      <Typography.Title level={3} style={{ margin: 0 }}>Công việc hôm nay</Typography.Title>
      <Button onClick={() => { setLoading(true); void loadData(); }}>Cập nhật</Button>
    </Flex>
    <Row gutter={[12, 12]}>
      <Col xs={24} sm={8}><Card size="small"><Statistic title="Chuyến đang đi" value={running.length} /></Card></Col>
      <Col xs={12} sm={8}><Card size="small"><Statistic title="Chờ nhận chuyến" value={pending.length} /></Card></Col>
      <Col xs={12} sm={8}><Card size="small"><Statistic title="Điểm cần hoàn tất" value={remaining ?? "—"} /></Card></Col>
    </Row>

    <Row gutter={[24, 24]} align="top">
      <Col xs={24} xl={16}><Flex vertical gap="middle">
        <Typography.Title level={4} style={{ margin: 0 }}>Chuyến hôm nay</Typography.Title>
        {today.length === 0 ? <Card><Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Hôm nay chưa có chuyến cần dẫn." /></Card> : today.map(trip => {
          const data = attendance[trip.schedule.id];
          const progress = data ? todayAttendanceProgress(data) : null;
          return <Card key={trip.schedule.id} styles={{ body: { padding: 20 } }}>
            <Flex vertical gap="small">
              <DemoClockNotice clock={trip.schedule.demo_clock} />
              <Typography.Title level={4} style={{ margin: 0, overflowWrap: "anywhere" }}>{trip.tour.title}</Typography.Title>
              {trip.tour.pickup_location && <Typography.Text>Điểm đón: {trip.tour.pickup_location}</Typography.Text>}
              <GuideDepartureRow trip={trip} />
              {trip.status === "in_progress" && (progressLoading ? <Typography.Text type="secondary">Đang tải tiến độ điểm danh…</Typography.Text> : !progress ?
                <Typography.Text type="secondary">Chưa tải được tiến độ. Mở điểm danh để kiểm tra.</Typography.Text> :
                <Flex gap="small" wrap align="center">
                  <Tag color={progress.total > 0 && progress.remaining === 0 ? "success" : "default"}>{progress.total === 0 ? "Hôm nay chưa có điểm dừng" : `${progress.complete}/${progress.total} điểm đã hoàn tất`}</Tag>
                  {progress.remaining > 0 && <Typography.Text>Còn {progress.remaining} điểm cần hoàn tất</Typography.Text>}
                  {progress.missingPassengerLists > 0 && <Typography.Text type="warning">{progress.missingPassengerLists} đơn chưa khai hành khách</Typography.Text>}
                </Flex>)}
            </Flex>
          </Card>;
        })}
      </Flex></Col>
      <Col xs={24} xl={8}><Flex vertical gap="middle">
        <Card title="Cần bạn xử lý">
          <Flex vertical gap="middle">
            {pending.length > 0 && <Flex vertical gap="small">
              <Typography.Text strong>{pending.length} chuyến chờ xác nhận nhận</Typography.Text>
              {pending.slice(0, 3).map(item => <Typography.Text key={item.schedule_id}>{item.tour_title} · {formatDateTime(item.start_date)}</Typography.Text>)}
              <Link to="/guide/assignments"><Button block>Xem chuyến được giao</Button></Link>
            </Flex>}
            {remaining !== null && remaining > 0 && <Typography.Text>Còn {remaining} điểm hôm nay chưa hoàn tất điểm danh hoặc ảnh check-in.</Typography.Text>}
            {remaining === null && <Typography.Text type="secondary">{progressLoading ? "Đang kiểm tra điểm danh…" : "Chưa tải đủ tiến độ điểm danh."}</Typography.Text>}
            {pending.length === 0 && remaining === 0 && <Typography.Text type="secondary">Không có việc đang chờ.</Typography.Text>}
          </Flex>
        </Card>
        <Card title="Thao tác nhanh"><Flex vertical gap="small">
          <Link to="/guide/incidents"><Button block>Báo sự cố</Button></Link>
          <Link to="/guide/handovers"><Button block>Bàn giao đoàn</Button></Link>
          <Link to="/guide/tours?view=history"><Button block>Xem chuyến cũ</Button></Link>
        </Flex></Card>
      </Flex></Col>
    </Row>

    <Flex justify="space-between" align="center" wrap gap="small">
      <Typography.Title level={4} style={{ margin: 0 }}>Sắp khởi hành</Typography.Title>
      <Link to="/guide/tours">Xem tất cả chuyến</Link>
    </Flex>
    {upcoming.length === 0 ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có chuyến sắp khởi hành." /> :
      <Row gutter={[16, 16]}>{upcoming.slice(0, 3).map(trip => <Col xs={24} lg={8} key={trip.schedule.id}>
        <Card style={{ height: "100%" }}><Typography.Text strong>{trip.tour.title}</Typography.Text><GuideDepartureRow trip={trip} /></Card>
      </Col>)}</Row>}
  </Flex>;
};

export default GuideDashboard;
