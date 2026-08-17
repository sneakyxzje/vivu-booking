import React from "react";

export type CheckpointType = "pickup" | "sightseeing" | "meal" | "hotel" | "dropoff";

export interface CheckpointItem {
  id: string;
  name: string;
  type: CheckpointType;
  expected_at?: string;
  requires_attendance: boolean;
  requires_photo: boolean;
  note?: string;
}

export const CHECKPOINT_TYPE_CONFIG: Record<
  CheckpointType,
  { label: string; icon: string; bgClass: string; textClass: string; borderClass: string }
> = {
  pickup: {
    label: "Điểm đón",
    icon: "📍",
    bgClass: "bg-emerald-50",
    textClass: "text-emerald-700",
    borderClass: "border-emerald-200",
  },
  sightseeing: {
    label: "Tham quan",
    icon: "🏝️",
    bgClass: "bg-blue-50",
    textClass: "text-blue-700",
    borderClass: "border-blue-200",
  },
  meal: {
    label: "Ăn uống",
    icon: "🍽️",
    bgClass: "bg-amber-50",
    textClass: "text-amber-700",
    borderClass: "border-amber-200",
  },
  hotel: {
    label: "Khách sạn",
    icon: "🏨",
    bgClass: "bg-purple-50",
    textClass: "text-purple-700",
    borderClass: "border-purple-200",
  },
  dropoff: {
    label: "Điểm trả",
    icon: "🏁",
    bgClass: "bg-rose-50",
    textClass: "text-rose-700",
    borderClass: "border-rose-200",
  },
};

interface Props {
  checkpoints: CheckpointItem[];
  onChange: (checkpoints: CheckpointItem[]) => void;
  labelClass?: string;
  fieldClass?: string;
}

export const CheckpointManager: React.FC<Props> = ({
  checkpoints,
  onChange,
  fieldClass = "block w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100",
}) => {
  const handleAdd = () => {
    const newItem: CheckpointItem = {
      id: `cp-${Date.now()}-${Math.random().toString(36).substr(2, 4)}`,
      name: "",
      type: "sightseeing",
      expected_at: "08:00",
      requires_attendance: true,
      requires_photo: false,
    };
    onChange([...checkpoints, newItem]);
  };

  const handleRemove = (index: number) => {
    const updated = checkpoints.filter((_, i) => i !== index);
    onChange(updated);
  };

  const handleUpdate = (index: number, key: keyof CheckpointItem, value: any) => {
    const updated = checkpoints.map((item, i) => {
      if (i === index) {
        return { ...item, [key]: value };
      }
      return item;
    });
    onChange(updated);
  };

  const handleMove = (index: number, direction: "up" | "down") => {
    if (
      (direction === "up" && index === 0) ||
      (direction === "down" && index === checkpoints.length - 1)
    ) {
      return;
    }
    const targetIndex = direction === "up" ? index - 1 : index + 1;
    const updated = [...checkpoints];
    const temp = updated[index];
    updated[index] = updated[targetIndex];
    updated[targetIndex] = temp;
    onChange(updated);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h4 className="text-xs font-bold uppercase tracking-wider text-gray-700">
            Danh sách điểm dừng / Điểm tham quan chi tiết
            <span className="ml-1.5 normal-case font-medium text-gray-400 tracking-normal">
              (không bắt buộc)
            </span>
          </h4>
          <p className="text-xs text-gray-500 mt-0.5">
            Cấu hình thứ tự điểm dừng, giờ dự kiến và yêu cầu ảnh check-in cho HDV. Bỏ trống thì
            hướng dẫn viên điểm danh một lần cho cả chặng.
          </p>
        </div>
        <button
          type="button"
          onClick={handleAdd}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary-50 text-primary-700 text-xs font-semibold hover:bg-primary-100 transition-colors shadow-sm"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          Thêm điểm dừng
        </button>
      </div>

      {checkpoints.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-center">
          <p className="text-xs text-gray-500">
            Chưa có điểm dừng nào cho chặng này, và để nguyên như vậy cũng được.
          </p>
          <button
            type="button"
            onClick={handleAdd}
            className="mt-2 text-xs font-semibold text-primary-600 hover:underline"
          >
            + Bấm để thêm điểm dừng đầu tiên
          </button>
        </div>
      ) : (
        <div className="space-y-3 relative before:absolute before:left-4 before:top-4 before:bottom-4 before:w-0.5 before:bg-gray-200">
          {checkpoints.map((cp, idx) => {
            const config = CHECKPOINT_TYPE_CONFIG[cp.type] || CHECKPOINT_TYPE_CONFIG.sightseeing;
            return (
              <div
                key={cp.id || idx}
                className="relative pl-9 pr-4 py-3.5 bg-white rounded-xl border border-gray-100 shadow-sm transition-all duration-200 hover:border-gray-200 hover:shadow-md"
              >
                {/* Timeline node */}
                <div className="absolute left-2.5 top-5 -translate-x-1/2 w-4 h-4 rounded-full bg-primary-600 border-2 border-white ring-2 ring-primary-100 flex items-center justify-center text-[9px] text-white font-bold">
                  {idx + 1}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                  {/* Tên điểm dừng */}
                  <div className="md:col-span-4">
                    <label className="block text-[11px] font-medium text-gray-500 mb-1">
                      Tên điểm dừng #{idx + 1}
                    </label>
                    <input
                      type="text"
                      value={cp.name}
                      onChange={(e) => handleUpdate(idx, "name", e.target.value)}
                      placeholder="VD: Cảng Tuần Châu, Khách sạn Mường Thanh..."
                      className={fieldClass}
                    />
                  </div>

                  {/* Phân loại */}
                  <div className="md:col-span-3">
                    <label className="block text-[11px] font-medium text-gray-500 mb-1">
                      Loại điểm dừng
                    </label>
                    <select
                      value={cp.type}
                      onChange={(e) => handleUpdate(idx, "type", e.target.value as CheckpointType)}
                      className={fieldClass}
                    >
                      {Object.entries(CHECKPOINT_TYPE_CONFIG).map(([typeKey, cfg]) => (
                        <option key={typeKey} value={typeKey}>
                          {cfg.icon} {cfg.label}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Giờ dự kiến */}
                  <div className="md:col-span-2">
                    <label className="block text-[11px] font-medium text-gray-500 mb-1">
                      Giờ dự kiến
                    </label>
                    <input
                      type="time"
                      value={cp.expected_at || ""}
                      onChange={(e) => handleUpdate(idx, "expected_at", e.target.value)}
                      className={fieldClass}
                    />
                  </div>

                  {/* Action buttons (Move & Delete) */}
                  <div className="md:col-span-3 flex items-center justify-end gap-1.5 pt-4 md:pt-0">
                    <button
                      type="button"
                      onClick={() => handleMove(idx, "up")}
                      disabled={idx === 0}
                      className="p-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                      title="Di chuyển lên"
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      onClick={() => handleMove(idx, "down")}
                      disabled={idx === checkpoints.length - 1}
                      className="p-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                      title="Di chuyển xuống"
                    >
                      ↓
                    </button>
                    <button
                      type="button"
                      onClick={() => handleRemove(idx)}
                      className="p-1.5 rounded-lg border border-rose-200 text-rose-600 bg-rose-50/50 hover:bg-rose-100 transition-colors"
                      title="Xóa điểm dừng"
                    >
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>

                {/* Tùy chọn thuộc tính (Requires Attendance / Photo) */}
                <div className="mt-2.5 pt-2.5 border-t border-gray-100 flex flex-wrap items-center gap-4 text-xs">
                  <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-medium text-[11px] border ${config.bgClass} ${config.textClass} ${config.borderClass}`}>
                    <span>{config.icon}</span>
                    <span>{config.label}</span>
                  </span>

                  <label className="inline-flex items-center gap-2 cursor-pointer text-gray-700 select-none">
                    <input
                      type="checkbox"
                      checked={cp.requires_attendance}
                      onChange={(e) => handleUpdate(idx, "requires_attendance", e.target.checked)}
                      className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>Yêu cầu điểm danh khách</span>
                  </label>

                  <label className="inline-flex items-center gap-2 cursor-pointer text-gray-700 select-none">
                    <input
                      type="checkbox"
                      checked={cp.requires_photo}
                      onChange={(e) => handleUpdate(idx, "requires_photo", e.target.checked)}
                      className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>Bắt buộc chụp ảnh đoàn</span>
                  </label>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default CheckpointManager;
