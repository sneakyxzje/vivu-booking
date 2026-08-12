import React, { useState } from "react";
import { CalendarDays, Users, AlertTriangle } from "lucide-react";
import type { Guide } from "@/types";
import type { ScheduleFormItem } from "./types";
import Pagination from "@/components/common/Pagination";

const getNowInputValue = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  const hours = String(now.getHours()).padStart(2, "0");
  const minutes = String(now.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const minStartDate = getNowInputValue();

const getPeriod = (startDate: string, numberOfDays: number) => {
  if (!startDate) return null;
  const start = new Date(
    startDate.includes("T") ? startDate : startDate + "T00:00:00",
  );
  const end = new Date(start);
  end.setDate(end.getDate() + Math.max(0, numberOfDays - 1));
  return { start, end };
};

interface Props {
  labelClass?: string;
  fieldClass: string;
  items: ScheduleFormItem[];
  numberOfDays: number;
  availableGuidesBySchedule: Record<number, Guide[]>;
  availabilityLoading: boolean;
  onAdd: () => void;
  onRemove: (index: number) => void;
  onChange: (
    index: number,
    field: "start_date" | "max_people" | "guide_id" | "min_people" | "booking_deadline" | "status",
    value: string,
  ) => void;
}

export const TourFormScheduleSection: React.FC<Props> = ({
  fieldClass,
  items,
  numberOfDays,
  availableGuidesBySchedule,
  availabilityLoading,
  onAdd,
  onRemove,
  onChange,
}) => {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(5);

  const total = items.length;
  const lastPage = Math.ceil(total / perPage) || 1;
  const safePage = Math.min(page, lastPage);

  const startIndex = (safePage - 1) * perPage;
  const paginatedItems = items.slice(startIndex, startIndex + perPage);

  const handleAdd = () => {
    onAdd();
    const nextTotal = total + 1;
    const nextLastPage = Math.ceil(nextTotal / perPage);
    setPage(nextLastPage);
  };

  const handleRemove = (index: number) => {
    onRemove(index);
    if (paginatedItems.length === 1 && safePage > 1) {
      setPage(safePage - 1);
    }
  };

  const getSelectableGuides = (index: number) => {
    const currentPeriod = getPeriod(items[index]?.start_date, numberOfDays);

    return (availableGuidesBySchedule[index] ?? []).filter((guide) => {
      if (!currentPeriod) return true;

      return !items.some((item, otherIndex) => {
        if (otherIndex === index || item.guide_id !== String(guide.id)) {
          return false;
        }

        const otherPeriod = getPeriod(item.start_date, numberOfDays);
        return (
          otherPeriod !== null &&
          currentPeriod.start <= otherPeriod.end &&
          currentPeriod.end >= otherPeriod.start
        );
      });
    });
  };

  return (
    <div className="rounded-2xl border border-gray-100 bg-gray-50/50 p-5 space-y-5">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-gray-950">Lịch khởi hành nâng cao</h3>
          <p className="mt-1 text-xs text-gray-500">
            Thiết lập thời gian đi, số lượng khách tối thiểu/tối đa và hạn chốt nhận khách.
          </p>
        </div>
        <button
          type="button"
          onClick={handleAdd}
          className="rounded-xl bg-white px-4 py-2.5 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50 transition-all active:scale-95 duration-200"
        >
          + Thêm lịch mới
        </button>
      </div>

      <div className="space-y-4">
        {paginatedItems.map((item, localIndex) => {
          const index = startIndex + localIndex;
          const selectableGuides = getSelectableGuides(index);
          const selectedGuideStillAvailable =
            !item.guide_id ||
            selectableGuides.some(
              (guide) => String(guide.id) === item.guide_id,
            );

          return (
            <div
              key={index}
              className="relative rounded-2xl border border-gray-200 border-l-4 border-l-primary-600 bg-white shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden"
            >
              {/* Card Header */}
              <div className="flex items-center justify-between bg-slate-50 px-5 py-3 border-b border-gray-150">
                <div className="flex items-center gap-2">
                  <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 font-mono">
                    {index + 1}
                  </span>
                  <span className="text-xs font-bold uppercase tracking-wider text-slate-700 font-mono">
                    Chuyến khởi hành nâng cao
                  </span>
                </div>
                <button
                  type="button"
                  onClick={() => handleRemove(index)}
                  disabled={items.length === 1}
                  className="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
                >
                  Xóa chuyến
                </button>
              </div>

              <div className="p-5 space-y-5">
                {/* Section 1: Thời gian & Trạng thái bán */}
                <div className="space-y-3">
                  <div className="flex items-center gap-1.5 text-xs font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5">
                    <CalendarDays className="h-4 w-4 text-primary-600" />
                    <span>Lịch trình & Đóng mở bán</span>
                  </div>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {/* Ngày khởi hành */}
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1.5">
                        Thời gian khởi hành <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="datetime-local"
                        min={minStartDate}
                        required
                        value={item.start_date}
                        onChange={(event) =>
                          onChange(index, "start_date", event.target.value)
                        }
                        className={fieldClass}
                      />
                      <p className="mt-1 text-[10px] text-gray-400">
                        Ngày giờ xuất phát dự kiến của đoàn.
                      </p>
                    </div>

                    {/* Hạn chốt đặt */}
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1.5">
                        Hạn chốt đặt tour <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="datetime-local"
                        required
                        value={item.booking_deadline}
                        onChange={(event) =>
                          onChange(index, "booking_deadline", event.target.value)
                        }
                        className={fieldClass}
                      />
                      <p className="mt-1 text-[10px] text-gray-400">
                        Khách không thể đặt sau mốc này (Mặc định trước đi 3 ngày).
                      </p>
                    </div>

                    {/* Trạng thái mở bán */}
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1.5">
                        Trạng thái mở bán ban đầu
                      </label>
                      <select
                        value={item.status}
                        onChange={(event) =>
                          onChange(index, "status", event.target.value)
                        }
                        className={fieldClass}
                      >
                        <option value="open">Đang mở bán (Open)</option>
                        <option value="closed">Đóng bán tạm thời (Closed)</option>
                      </select>
                      <p className="mt-1 text-[10px] text-gray-400">
                        Thiết lập chế độ đăng ký ngay sau khi tạo tour.
                      </p>
                    </div>
                  </div>
                </div>

                {/* Section 2: Quy mô & Hướng dẫn viên */}
                <div className="space-y-3 pt-2">
                  <div className="flex items-center gap-1.5 text-xs font-bold text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5">
                    <Users className="h-4 w-4 text-primary-600" />
                    <span>Quy mô đoàn & Hướng dẫn viên</span>
                  </div>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {/* Khách tối thiểu */}
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1.5">
                        Số khách tối thiểu <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="number"
                        min={1}
                        required
                        value={item.min_people}
                        onChange={(event) =>
                          onChange(index, "min_people", event.target.value)
                        }
                        className={fieldClass}
                        placeholder="Ví dụ: 5"
                      />
                      <p className="mt-1 text-[10px] text-gray-400">
                        Mức khách tối thiểu để đoàn khởi hành chắc chắn.
                      </p>
                    </div>

                    {/* Khách tối đa */}
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1.5">
                        Số khách tối đa <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="number"
                        min={1}
                        required
                        value={item.max_people}
                        onChange={(event) =>
                          onChange(index, "max_people", event.target.value)
                        }
                        className={fieldClass}
                        placeholder="Ví dụ: 10"
                      />
                      <p className="mt-1 text-[10px] text-gray-400">
                        Giới hạn chỗ ngồi. Đủ số lượng hệ thống tự động khóa sổ.
                      </p>
                    </div>

                    {/* Hướng dẫn viên */}
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1.5">
                        Hướng dẫn viên điều hành
                      </label>
                      <select
                        value={item.guide_id}
                        disabled={!item.start_date || availabilityLoading}
                        onChange={(event) =>
                          onChange(index, "guide_id", event.target.value)
                        }
                        className={fieldClass + " disabled:cursor-not-allowed disabled:bg-gray-50"}
                      >
                        <option value="">
                          {!item.start_date
                            ? "Chọn ngày khởi hành trước"
                            : availabilityLoading
                              ? "Đang tìm HDV rảnh..."
                              : "Chưa phân công"}
                        </option>
                        {!selectedGuideStillAvailable && item.guide_id && (
                          <option value={item.guide_id} disabled>
                            HDV đã chọn không còn rảnh
                          </option>
                        )}
                        {selectableGuides.map((guide) => (
                          <option key={guide.id} value={guide.id}>
                            {guide.name}
                          </option>
                        ))}
                      </select>
                      {item.start_date &&
                        !availabilityLoading &&
                        selectableGuides.length === 0 ? (
                        <div className="mt-1.5 flex items-center gap-1 text-[10px] text-amber-700 bg-amber-50 px-2 py-1 rounded border border-amber-100">
                          <AlertTriangle className="h-3 w-3 shrink-0" />
                          <span>Không có hướng dẫn viên rảnh trong khoảng này.</span>
                        </div>
                      ) : (
                        <p className="mt-1 text-[10px] text-gray-400">
                          Chỉ hiển thị hướng dẫn viên đang trống lịch.
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Reusable Common Component Pagination */}
      <Pagination
        currentPage={safePage}
        lastPage={lastPage}
        total={total}
        perPage={perPage}
        itemLabel="lịch"
        perPageOptions={[5, 10, 20]}
        onPageChange={(p) => setPage(p)}
        onPerPageChange={(newPerPage) => {
          setPerPage(newPerPage);
          setPage(1);
        }}
      />
    </div>
  );
};