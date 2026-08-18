import React from "react";

export interface PaginationProps {
  currentPage: number;
  lastPage: number;
  total: number;
  perPage: number;
  onPageChange: (page: number) => void;
  onPerPageChange?: (perPage: number) => void;
  perPageOptions?: number[];
  itemLabel?: string;
  className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  lastPage,
  total,
  perPage,
  onPageChange,
  onPerPageChange,
  perPageOptions = [10, 25, 50, 100],
  itemLabel = "bản ghi",
  className = "",
}) => {
  const startItem = total > 0 ? (currentPage - 1) * perPage + 1 : 0;
  const endItem = Math.min(currentPage * perPage, total);

  // Tính toán dãy trang hiển thị xung quanh trang hiện tại
  const getPageNumbers = () => {
    return Array.from({ length: lastPage }, (_, i) => i + 1).filter(
      (p) => Math.abs(p - currentPage) <= 2 || p === 1 || p === lastPage
    );
  };

  const pageNumbers = getPageNumbers();

  return (
    <div
      className={`flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-hairline-soft ${className}`}
    >
      <div className="text-body-sm text-muted">
        Hiển thị <span className="font-semibold text-ink tabular-nums">{startItem} - {endItem}</span> /{" "}
        <span className="font-semibold text-ink tabular-nums">{total}</span> {itemLabel}
      </div>

      {/* Bên phải: Nút Icon điều hướng trang + Dropdown chỉ hiển thị số */}
      <div className="flex items-center gap-2">
        {/* Nút điều hướng trang */}
        <div className="flex items-center gap-1">
          {/* Trang đầu */}
          <button
            type="button"
            title="Trang đầu"
            disabled={currentPage <= 1}
            onClick={() => onPageChange(1)}
            className="w-9 h-9 flex items-center justify-center rounded-full border border-hairline text-ink hover:bg-surface-soft disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
          </button>

          {/* Trang trước */}
          <button
            type="button"
            title="Trang trước"
            disabled={currentPage <= 1}
            onClick={() => onPageChange(currentPage - 1)}
            className="w-9 h-9 flex items-center justify-center rounded-full border border-hairline text-ink hover:bg-surface-soft disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          {/* Các số trang */}
          {pageNumbers.map((p, idx) => (
            <React.Fragment key={p}>
              {idx > 0 && pageNumbers[idx - 1] !== p - 1 && (
                <span className="px-1 text-gray-400 text-xs">...</span>
              )}
              <button
                type="button"
                onClick={() => onPageChange(p)}
                /*
                  Ô số trang là hình tròn 36px, cùng khuôn với ô ngày của bộ chọn lịch trong
                  DESIGN.md. Trang đang xem tô đặc, không đổ bóng — bóng để tách tầng, không
                  để đánh dấu trạng thái.
                */
                className={`w-9 h-9 rounded-full text-button-sm transition-colors cursor-pointer ${p === currentPage
                    ? "bg-primary-600 text-white"
                    : "bg-canvas border border-hairline text-ink hover:bg-surface-soft"
                  }`}
              >
                {p}
              </button>
            </React.Fragment>
          ))}

          {/* Trang sau */}
          <button
            type="button"
            title="Trang sau"
            disabled={currentPage >= lastPage}
            onClick={() => onPageChange(currentPage + 1)}
            className="w-9 h-9 flex items-center justify-center rounded-full border border-hairline text-ink hover:bg-surface-soft disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </button>

          {/* Trang cuối */}
          <button
            type="button"
            title="Trang cuối"
            disabled={currentPage >= lastPage}
            onClick={() => onPageChange(lastPage)}
            className="w-9 h-9 flex items-center justify-center rounded-full border border-hairline text-ink hover:bg-surface-soft disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        {/* Dropdown bên phải chỉ hiển thị số bản ghi/trang */}
        {onPerPageChange && (
          <select
            value={perPage}
            onChange={(e) => onPerPageChange(Number(e.target.value))}
            className="px-3.5 h-9 min-w-[64px] rounded-full border border-hairline text-button-sm bg-canvas text-ink outline-none focus:border-ink cursor-pointer hover:bg-surface-soft transition-colors"
            title="Số bản ghi mỗi trang"
          >
            {perPageOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        )}
      </div>
    </div>
  );
};

export default Pagination;
