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
    field: "start_date" | "max_people" | "guide_id",
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
    <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-gray-950">Lịch khởi hành</h3>
          <p className="mt-1 text-xs text-gray-500">
            Có thể phân công ngay hướng dẫn viên đang rảnh cho từng chuyến.
          </p>
        </div>
        <button
          type="button"
          onClick={onAdd}
          className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50"
        >
          Thêm lịch
        </button>
      </div>

      <div className="space-y-3">
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
              className="grid grid-cols-1 gap-3 rounded-lg border border-gray-100 bg-white p-4 lg:grid-cols-[minmax(0,1fr)_140px_minmax(220px,1fr)_auto]"
            >
              <div>
                <label className={labelClass}>Thời gian khởi hành</label>
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
                <label className={labelClass}>Số khách tối đa</label>
                <input
                  type="number"
                  min={1}
                  required
                  value={item.max_people}
                  onChange={(event) =>
                    onChange(index, "max_people", event.target.value)
                  }
                  className={fieldClass}
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
                  className={fieldClass + " disabled:cursor-not-allowed disabled:bg-gray-100"}
                >
                  <option value="">
                    {!item.start_date
                      ? "Chọn thời gian khởi hành trước"
                      : availabilityLoading
                        ? "Đang kiểm tra lịch..."
                        : "Chưa phân công"}
                  </option>
                  {!selectedGuideStillAvailable && item.guide_id && (
                    <option value={item.guide_id} disabled>
                      Guide đã chọn không còn rảnh
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
                    <p className="mt-1.5 text-xs text-amber-700">
                      Không có hướng dẫn viên rảnh trong khoảng này.
                    </p>
                  )}
              </div>

              <div className="flex items-end">
                <button
                  type="button"
                  onClick={() => onRemove(index)}
                  disabled={items.length === 1}
                  className="w-full rounded-lg border border-gray-200 bg-white px-3 py-3 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Xóa
                </button>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};