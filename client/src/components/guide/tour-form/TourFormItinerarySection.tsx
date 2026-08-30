import React, { useState } from "react";
import { ArrowRight, CalendarRange, ChevronDown, Plus, Trash2 } from "lucide-react";
import type { ItineraryFormItem } from "./types";
import { danhSoLai, ngayRong } from "./formHelpers";
import { CheckpointManager } from "../../admin/CheckpointManager";

/**
 * Bước 2: hành trình từng ngày.
 *
 * ## Gấp lại theo ngày
 *
 * Một tour 5 ngày trước đây trải ra năm khối, mỗi khối bảy ô nhập cộng phần điểm dừng — cuộn
 * mãi không hết và không nhìn ra ngày nào còn dở. Giờ mỗi ngày là một hàng gấp được, mở đúng
 * ngày đang sửa, và hàng nào thiếu thì mang nhãn thiếu ngay trên đầu.
 *
 * ## Số ngày không hỏi nữa
 *
 * `day_number` suy ra từ thứ tự trong danh sách. Trước đây nó là một ô nhập tay, nên người dùng
 * gõ được "Ngày 3" ở vị trí thứ nhất, hoặc hai hàng cùng mang số 2 — máy chủ từ chối, còn thông
 * báo lỗi thì nói về một con số mà biểu mẫu vừa hiển thị đúng ngay bên cạnh.
 */

interface Props {
  labelClass: string;
  fieldClass: string;
  items: ItineraryFormItem[];
  maxDays: number;
  onChange: (next: ItineraryFormItem[]) => void;
}

export const TourFormItinerarySection: React.FC<Props> = ({
  labelClass,
  fieldClass,
  items,
  maxDays,
  onChange,
}) => {
  const [dangMo, setDangMo] = useState<number | null>(0);

  const conThieu = Math.max(0, maxDays - items.length);

  const sua = (index: number, thayDoi: Partial<ItineraryFormItem>) =>
    onChange(items.map((item, i) => (i === index ? { ...item, ...thayDoi } : item)));

  const them = (soLuong = 1) => {
    const themVao = Array.from({ length: Math.min(soLuong, conThieu) }, (_, i) =>
      ngayRong(items.length + i + 1),
    );
    if (themVao.length === 0) return;

    onChange(danhSoLai([...items, ...themVao]));
    setDangMo(items.length);
  };

  const xoa = (index: number) => {
    onChange(danhSoLai(items.filter((_, i) => i !== index)));
    setDangMo(null);
  };

  const suaChang = (index: number, viTri: number, giaTri: string) =>
    sua(index, {
      route_points: items[index].route_points.map((p, i) => (i === viTri ? giaTri : p)),
    });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div className="flex items-start gap-3">
          <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
            <CalendarRange className="h-4 w-4" />
          </span>
          <div>
            <h3 className="text-sm font-bold text-gray-950">Hành trình từng ngày</h3>
            <p className="mt-0.5 text-xs text-gray-500">
              Tour dài {maxDays} ngày, đã khai {items.length} ngày.
              {conThieu > 0 && ` Còn thiếu ${conThieu} ngày.`}
            </p>
          </div>
        </div>

        <div className="flex gap-2">
          {conThieu > 1 && (
            <button
              type="button"
              onClick={() => them(conThieu)}
              className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50"
            >
              Tạo đủ {conThieu} ngày
            </button>
          )}
          <button
            type="button"
            onClick={() => them()}
            disabled={conThieu === 0}
            className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400"
          >
            <Plus className="h-3.5 w-3.5" />
            Thêm ngày
          </button>
        </div>
      </div>

      {items.length === 0 && (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50/60 px-6 py-10 text-center">
          <p className="text-sm font-semibold text-gray-700">Chưa khai ngày nào</p>
          <p className="mt-1 text-xs text-gray-500">
            Khách xem tour sẽ không thấy lịch trình. Bấm "Thêm ngày" để bắt đầu.
          </p>
        </div>
      )}

      <div className="space-y-3">
        {items.map((item, index) => {
          const moRong = dangMo === index;
          const thieu = !item.title.trim() || !item.content.trim();

          return (
            <div
              key={index}
              className={`overflow-hidden rounded-xl border bg-white shadow-sm ${
                thieu ? "border-amber-200" : "border-gray-200"
              }`}
            >
              <div className="flex items-center gap-3 px-4 py-3">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white">
                  N{index + 1}
                </span>

                <button
                  type="button"
                  onClick={() => setDangMo(moRong ? null : index)}
                  className="flex min-w-0 flex-1 items-center gap-3 text-left"
                >
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-bold text-gray-900">
                      {item.title.trim() || `Ngày ${index + 1} — chưa đặt tiêu đề`}
                    </span>
                    <span className="mt-0.5 flex items-center gap-1 truncate text-[11px] text-gray-500">
                      {item.start_point || item.end_point ? (
                        <>
                          {item.start_point || "…"}
                          <ArrowRight className="h-3 w-3 shrink-0" />
                          {item.end_point || "…"}
                        </>
                      ) : (
                        "Chưa khai điểm đi và điểm đến"
                      )}
                      {(item.checkpoints?.length ?? 0) > 0 &&
                        ` · ${item.checkpoints?.length} điểm dừng`}
                    </span>
                  </span>

                  {thieu && (
                    <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                      Còn thiếu
                    </span>
                  )}
                  <ChevronDown
                    className={`h-4 w-4 shrink-0 text-gray-400 transition-transform ${
                      moRong ? "rotate-180" : ""
                    }`}
                  />
                </button>

                <button
                  type="button"
                  onClick={() => xoa(index)}
                  aria-label={`Xóa ngày ${index + 1}`}
                  className="shrink-0 rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>

              {moRong && (
                <div className="space-y-4 border-t border-gray-100 bg-gray-50/40 p-4">
                  <div>
                    <label className={labelClass}>
                      Tiêu đề ngày <span className="text-red-500">*</span>
                    </label>
                    <input
                      required
                      value={item.title}
                      onChange={(e) => sua(index, { title: e.target.value })}
                      placeholder="VD: Khởi hành Hà Nội - du thuyền vịnh Hạ Long"
                      className={fieldClass}
                    />
                  </div>

                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <label className={labelClass}>Điểm đầu trong ngày</label>
                      <input
                        value={item.start_point}
                        onChange={(e) => sua(index, { start_point: e.target.value })}
                        placeholder="VD: Hà Nội"
                        className={fieldClass}
                      />
                    </div>
                    <div>
                      <label className={labelClass}>Điểm đến trong ngày</label>
                      <input
                        value={item.end_point}
                        onChange={(e) => sua(index, { end_point: e.target.value })}
                        placeholder="VD: Hạ Long"
                        className={fieldClass}
                      />
                    </div>
                  </div>

                  <div>
                    <div className="mb-2 flex items-center justify-between gap-3">
                      <label className={`${labelClass} mb-0`}>
                        Các chặng đi qua
                        <span className="ml-1.5 text-xs font-medium text-gray-400">
                          (không bắt buộc)
                        </span>
                      </label>
                      <button
                        type="button"
                        onClick={() =>
                          sua(index, { route_points: [...item.route_points, ""] })
                        }
                        className="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-2.5 py-1.5 text-[11px] font-semibold text-primary-700 hover:bg-primary-100"
                      >
                        <Plus className="h-3.5 w-3.5" />
                        Thêm chặng
                      </button>
                    </div>

                    <div className="space-y-2">
                      {item.route_points.length === 0 && (
                        <p className="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-2.5 text-[11px] text-gray-500">
                          Chưa khai chặng nào, và để nguyên như vậy cũng được. Tour đi thẳng thì
                          không có chặng trung gian để ghi.
                        </p>
                      )}

                      {item.route_points.map((point, viTri) => (
                        <div key={viTri} className="flex items-center gap-2">
                          <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">
                            {viTri + 1}
                          </span>
                          <input
                            value={point}
                            onChange={(e) => suaChang(index, viTri, e.target.value)}
                            placeholder={`VD: ${
                              ["Hải Dương", "Uông Bí", "Bãi Cháy"][viTri] ?? "Điểm dừng tiếp theo"
                            }`}
                            className={fieldClass}
                          />
                          {/* Xóa được cả hàng cuối cùng: trường này vốn không bắt buộc. */}
                          <button
                            type="button"
                            onClick={() =>
                              sua(index, {
                                route_points: item.route_points.filter((_, i) => i !== viTri),
                              })
                            }
                            aria-label={`Xóa chặng ${viTri + 1}`}
                            className="shrink-0 rounded-lg p-2 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="border-t border-gray-200 pt-4">
                    <CheckpointManager
                      checkpoints={item.checkpoints ?? []}
                      fieldClass={fieldClass}
                      onChange={(checkpoints) => sua(index, { checkpoints })}
                    />
                  </div>

                  <div className="grid grid-cols-1 gap-4 border-t border-gray-200 pt-4">
                    <div>
                      <label className={labelClass}>
                        Nội dung trong ngày <span className="text-red-500">*</span>
                      </label>
                      <textarea
                        required
                        rows={4}
                        value={item.content}
                        onChange={(e) => sua(index, { content: e.target.value })}
                        placeholder="Mô tả hoạt động, bữa ăn, nghỉ ngơi trong ngày..."
                        className={`${fieldClass} resize-y`}
                      />
                    </div>
                    <div>
                      <label className={labelClass}>
                        Điểm nghỉ chân
                        <span className="ml-1.5 text-xs font-medium text-gray-400">
                          (không bắt buộc)
                        </span>
                      </label>
                      <textarea
                        rows={2}
                        value={item.rest_stops}
                        onChange={(e) => sua(index, { rest_stops: e.target.value })}
                        placeholder="VD: Trạm dừng Sao Đỏ"
                        className={`${fieldClass} resize-y`}
                      />
                    </div>
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};
