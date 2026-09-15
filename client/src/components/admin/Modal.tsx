import { type FormEvent, type ReactNode } from "react";
import { Flex, Modal as AntModal, Typography } from "antd";

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: ReactNode;
  subtitle?: ReactNode;
  size?: "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "4xl";
  children: ReactNode;
  footer?: ReactNode;
  onSubmit?: (e: FormEvent) => void;
}
const widths = { sm: 400, md: 520, lg: 640, xl: 800, "2xl": 960, "3xl": 1120, "4xl": 1280 };
export function Modal({ isOpen, onClose, title, subtitle, size = "lg", children, footer, onSubmit }: ModalProps) {
  const content = <Flex vertical gap="middle">{subtitle && <Typography.Text type="secondary">{subtitle}</Typography.Text>}{children}</Flex>;
  const actions = footer && <Flex justify="end" gap="small" wrap style={{ marginTop: 24 }}>{footer}</Flex>;
  return <AntModal open={isOpen} onCancel={onClose} title={title} width={widths[size]} footer={null}
    mask={{ closable: false }} destroyOnHidden styles={{ body: { maxHeight: "75vh", overflowY: "auto" } }}>
    {onSubmit ? <form onSubmit={onSubmit}>{content}{actions}</form> : <>{content}{actions}</>}
  </AntModal>;
}
export default Modal;
