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
    <div
      onClick={handleBackdropClick}
      className="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-fade-in"
    >
      <div
        className={`relative bg-white w-full ${sizeClasses[size]} rounded-lg shadow-2xl border border-gray-100 overflow-hidden transform transition-all duration-300`}
      >
        {/* Modal Header */}
        <div className="p-6 pb-4 border-b border-gray-100 flex items-start justify-between bg-white">
          <div className="border-l-4 border-primary-500 pl-3.5">
            <h3 className="text-lg font-bold text-gray-900 font-plus-jakarta tracking-tight">
              {title}
            </h3>
            {subtitle && (
              <p className="text-xs text-gray-500 font-inter mt-1 font-normal leading-relaxed">
                {subtitle}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 bg-gray-50 hover:bg-gray-100 text-gray-400 hover:text-gray-600 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Modal Content Form/Div */}
        {/* @ts-ignore */}
        <ContentWrapper
          onSubmit={onSubmit}
          className="flex flex-col"
        >
          {/* Modal Body */}
          <div className="p-6 space-y-5 max-h-[70vh] overflow-y-auto font-inter text-sm">
            {children}
          </div>

          {/* Modal Footer */}
          {footer && (
            <div className="bg-gray-50/50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100 rounded-b-lg">
              {footer}
            </div>
          )}
        </ContentWrapper>
      </div>
    </div>
  );
};

export default Modal;
