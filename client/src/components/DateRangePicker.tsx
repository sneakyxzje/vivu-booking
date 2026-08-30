import React, { useCallback, useMemo, useRef, useState } from "react";
import { CalendarDays, ChevronLeft, ChevronRight, X } from "lucide-react";
import { MonthGrid } from "./date/MonthGrid";
import type { OTrangThai } from "./date/MonthGrid";
import { PopoverNoi } from "./date/PopoverNoi";
import {
  cungNgay,
  dauNgay,
  doiSangNgay,
  ghepGio,
  hienThiNgay,
  khoaNgay,
  layGio,
} from "./date/dateHelpers";

/**
 * Chọn một khoảng thời gian: danh sách mốc dựng sẵn bên trái, lịch hai tháng bên phải.
 *
 * ## Vì sao một component dùng chung
 *
 * Bốn màn hình từng tự dựng hai ô `<input type="date">` cạnh nhau, mỗi màn một kiểu nhãn, một
 * cách chặn ngày, và không màn nào có mốc dựng sẵn. Người dùng muốn xem "tháng trước" phải tự
 * nhớ tháng trước bắt đầu và kết thúc ngày nào rồi gõ tay hai lần.
 *
 * ## Giá trị trao đổi là CHUỖI, không phải `Date`
 *
 * `YYYY-MM-DD`, hoặc `YYYY-MM-DDTHH:mm` khi bật `withTime` — đúng dạng máy chủ nhận và đúng dạng
 * đặt thẳng vào chuỗi truy vấn. Trả về `Date` thì mỗi nơi gọi lại phải tự định dạng, và sớm muộn
 * có nơi dùng `toISOString()` — hàm đổi sang UTC, khiến 00:00 ngày 1/9 giờ Việt Nam thành 17:00
 * ngày 31/8. Toàn bộ tệp này chỉ đọc và ghi ngày bằng các thành phần GIỜ ĐỊA PHƯƠNG.
 */

export interface DateRange {
  /** Rỗng nghĩa là không giới hạn đầu này. */
  from: string;
  to: string;
}

interface Props {
  value: DateRange;
  onChange: (next: DateRange) => void;
  /** Cho chọn cả giờ. Chỉ bật ở nơi máy chủ thực sự lọc theo giờ. */
  withTime?: boolean;
  /** Chặn mọi ngày trước mốc này. Dùng cho bộ lọc hướng tới tương lai. */
  minDate?: Date;
  /** Chặn mọi ngày sau mốc này. Mặc định không chặn. */
  maxDate?: Date;
  /**
   * Bộ mốc dựng sẵn hướng về đâu.
   *
   * `past` cho bộ lọc dữ liệu đã xảy ra (sổ giao dịch, nhật ký), `future` cho bộ lọc thứ sắp tới
   * (ngày khởi hành). Đưa "tháng trước" vào ô chọn ngày đi tour là mời khách lọc ra một khoảng
   * chắc chắn không còn chuyến nào.
   */
  presets?: "past" | "future";
  label?: string;
  placeholder?: string;
  className?: string;
}

// ── Mốc dựng sẵn ────────────────────────────────────────────────────────────

/**
 * Mọi mốc đều nhìn về QUÁ KHỨ, vì đây là bộ lọc cho dữ liệu đã xảy ra.
 *
 * "Tuần này" và "tháng này" tính tới hôm nay chứ không tới cuối kỳ: chọn tháng này mà nhận cả
 * những ngày chưa tới thì con số tổng ở trên vẫn đúng, nhưng khoảng ngày hiển thị lại nói một
 * điều không thật.
 *
 * Tuần bắt đầu từ Thứ Hai, theo cách người Việt đọc lịch.
 */
const MOC_DUNG_SAN: { nhan: string; tinh: () => [Date, Date] }[] = [
  {
    nhan: "Hôm nay",
    tinh: () => {
      const h = new Date();
      return [h, h];
    },
  },
  {
    nhan: "Hôm qua",
    tinh: () => {
      const h = new Date();
      h.setDate(h.getDate() - 1);
      return [h, h];
    },
  },
  {
    nhan: "7 ngày qua",
    tinh: () => {
      const den = new Date();
      const tu = new Date();
      tu.setDate(tu.getDate() - 6);
      return [tu, den];
    },
  },
  {
    nhan: "30 ngày qua",
    tinh: () => {
      const den = new Date();
      const tu = new Date();
      tu.setDate(tu.getDate() - 29);
      return [tu, den];
    },
  },
  {
    nhan: "Tuần này",
    tinh: () => {
      const den = new Date();
      const tu = new Date();
      // getDay(): 0 là Chủ nhật. Lùi về Thứ Hai gần nhất.
      const lui = (tu.getDay() + 6) % 7;
      tu.setDate(tu.getDate() - lui);
      return [tu, den];
    },
  },
  {
    nhan: "Tuần trước",
    tinh: () => {
      const den = new Date();
      den.setDate(den.getDate() - ((den.getDay() + 6) % 7) - 1);
      const tu = new Date(den);
      tu.setDate(tu.getDate() - 6);
      return [tu, den];
    },
  },
  {
    nhan: "Tháng này",
    tinh: () => {
      const den = new Date();
      return [new Date(den.getFullYear(), den.getMonth(), 1), den];
    },
  },
  {
    nhan: "Tháng trước",
    tinh: () => {
      const h = new Date();
      const tu = new Date(h.getFullYear(), h.getMonth() - 1, 1);
      // Ngày 0 của tháng này là ngày cuối tháng trước.
      const den = new Date(h.getFullYear(), h.getMonth(), 0);
      return [tu, den];
    },
  },
  {
    nhan: "Năm nay",
    tinh: () => {
      const h = new Date();
      return [new Date(h.getFullYear(), 0, 1), h];
    },
  },
];

/**
 * Mốc hướng về TƯƠNG LAI, cho bộ lọc "tôi muốn đi ngày nào".
 *
 * Cùng một component nhưng không dùng chung bộ mốc với quá khứ được: hai câu hỏi ngược chiều
 * nhau, và một danh sách chứa cả "tháng trước" lẫn "tháng sau" thì người dùng phải đọc kỹ mới
 * bấm đúng — trong khi mỗi màn hình chỉ bao giờ cần một chiều.
 */
const MOC_TUONG_LAI: { nhan: string; tinh: () => [Date, Date] }[] = [
  {
    nhan: "Cuối tuần này",
    tinh: () => {
      const h = new Date();
      const t7 = new Date(h);
      // Thứ Bảy gần nhất kể từ hôm nay; đang là T7 hoặc CN thì lấy luôn hôm nay.
      t7.setDate(t7.getDate() + ((6 - t7.getDay() + 7) % 7));
      const cn = new Date(t7);
      cn.setDate(cn.getDate() + 1);
      return [t7 < h ? h : t7, cn];
    },
  },
  {
    nhan: "7 ngày tới",
    tinh: () => {
      const tu = new Date();
      const den = new Date();
      den.setDate(den.getDate() + 6);
      return [tu, den];
    },
  },
  {
    nhan: "Tuần sau",
    tinh: () => {
      const tu = new Date();
      tu.setDate(tu.getDate() + (8 - ((tu.getDay() + 6) % 7) - 1));
      const den = new Date(tu);
      den.setDate(den.getDate() + 6);
      return [tu, den];
    },
  },
  {
    nhan: "30 ngày tới",
    tinh: () => {
      const tu = new Date();
      const den = new Date();
      den.setDate(den.getDate() + 29);
      return [tu, den];
    },
  },
  {
    nhan: "Tháng này",
    tinh: () => {
      const h = new Date();
      return [h, new Date(h.getFullYear(), h.getMonth() + 1, 0)];
    },
  },
  {
    nhan: "Tháng sau",
    tinh: () => {
      const h = new Date();
      return [
        new Date(h.getFullYear(), h.getMonth() + 1, 1),
        new Date(h.getFullYear(), h.getMonth() + 2, 0),
      ];
    },
  },
  {
    nhan: "3 tháng tới",
    tinh: () => {
      const tu = new Date();
      const den = new Date();
      den.setMonth(den.getMonth() + 3);
      return [tu, den];
    },
  },
];

export const DateRangePicker: React.FC<Props> = ({
  value,
  onChange,
  withTime = false,
  minDate,
  maxDate,
  presets = "past",
  label,
  placeholder = "Mọi thời điểm",
  className = "",
}) => {
  const [mo, setMo] = useState(false);
  const [nhap, setNhap] = useState<DateRange>(value);
  const [dangDiChuot, setDangDiChuot] = useState<string | null>(null);
  const [thangTrai, setThangTrai] = useState(() => {
    const goc = doiSangNgay(value.from) ?? new Date();
    return new Date(goc.getFullYear(), goc.getMonth(), 1);
  });

  // Neo là chính cái nút: bảng vẽ ở `body` để không `overflow-hidden` nào cắt được nó.
  const nut = useRef<HTMLButtonElement>(null);
  const danhSachMoc = presets === "future" ? MOC_TUONG_LAI : MOC_DUNG_SAN;

  /**
   * Mở bảng chọn, chép lại giá trị đang áp dụng để bấm Hủy quay về đúng nó.
   *
   * Làm ngay trong sự kiện bấm chứ không trong một `useEffect` theo dõi `mo`: đây là hệ quả của
   * một hành động, không phải việc đồng bộ với thứ gì bên ngoài. Đặt vào effect thì mỗi lần
   * `value` đổi trong lúc bảng đang mở, phần đang gõ dở lại bị ghi đè.
   */
  const doiTrangThaiMo = () => {
    if (!mo) setNhap(value);
    setMo((v) => !v);
  };

  // `useCallback` vì `PopoverNoi` gắn/gỡ trình nghe sự kiện theo tham chiếu hàm này.
  const dong = useCallback(() => setMo(false), []);

  const tuNgay = useMemo(() => doiSangNgay(nhap.from), [nhap.from]);
  const denNgay = useMemo(() => doiSangNgay(nhap.to), [nhap.to]);

  const bienDuoi = minDate ? dauNgay(minDate) : null;
  const bienTren = maxDate ? dauNgay(maxDate) : null;
  const ngoaiBien = (d: Date) => (!!bienDuoi && d < bienDuoi) || (!!bienTren && d > bienTren);

  const chonNgay = (d: Date) => {
    if (ngoaiBien(d)) return;

    const khoa = khoaNgay(d);

    // Chưa có mốc đầu, hoặc đã đủ cả hai đầu → bắt đầu một khoảng mới.
    if (!tuNgay || (tuNgay && denNgay)) {
      setNhap({ from: ghepGio(khoa, layGio(nhap.from, "00:00"), withTime), to: "" });
      return;
    }

    // Bấm vào ngày trước mốc đầu thì đảo lại, thay vì bắt người ta bắt đầu lại.
    if (d < tuNgay) {
      setNhap({
        from: ghepGio(khoa, "00:00", withTime),
        to: ghepGio(khoaNgay(tuNgay), withTime ? "23:59" : "", withTime),
      });
      return;
    }

    setNhap((cu) => ({ ...cu, to: ghepGio(khoa, layGio(cu.to, "23:59"), withTime) }));
  };

  const chonMoc = (tinh: () => [Date, Date]) => {
    const [tu, den] = tinh();
    setNhap({
      from: ghepGio(khoaNgay(tu), "00:00", withTime),
      to: ghepGio(khoaNgay(den), "23:59", withTime),
    });
    setThangTrai(new Date(tu.getFullYear(), tu.getMonth(), 1));
  };

  const apDung = () => {
    onChange(nhap);
    setMo(false);
  };

  const xoa = () => {
    onChange({ from: "", to: "" });
    setNhap({ from: "", to: "" });
    setMo(false);
  };

  const nhanNut = value.from || value.to
    ? `${hienThiNgay(value.from, withTime) || "…"} – ${hienThiNgay(value.to, withTime) || "…"}`
    : placeholder;

  const mocDangChon = danhSachMoc.find(({ tinh }) => {
    const [tu, den] = tinh();
    return (
      nhap.from.slice(0, 10) === khoaNgay(tu) && nhap.to.slice(0, 10) === khoaNgay(den)
    );
  })?.nhan;

  const trangThaiO = (d: Date): OTrangThai => {
    if (tuNgay && cungNgay(d, tuNgay)) return denNgay && cungNgay(d, denNgay) ? "single" : "start";
    if (denNgay && cungNgay(d, denNgay)) return "end";

    // Xem trước khoảng khi mới chọn một đầu và đang rê chuột trên lịch.
    const cuoi = denNgay ?? (dangDiChuot ? doiSangNgay(dangDiChuot) : null);
    if (tuNgay && cuoi && d > tuNgay && d < cuoi) return "between";

    return "none";
  };

  const veThang = (goc: Date) => (
    <MonthGrid
      month={goc}
      trangThai={trangThaiO}
      chan={ngoaiBien}
      onPick={chonNgay}
      onHover={(d) => setDangDiChuot(d ? khoaNgay(d) : null)}
    />
  );


  const thangPhai = new Date(thangTrai.getFullYear(), thangTrai.getMonth() + 1, 1);

  return (
    <div className={`relative ${className}`}>
      {label && (
        <span className="mb-1 block text-[11px] font-semibold text-gray-500">{label}</span>
      )}

      <button
        ref={nut}
        type="button"
        onClick={doiTrangThaiMo}
        aria-haspopup="dialog"
        aria-expanded={mo}
        className="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left text-sm text-gray-800 transition-colors hover:bg-white focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20"
      >
        <CalendarDays className="h-4 w-4 shrink-0 text-gray-400" />
        <span className={`flex-1 truncate ${value.from || value.to ? "" : "text-gray-400"}`}>
          {nhanNut}
        </span>
        {(value.from || value.to) && (
          // <span> chứ không <button>: nút lồng trong nút là HTML không hợp lệ, và trình duyệt
          // sẽ tự tách chúng ra theo cách không đoán trước được.
          <span
            role="button"
            tabIndex={0}
            aria-label="Xóa khoảng thời gian"
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
        nhan="Chọn khoảng thời gian"
        className="max-w-[calc(100vw-1rem)]"
      >
        <div className="flex flex-col sm:flex-row">
          {/* Mốc dựng sẵn. Bên trái, như mọi bộ chọn khoảng ngày người ta đã quen. */}
          <div className="flex shrink-0 flex-row gap-1 overflow-x-auto border-gray-100 p-2 sm:w-[150px] sm:flex-col sm:overflow-visible sm:border-r">
            {danhSachMoc.map((m) => (
              <button
                key={m.nhan}
                type="button"
                onClick={() => chonMoc(m.tinh)}
                className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-left text-xs font-semibold transition-colors ${
                  mocDangChon === m.nhan
                    ? "bg-primary-600 text-white"
                    : "text-gray-600 hover:bg-gray-100"
                }`}
              >
                {m.nhan}
              </button>
            ))}
          </div>

          <div className="p-3">
            <div className="mb-2 flex items-center justify-between">
              <button
                type="button"
                aria-label="Tháng trước"
                onClick={() =>
                  setThangTrai((t) => new Date(t.getFullYear(), t.getMonth() - 1, 1))
                }
                className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button
                type="button"
                aria-label="Tháng sau"
                onClick={() =>
                  setThangTrai((t) => new Date(t.getFullYear(), t.getMonth() + 1, 1))
                }
                className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>

            <div
              className="flex flex-col gap-5 sm:flex-row"
              onMouseLeave={() => setDangDiChuot(null)}
            >
              {veThang(thangTrai)}
              <div className="hidden sm:block">{veThang(thangPhai)}</div>
            </div>

            {withTime && (
              <div className="mt-3 flex items-center gap-3 border-t border-gray-100 pt-3">
                <label className="flex items-center gap-2 text-xs text-gray-600">
                  Từ
                  <input
                    type="time"
                    value={layGio(nhap.from, "00:00")}
                    disabled={!nhap.from}
                    onChange={(e) =>
                      setNhap((cu) => ({
                        ...cu,
                        from: ghepGio(cu.from.slice(0, 10), e.target.value, true),
                      }))
                    }
                    className="rounded-lg border border-gray-200 px-2 py-1 text-xs disabled:bg-gray-50 disabled:text-gray-400"
                  />
                </label>
                <label className="flex items-center gap-2 text-xs text-gray-600">
                  Đến
                  <input
                    type="time"
                    value={layGio(nhap.to, "23:59")}
                    disabled={!nhap.to}
                    onChange={(e) =>
                      setNhap((cu) => ({
                        ...cu,
                        to: ghepGio(cu.to.slice(0, 10), e.target.value, true),
                      }))
                    }
                    className="rounded-lg border border-gray-200 px-2 py-1 text-xs disabled:bg-gray-50 disabled:text-gray-400"
                  />
                </label>
              </div>
            )}

            <div className="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
              <button
                type="button"
                onClick={xoa}
                className="text-xs font-semibold text-gray-500 hover:text-gray-800"
              >
                Xóa khoảng
              </button>
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
                  className="rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-primary-700"
                >
                  Áp dụng
                </button>
              </div>
            </div>
          </div>
        </div>
      </PopoverNoi>
    </div>
  );
};

export default DateRangePicker;
