import React from "react";
import type { ItineraryFormItem } from "./types";

interface Props {
  labelClass: string;
  fieldClass: string;
  items: ItineraryFormItem[];
  maxDays: number;
  onAdd: () => void;
  onRemove: (index: number) => void;
  onChange: (
    index: number,
    field: "day_number" | "title" | "start_point" | "end_point" | "route_points" | "rest_stops" | "content",
    value: string,
  ) => void;
}

export const TourFormItinerarySection: React.FC<Props> = ({
  labelClass,
  fieldClass,
  items,
  maxDays,
  onAdd,
  onRemove,
  onChange,
}) => (
  <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
    <div className="mb-4 flex items-center justify-between gap-3">
      <div>
        <h3 className="text-sm font-bold text-gray-950">
          Lịch trình theo ngày
        </h3>
      </div>
      <button
        type="button"
        onClick={onAdd}
        disabled={items.length >= maxDays}
        className="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-primary-600 shadow-sm ring-1 ring-gray-200 hover:bg-primary-50 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
      >
        Thêm ngày
      </button>
    </div>
    <div className="space-y-3">
      {items.map((item, index) => (
        <div
          key={index}
          className="rounded-lg border border-gray-100 bg-white p-4"
        >
          <div className="mb-3 flex items-center justify-between gap-3">
            <p className="text-sm font-semibold text-gray-900">
              Ngày {index + 1}
            </p>
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
                max={maxDays}
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
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className={labelClass}>Điểm đầu trong ngày</label>
              <input
                value={item.start_point}
                onChange={(e) => onChange(index, "start_point", e.target.value)}
                placeholder="VD: Hà Nội"
                className={fieldClass}
              />
            </div>
            <div>
              <label className={labelClass}>Điểm đến trong ngày</label>
              <input
                value={item.end_point}
                onChange={(e) => onChange(index, "end_point", e.target.value)}
                placeholder="VD: Hạ Long"
                className={fieldClass}
              />
            </div>
          </div>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label className={labelClass}>Các chặng đi qua</label>
              <textarea
                rows={2}
                value={item.route_points}
                onChange={(e) => onChange(index, "route_points", e.target.value)}
                placeholder="VD: Hải Dương, Uông Bí, Bãi Cháy"
                className={`${fieldClass} resize-y`}
              />
            </div>
            <div>
              <label className={labelClass}>Điểm nghỉ chân</label>
              <textarea
                rows={2}
                value={item.rest_stops}
                onChange={(e) => onChange(index, "rest_stops", e.target.value)}
                placeholder="VD: Trạm dừng Sao Đỏ"
                className={`${fieldClass} resize-y`}
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

