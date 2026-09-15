import { useEffect, useEffectEvent, useId } from "react";
import { Modal, notification } from "antd";

export interface ToastProps {
  message: string;
  type: "success" | "error" | "info";
  isOpen: boolean;
  onClose: () => void;
  duration?: number;
}
export function Toast({ message, type, isOpen, onClose, duration = 3000 }: ToastProps) {
  const [api, holder] = notification.useNotification();
  const key = useId();
  const close = useEffectEvent(onClose);
  useEffect(() => {
    if (isOpen) api.open({ key, type, title: message, duration: duration / 1000, onClose: () => close(), placement: "topRight" });
    else api.destroy(key);
    return () => api.destroy(key);
  }, [api, key, message, type, isOpen, duration]);
  return holder;
}

export interface ConfirmModalProps {
  message: string;
  isOpen: boolean;
  onConfirm: () => void;
  onCancel: () => void;
  title?: string;
  confirmText?: string;
  cancelText?: string;
  type?: "danger" | "warning" | "info";
}
export function ConfirmModal({ message, isOpen, onConfirm, onCancel, title = "Xác nhận hành động", confirmText = "Xác nhận", cancelText = "Hủy bỏ", type = "danger" }: ConfirmModalProps) {
  return <Modal open={isOpen} title={title} onOk={onConfirm} onCancel={onCancel}
    okText={confirmText} cancelText={cancelText} okButtonProps={{ danger: type === "danger" }} mask={{ closable: false }}>{message}</Modal>;
}
