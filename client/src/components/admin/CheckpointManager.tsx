import React from "react";
import { Camera, MapPin, Plus, Trash2 } from "lucide-react";
import type { CheckpointItem } from "@/components/guide/tour-form/types";
import { checkpointRong } from "@/components/guide/tour-form/formHelpers";

/**
 * Điểm dừng trong một ngày của lịch trình.
 *
 * ## Chỉ khai những gì máy chủ lưu được
 *
 * Bảng `itinerary_checkpoints` có đúng: tên, mô tả, tọa độ, thứ tự, và cờ bắt buộc chụp ảnh.
 * Biểu mẫu trước đây còn hỏi thêm loại điểm dừng, giờ dự kiến và cờ "yêu cầu điểm danh" — ba
 * thứ không có cột nào để ghi và cũng không nằm trong payload gửi đi, nên người dùng khai xong
 * lưu tour là mất trắng, mà không có gì báo cho biết.
 *
 * Ô nhập nào cũng phải sống sót qua nút Lưu. Thà ít ô mà thật.
 */

interface Props {
  checkpoints: CheckpointItem[];
  onChange: (checkpoints: CheckpointItem[]) => void;
  labelClass?: string;
  fieldClass?: string;
}

export const CheckpointManager: React.FC<Props> = ({
  checkpoints,
  onChange,
  fieldClass = "block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100",
}) => {
  const them = () => onChange([...checkpoints, checkpointRong()]);

  const xoa = (index: number) => onChange(checkpoints.filter((_, i) => i !== index));

  const sua = (index: number, thayDoi: Partial<CheckpointItem>) =>
    onChange(checkpoints.map((item, i) => (i === index ? { ...item, ...thayDoi } : item)));

  const doiCho = (index: number, huong: "len" | "xuong") => {
    const dich = huong === "len" ? index - 1 : index + 1;
    if (dich < 0 || dich >= checkpoints.length) return;

    const sau = [...checkpoints];
    [sau[index], sau[dich]] = [sau[dich], sau[index]];
    onChange(sau);
  };

  return (
    <div className="space-y-3">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h4 className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-gray-700">
            <MapPin className="h-3.5 w-3.5 text-primary-600" />
            Điểm dừng trong ngày
            <span className="font-medium normal-case tracking-normal text-gray-400">
              (không bắt buộc)
            </span>
          </h4>
          <p className="mt-0.5 text-[11px] text-gray-500">
            Thứ tự các điểm hướng dẫn viên phải điểm danh khách. Bỏ trống thì điểm danh một lần
            cho cả chặng.
          </p>
        </div>
        <button
          type="button"
          onClick={them}
          className="inline-flex shrink-0 items-center gap-1 rounded-lg bg-primary-50 px-2.5 py-1.5 text-[11px] font-semibold text-primary-700 transition-colors hover:bg-primary-100"
        >
          <Plus className="h-3.5 w-3.5" />
          Thêm điểm dừng
        </button>
      </div>

      {checkpoints.length === 0 ? (
        <button
          type="button"
          onClick={them}
          className="w-full rounded-lg border border-dashed border-gray-300 bg-gray-50/60 px-4 py-3 text-[11px] text-gray-500 transition-colors hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-700"
        >
          Chưa có điểm dừng nào — bấm để thêm điểm đầu tiên.
        </button>
      ) : (
        <div className="space-y-2">
          {checkpoints.map((cp, idx) => (
            <div
              key={idx}
              className="flex items-start gap-2.5 rounded-lg border border-gray-200 bg-white p-2.5"
            >
              <span className="mt-1.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[11px] font-bold text-primary-700">
                {idx + 1}
              </span>

              <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                <input
                  value={cp.name}
                  onChange={(e) => sua(idx, { name: e.target.value })}
                  placeholder="Tên điểm dừng, VD: Cảng Tuần Châu"
                  className={fieldClass}
                />
                <input
                  value={cp.description}
                  onChange={(e) => sua(idx, { description: e.target.value })}
                  placeholder="Ghi chú cho hướng dẫn viên (không bắt buộc)"
                  className={fieldClass}
                />
                <label className="inline-flex cursor-pointer select-none items-center gap-2 text-[11px] font-medium text-gray-700">
                  <input
                    type="checkbox"
                    checked={cp.is_required_photo}
                    onChange={(e) => sua(idx, { is_required_photo: e.target.checked })}
                    className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <Camera className="h-3.5 w-3.5 text-gray-400" />
                  Bắt buộc chụp ảnh đoàn tại điểm này
                </label>
              </div>

              <div className="flex shrink-0 items-center gap-1">
                <button
                  type="button"
                  onClick={() => doiCho(idx, "len")}
                  disabled={idx === 0}
                  className="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-30"
                >
                  Lên
                </button>
                <button
                  type="button"
                  onClick={() => doiCho(idx, "xuong")}
                  disabled={idx === checkpoints.length - 1}
                  className="rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-30"
                >
                  Xuống
                </button>
                <button
                  type="button"
                  onClick={() => xoa(idx)}
                  aria-label={`Xóa điểm dừng ${idx + 1}`}
                  className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default CheckpointManager;
