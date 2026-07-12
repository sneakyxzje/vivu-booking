import React from "react";
import type { ScheduleFormItem } from "./types";

const getTodayInputValue = () => {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");

  return [year, month, day].join("-");
};

const minStartDate = getTodayInputValue();

interface Props {
  labelClass: string;
  fieldClass: string;
  items: ScheduleFormItem[];
  onAdd: () => void;
  onRemove: (index: number) => void;
  onChange: (
    index: number,
    field: "start_date" | "max_people",
    value: string,
  ) => void;
}

export const TourFormScheduleSection: React.FC<Props> = ({
  labelClass,
  fieldClass,
  items,
  onAdd,
  onRemove,
  onChange,
}) => (
  <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <div className="mb-4 flex items-center justify-between gap-3">
      <div>
        <h3 className="text-sm font-bold text-gray-950">Lịch khởi hành</h3>
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
      {items.map((item, index) => (
        <div
          key={index}
          className="grid grid-cols-1 gap-3 rounded-lg border border-gray-100 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_160px_auto]"
        >
          <div>
            <label className={labelClass}>Ngày khởi hành</label>
            <input
              type="date"
              min={minStartDate}
              required
              value={item.start_date}
              onChange={(e) => onChange(index, "start_date", e.target.value)}
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
              onChange={(e) => onChange(index, "max_people", e.target.value)}
              className={fieldClass}
            />
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
      ))}
    </div>
  </div>
);
