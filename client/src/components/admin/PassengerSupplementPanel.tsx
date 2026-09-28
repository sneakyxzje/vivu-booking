import { Alert, App, Button, Card, Flex, Form, Input, Modal, Select, Skeleton, Tag, Typography } from "antd";
import { useCallback, useEffect, useRef, useState } from "react";
import { passengerSupplementService } from "@/services/passengerSupplementService";
import type { PassengerSupplement, SupplementContext, SupplementInput } from "@/services/passengerSupplementService";
import { apiErrorMessage } from "@/utils/apiErrorMessage";
import { formatDateTime } from "@/utils/format";

const types = [
  { value: "adult", label: "Người lớn" },
  { value: "child", label: "Trẻ em" },
  { value: "infant", label: "Em bé" },
] as const;

export default function PassengerSupplementPanel({ bookingId, onChanged }: { bookingId: number; onChanged?: () => void | Promise<void> }) {
  const { message } = App.useApp();
  const [data, setData] = useState<SupplementContext | null>(null);
  const [loadError, setLoadError] = useState("");
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [sentItem, setSentItem] = useState<PassengerSupplement | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");
  const [downloading, setDownloading] = useState<number | null>(null);
  const [form] = Form.useForm<SupplementInput>();
  const [sentForm] = Form.useForm<{ sent_to: string; sent_note?: string }>();
  const requestKey = useRef("");
  const inFlight = useRef(false);
  const generation = useRef(0);

  const load = useCallback((signal?: AbortSignal) => {
    const current = ++generation.current;
    return passengerSupplementService.get(bookingId, signal).then(result => {
      if (current === generation.current && !signal?.aborted) {
        setData(result);
        setLoadError("");
      }
    }).catch(error => {
      if (current === generation.current && !signal?.aborted) setLoadError(apiErrorMessage(error, "Chưa tải được thông tin bổ sung."));
    }).finally(() => {
      if (current === generation.current && !signal?.aborted) setLoading(false);
    });
  }, [bookingId]);

  useEffect(() => {
    const controller = new AbortController();
    void load(controller.signal);
    return () => controller.abort();
  }, [load]);

  const afterSave = async () => {
    await load();
    try { await onChanged?.(); }
    catch { void message.warning("Đã lưu. Hãy tải lại danh sách để xem dữ liệu mới nhất."); }
  };
  const save = async (values: SupplementInput) => {
    if (inFlight.current) return;
    inFlight.current = true;
    setSaving(true);
    setFormError("");
    try {
      await passengerSupplementService.append(bookingId, { ...values, request_key: requestKey.current });
      setOpen(false);
      void message.success("Đã bổ sung hành khách. Hãy gửi bản cập nhật cho nhà cung cấp.");
      await afterSave();
    } catch (error) {
      setFormError(apiErrorMessage(error, "Chưa xác nhận được kết quả. Giữ nguyên thông tin và bấm lưu lại."));
    } finally { inFlight.current = false; setSaving(false); }
  };
  const markSent = async (values: { sent_to: string; sent_note?: string }) => {
    if (!sentItem || inFlight.current) return;
    inFlight.current = true;
    setSaving(true);
    setFormError("");
    try {
      await passengerSupplementService.markSent(bookingId, sentItem.id, values);
      setSentItem(null);
      void message.success("Đã ghi nhận việc gửi bản bổ sung.");
      await afterSave();
    } catch (error) { setFormError(apiErrorMessage(error, "Chưa ghi nhận được việc gửi.")); }
    finally { inFlight.current = false; setSaving(false); }
  };
  const download = async (id: number) => {
    setDownloading(id);
    try { await passengerSupplementService.download(bookingId, id); }
    catch (error) { void message.error(apiErrorMessage(error, "Không tải được bản bổ sung.")); }
    finally { setDownloading(null); }
  };

  if (loading && !data) return <Skeleton active paragraph={{ rows: 2 }} />;
  if (loadError) return <Alert showIcon type="error" title={loadError} action={<Button onClick={() => void load()}>Tải lại</Button>} />;
  if (!data) return null;
  const options = types.filter(type => data.is_group || data.remaining[type.value] > 0);
  return <Flex vertical gap="middle">
    <Flex align="center" justify="space-between" gap="small" wrap>
      <Typography.Text type={data.missing ? "warning" : "secondary"}>
        {data.missing ? `${data.deadline_passed ? "Quá hạn khai thông tin · " : ""}Còn thiếu ${data.missing}/${data.guests} hành khách` : `Đã khai ${data.declared}/${data.guests} hành khách`}
      </Typography.Text>
      {data.can_supplement && <Button type="primary" disabled={loading || saving} onClick={() => {
        requestKey.current = crypto.randomUUID();
        form.resetFields();
        form.setFieldsValue({ passengers: [{ name: "", type: options[0]?.value ?? "adult", id_type: "cccd" }] });
        setFormError(""); setOpen(true);
      }}>Bổ sung khách còn thiếu</Button>}
    </Flex>
    {!data.can_supplement && data.missing > 0 && <Typography.Text type="secondary">{data.unavailable_reason}</Typography.Text>}
    {data.supplements.map(item => <Card key={item.id} size="small" title={`Bổ sung ${item.passengers.length} khách · Chuyến #${item.tour_schedule_id}`}
      extra={<Tag color={item.sent_at ? "success" : "warning"}>{item.sent_at ? "Đã gửi nhà cung cấp" : "Chờ gửi nhà cung cấp"}</Tag>}>
      <Flex vertical gap="small">
        <Typography.Text>{item.passengers.map(p => p.name).join(", ")}</Typography.Text>
        <Typography.Text type="secondary">{formatDateTime(item.recorded_at)} · {item.created_by_name} · Người báo: {item.reported_by}</Typography.Text>
        <Typography.Text>{item.reason}</Typography.Text>
        {item.sent_at && <Typography.Text type="secondary">{item.sent_by_name} xác nhận đã gửi tới {item.sent_to} · {formatDateTime(item.sent_at)}{item.sent_note ? ` · ${item.sent_note}` : ""}</Typography.Text>}
        <Flex gap="small" wrap>
          <Button loading={downloading === item.id} onClick={() => void download(item.id)}>Tải bản bổ sung</Button>
          {!item.sent_at && <Button disabled={saving} onClick={() => { sentForm.resetFields(); setFormError(""); setSentItem(item); }}>Xác nhận đã gửi</Button>}
        </Flex>
      </Flex>
    </Card>)}
    <Modal open={open} title={`Bổ sung hành khách · BK${bookingId}`} width={760} onCancel={() => setOpen(false)}
      styles={{ body: { maxHeight: "65vh", overflowY: "auto" } }}
      closable={!saving} keyboard={!saving} mask={{ closable: false }} okText="Lưu bổ sung" cancelText="Đóng"
      confirmLoading={saving} cancelButtonProps={{ disabled: saving }} onOk={() => form.submit()}>
      <Form form={form} layout="vertical" onFinish={save} disabled={saving}>
        <Typography.Paragraph type="secondary">Còn {data.missing} suất chưa khai. Danh sách và điểm danh đã có được giữ nguyên.</Typography.Paragraph>
        {formError && <Alert showIcon type="error" title={formError} style={{ marginBottom: 16 }} />}
        <Form.List name="passengers">{(fields, { add, remove }) => <Flex vertical gap="small">
          {fields.map((field, index) => <Card key={field.key} size="small" title={`Hành khách ${index + 1}`}
            extra={fields.length > 1 && <Button type="text" danger onClick={() => remove(field.name)}>Bỏ dòng</Button>}>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "0 16px" }}>
              <Form.Item name={[field.name, "name"]} label="Họ và tên" rules={[{ required: true, whitespace: true, message: "Nhập họ tên hành khách." }, { max: 255 }]}><Input maxLength={255} /></Form.Item>
              <Form.Item name={[field.name, "type"]} label="Loại khách" rules={[{ required: true, message: "Chọn loại khách." }]}><Select options={options.map(type => ({ ...type, label: data.is_group ? type.label : `${type.label} (còn ${data.remaining[type.value]})` }))} /></Form.Item>
              <Form.Item name={[field.name, "date_of_birth"]} label="Ngày sinh"><Input type="date" /></Form.Item>
              <Form.Item name={[field.name, "phone"]} label="Điện thoại"><Input maxLength={20} /></Form.Item>
              <Form.Item name={[field.name, "id_type"]} label="Loại giấy tờ"><Select allowClear options={[{ value: "cccd", label: "Căn cước" }, { value: "passport", label: "Hộ chiếu" }, { value: "birth_certificate", label: "Giấy khai sinh" }, { value: "cmnd", label: "CMND" }]} /></Form.Item>
              <Form.Item name={[field.name, "identity_number"]} label="Số giấy tờ"><Input maxLength={50} /></Form.Item>
            </div>
            <Form.Item name={[field.name, "special_request"]} label="Yêu cầu riêng"><Input maxLength={500} /></Form.Item>
          </Card>)}
          {fields.length < Math.min(data.missing, 50) && <Button onClick={() => add({ name: "", type: options[0]?.value ?? "adult", id_type: "cccd" })}>Thêm hành khách</Button>}
        </Flex>}</Form.List>
        <Form.Item name="reported_by" label="Người báo thông tin" style={{ marginTop: 16 }} rules={[{ required: true, whitespace: true, message: "Nhập tên HDV hoặc người báo thông tin." }, { max: 255 }]}><Input placeholder="Tên HDV hoặc người đặt" maxLength={255} /></Form.Item>
        <Form.Item name="reason" label="Lý do bổ sung" rules={[{ required: true, whitespace: true, min: 5, max: 1000, message: "Nhập lý do từ 5 đến 1.000 ký tự." }]}><Input.TextArea rows={2} maxLength={1000} /></Form.Item>
      </Form>
    </Modal>
    <Modal open={!!sentItem} title="Xác nhận đã gửi bản bổ sung" onCancel={() => setSentItem(null)}
      closable={!saving} keyboard={!saving} mask={{ closable: false }} okText="Ghi nhận đã gửi" cancelText="Đóng"
      confirmLoading={saving} cancelButtonProps={{ disabled: saving }} onOk={() => sentForm.submit()}>
      <Typography.Paragraph type="secondary">Ghi nhận sau khi bạn đã gửi cho các nhà cung cấp liên quan.</Typography.Paragraph>
      {formError && <Alert showIcon type="error" title={formError} />}
      <Form form={sentForm} layout="vertical" onFinish={markSent} disabled={saving}>
        <Form.Item name="sent_to" label="Đã gửi cho" rules={[{ required: true, whitespace: true, min: 3, max: 500, message: "Nhập các đơn vị hoặc người đã nhận." }]}><Input placeholder="Khách sạn, nhà xe, đầu mối nhận…" maxLength={500} /></Form.Item>
        <Form.Item name="sent_note" label="Ghi chú / kênh gửi"><Input.TextArea rows={2} maxLength={1000} /></Form.Item>
      </Form>
    </Modal>
  </Flex>;
}
