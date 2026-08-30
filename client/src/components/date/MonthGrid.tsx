import React from "react";
import { TEN_THANG, THU, cungNgay, khoaNgay, luoiThang } from "./dateHelpers";

/**
 * Lưới ngày của một tháng. Chỉ vẽ, không quyết định gì.
 *
 * Trạng thái mỗi ô do nơi gọi trả lời qua `trangThai`: bộ chọn một ngày chỉ dùng `single`, bộ
 * chọn khoảng dùng thêm `start`/`end`/`between`. Để lưới tự suy ra thì nó phải biết cả hai kiểu
 * nghiệp vụ, và thêm kiểu thứ ba là lại sửa vào đây.
 */

export type OTrangThai = "none" | "single" | "start" | "end" | "between";

interface Props {
  /** Một ngày bất kỳ trong tháng cần vẽ. */
  month: Date;
  trangThai: (d: Date) => OTrangThai;
  chan?: (d: Date) => boolean;
  onPick: (d: Date) => void;
  onHover?: (d: Date | null) => void;
  /** Viền nhạt quanh ngày hôm nay. Tắt ở bộ chọn ngày sinh, nơi hôm nay không có ý nghĩa gì. */
  vienHomNay?: boolean;
  /** Tên tháng phía trên lưới. Tắt khi nơi gọi tự vẽ tiêu đề riêng. */
  tieuDe?: boolean;
}

export const MonthGrid: React.FC<Props> = ({
  month,
  trangThai,
  chan,
  onPick,
  onHover,
  vienHomNay = true,
  tieuDe = true,
}) => {
  const o = luoiThang(month.getFullYear(), month.getMonth());
  const homNay = new Date();

  return (
    <div className="w-[248px]">
      {tieuDe && (
        <p className="mb-2 text-center text-sm font-bold text-gray-900">
          {TEN_THANG[month.getMonth()]} {month.getFullYear()}
        </p>
      )}

      <div className="grid grid-cols-7 gap-y-1">
        {THU.map((t) => (
          <span
            key={t}
            className="py-1 text-center text-[10px] font-semibold uppercase text-gray-400"
          >
            {t}
          </span>
        ))}

        {o.map((d, i) => {
          if (!d) return <span key={`trong-${i}`} />;

          const bic = chan?.(d) ?? false;
          const tt = trangThai(d);
          const noiBat = tt === "single" || tt === "start" || tt === "end";

          return (
            <button
              key={khoaNgay(d)}
              type="button"
              disabled={bic}
              onClick={() => onPick(d)}
              onMouseEnter={() => onHover?.(d)}
              className={[
                "h-8 text-xs font-medium transition-colors",
                bic ? "cursor-not-allowed text-gray-300" : "cursor-pointer",
                noiBat
                  ? "bg-primary-600 font-bold text-white"
                  : tt === "between"
                    ? "bg-primary-50 text-primary-800"
                    : bic
                      ? ""
                      : "text-gray-700 hover:bg-gray-100",
                tt === "single"
                  ? "rounded-lg"
                  : tt === "start"
                    ? "rounded-l-lg"
                    : tt === "end"
                      ? "rounded-r-lg"
                      : "",
                vienHomNay && cungNgay(d, homNay) && !noiBat
                  ? "ring-1 ring-inset ring-primary-300"
                  : "",
              ].join(" ")}
            >
              {d.getDate()}
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default MonthGrid;
