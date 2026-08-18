import React, { useEffect } from "react";

// --- TOAST COMPONENT ---
export interface ToastProps {
  message: string;
  type: "success" | "error" | "info";
  isOpen: boolean;
  onClose: () => void;
  duration?: number;
}

export const Toast: React.FC<ToastProps> = ({
  message,
  type,
  isOpen,
  onClose,
  duration = 3000,
}) => {
  useEffect(() => {
    if (isOpen) {
      const timer = setTimeout(() => {
        onClose();
      }, duration);
      return () => clearTimeout(timer);
    }
  }, [isOpen, duration, onClose]);

  if (!isOpen) return null;

  /*
    Màu ngữ nghĩa (tốt / lỗi / thông tin) tách khỏi màu thương hiệu — đúng nguyên tắc "màu
    thương hiệu chỉ dùng cho hành động chính". Ba lớp này trước đây có một lỗi gõ:
    `bg-emerald-550` không tồn tại trong Tailwind, và ngay sau đó lại khai đè `bg-emerald-50`.
  */
  const bgClass =
    type === "success"
      ? "bg-emerald-50 border-emerald-200 text-emerald-800"
      : type === "error"
      ? "bg-rose-50 border-rose-200 text-rose-800"
      : "bg-blue-50 border-blue-200 text-blue-800";

  const icon =
    type === "success" ? (
      <svg className="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    ) : type === "error" ? (
      <svg className="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    ) : (
      <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    );

  return (
    <div className="fixed bottom-5 right-5 z-55 max-w-sm w-full animate-slide-in pointer-events-auto">
      <div className={`flex items-center gap-3 p-4 rounded-lg border shadow-float ${bgClass}`}>
        <div className="shrink-0">{icon}</div>
        <p className="text-body-sm font-medium flex-1">{message}</p>
        <button
          onClick={onClose}
          aria-label="Đóng thông báo"
          className="shrink-0 p-1 rounded-full hover:bg-black/5 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  );
};

// --- CONFIRM MODAL COMPONENT ---
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

export const ConfirmModal: React.FC<ConfirmModalProps> = ({
  message,
  isOpen,
  onConfirm,
  onCancel,
  title = "Xác nhận hành động",
  confirmText = "Xác nhận",
  cancelText = "Hủy bỏ",
  type = "danger",
}) => {
  if (!isOpen) return null;

  const btnBg =
    type === "danger"
      ? "bg-rose-600 hover:bg-rose-700"
      : "bg-primary-600 hover:bg-primary-700";

  const iconBg =
    type === "danger"
      ? "bg-rose-50 text-rose-600"
      : "bg-amber-50 text-amber-600";

  const icon =
    type === "danger" ? (
      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
        />
      </svg>
    ) : (
      <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
        />
      </svg>
    );

  return (
    <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/50 animate-fade-in pointer-events-auto">
      <div className="bg-canvas w-full max-w-sm rounded-xl shadow-2xl p-6 flex flex-col items-center text-center animate-scale-up">
        {/* Vòng tròn cho biểu tượng — hệ này bo tròn mọi thứ trừ chính lưới trang. */}
        <div className={`w-14 h-14 flex items-center justify-center rounded-full ${iconBg} mb-4`}>
          {icon}
        </div>

        <h4 className="text-display-sm text-ink mb-1.5">{title}</h4>
        <p className="text-body-sm text-muted mb-6">{message}</p>

        {/*
          Hai nút cùng cỡ 48px như quy cách nút của hệ. Nút xác nhận không đổ bóng: bóng dành
          cho việc tách tầng, không dùng để làm nút trông "bấm được" hơn.
        */}
        <div className="flex w-full gap-2">
          <button
            onClick={onCancel}
            className="flex-1 min-h-12 px-4 text-button-md border border-hairline hover:bg-surface-soft text-ink rounded-lg transition-colors cursor-pointer"
          >
            {cancelText}
          </button>
          <button
            onClick={onConfirm}
            className={`flex-1 min-h-12 px-4 text-button-md text-white rounded-lg transition-colors cursor-pointer ${btnBg}`}
          >
            {confirmText}
          </button>
        </div>
      </div>
    </div>
  );
};
