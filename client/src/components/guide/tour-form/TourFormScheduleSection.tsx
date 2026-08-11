import React from "react";
import type { Guide } from "@/types";
import type { ScheduleFormItem } from "./types";

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
  labelClass: string;
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
  labelClass,
  fieldClass,
  items,
  numberOfDays,
  availableGuidesBySchedule,
  availabilityLoading,
  onAdd,
  onRemove,
  onChange,
}) => {
  const getSelectableGuides = (index: number) => {
    const currentPeriod = getPeriod(items[index].start_date, numberOfDays);

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
    <div className="rounded-2xl border border-gray-100 bg-gray-50/50 p-5">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-gray-950">Lịch khởi hành nâng cao</h3>
          <p className="mt-1 text-xs text-gray-500">
            Thiết lập thời gian đi, số lượng khách tối thiểu/tối đa và hạn chốt nhận khách.
          </p>
        </div>
        <button
          type="button"
          onClick={onAdd}
          className="rounded-xl bg-white px-4 py-2.5 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50 transition-all active:scale-95 duration-200"
        >
          Thêm lịch mới
        </button>
      </div>

      <div className="space-y-4">
        {items.map((item, index) => {
          const selectableGuides = getSelectableGuides(index);
          const selectedGuideStillAvailable =
            !item.guide_id ||
            selectableGuides.some(
              (guide) => String(guide.id) === item.guide_id,
            );

          return (
            <div
              key={index}
              className="relative rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-4"
            >
              {/* Top Row: Title and Delete Button */}
              <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                <span className="text-xs font-bold text-primary-700 font-mono">
                  CHUYẾN KHỞI HÀNH #{index + 1}
                </span>
                <button
                  type="button"
                  onClick={() => onRemove(index)}
                  disabled={items.length === 1}
                  className="rounded-lg border border-gray-100 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 transition-colors"
                >
                  Xóa chuyến
                </button>
              </div>

              {/* Form Grid */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                  <label className={labelClass}>Thời gian khởi hành *</label>
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
                </div>

                <div>
                  <label className={labelClass}>Hạn chốt đặt tour *</label>
                  <input
                    type="datetime-local"
                    required
                    value={item.booking_deadline}
                    onChange={(event) =>
                      onChange(index, "booking_deadline", event.target.value)
                    }
                    className={fieldClass}
                  />
                  <p className="mt-1 text-[11px] text-gray-400">
                    Mặc định tự động trước ngày khởi hành 3 ngày.
                  </p>
                </div>

                <div>
                  <label className={labelClass}>Trạng thái ban đầu</label>
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
                </div>

                <div>
                  <label className={labelClass}>Số khách tối thiểu *</label>
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
                </div>

                <div>
                  <label className={labelClass}>Số khách tối đa *</label>
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
                </div>

                <div>
                  <label className={labelClass}>Hướng dẫn viên</label>
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
                    selectableGuides.length === 0 && (
                      <p className="mt-1 text-[11px] text-amber-700">
                        Không có hướng dẫn viên rảnh trong khoảng này.
                      </p>
                    )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};