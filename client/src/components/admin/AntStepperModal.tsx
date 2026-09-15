import type { ReactNode } from "react";
import { Alert, Button, Flex, Modal, Steps, Typography } from "antd";
import type { BuocModal } from "./StepperModal";

interface Props {
  title: ReactNode;
  subtitle?: ReactNode;
  buoc: BuocModal[];
  hienTai: number;
  onDoiBuoc: (step: number) => void;
  onClose: () => void;
  onHoanTat: () => void;
  nhanHoanTat: string;
  dangChay?: boolean;
  error?: string;
  danger?: boolean;
}

export function AntStepperModal({ title, subtitle, buoc, hienTai, onDoiBuoc, onClose, onHoanTat, nhanHoanTat, dangChay, error, danger }: Props) {
  const step = Math.min(Math.max(hienTai, 0), buoc.length - 1);
  const current = buoc[step];
  if (!current) return null;
  const last = step === buoc.length - 1;
  // Recheck earlier steps as well: refreshed previews can invalidate a previous selection.
  const blocked = buoc.slice(0, step + 1).find((item) => item.chuaXong)?.chuaXong;
  return (
    <Modal open title={title} width={800} onCancel={onClose} closable={!dangChay}
      keyboard={!dangChay} mask={{ closable: false }}
      styles={{ body: { maxHeight: "65vh", overflowY: "auto" } }}
      footer={<Flex justify="space-between" align="center" wrap gap="small">
        <Typography.Text type="secondary">{blocked}</Typography.Text>
        <Flex gap="small">
          <Button disabled={dangChay} onClick={() => step === 0 ? onClose() : onDoiBuoc(step - 1)}>{step === 0 ? "Đóng" : "Quay lại"}</Button>
          <Button type="primary" danger={danger && last} loading={dangChay} disabled={!!blocked}
            onClick={() => last ? onHoanTat() : onDoiBuoc(step + 1)}>{last ? nhanHoanTat : "Tiếp tục"}</Button>
        </Flex>
      </Flex>}>
      <Flex vertical gap="middle">
        {subtitle && <Typography.Text type="secondary">{subtitle}</Typography.Text>}
        <Steps current={step} size="small" onChange={onDoiBuoc}
          items={buoc.map((item, index) => ({ title: item.ten, disabled: !!dangChay || index >= step }))} />
        {error && <Alert type="error" showIcon title={error} />}
        {current.moTa && <Typography.Paragraph>{current.moTa}</Typography.Paragraph>}
        <Flex vertical gap="middle">{current.noiDung}</Flex>
      </Flex>
    </Modal>
  );
}
