import { Alert, Button, Card, Col, Descriptions, Drawer, Empty, Flex, Form, Input, Row, Select, Skeleton, Statistic, Table, Tag, Timeline, Typography, theme } from "antd";
import type { TableColumnsType } from "antd";
import { useEffect, useRef, useState } from "react";
import { ArrowDownLeft, ArrowUpRight, Download, RefreshCw, Search } from "lucide-react";
import adminService from "@/services/adminService";
import type { TransactionFilters, TransactionRow } from "@/services/adminService";
import type { BookingLedger } from "@/types";
import { DateRangePicker } from "@/components/admin/AdminDateRangePicker";
import { formatDateTime, formatPrice } from "@/utils/format";

const { Text, Title } = Typography;
const METHODS = [
  { value: "", label: "Tất cả hình thức" },
  { value: "bank_transfer", label: "Chuyển khoản" },
  { value: "cash", label: "Tiền mặt" },
  { value: "gateway", label: "Cổng thanh toán" },
];
const KINDS = [
  { value: "", label: "Tất cả loại giao dịch" },
  { value: "deposit", label: "Tiền cọc" },
  { value: "balance", label: "Thanh toán phần còn lại" },
  { value: "refund", label: "Hoàn tiền" },
  { value: "surcharge", label: "Thu phụ phí sự cố" },
  { value: "surcharge_refund", label: "Hoàn do sự cố" },
];
const messageOf = (err: unknown, fallback: string) =>
  (err as { response?: { data?: { message?: string } } })?.response?.data?.message || fallback;

/** Tổng, bảng và CSV dùng cùng bộ lọc; lịch sử của một đơn luôn lấy toàn bộ. */
export default function TransactionRegister() {
  const { token } = theme.useToken();
  const [rows, setRows] = useState<TransactionRow[]>([]);
  const [totals, setTotals] = useState<{ in: number; out: number; net: number; count: number } | null>(null);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<TransactionFilters>({});
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState("");
  const [exportError, setExportError] = useState("");
  const [reload, setReload] = useState(0);
  const [selected, setSelected] = useState<TransactionRow | null>(null);
  const [ledger, setLedger] = useState<BookingLedger | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState("");
  const [detailReload, setDetailReload] = useState(0);
  const requestId = useRef(0);

  const changeFilters = (patch: TransactionFilters, reset = false) => {
    requestId.current += 1;
    setLoading(true);
    setPage(1);
    setFilters((previous) => reset ? patch : { ...previous, ...patch });
  };
  const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value ?? "").trim() !== "")) as TransactionFilters;

  useEffect(() => {
    const id = ++requestId.current;
    let active = true;
    const timer = setTimeout(async () => {
      setLoading(true);
      setError("");
      const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value ?? "").trim() !== "")) as TransactionFilters;
      try {
        const result = await adminService.getTransactions({ ...query, page });
        if (!active || id !== requestId.current) return;
        if (!result) throw new Error("Empty response");
        setRows(result.data);
        setTotals(result.totals);
      } catch (err) {
        if (!active || id !== requestId.current) return;
        setRows([]);
        setTotals(null);
        setError(messageOf(err, "Không tải được sổ giao dịch. Vui lòng thử lại."));
      } finally {
        if (active && id === requestId.current) setLoading(false);
      }
    }, 300);
    return () => { active = false; clearTimeout(timer); };
  }, [filters, page, reload]);

  useEffect(() => {
    if (!selected) return;
    let active = true;
    adminService.getBookingLedger(selected.booking_id).then((result) => {
      if (!active) return;
      if (!result) throw new Error("Empty response");
      setLedger(result);
    }).catch((err) => {
      if (active) setDetailError(messageOf(err, "Không tải được lịch sử thu–hoàn của đơn."));
    }).finally(() => { if (active) setDetailLoading(false); });
    return () => { active = false; };
  }, [selected, detailReload]);

  const exportCsv = async () => {
    setExporting(true);
    setExportError("");
    try { await adminService.exportTransactions(params); }
    catch (err) { setExportError(messageOf(err, "Không tải được tệp CSV.")); }
    finally { setExporting(false); }
  };

  const openDetail = (row: TransactionRow) => {
    setLedger(null);
    setDetailLoading(true);
    setDetailError("");
    setSelected(row);
  };

  const columns: TableColumnsType<TransactionRow> = [
    { title: "Giao dịch / thời gian", key: "transaction", width: 180, render: (_, row) => <Flex vertical gap={4}>
      <Button type="link" style={{ padding: 0, height: "auto", alignSelf: "flex-start" }} onClick={() => openDetail(row)}>GD-{row.id}</Button>
      <Text type="secondary">{row.paid_at ? formatDateTime(row.paid_at) : "Chưa có thời gian"}</Text>
    </Flex> },
    { title: "Đơn đặt / khách hàng", key: "booking", width: 260, render: (_, row) => <Flex vertical gap={4}>
      <Flex gap="small" align="center" wrap>
        <Button type="link" style={{ padding: 0, height: "auto" }} onClick={() => openDetail(row)}>BK-{row.booking_id}</Button>
        <Text strong>{row.customer_name || "Khách chưa có tên"}</Text>
      </Flex>
      <Text type="secondary" ellipsis={{ tooltip: row.tour_title }}>{row.tour_title || "—"}</Text>
    </Flex> },
    { title: "Nội dung", key: "kind", width: 190, render: (_, row) => <Flex vertical gap={6}>
      <div><Tag color={row.direction === "in" ? "green" : "volcano"}>{row.direction === "in" ? "Thu tiền" : "Hoàn tiền"}</Tag></div>
      <Text>{row.kind_label}</Text>
    </Flex> },
    { title: "Số tiền", key: "amount", width: 170, align: "right", render: (_, row) => <Text strong style={{ color: row.direction === "in" ? token.colorSuccessText : token.colorErrorText, fontVariantNumeric: "tabular-nums", whiteSpace: "nowrap" }}>
      {row.direction === "in" ? "+" : "−"}{formatPrice(row.amount)}
    </Text> },
    { title: "Hình thức / chứng từ", key: "reference", width: 230, render: (_, row) => <Flex vertical gap={4}>
      <Text>{row.method_label || "Chưa ghi hình thức"}</Text>
      {row.reference ? <Text code copyable={{ text: row.reference }} style={{ overflowWrap: "anywhere" }}>{row.reference}</Text> : <Text type="secondary">Chưa có mã chứng từ</Text>}
    </Flex> },
    { title: "Người ghi nhận", key: "actor", width: 155, render: (_, row) => <Text>{row.recorded_by || "Hệ thống"}</Text> },
  ];
  const activeFilters = Object.keys(params).length;

  return <Flex vertical gap="middle">
    <Flex justify="space-between" align="center" wrap gap="small">
      <div><Title level={4} style={{ margin: 0 }}>Lịch sử thu & hoàn tiền</Title><Text type="secondary">Bấm mã giao dịch hoặc mã đơn để xem chi tiết và toàn bộ lịch sử của đơn.</Text></div>
      <Flex gap="small">
        <Button icon={<RefreshCw size={16} />} loading={loading} onClick={() => { setLoading(true); setReload((value) => value + 1); }}>Tải lại</Button>
        <Button icon={<Download size={16} />} loading={exporting} disabled={loading || !totals?.count || !!error} onClick={exportCsv}>Xuất CSV</Button>
      </Flex>
    </Flex>

    <Card size="small">
      <Form layout="vertical">
        <Row gutter={[16, 12]}>
          <Col xs={24} lg={12}><Form.Item label="Tìm giao dịch" style={{ marginBottom: 0 }}>
            <Input prefix={<Search size={16} />} allowClear maxLength={100} value={filters.q || ""} placeholder="Mã đơn BK-123, GD-456, chứng từ, tên hoặc email khách" onChange={(event) => changeFilters({ q: event.target.value })} />
          </Form.Item></Col>
          <Col xs={24} lg={12}><Form.Item style={{ marginBottom: 0 }}>
            <DateRangePicker label="Thời gian giao dịch" withTime maxDate={new Date()} value={{ from: filters.from || "", to: filters.to || "" }} onChange={(range) => changeFilters(range)} />
          </Form.Item></Col>
          <Col xs={24} sm={8}><Form.Item label="Chiều tiền" style={{ marginBottom: 0 }}><Select value={filters.direction || ""} options={[{ value: "", label: "Tất cả tiền vào / ra" }, { value: "in", label: "Tiền vào" }, { value: "out", label: "Tiền hoàn ra" }]} onChange={(direction) => changeFilters({ direction: direction as TransactionFilters["direction"], kind: "" })} /></Form.Item></Col>
          <Col xs={24} sm={8}><Form.Item label="Loại giao dịch" style={{ marginBottom: 0 }}><Select value={filters.kind || ""} options={KINDS.filter((kind) => !kind.value || !filters.direction || (filters.direction === "out" ? kind.value.includes("refund") : !kind.value.includes("refund")))} onChange={(kind) => changeFilters({ kind: kind as TransactionFilters["kind"] })} /></Form.Item></Col>
          <Col xs={24} sm={8}><Form.Item label="Hình thức thanh toán" style={{ marginBottom: 0 }}><Select value={filters.method || ""} options={METHODS} onChange={(method) => changeFilters({ method: method as TransactionFilters["method"] })} /></Form.Item></Col>
        </Row>
      </Form>
      {!!activeFilters && <Flex justify="space-between" align="center" wrap gap="small" style={{ marginTop: 16 }}><Text type="secondary">Đang áp dụng bộ lọc · Tổng tiền và CSV theo cùng kết quả.</Text><Button onClick={() => changeFilters({}, true)}>Xóa bộ lọc</Button></Flex>}
    </Card>

    <Row gutter={[16, 12]}>
      {[
        { title: "Tổng tiền vào", amount: totals?.in, color: token.colorSuccessText, icon: <ArrowDownLeft size={20} />, hint: "Các khoản thu trong kết quả lọc" },
        { title: "Tổng tiền hoàn ra", amount: totals?.out, color: token.colorErrorText, icon: <ArrowUpRight size={20} />, hint: "Các khoản hoàn đã ghi nhận" },
        { title: "Chênh lệch thu − hoàn", amount: totals?.net, color: token.colorText, icon: undefined, hint: "Theo bộ lọc, không phải số dư tài khoản" },
      ].map((item) => <Col xs={24} md={8} key={item.title}><Card size="small" loading={loading}>
        <Statistic title={item.title} value={item.amount ?? 0} formatter={(value) => item.amount == null ? "—" : formatPrice(Number(value))} prefix={item.icon} styles={{ content: { color: item.color, fontSize: 24, fontVariantNumeric: "tabular-nums" } }} />
        <Text type="secondary">{item.hint}</Text>
      </Card></Col>)}
    </Row>

    {error && <Alert type="error" showIcon title={error} action={<Button onClick={() => { setLoading(true); setReload((value) => value + 1); }}>Thử lại</Button>} />}
    {exportError && <Alert type="error" showIcon title={exportError} closable onClose={() => setExportError("")} />}
    <Card size="small" title={totals ? `${totals.count.toLocaleString("vi-VN")} giao dịch` : "Danh sách giao dịch"} extra={<Text type="secondary">Mới nhất trước</Text>}>
      <Table<TransactionRow> rowKey="id" columns={columns} dataSource={rows} loading={loading} scroll={{ x: 1185 }}
        locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={error ? "Dữ liệu chưa tải được" : "Không có giao dịch phù hợp"}>{!!activeFilters && !error && <Button onClick={() => changeFilters({}, true)}>Xóa bộ lọc</Button>}</Empty> }}
        pagination={{ current: page, pageSize: 25, total: totals?.count ?? 0, showSizeChanger: false, showTotal: (total, range) => `${range[0]}–${range[1]} / ${total} giao dịch`, onChange: (next) => { requestId.current += 1; setLoading(true); setPage(next); } }} />
    </Card>

    <Drawer open={!!selected} onClose={() => setSelected(null)} title={selected ? `Giao dịch GD-${selected.id} · Đơn BK-${selected.booking_id}` : "Chi tiết giao dịch"} size={680}>
      {selected && <Flex vertical gap="large">
        <Card size="small">
          <Flex justify="space-between" align="start" gap="middle" wrap>
            <div><Tag color={selected.direction === "in" ? "green" : "volcano"}>{selected.kind_label}</Tag><Title level={3} style={{ color: selected.direction === "in" ? token.colorSuccessText : token.colorErrorText, marginTop: 12 }}>{selected.direction === "in" ? "+" : "−"}{formatPrice(selected.amount)}</Title></div>
            <Text type="secondary">{selected.paid_at ? formatDateTime(selected.paid_at) : "—"}</Text>
          </Flex>
          <Descriptions column={1} size="small" items={[
            { key: "customer", label: "Khách hàng", children: selected.customer_name || "—" },
            { key: "tour", label: "Tour", children: selected.tour_title || "—" },
            { key: "method", label: "Hình thức", children: selected.method_label || "—" },
            { key: "reference", label: "Mã chứng từ", children: selected.reference ? <Text copyable style={{ overflowWrap: "anywhere" }}>{selected.reference}</Text> : "Chưa có mã chứng từ" },
            { key: "actor", label: "Người ghi", children: selected.recorded_by || "Hệ thống" },
            { key: "note", label: "Ghi chú", children: <Text style={{ whiteSpace: "pre-wrap" }}>{selected.note || "Không có ghi chú"}</Text> },
          ]} />
        </Card>
        <Flex vertical gap="middle">
          <div><Title level={4} style={{ margin: 0 }}>Lịch sử đơn BK-{selected.booking_id}</Title><Text type="secondary">Toàn bộ các lần thu và hoàn, không giới hạn bởi bộ lọc bên ngoài.</Text></div>
          {detailLoading ? <Skeleton active /> : detailError ? <Alert type="error" showIcon title={detailError} action={<Button onClick={() => { setDetailLoading(true); setDetailError(""); setDetailReload((value) => value + 1); }}>Thử lại</Button>} /> : ledger && <>
            <Descriptions bordered size="small" column={1} items={[
              { key: "total", label: "Giá trị đơn", children: formatPrice(ledger.total_amount) },
              { key: "net", label: "Đã thu cho tour (trừ hoàn)", children: formatPrice(ledger.net_paid) },
              ...(!["cancelled", "transferred"].includes(selected.booking_status || "") ? [{ key: "due", label: "Còn phải thu", children: formatPrice(ledger.balance_due) }] : []),
              { key: "refund", label: "Còn phải hoàn khách", children: formatPrice(ledger.refund_outstanding) },
            ]} />
            <Text type="secondary">Phụ phí sự cố được liệt kê riêng trong lịch sử; không cộng vào tiền thu cho giá tour.</Text>
            {ledger.entries.length === 0 ? <Empty description="Đơn chưa có giao dịch" /> : <Timeline items={[...ledger.entries].sort((a, b) => new Date(b.paid_at).getTime() - new Date(a.paid_at).getTime() || b.id - a.id).map((entry) => ({
              color: entry.direction === "out" ? "red" : "green",
              content: <Flex vertical gap={6}>
                <Flex justify="space-between" align="center" wrap gap="small"><Text strong>GD-{entry.id} · {entry.kind_label}</Text><Text strong style={{ color: entry.direction === "out" ? token.colorErrorText : token.colorSuccessText }}>{entry.direction === "out" ? "−" : "+"}{formatPrice(entry.amount)}</Text></Flex>
                {entry.id === selected.id && <div><Tag color="blue">Giao dịch đang xem</Tag></div>}
                <Text type="secondary">{entry.paid_at ? formatDateTime(entry.paid_at) : "—"} · {METHODS.find((method) => method.value === entry.method)?.label || entry.method || "Chưa có hình thức"} · {entry.recorded_by || "Hệ thống"}</Text>
                {entry.reference && <Text code copyable style={{ overflowWrap: "anywhere" }}>{entry.reference}</Text>}
                {entry.note && <Text style={{ whiteSpace: "pre-wrap" }}>{entry.note}</Text>}
              </Flex>,
            }))} />}
            <Button onClick={() => { changeFilters({ q: `BK-${selected.booking_id}` }, true); setSelected(null); }}>Lọc các giao dịch của đơn này</Button>
          </>}
        </Flex>
      </Flex>}
    </Drawer>
  </Flex>;
}
