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

  const bgClass =
    type === "success"
      ? "bg-emerald-550 border-emerald-200 text-emerald-800 bg-emerald-50"
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
      <div className={`flex items-center gap-3 p-4 rounded-2xl border shadow-lg ${bgClass} transition-all duration-300`}>
        <div className="shrink-0">{icon}</div>
        <p className="text-sm font-semibold flex-1">{message}</p>
        <button
          onClick={onClose}
          className="shrink-0 p-1 rounded-lg hover:bg-black/5 text-gray-400 hover:text-gray-600 transition-colors"
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
      ? "bg-rose-600 hover:bg-rose-700 focus:ring-rose-500"
      : "bg-primary-600 hover:bg-primary-700 focus:ring-primary-500";

  const iconBg =
    type === "danger"
      ? "bg-rose-50 text-rose-600 border border-rose-100"
      : "bg-amber-50 text-amber-600 border border-amber-100";

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
    <div className="fixed inset-0 z-55 flex items-center justify-center p-4 bg-black/45 animate-fade-in pointer-events-auto">
      <div className="bg-white w-full max-w-sm rounded-3xl shadow-2xl border border-gray-100 p-6 flex flex-col items-center text-center animate-scale-up">
        {/* Icon */}
        <div className={`p-3.5 rounded-2xl ${iconBg} mb-4`}>{icon}</div>

        {/* Title */}
        <h4 className="text-lg font-bold text-gray-900 mb-1">{title}</h4>

        {/* Message */}
        <p className="text-sm text-gray-500 mb-6">{message}</p>

        {/* Buttons */}
        <div className="flex w-full gap-2">
          <button
            onClick={onCancel}
            className="flex-1 py-2.5 text-sm font-semibold border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl transition-colors"
          >
            {cancelText}
          </button>
          <button
            onClick={onConfirm}
            className={`flex-1 py-2.5 text-sm font-semibold text-white rounded-xl shadow-md transition-colors ${btnBg}`}
          >
            {confirmText}
          </button>
        </div>
      </div>
    </div>
  );
};
