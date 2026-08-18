import React, { useEffect, useRef, useState } from "react";
import { Settings2 } from "lucide-react";

export interface ActionItem {
  label: string;
  onClick: () => void;
  icon?: React.ReactNode;
  variant?: "danger" | "default" | "success" | "warning";
  /** Ghi chú một dòng dưới nhãn, cho hành động cần giải thích trước khi bấm. */
  hint?: string;
  disabled?: boolean;
}

interface TableActionsProps {
  id: string | number;
  actions: ActionItem[];
  /** Nhãn đọc màn hình cho nút mở, mặc định "Thao tác". */
  label?: string;
}

/**
 * Cụm thao tác của một hàng bảng, gói sau một nút bánh răng.
 *
 * Trước đây mỗi hàng trải hết nút ra cạnh nhau — có hàng tới tám cái. Hệ quả là cột thao tác
 * rộng hơn cả cột dữ liệu, và mắt phải đọc cả tám nhãn mới tìm ra cái cần bấm. Gói lại sau một
 * biểu tượng thì hàng ngắn đi, còn khi mở ra thì các hành động xếp dọc nên đọc theo nhãn nhanh
 * hơn nhiều so với đọc ngang.
 *
 * Nguy hiểm luôn nằm cuối và có đường kẻ tách: hủy chuyến không được đứng cạnh xem danh sách.
 */
export const TableActions: React.FC<TableActionsProps> = ({ actions, label = "Thao tác" }) => {
  const [isOpen, setIsOpen] = useState(false);
  const boc = useRef<HTMLDivElement>(null);

  // Phím Esc đóng menu — bàn phím phải thoát ra được, không chỉ chuột.
  useEffect(() => {
    if (!isOpen) return;

    const khiNhanPhim = (e: KeyboardEvent) => {
      if (e.key === "Escape") setIsOpen(false);
    };

    document.addEventListener("keydown", khiNhanPhim);
    return () => document.removeEventListener("keydown", khiNhanPhim);
  }, [isOpen]);

  if (actions.length === 0) return null;

  return (
    <div ref={boc} className="inline-block text-left relative">
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        aria-label={label}
        aria-expanded={isOpen}
        title={label}
        className={`w-9 h-9 flex items-center justify-center rounded-full border transition-colors cursor-pointer ${
          isOpen
            ? "border-ink bg-surface-strong text-ink"
            : "border-hairline bg-canvas text-muted hover:text-ink hover:bg-surface-soft"
        }`}
      >
        <Settings2 className="w-4 h-4" />
      </button>

      {isOpen && (
        <>
          {/* Bấm ra ngoài thì đóng. */}
          <div
            className="fixed inset-0 z-10 cursor-default"
            onClick={() => setIsOpen(false)}
          />

          <div
            role="menu"
            className="absolute right-0 top-full mt-2 min-w-[13rem] w-max max-w-xs rounded-xl bg-canvas shadow-float border border-hairline-soft py-1.5 z-20 animate-fade-in origin-top-right text-left"
          >
            {actions.map((action, index) => {
              const colorClass = action.disabled
                ? "text-muted-soft cursor-not-allowed"
                : action.variant === "danger"
                  ? "text-rose-600 hover:bg-rose-50"
                  : action.variant === "success"
                    ? "text-emerald-700 hover:bg-emerald-50"
                    : action.variant === "warning"
                      ? "text-amber-700 hover:bg-amber-50"
                      : "text-ink hover:bg-surface-soft";

              return (
                <React.Fragment key={index}>
                  {action.variant === "danger" && index > 0 && (
                    <div className="h-px bg-hairline-soft my-1.5" />
                  )}
                  <button
                    type="button"
                    role="menuitem"
                    disabled={action.disabled}
                    onClick={() => {
                      setIsOpen(false);
                      action.onClick();
                    }}
                    className={`w-full px-4 py-2.5 transition-colors flex items-start gap-2.5 text-left cursor-pointer ${colorClass}`}
                  >
                    {action.icon && (
                      <span className="shrink-0 mt-0.5">{action.icon}</span>
                    )}
                    <span className="min-w-0">
                      <span className="block text-button-sm whitespace-nowrap">
                        {action.label}
                      </span>
                      {action.hint && (
                        <span className="block text-caption-sm text-muted mt-0.5">
                          {action.hint}
                        </span>
                      )}
                    </span>
                  </button>
                </React.Fragment>
              );
            })}
          </div>
        </>
      )}
    </div>
  );
};
