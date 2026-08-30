import React, { useCallback, useRef, useState } from "react";
import { CalendarDays, ChevronLeft, ChevronRight, X } from "lucide-react";
import { MonthGrid } from "./date/MonthGrid";
import type { OTrangThai } from "./date/MonthGrid";
import { PopoverNoi } from "./date/PopoverNoi";
import {
  TEN_THANG,
  cungNgay,
  dauNgay,
  doiSangNgay,
  ghepGio,
  hienThiNgay,
  khoaNgay,
  layGio,
} from "./date/dateHelpers";

/**
 * Chọn MỘT mốc thời gian. Cùng ngôn ngữ hình ảnh với `DateRangePicker`, dùng chung lưới lịch.
 *
 * ## Hai chế độ, vì hai câu hỏi khác nhau
 *
 * `calendar` — mốc gần hiện tại: giờ khởi hành, hạn chốt, hạn hiệu lực báo giá. Lịch mở ở tháng
 * đang xem và có nút lùi/tiến từng tháng, vì đích đến chỉ cách vài tháng.
 *
 * `birthday` — ngày sinh. Cùng cách ấy thì phải bấm nút lùi ba trăm lần để tới năm 1995. Chế độ
 * này thay hai nút lùi/tiến bằng hai ô chọn Năm và Tháng, và mở sẵn ở khoảng năm của một người
 * trưởng thành khi ô còn trống.
 */

interface Props {
  /** "" | "YYYY-MM-DD" | "YYYY-MM-DDTHH:mm" */
  value: string;
  onChange: (next: string) => void;
  withTime?: boolean;
  minDate?: Date;
  maxDate?: Date;
  mode?: "calendar" | "birthday";
  disabled?: boolean;
  required?: boolean;
  label?: string;
  placeholder?: string;
  className?: string;
  /** Lớp CSS của nút mở, để hòa vào biểu mẫu sẵn có của từng màn. */
  buttonClassName?: string;
}

/** Bao nhiêu năm được liệt kê trong ô chọn năm ở chế độ ngày sinh. */
const SO_NAM_NGAY_SINH = 100;

/** Tuổi giả định khi ô ngày sinh còn trống, để lịch mở ra gần chỗ cần chứ không ở năm nay. */
const TUOI_MAC_DINH = 30;

export const DateTimePicker: React.FC<Props> = ({
  value,
  onChange,
  withTime = false,
  minDate,
  maxDate,
  mode = "calendar",
  disabled = false,
  required = false,
  label,
  placeholder = "Chọn thời gian",
  className = "",
  buttonClassName,
}) => {
  const laNgaySinh = mode === "birthday";

  // Ngày sinh không bao giờ ở tương lai, kể cả khi nơi gọi quên truyền `maxDate`.
  const tranTren = laNgaySinh ? (maxDate ?? new Date()) : maxDate;

  const [mo, setMo] = useState(false);
  const [nhap, setNhap] = useState(value);
  const [thangDangXem, setThangDangXem] = useState(() => thangMoDau(value, laNgaySinh));

  // Neo là chính cái nút: bảng lịch vẽ ở `body` nên phải bám theo tọa độ của nút trên màn hình.
  const nut = useRef<HTMLButtonElement>(null);

  function thangMoDau(giaTri: string, ngaySinh: boolean) {
    const d = doiSangNgay(giaTri);
    if (d) return new Date(d.getFullYear(), d.getMonth(), 1);

    const goc = new Date();
    if (ngaySinh) goc.setFullYear(goc.getFullYear() - TUOI_MAC_DINH);

    return new Date(goc.getFullYear(), goc.getMonth(), 1);
  }

  /**
   * Mở bảng chọn và chép lại giá trị đang áp dụng.
   *
   * Làm trong sự kiện bấm chứ không trong `useEffect`: đây là hệ quả của một hành động, và đặt
   * vào effect thì mỗi lần `value` đổi lúc bảng đang mở lại ghi đè phần đang chọn dở.
   */
  const doiTrangThaiMo = () => {
    if (disabled) return;

    if (!mo) {
      setNhap(value);
      setThangDangXem(thangMoDau(value, laNgaySinh));
    }

    setMo((v) => !v);
  };

  // `useCallback` vì `PopoverNoi` gắn/gỡ trình nghe sự kiện theo tham chiếu hàm này.
  const dong = useCallback(() => setMo(false), []);

  const daChon = doiSangNgay(nhap);

  const duoi = minDate ? dauNgay(minDate) : null;
  const tren = tranTren ? dauNgay(tranTren) : null;
  const chan = (d: Date) => (!!duoi && d < duoi) || (!!tren && d > tren);

  const trangThai = (d: Date): OTrangThai =>
    daChon && cungNgay(d, daChon) ? "single" : "none";

  const chonNgay = (d: Date) => {
    if (chan(d)) return;
    // Giữ nguyên giờ đã chọn khi đổi ngày: người dùng chỉnh 08:00 rồi đổi sang ngày khác không
    // có lý do gì để mất con số ấy.
    setNhap(ghepGio(khoaNgay(d), layGio(nhap, "08:00"), withTime));
  };

  const apDung = () => {
    onChange(nhap);
    setMo(false);
  };

  const xoa = () => {
    onChange("");
    setNhap("");
    setMo(false);
  };

  const namHienTai = (tranTren ?? new Date()).getFullYear();
  const danhSachNam = Array.from({ length: SO_NAM_NGAY_SINH }, (_, i) => namHienTai - i);

  const nhanNut = value ? hienThiNgay(value, withTime) : placeholder;

  const lopNut =
    buttonClassName ??
    "flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left text-sm text-gray-800 transition-colors hover:bg-white focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60";

  return (
    <div className={`relative ${className}`}>
      {label && (
        <span className="mb-1 block text-[11px] font-semibold text-gray-500">
          {label}
          {required && <span className="ml-0.5 text-rose-500">*</span>}
        </span>
      )}

      <button
        ref={nut}
        type="button"
        disabled={disabled}
        onClick={doiTrangThaiMo}
        aria-haspopup="dialog"
        aria-expanded={mo}
        className={lopNut}
      >
        <CalendarDays className="h-4 w-4 shrink-0 text-gray-400" />
        <span className={`flex-1 truncate ${value ? "" : "text-gray-400"}`}>{nhanNut}</span>
        {value && !disabled && !required && (
          // <span> chứ không <button>: nút lồng trong nút là HTML không hợp lệ.
          <span
            role="button"
            tabIndex={0}
            aria-label="Xóa giá trị"
            onClick={(e) => {
              e.stopPropagation();
              xoa();
            }}
            onKeyDown={(e) => {
              if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                e.stopPropagation();
                xoa();
              }
            }}
            className="rounded p-0.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700"
          >
            <X className="h-3.5 w-3.5" />
          </span>
        )}
      </button>

      <PopoverNoi
        mo={mo}
        neo={nut}
        onDong={dong}
        nhan={label ?? "Chọn thời gian"}
        className="w-max max-w-[calc(100vw-1rem)] p-3"
      >
        <div>
          {laNgaySinh ? (
            /*
              Chọn năm và tháng bằng ô danh sách, không bằng nút lùi từng tháng.
              Từ năm nay lùi về 1995 là hơn ba trăm lần bấm.
            */
            <div className="mb-3 flex gap-2">
              <select
                aria-label="Năm sinh"
                value={thangDangXem.getFullYear()}
                onChange={(e) =>
                  setThangDangXem((t) => new Date(Number(e.target.value), t.getMonth(), 1))
                }
                className="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-sm font-semibold text-gray-800 focus:border-primary-500 focus:outline-none"
              >
                {danhSachNam.map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
              <select
                aria-label="Tháng sinh"
                value={thangDangXem.getMonth()}
                onChange={(e) =>
                  setThangDangXem((t) => new Date(t.getFullYear(), Number(e.target.value), 1))
                }
                className="flex-1 rounded-lg border border-gray-200 px-2 py-1.5 text-sm font-semibold text-gray-800 focus:border-primary-500 focus:outline-none"
              >
                {TEN_THANG.map((t, i) => (
                  <option key={t} value={i}>
                    {t}
                  </option>
                ))}
              </select>
            </div>
          ) : (
            <div className="mb-2 flex items-center justify-between">
              <button
                type="button"
                aria-label="Tháng trước"
                onClick={() =>
                  setThangDangXem((t) => new Date(t.getFullYear(), t.getMonth() - 1, 1))
                }
                className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <span className="text-sm font-bold text-gray-900">
                {TEN_THANG[thangDangXem.getMonth()]} {thangDangXem.getFullYear()}
              </span>
              <button
                type="button"
                aria-label="Tháng sau"
                onClick={() =>
                  setThangDangXem((t) => new Date(t.getFullYear(), t.getMonth() + 1, 1))
                }
                className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          )}

          <MonthGrid
            month={thangDangXem}
            trangThai={trangThai}
            chan={chan}
            onPick={chonNgay}
            vienHomNay={!laNgaySinh}
            tieuDe={laNgaySinh}
          />

          {withTime && (
            <div className="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
              <label className="flex items-center gap-2 text-xs text-gray-600">
                Giờ
                <input
                  type="time"
                  value={layGio(nhap, "08:00")}
                  disabled={!nhap}
                  onChange={(e) =>
                    setNhap((cu) => ghepGio(cu.slice(0, 10), e.target.value, true))
                  }
                  className="rounded-lg border border-gray-200 px-2 py-1 text-xs disabled:bg-gray-50 disabled:text-gray-400"
                />
              </label>
              {!nhap && (
                <span className="text-[11px] text-gray-400">Chọn ngày trước đã</span>
              )}
            </div>
          )}

          <div className="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
            {required ? (
              <span />
            ) : (
              <button
                type="button"
                onClick={xoa}
                className="text-xs font-semibold text-gray-500 hover:text-gray-800"
              >
                Xóa
              </button>
            )}
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setMo(false)}
                className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={apDung}
                disabled={!nhap}
                className="rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-primary-700 disabled:opacity-50"
              >
                Chọn
              </button>
            </div>
          </div>
        </div>
      </PopoverNoi>
    </div>
  );
};

export default DateTimePicker;
