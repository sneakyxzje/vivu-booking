import { useGuideFeedback } from "@/hooks/useGuideFeedback";
import { Empty, Alert, Button, Card, Form, InputNumber, Modal, Select, Skeleton, Table, Tabs, Typography } from "antd";
import React, { useCallback, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import guideService from "@/services/guideService";
import type { GuideBooking, BookingStatus } from "@/types/guide";
import { BookingStatusBadge } from "@/components/guide/GuideStatusBadge";
import { formatDateTime } from "@/utils/format";

const formatPrice = (v: number) =>
  new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(v);

/*
 * ĐÃ GỠ: bản dựng ngày riêng của màn này.
 *
 * Nó cho ra đúng dạng "09/09/2026 06:00" như bản dùng chung, nên không sai — nhưng là bản thứ hai
 * của cùng một luật, và bản thứ hai luôn là bản không được sửa khi luật đổi. Nó cũng thiếu hai
 * việc bản chung làm: trả "-" khi giá trị rỗng, và trả nguyên chuỗi thay vì "Invalid Date" khi
 * máy chủ gửi về thứ không đọc được.
 */
const formatDate = formatDateTime;

type StatusFilter = "all" | BookingStatus;

export const GuideBookings: React.FC = () => {
  const feedback = useGuideFeedback();
  const [searchParams] = useSearchParams();
  const initialStatus = (searchParams.get("status") as StatusFilter) || "all";

  const [bookings, setBookings] = useState<GuideBooking[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>(initialStatus);
  const [confirmingId, setConfirmingId] = useState<number | null>(null);
  /** Đơn đang mở ô thu tiền. Xác nhận là khẳng định đã cầm tiền, nên phải khai số. */
  const [dangThu, setDangThu] = useState<GuideBooking | null>(null);
  const [soTien, setSoTien] = useState("");
  const [hinhThuc, setHinhThuc] = useState<"cash" | "bank_transfer">("cash");

  const loadData = useCallback(() => {
    feedback.clearLoadError("Chưa tải được danh sách đặt chỗ");
    return guideService.getBookings()
      .then(result => {
        setBookings(result);
        setLoadFailed(false);
      })
      .catch(err => {
        setLoadFailed(true);
        feedback.loadError(err, "Chưa tải được danh sách đặt chỗ");
      })
      .finally(() => setLoading(false));
  }, [feedback]);

  useEffect(() => { void loadData(); }, [loadData]);

  const filtered = useMemo(() => {
    if (statusFilter === "all") return bookings;
    return bookings.filter((b) => b.status === statusFilter);
  }, [bookings, statusFilter]);

  /**
   * Mở ô thu tiền cho một đơn.
   *
   * Xác nhận tại điểm tập trung nghĩa là hướng dẫn viên vừa cầm tiền của khách, nên phải khai đã
   * cầm bao nhiêu — máy chủ ghi thẳng vào sổ giao dịch. Điền sẵn đúng giá trị đơn vì đó là con số
   * đúng trong hầu hết trường hợp.
   */
  const moOThuTien = (b: GuideBooking) => {
    setDangThu(b);
    setSoTien(String(b.total_amount ?? ""));
    setHinhThuc("cash");
  };

  const handleConfirm = async () => {
    if (!dangThu || confirmingId !== null) return;

    const id = dangThu.id;
    setConfirmingId(id);

    try {
      const so = Number(soTien);

      const result = await guideService.confirmBooking(
        id,
        so > 0 ? { amount: so, method: hinhThuc } : undefined,
      );
      if (!result.success) {
        feedback.error(null, "Chưa xác nhận được đặt chỗ. Vui lòng thử lại.");
        return;
      }

      // The payment succeeded. A failed list refresh must not invite a second collection.
      setBookings(previous => previous.map(booking => booking.id === id ? { ...booking, status: "confirmed" } : booking));
      setDangThu(null);
      feedback.success(so > 0 ? "Đã xác nhận đặt chỗ và ghi khoản thu." : "Đã xác nhận đặt chỗ.");
      try {
        setBookings(await guideService.getBookings());
        setLoadFailed(false);
      } catch {
        feedback.warning("Đơn đã xác nhận và khoản thu đã được lưu. Chưa cập nhật được danh sách; vui lòng tải lại trang, không thu lại tiền.");
      }
    } catch (err) {
      feedback.error(err, `Chưa xác nhận được đơn BK-${id}. Vui lòng kiểm tra lại trước khi thử lại.`);
    } finally {
      setConfirmingId(null);
    }
  };

  const tabs: { key: StatusFilter; label: string }[] = [
    { key: "all", label: "Tất cả" },
    { key: "pending", label: "Chờ xác nhận" },
    { key: "confirmed", label: "Đã xác nhận" },
    { key: "cancelled", label: "Đã hủy" },
  ];

  return (
    <div className="space-y-6 animate-fade-in">

      <div>
        <Typography.Title level={3} style={{ margin: 0 }}>Quản lý đặt chỗ</Typography.Title>
        <p className="text-gray-500 text-sm mt-1">
          Xem và xác nhận đặt tour từ khách hàng
        </p>
      </div>

      <Tabs activeKey={statusFilter} onChange={(key) => setStatusFilter(key as StatusFilter)} items={tabs} />

      {loading ? (
        <Skeleton active />
      ) : loadFailed ? (
        <Empty description="Danh sách chưa tải được"><Button onClick={() => { setLoading(true); void loadData(); }}>Tải lại</Button></Empty>
      ) : filtered.length === 0 ? (
        <Card ><div>
          <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không có đặt chỗ khớp bộ lọc." />
        </div></Card>
      ) : (
        <Card ><div>
          <div className="overflow-x-auto">
            <Table rowKey="id" dataSource={filtered} scroll={{ x: 1100 }} pagination={{ pageSize: 10, showSizeChanger: false, hideOnSinglePage: true }} columns={[{
key: "col0", title: <>Mã</>, render: (_: unknown, b: GuideBooking) => <>
                #{b.id}
              </>
},
            {
key: "col1", title: <>Khách hàng</>, render: (_: unknown, b: GuideBooking) => <>
                <p className="font-medium text-gray-900">{b.customer_name}</p>
                <p className="text-xs text-gray-500">{b.customer_phone}</p>
              </>
},
            {
key: "col2", title: <>Tour</>, render: (_: unknown, b: GuideBooking) => <>
                {b.tour_title}
              </>
},
            {
key: "col3", title: <>Ngày đi</>, render: (_: unknown, b: GuideBooking) => <>
                {formatDate(b.departure_date)}
              </>
},
            { key: "col4", title: <>Số khách</>, render: (_: unknown, b: GuideBooking) => <>{b.guests}</> },
            {
key: "col5", title: <>Tổng tiền</>, render: (_: unknown, b: GuideBooking) => <>
                {formatPrice(b.total_amount)}
              </>
},
            {
key: "col6", title: <>Trạng thái</>, render: (_: unknown, b: GuideBooking) => <>
                <BookingStatusBadge status={b.status} />
              </>
},
            {
key: "col7", title: <>Thao tác</>, render: (_: unknown, b: GuideBooking) => <>
                {b.status === "pending" ? (
                  <Button type="primary" disabled={confirmingId === b.id} onClick={() => moOThuTien(b)}>
                    {confirmingId === b.id ? "..." : "Xác nhận"}
                  </Button>
                ) : (
                  <span className="text-xs text-gray-400">—</span>
                )}
              </>
}]} />
          </div>
        </div></Card>
      )}

      {/*
        Ô thu tiền trước khi xác nhận.

        Xác nhận tại điểm tập trung là khẳng định "khách này đã trả tiền", nên số tiền đi cùng thao
        tác chứ không phải một việc riêng mà ai đó phải nhớ làm sau. Trước đây nút này chỉ đổi trạng
        thái, và sổ giao dịch vẫn ghi đơn ấy thu 0 đồng.
      */}
      {dangThu && (
        <Modal open title={"Xác nhận đơn BK-" + dangThu.id} onCancel={() => setDangThu(null)} closable={confirmingId === null} keyboard={confirmingId === null} mask={{ closable: false }} okText="Ghi nhận & xác nhận" cancelText="Hủy" onOk={handleConfirm} confirmLoading={confirmingId !== null} cancelButtonProps={{ disabled: confirmingId !== null }}>
          <Typography.Paragraph>{dangThu.customer_name} · {formatPrice(dangThu.total_amount)}</Typography.Paragraph>
          <Form layout="vertical" disabled={confirmingId !== null}>
            <Form.Item htmlFor="GuideBookings-field-1" label="Số tiền vừa thu" help="Chỉ điền khoản tiền bạn vừa nhận, tránh ghi trùng khoản văn phòng đã thu.">
              <InputNumber id="GuideBookings-field-1" min={0} precision={0} value={soTien === "" ? null : Number(soTien)} onChange={(value) => setSoTien(value === null ? "" : String(value))} suffix="₫" style={{ width: "100%" }} />
            </Form.Item>
            <Form.Item htmlFor="GuideBookings-field-2" label="Hình thức"><Select id="GuideBookings-field-2" value={hinhThuc} onChange={setHinhThuc} options={[{ value: "cash", label: "Tiền mặt" }, { value: "bank_transfer", label: "Chuyển khoản" }]} /></Form.Item>
          </Form>
          <Alert showIcon type="info" title="Để trống chỉ được khi văn phòng đã ghi nhận khoản thu từ trước." />
        </Modal>
      )}
    </div>
  );
};

export default GuideBookings;
