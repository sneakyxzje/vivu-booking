import { useMemo } from "react";
import { CalendarRange, X } from "lucide-react";
import type { DashboardRange } from "@/services/adminService";

/**
 * Bộ lọc khoảng ngày cho các màn hình thống kê.
 *
 * ## Vì sao có cả nút dựng sẵn lẫn hai ô ngày
 *
 * Gần như mọi lần người ta mở bảng điều khiển là để hỏi một trong dăm câu quen thuộc: hôm nay thế
 * nào, tuần này thế nào, tháng này thế nào. Bắt họ chọn tay hai đầu ngày cho những câu ấy là bắt
 * làm bốn thao tác cho một câu hỏi. Hai ô ngày vẫn còn, để trả lời câu thứ sáu mà không nút nào
 * đoán trước được.
 *
 * ## Ô ngày dùng `input type="date"`
 *
 * Trình duyệt tự lo lịch bật lên, tự lo định dạng theo ngôn ngữ máy, và bàn phím dùng được ngay.
 * Giá trị trao đổi luôn là "YYYY-MM-DD" bất kể máy hiển thị thế nào — đúng dạng máy chủ nhận.
 *
 * `max` của ô bắt đầu và `min` của ô kết thúc trỏ vào nhau, nên không chọn được khoảng ngược. Máy
 * chủ vẫn kiểm lại: chặn ở giao diện là để đỡ phiền, không phải để tin.
 */

/** Hôm nay theo lịch máy, dạng "YYYY-MM-DD" — không đi qua `toISOString()` để khỏi lệch múi giờ. */
const khoaNgay = (ngay: Date): string =>
  `${ngay.getFullYear()}-${String(ngay.getMonth() + 1).padStart(2, "0")}-${String(ngay.getDate()).padStart(2, "0")}`;

const luiNgay = (soNgay: number): Date => {
  const ngay = new Date();
  ngay.setDate(ngay.getDate() - soNgay);
  return ngay;
};

type Props = {
  value: DashboardRange;
  onChange: (range: DashboardRange) => void;
  /** Khóa các ô khi đang tải, để một cú bấm vội không sinh hai lần gọi chồng nhau. */
  disabled?: boolean;
};

export const DateRangeFilter = ({ value, onChange, disabled }: Props) => {
  const homNay = khoaNgay(new Date());

  const nutDungSan = useMemo(() => {
    const dauThang = new Date();
    dauThang.setDate(1);

    const dauNam = new Date(new Date().getFullYear(), 0, 1);

    return [
      { nhan: "Hôm nay", from: homNay, to: homNay },
      { nhan: "7 ngày", from: khoaNgay(luiNgay(6)), to: homNay },
      { nhan: "30 ngày", from: khoaNgay(luiNgay(29)), to: homNay },
      { nhan: "Tháng này", from: khoaNgay(dauThang), to: homNay },
      { nhan: "Năm nay", from: khoaNgay(dauNam), to: homNay },
    ];
  }, [homNay]);

  const dangChon = (from: string, to: string) =>
    value.from === from && value.to === to;

  const coLoc = Boolean(value.from || value.to);

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex items-center gap-1.5 text-gray-400">
        <CalendarRange className="h-4 w-4" />
        <span className="text-xs font-semibold tracking-wider uppercase">
          Khoảng thời gian
        </span>
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        {nutDungSan.map((nut) => (
          <button
            key={nut.nhan}
            type="button"
            disabled={disabled}
            aria-pressed={dangChon(nut.from, nut.to)}
            onClick={() => onChange({ from: nut.from, to: nut.to })}
            className={`rounded-md border px-2.5 py-1.5 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50 ${
              dangChon(nut.from, nut.to)
                ? "border-indigo-200 bg-indigo-50 text-indigo-700"
                : "border-gray-200 bg-white text-gray-600 hover:border-indigo-200 hover:text-indigo-600"
            }`}
          >
            {nut.nhan}
          </button>
        ))}
      </div>

      <div className="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1">
        <input
          type="date"
          value={value.from ?? ""}
          max={value.to ?? homNay}
          disabled={disabled}
          aria-label="Từ ngày"
          onChange={(e) => onChange({ ...value, from: e.target.value || null })}
          className="w-[8.5rem] bg-transparent text-xs font-medium text-gray-700 focus:outline-none disabled:opacity-50"
        />
        <span className="text-xs text-gray-300">—</span>
        <input
          type="date"
          value={value.to ?? ""}
          min={value.from ?? undefined}
          max={homNay}
          disabled={disabled}
          aria-label="Đến ngày"
          onChange={(e) => onChange({ ...value, to: e.target.value || null })}
          className="w-[8.5rem] bg-transparent text-xs font-medium text-gray-700 focus:outline-none disabled:opacity-50"
        />
      </div>

      {coLoc && (
        <button
          type="button"
          disabled={disabled}
          onClick={() => onChange({ from: null, to: null })}
          className="flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-500 transition-colors hover:border-rose-200 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 disabled:opacity-50"
        >
          <X className="h-3.5 w-3.5" />
          Toàn thời gian
        </button>
      )}
    </div>
  );
};

export default DateRangeFilter;
