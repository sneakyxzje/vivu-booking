import React, { useState } from "react";

export interface ActionItem {
  label: string;
  onClick: () => void;
  icon?: React.ReactNode;
  variant?: "danger" | "default" | "success" | "warning";
}

interface TableActionsProps {
  id: string | number;
  actions: ActionItem[];
}

export const TableActions: React.FC<TableActionsProps> = ({ actions }) => {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="inline-block text-left relative">
      {/* 3-dots button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="p-1.5 hover:bg-gray-100 rounded-full text-gray-500 hover:text-gray-700 focus:outline-none transition-colors"
      >
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
        </svg>
      </button>

      {isOpen && (
        <>
          {/* Transparent click-outside overlay */}
          <div
            className="fixed inset-0 z-10 cursor-default"
            onClick={() => setIsOpen(false)}
          />
          {/* Dropdown Menu popover */}
          <div className="absolute right-0 mt-1 w-36 rounded-2xl bg-white shadow-xl border border-gray-100 py-1.5 z-20 animate-fade-in origin-top-right text-left">
            {actions.map((action, index) => {
              const colorClass =
                action.variant === "danger"
                  ? "text-rose-600 hover:bg-rose-50/70 hover:text-rose-700 font-semibold"
                  : action.variant === "success"
                  ? "text-emerald-600 hover:bg-emerald-50/50 font-medium"
                  : action.variant === "warning"
                  ? "text-amber-600 hover:bg-amber-50/50 font-medium"
                  : "text-gray-700 hover:bg-gray-50 hover:text-indigo-650 font-medium";

              return (
                <React.Fragment key={index}>
                  {action.variant === "danger" && index > 0 && (
                    <div className="h-px bg-gray-100 my-1" />
                  )}
                  <button
                    onClick={() => {
                      setIsOpen(false);
                      action.onClick();
                    }}
                    className={`w-full px-3.5 py-2 text-sm transition-colors flex items-center gap-2 ${colorClass}`}
                  >
                    {action.icon && <span className="shrink-0">{action.icon}</span>}
                    {action.label}
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
