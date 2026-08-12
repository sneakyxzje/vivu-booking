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
      className={`flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-gray-100 text-xs ${className}`}
    >
      {/* Bên trái: Text hiển thị ngắn gọn */}
      <div className="text-gray-500 font-medium">
        Hiển thị <span className="font-bold text-gray-800">{startItem} - {endItem}</span> /{" "}
        <span className="font-bold text-gray-800">{total}</span> {itemLabel}
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
            className="p-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
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
            className="p-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
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
                className={`w-7 h-7 rounded-xl text-xs font-bold transition-all ${p === currentPage
                    ? "bg-primary-600 text-white shadow-xs"
                    : "bg-white border border-gray-200 text-gray-700 hover:bg-gray-50"
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
            className="p-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
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
            className="p-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
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
            className="px-3.5 py-1.5 min-w-[64px] h-8.5 rounded-xl border border-gray-300 text-xs font-extrabold bg-white text-gray-800 outline-none focus:border-primary-500 shadow-xs cursor-pointer hover:bg-gray-50 transition-colors"
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
