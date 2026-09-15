import { Pagination } from "antd";
import type { PaginationProps } from "@/components/common/Pagination";

export default function AdminPagination({ currentPage, total, perPage, onPageChange, onPerPageChange, perPageOptions = [10, 25, 50, 100], itemLabel = "bản ghi" }: PaginationProps) {
  return <Pagination current={currentPage} total={total} pageSize={perPage} responsive
    showSizeChanger={!!onPerPageChange} pageSizeOptions={perPageOptions}
    showTotal={(count, range) => `${count ? range[0] : 0}–${range[1]} / ${count} ${itemLabel}`}
    onChange={(page, size) => { if (size !== perPage && onPerPageChange) onPerPageChange(size); else onPageChange(page); }} />;
}
