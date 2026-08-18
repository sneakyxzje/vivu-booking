import React, { useEffect } from "react";

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  size?: "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "4xl";
  children: React.ReactNode;
  footer?: React.ReactNode;
  onSubmit?: (e: React.FormEvent) => void;
}

export const Modal: React.FC<ModalProps> = ({
  isOpen,
  onClose,
  title,
  subtitle,
  size = "lg",
  children,
  footer,
  onSubmit,
}) => {
  // Prevent body scrolling when modal is open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "unset";
    }
    return () => {
      document.body.style.overflow = "unset";
    };
  }, [isOpen]);

  if (!isOpen) return null;

  const sizeClasses = {
    sm: "max-w-sm",
    md: "max-w-md",
    lg: "max-w-lg",
    xl: "max-w-xl",
    "2xl": "max-w-2xl",
    "3xl": "max-w-3xl",
    "4xl": "max-w-4xl",
  };

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  const ContentWrapper = onSubmit ? "form" : "div";

  return (
    /*
      Lớp phủ đen 50% — đúng `{colors.scrim}` của DESIGN.md. Bỏ `backdrop-blur`: hệ này tách
      tầng bằng lớp phủ và bo góc, không bằng hiệu ứng làm mờ nền.
    */
    <div
      onClick={handleBackdropClick}
      className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 animate-fade-in"
    >
      <div
        className={`relative bg-canvas w-full ${sizeClasses[size]} rounded-xl shadow-2xl overflow-hidden`}
      >
        {/*
          Bỏ thanh màu dọc bên trái tiêu đề. Hệ này không dùng vạch trang trí cạnh thẻ — thứ bậc
          do cỡ chữ và khoảng trắng gánh, thêm vạch màu là thêm một tín hiệu không mang thông tin.
        */}
        <div className="px-6 pt-6 pb-4 border-b border-hairline-soft flex items-start justify-between gap-4">
          <div className="min-w-0">
            <h3 className="text-display-sm text-ink">{title}</h3>
            {subtitle && (
              <p className="text-body-sm text-muted mt-1">{subtitle}</p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Đóng"
            className="shrink-0 w-8 h-8 flex items-center justify-center bg-surface-strong hover:bg-hairline-soft text-ink rounded-full transition-colors cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* @ts-ignore */}
        <ContentWrapper onSubmit={onSubmit} className="flex flex-col">
          <div className="px-6 py-6 space-y-5 max-h-[70vh] overflow-y-auto text-body-md text-body">
            {children}
          </div>

          {footer && (
            <div className="bg-surface-soft px-6 py-4 flex justify-end gap-3 border-t border-hairline-soft">
              {footer}
            </div>
          )}
        </ContentWrapper>
      </div>
    </div>
  );
};

export default Modal;
