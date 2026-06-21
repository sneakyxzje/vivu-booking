import React from "react";
import type { ItineraryFormItem } from "./types";

interface Props {
  labelClass: string;
  fieldClass: string;
  items: ItineraryFormItem[];
  onAdd: () => void;
  onRemove: (index: number) => void;
  onChange: (index: number, field: "day_number" | "title" | "content", value: string) => void;
}

export const TourFormItinerarySection: React.FC<Props> = ({
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
        <h3 className="text-sm font-bold text-gray-950">Lịch trình theo ngày</h3>
        <p className="mt-1 text-xs text-gray-500">Lưu vào bảng tour_itineraries.</p>
      </div>
      <button
        type="button"
        onClick={onAdd}
        className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50"
      >
        Thêm ngày
      </button>
    </div>
    <div className="space-y-3">
      {items.map((item, index) => (
        <div key={index} className="rounded-lg border border-gray-100 bg-white p-4">
          <div className="mb-3 flex items-center justify-between gap-3">
            <p className="text-sm font-semibold text-gray-900">Ngày {index + 1}</p>
            {items.length > 1 && (
              <button
                type="button"
                onClick={() => onRemove(index)}
                className="text-xs font-semibold text-red-600 hover:text-red-700"
              >
                Xóa
              </button>
            )}
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-[120px_minmax(0,1fr)]">
            <div>
              <label className={labelClass}>Số ngày</label>
              <input
                type="number"
                min={1}
                required
                value={item.day_number}
                onChange={(e) => onChange(index, "day_number", e.target.value)}
                className={fieldClass}
              />
            </div>
            <div>
              <label className={labelClass}>Tiêu đề</label>
              <input
                required
                value={item.title}
                onChange={(e) => onChange(index, "title", e.target.value)}
                placeholder="VD: Khởi hành - tham quan vịnh"
                className={fieldClass}
              />
            </div>
          </div>
          <div className="mt-3">
            <label className={labelClass}>Nội dung</label>
            <textarea
              required
              rows={3}
              value={item.content}
              onChange={(e) => onChange(index, "content", e.target.value)}
              placeholder="Mô tả hoạt động trong ngày..."
              className={`${fieldClass} resize-y`}
            />
          </div>
        </div>
      ))}
    </div>
  </div>
);
