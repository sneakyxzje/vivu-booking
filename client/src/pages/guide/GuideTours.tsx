import { useCallback, useEffect, useMemo, useState } from "react";
import { Button, Card, Divider, Empty, Flex, Input, Pagination, Skeleton, Tabs, Typography } from "antd";
import { useSearchParams } from "react-router-dom";
import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { GuideDepartureRow } from "@/components/guide/GuideDepartureRow";
import guideService from "@/services/guideService";
import type { Tour } from "@/types";
import { guideTourGroups, guideTrips, isTripHistory, type GuideTrip, type GuideTripFilter } from "@/utils/guideWork";

function TourCard({ tour, trips }: { tour: Tour; trips: GuideTrip[] }) {
  const [expanded, setExpanded] = useState(false);
  const visible = expanded ? trips : trips.slice(0, 3);
  return <Card styles={{ body: { padding: 20 } }}>
    <Flex vertical gap={6}>
      <Typography.Title level={4} style={{ margin: 0, overflowWrap: "anywhere" }}>{tour.title}</Typography.Title>
      <Typography.Text type="secondary">{tour.start_location}{tour.end_location ? ` → ${tour.end_location}` : ""} · {tour.number_of_days} ngày {tour.number_of_nights} đêm</Typography.Text>
      {tour.pickup_location && <Typography.Text>Điểm đón: {tour.pickup_location}</Typography.Text>}
    </Flex>
    {visible.map((trip, index) => <div key={trip.schedule.id}>
      <Divider style={{ marginBlock: index === 0 ? 12 : 0 }} />
      <GuideDepartureRow trip={trip} />
    </div>)}
    {trips.length > 3 && <Button block type="text" onClick={() => setExpanded(value => !value)}>{expanded ? "Thu gọn" : `Xem thêm ${trips.length - 3} chuyến`}</Button>}
  </Card>;
}

export const GuideTours = () => {
  const feedback = useGuideFeedback();
  const [searchParams, setSearchParams] = useSearchParams();
  const filterParam = searchParams.get("view");
  const filter: GuideTripFilter = filterParam === "running" || filterParam === "history" || filterParam === "all" ? filterParam : "active";
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const loadData = useCallback(() => {
    feedback.clearLoadError("Chưa tải được tour của bạn");
    return guideService.getMyTours().then(result => { setTours(result); setLoadFailed(false); })
      .catch(err => { setLoadFailed(true); feedback.loadError(err, "Chưa tải được tour của bạn"); })
      .finally(() => setLoading(false));
  }, [feedback]);
  useEffect(() => { void loadData(); }, [loadData]);

  const groups = useMemo(() => guideTourGroups(tours, filter, search), [tours, filter, search]);
  const currentPage = Math.min(page, Math.max(1, Math.ceil(groups.length / 10)));
  const trips = guideTrips(tours);
  const counts = { active: trips.filter(trip => !isTripHistory(trip)).length,
    running: trips.filter(trip => trip.status === "in_progress").length,
    history: trips.filter(isTripHistory).length, all: trips.length };
  const tabs = [{ key: "active", label: `Đang & sắp đi (${counts.active})` },
    { key: "running", label: `Đang đi (${counts.running})` },
    { key: "history", label: `Chuyến cũ (${counts.history})` }, { key: "all", label: `Tất cả (${counts.all})` }];

  return <Flex vertical gap="middle">
    <Flex justify="space-between" align="center" gap="small" wrap>
      <Typography.Title level={3} style={{ margin: 0 }}>Tour của tôi</Typography.Title>
      <Button loading={loading} onClick={() => { setLoading(true); void loadData(); }}>Tải lại</Button>
    </Flex>
    <Input.Search allowClear aria-label="Tìm tour, địa điểm hoặc mã chuyến" placeholder="Tìm tour, địa điểm hoặc mã chuyến" value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} />
    <Tabs activeKey={filter} items={tabs} onChange={key => { setSearchParams({ view: key }); setPage(1); }} />
    {loading ? <Skeleton active paragraph={{ rows: 6 }} /> : loadFailed ?
      <Empty description="Danh sách chưa tải được"><Button onClick={() => { setLoading(true); void loadData(); }}>Tải lại</Button></Empty> :
      groups.length === 0 ? <Card><Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={search ? "Không tìm thấy chuyến phù hợp." : filter === "history" ? "Chưa có chuyến cũ." : "Không có chuyến trong mục này."}>
        {filter !== "all" && counts.all > 0 && <Button onClick={() => setSearchParams({ view: "all" })}>Xem tất cả chuyến</Button>}
      </Empty></Card> : groups.slice((currentPage - 1) * 10, currentPage * 10).map(group => <TourCard key={`${filter}-${group.tour.id}`} {...group} />)}
    {!loading && !loadFailed && groups.length > 10 && <Pagination current={currentPage} pageSize={10} total={groups.length} onChange={setPage} showSizeChanger={false} />}
  </Flex>;
};

export default GuideTours;
