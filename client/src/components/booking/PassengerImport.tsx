import { useMemo, useRef, useState } from "react";
import { Alert, Button, Flex, Modal, Select, Table } from "antd";
import { Upload, Download } from "lucide-react";
import { passengerTypeLabels, previewPassengers } from "@/utils/passengerImport";
import type { ImportContext, ImportTable, PassengerRow } from "@/utils/passengerImport";

type Props = {
  context: ImportContext;
  disabled: boolean;
  onApply: (passengers: PassengerRow[]) => void;
};

export default function PassengerImport({ context, disabled, onApply }: Props) {
  const input = useRef<HTMLInputElement>(null);
  const requestId = useRef(0);
  const [loading, setLoading] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState("");
  const [fileName, setFileName] = useState("");
  const [tables, setTables] = useState<ImportTable[]>([]);
  const [selected, setSelected] = useState(0);
  const [open, setOpen] = useState(false);
  const preview = useMemo(() => tables[selected] ? previewPassengers(tables[selected], context) : null, [tables, selected, context]);

  const read = async (file: File) => {
    if (disabled) return;
    const id = ++requestId.current;
    setLoading(true);
    setError("");
    setTables([]);
    try {
      const { readPassengerFile } = await import("@/utils/passengerImportFile");
      const result = await readPassengerFile(file);
      if (id !== requestId.current) return;
      setTables(result);
      setSelected(0);
      setFileName(file.name);
      setOpen(true);
    } catch (err) {
      if (id === requestId.current) setError(err instanceof Error ? err.message : "Không đọc được file. Hãy kiểm tra định dạng và thử lại.");
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  };

  const template = async () => {
    setDownloading(true);
    setError("");
    try {
      const { passengerExcelTemplate } = await import("@/utils/passengerExcelTemplate");
      const url = URL.createObjectURL(new Blob([passengerExcelTemplate(context)], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" }));
      const link = document.createElement("a");
      link.href = url;
      link.download = "ViVu-Danh-sach-hanh-khach.xlsx";
      link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
      setError("Không tạo được mẫu Excel. Vui lòng thử lại.");
    } finally {
      setDownloading(false);
    }
  };

  return <div className="space-y-3">
    <Flex gap="small" wrap>
      <Button icon={<Upload size={16} />} loading={loading} disabled={disabled || loading} onClick={() => input.current?.click()}>Nhập từ file</Button>
      <Button icon={<Download size={16} />} loading={downloading} onClick={template}>Tải mẫu Excel</Button>
    </Flex>
    <input ref={input} type="file" hidden accept=".xlsx,.csv,.docx" disabled={disabled || loading} aria-label="Chọn file danh sách hành khách" onChange={event => {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (file) void read(file);
    }} />
    {error && <Alert showIcon type="error" title="Không nhập được file" description={error} />}
    <Modal open={open} width={1000} title={`Xem trước: ${fileName}`} okText="Thay danh sách trên form" cancelText="Hủy" onCancel={() => setOpen(false)} okButtonProps={{ disabled: disabled || !preview || preview.errors.length > 0 }} onOk={() => {
      if (disabled || !preview || preview.errors.length) return;
      onApply(preview.passengers);
      setOpen(false);
      setTables([]);
    }}>
      <Flex vertical gap="middle">
        {tables.length > 1 && <Select aria-label="Chọn sheet hoặc bảng để nhập" value={selected} options={tables.map((table, i) => ({ value: i, label: table.name }))} onChange={setSelected} />}
        <Alert showIcon type="warning" title="Áp dụng sẽ thay toàn bộ danh sách trên form; vị trí thiếu sẽ để trống. Thay đổi chưa được lưu." />
        {preview && preview.errors.length > 0 && <Alert showIcon type="error" title={`Cần sửa ${preview.errors.length} lỗi trong file rồi nhập lại`} description={<ul className="list-disc pl-5 max-h-48 overflow-y-auto">{preview.errors.map((message, i) => <li key={i}>{message}</li>)}</ul>} />}
        {preview && preview.warnings.length > 0 && <Alert showIcon type="info" title="Lưu ý" description={<ul className="list-disc pl-5">{preview.warnings.map((message, i) => <li key={i}>{message}</li>)}</ul>} />}
        <Table rowKey="importIndex" size="small" scroll={{ x: 1000 }} pagination={{ pageSize: 10, hideOnSinglePage: true }} dataSource={preview?.passengers.map((p, i) => ({ ...p, importIndex: i })) ?? []} columns={[
          { title: "Họ tên", dataIndex: "name" },
          { title: "Loại khách", dataIndex: "type", render: (type: PassengerRow["type"]) => passengerTypeLabels[type] },
          { title: "Ngày sinh", dataIndex: "date_of_birth" },
          { title: "Giới tính", dataIndex: "gender", render: (gender: string) => ({ male: "Nam", female: "Nữ", other: "Khác" })[gender] ?? "—" },
          { title: "Loại giấy tờ", dataIndex: "id_type" },
          { title: "Số giấy tờ", dataIndex: "identity_number" },
          { title: "Điện thoại", dataIndex: "phone" },
          { title: "Yêu cầu riêng", dataIndex: "special_request" },
          { title: "Đại diện", dataIndex: "is_contact", render: (value: boolean) => value ? "Có" : "" },
        ]} />
      </Flex>
    </Modal>
  </div>;
}
