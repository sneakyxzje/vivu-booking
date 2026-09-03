import React, { useMemo, useState } from "react";
import {
  AlertTriangle,
  CalendarDays,
  CalendarPlus,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Trash2,
  Users,
} from "lucide-react";
import type { Guide } from "@/types";
import type { ScheduleFormItem } from "./types";
import {
  GIO_MAC_DINH,
  GIO_VE_MAC_DINH,
  SO_KHACH_TOI_DA_MAC_DINH,
  SO_KHACH_TOI_THIEU_MAC_DINH,
  daDoiHanChot,
  hanChotMacDinh,
  ketThucMacDinh,
  taoChuyen,
} from "./formHelpers";
import { LY_DO_DOI_HAN_TOI_THIEU, statusLabel } from "@/utils/schedule";
import { DateTimePicker } from "@/components/DateTimePicker";
import {
  TEN_THANG,
  THU,
  dauNgay,
  doiSangNgay,
  hai,
  hienThiNgay,
  khoaNgay,
  luoiThang,
} from "@/components/date/dateHelpers";

/**
 * Lịch khởi hành: bấm ngày trên lịch tháng để mở chuyến, chỉnh chi tiết ở danh sách bên cạnh.
 *
 * ## Vì sao là lịch chứ không phải danh sách biểu mẫu
 *
 * Một tour bán quanh năm có vài chục chuyến. Kiểu cũ bắt khai từng chuyến bằng một thẻ sáu ô
 * nhập, mỗi thẻ cao gần một màn hình, và ngày tháng chỉ đọc được sau khi mở từng bộ chọn ra
 * xem. Không ai trả lời nổi câu "tháng 9 đã mở những ngày nào" mà không cuộn hết danh sách.
 *
 * Lịch trả lời câu ấy bằng một cái nhìn, và mở mười chuyến là mười cú bấm. Đây cũng là cách
 * các trang bán tour dựng phần này.
 *
 * ## Một nguồn sự thật
 *
 * Component nhận cả mảng và trả về cả mảng qua `onChange`. Trước đây có bốn hàm gọi ngược
 * (thêm, xóa, sửa một trường, sửa danh sách hướng dẫn viên), và luật "hạn chốt mặc định trước
 * ba ngày" nằm ở màn cha — xa chỗ người dùng nhìn thấy nó.
 */

/** Thứ trong tuần theo `getDay()` — 0 là Chủ nhật. */
const THU_NGAN = ["CN", "T2", "T3", "T4", "T5", "T6", "T7"];

/** Khoảng ngày một chuyến chiếm chỗ của hướng dẫn viên: từ ngày đi tới ngày về. */
const khoangChuyen = (batDau: string, soNgay: number) => {
  const dau = doiSangNgay(batDau);
  if (!dau) return null;

  const cuoi = new Date(dau);
  cuoi.setDate(cuoi.getDate() + Math.max(0, soNgay - 1));
  return { dau, cuoi };
};

const nhanNgay = (batDau: string) => {
  const d = doiSangNgay(batDau);
  if (!d) return "Chưa chọn ngày";

  return `${THU_NGAN[d.getDay()]}, ${hai(d.getDate())}/${hai(d.getMonth() + 1)}/${d.getFullYear()}`;
};

const NHAN_TRANG_THAI: Record<string, { chu: string; lop: string }> = {
  open: { chu: "Đang mở bán", lop: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
  closed: { chu: "Tạm đóng", lop: "bg-gray-100 text-gray-600 ring-gray-200" },
  confirmed: { chu: "Đã chốt chuyến", lop: "bg-blue-50 text-blue-700 ring-blue-200" },
  in_progress: { chu: "Đang khởi hành", lop: "bg-amber-50 text-amber-700 ring-amber-200" },
  completed: { chu: "Đã kết thúc", lop: "bg-indigo-50 text-indigo-700 ring-indigo-200" },
  cancelled: { chu: "Đã hủy", lop: "bg-rose-50 text-rose-700 ring-rose-200" },
};

/**
 * Chuyến còn ở giai đoạn bán, tức trạng thái của nó sửa được từ biểu mẫu này.
 *
 * Khớp với `AdminTourController::conDangBan()`. Sau khi chốt chạy thì vòng đời do lệnh nền và màn
 * quản lý chuyến điều khiển, biểu mẫu tour không có tiếng nói ở đó.
 */
const conDangBan = (status: string): boolean => status === "open" || status === "closed";

interface Props {
  fieldClass: string;
  items: ScheduleFormItem[];
  numberOfDays: number;
  /** Hướng dẫn viên đang rảnh, tra theo `uid` của chuyến — xem ghi chú ở `ScheduleFormItem.uid`. */
  guidesByUid: Record<string, Guide[]>;
  availabilityLoading: boolean;
  onChange: (next: ScheduleFormItem[]) => void;
}

export const TourFormScheduleSection: React.FC<Props> = ({
  fieldClass,
  items,
  numberOfDays,
  guidesByUid,
  availabilityLoading,
  onChange,
}) => {
  /*
   * Mở ở tháng của chuyến sắp tới gần nhất, không phải chuyến đầu danh sách. Tour đã bán vài
   * năm thì chuyến đầu tiên nằm ở quá khứ, mà mở lịch ra thấy một tháng toàn ngày bị chặn là
   * mở nhầm chỗ — người vào đây gần như luôn để thêm ngày sắp tới.
   */
  const [thangDangXem, setThangDangXem] = useState(() => {
    const homNayGoc = new Date();
    const mocGan = [...items]
      .map((item) => item.start_date)
      .filter((ngay) => ngay && ngay.slice(0, 10) >= khoaNgay(homNayGoc))
      .sort()[0];

    const goc = doiSangNgay(mocGan ?? "") ?? homNayGoc;
    return new Date(goc.getFullYear(), goc.getMonth(), 1);
  });
  const [dangChon, setDangChon] = useState<string[]>([]);
  const [dangMo, setDangMo] = useState<string | null>(null);

  // Mặc định áp cho mọi chuyến mở từ lịch. Đặt một lần rồi bấm mười ngày, thay vì sửa mười lần.
  const [gioMacDinh, setGioMacDinh] = useState(GIO_MAC_DINH);
  const [gioVeMacDinh, setGioVeMacDinh] = useState(GIO_VE_MAC_DINH);
  const [toiThieuMacDinh, setToiThieuMacDinh] = useState(SO_KHACH_TOI_THIEU_MAC_DINH);
  const [toiDaMacDinh, setToiDaMacDinh] = useState(SO_KHACH_TOI_DA_MAC_DINH);

  const homNay = dauNgay(new Date());

  /** Chuyến theo ngày, để lưới lịch biết ô nào đã mở. Ngày trống thì không có khóa. */
  const theoNgay = useMemo(() => {
    const bang = new Map<string, ScheduleFormItem[]>();

    items.forEach((item) => {
      const khoa = item.start_date.slice(0, 10);
      if (!khoa) return;
      bang.set(khoa, [...(bang.get(khoa) ?? []), item]);
    });

    return bang;
  }, [items]);

  /** Danh sách hiển thị luôn theo thứ tự thời gian; chuyến chưa có ngày xếp cuối. */
  const danhSach = useMemo(
    () =>
      [...items].sort((a, b) => {
        if (!a.start_date) return 1;
        if (!b.start_date) return -1;
        return a.start_date.localeCompare(b.start_date);
      }),
    [items],
  );

  const capNhat = (uid: string, thayDoi: Partial<ScheduleFormItem>) =>
    onChange(items.map((item) => (item.uid === uid ? { ...item, ...thayDoi } : item)));

  /**
   * Đổi ngày khởi hành thì kéo theo hạn chốt, nhưng chỉ khi hạn chốt còn đang là giá trị tự
   * sinh. Người đã tự đặt hạn chốt riêng thì không được ghi đè.
   */
  const doiNgayKhoiHanh = (item: ScheduleFormItem, giaTri: string) => {
    const conMacDinh =
      !item.booking_deadline || item.booking_deadline === hanChotMacDinh(item.start_date);

    /*
     * Mốc kết thúc cũng đi theo, nhưng chỉ khi nó còn đang là gợi ý.
     *
     * Cùng lý lẽ với hạn chốt: dời ngày đi mà để nguyên ngày về thì chuyến dài ra hoặc âm, và
     * người dùng không nhận ra vì ô kia nằm cách đó một cột. Ai đã tự đặt ngày về riêng — xe đêm
     * về sáng hôm sau chẳng hạn — thì không được ghi đè.
     *
     * Giữ nguyên GIỜ về họ đã chọn, chỉ dịch phần ngày: giờ trả khách là thỏa thuận với nhà xe,
     * nó không đổi chỉ vì chuyến dời sang tuần sau.
     */
    const gioVeHienTai = item.end_date?.slice(11, 16) || GIO_VE_MAC_DINH;
    const veConMacDinh =
      !item.end_date || item.end_date === ketThucMacDinh(item.start_date, numberOfDays, gioVeHienTai);

    capNhat(item.uid, {
      start_date: giaTri,
      booking_deadline: conMacDinh ? hanChotMacDinh(giaTri) : item.booking_deadline,
      end_date: veConMacDinh
        ? ketThucMacDinh(giaTri, numberOfDays, gioVeHienTai)
        : item.end_date,
    });
  };

  /**
   * Mở chuyến cho một loạt ngày.
   *
   * Bỏ qua theo NGÀY GIỜ chứ không theo ngày: một ngày chạy hai chuyến khác giờ là chuyện thường
   * của tour trong ngày, ca sáng và ca chiều. Chỉ trùng khít cả giờ mới là lặp.
   */
  const themNgay = (danhSachNgay: string[]) => {
    const daCo = new Set(items.map((item) => item.start_date));
    const moi = danhSachNgay
      .map((ngay) => `${ngay}T${gioMacDinh}`)
      .filter((batDau) => !daCo.has(batDau))
      .map((batDau) =>
        taoChuyen(batDau, {
          toiThieu: toiThieuMacDinh,
          toiDa: toiDaMacDinh,
          gioVe: gioVeMacDinh,
          soNgay: numberOfDays,
        }),
      );

    if (moi.length === 0) return;

    onChange([...items, ...moi]);
    setDangChon((cu) => [...cu, ...moi.map((item) => item.uid)]);
  };

  /**
   * Thêm một chuyến nữa vào đúng ngày của chuyến này.
   *
   * Lịch bên trái không làm được việc này: bấm vào ngày đã có chuyến là chọn chúng để sửa hàng
   * loạt, không phải mở thêm. Nên lối thêm nằm ngay trên dòng của chuyến, chỗ người ta đang nhìn.
   *
   * Giờ mặc định bị chiếm thì lùi sang giờ kế tiếp, để chuyến mới không trùng khít chuyến cũ.
   */
  const themCungNgay = (item: ScheduleFormItem) => {
    const ngay = item.start_date.slice(0, 10);
    if (!ngay) return;

    const gioDaCo = new Set(
      items
        .filter((khac) => khac.start_date.slice(0, 10) === ngay)
        .map((khac) => khac.start_date.slice(11, 16)),
    );

    let gio = gioMacDinh;
    for (let lui = 0; gioDaCo.has(gio) && lui < 24; lui++) {
      const [h, p] = gio.split(":");
      gio = `${hai((Number(h) + 1) % 24)}:${p}`;
    }

    const moi = taoChuyen(`${ngay}T${gio}`, {
      toiThieu: toiThieuMacDinh,
      toiDa: toiDaMacDinh,
      gioVe: gioVeMacDinh,
      soNgay: numberOfDays,
    });

    onChange([...items, moi]);
    setDangMo(moi.uid);
  };

  const xoaChuyen = (uids: string[]) => {
    const bo = new Set(uids);
    onChange(items.filter((item) => !bo.has(item.uid)));
    setDangChon((cu) => cu.filter((uid) => !bo.has(uid)));
    if (dangMo && bo.has(dangMo)) setDangMo(null);
  };

  const bamO = (ngay: Date) => {
    const khoa = khoaNgay(ngay);
    const daMo = theoNgay.get(khoa);

    // Ngày trống thì mở chuyến. Ngày đã có chuyến thì chọn/bỏ chọn để sửa hàng loạt — không xóa,
    // vì xóa nhầm một chuyến là mất luôn cả phân công hướng dẫn viên của nó.
    if (!daMo || daMo.length === 0) {
      themNgay([khoa]);
      return;
    }

    const uids = daMo.map((item) => item.uid);
    const dangChonHet = uids.every((uid) => dangChon.includes(uid));

    setDangChon((cu) =>
      dangChonHet
        ? cu.filter((uid) => !uids.includes(uid))
        : [...cu.filter((uid) => !uids.includes(uid)), ...uids],
    );
    setDangMo(uids[0]);
  };

  /** Mở hàng loạt các ngày cùng thứ trong tháng đang xem, bỏ qua ngày đã qua. */
  const themTheoThu = (thu: number) => {
    const ngayTrongThang = luoiThang(thangDangXem.getFullYear(), thangDangXem.getMonth())
      .filter((d): d is Date => d !== null)
      .filter((d) => d.getDay() === thu && d >= homNay);

    themNgay(ngayTrongThang.map(khoaNgay));
  };

  const apDungHangLoat = (thayDoi: Partial<ScheduleFormItem>) => {
    const chon = new Set(dangChon);
    onChange(items.map((item) => (chon.has(item.uid) ? { ...item, ...thayDoi } : item)));
  };

  /**
   * Đổi giờ về cho loạt chuyến đang chọn, giữ nguyên NGÀY về của từng chuyến.
   *
   * Không dùng `apDungHangLoat` được: mỗi chuyến về một ngày khác nhau, nên không có một mốc ngày
   * giờ chung nào để áp cả loạt. Thứ áp được chỉ là giờ trong ngày — đúng thứ người ta muốn khi
   * nhà xe đổi giờ trả khách cho cả tuyến.
   */
  const apGioVeHangLoat = (gio: string) => {
    const chon = new Set(dangChon);

    onChange(
      items.map((item) => {
        if (!chon.has(item.uid)) return item;

        const ngayVe = item.end_date?.slice(0, 10) || item.start_date.slice(0, 10);

        return { ...item, end_date: `${ngayVe}T${gio}` };
      }),
    );
  };

  /** Giờ đi tách riêng khỏi `apDungHangLoat`: đổi giờ thì hạn chốt tự sinh phải đi theo. */
  const apGioHangLoat = (gio: string) => {
    const chon = new Set(dangChon);

    onChange(
      items.map((item) => {
        if (!chon.has(item.uid) || !item.start_date) return item;

        const batDau = `${item.start_date.slice(0, 10)}T${gio}`;
        const conMacDinh =
          !item.booking_deadline || item.booking_deadline === hanChotMacDinh(item.start_date);

        return {
          ...item,
          start_date: batDau,
          booking_deadline: conMacDinh ? hanChotMacDinh(batDau) : item.booking_deadline,
        };
      }),
    );
  };

  /**
   * Hướng dẫn viên chọn được cho một chuyến: người máy chủ báo đang rảnh, trừ đi những người
   * vừa được xếp cho một chuyến khác trùng ngày ngay trong biểu mẫu này — máy chủ chưa biết
   * các chuyến đang soạn dở.
   */
  const huongDanVienChonDuoc = (item: ScheduleFormItem) => {
    const khoang = khoangChuyen(item.start_date, numberOfDays);

    return (guidesByUid[item.uid] ?? []).filter((guide) => {
      if (!khoang) return true;

      return !items.some((khac) => {
        if (khac.uid === item.uid || !khac.guide_ids.includes(String(guide.id))) return false;

        const khoangKhac = khoangChuyen(khac.start_date, numberOfDays);
        return (
          khoangKhac !== null &&
          khoang.dau <= khoangKhac.cuoi &&
          khoang.cuoi >= khoangKhac.dau
        );
      });
    });
  };

  const canhBao = (item: ScheduleFormItem): string | null => {
    if (!item.start_date) return "Chuyến này chưa có ngày khởi hành.";

    const toiThieu = Number(item.min_people);
    const toiDa = Number(item.max_people);

    if (toiThieu > 0 && toiDa > 0 && toiThieu > toiDa) {
      return `Khách tối thiểu (${toiThieu}) đang lớn hơn sức chứa (${toiDa}).`;
    }

    // Máy chủ đòi hạn chốt phải TRƯỚC giờ khởi hành, bằng nhau cũng bị từ chối.
    if (item.booking_deadline && item.booking_deadline >= item.start_date) {
      return "Hạn chốt phải trước giờ khởi hành.";
    }

    /*
     * Trùng là trùng cả GIỜ, không phải trùng ngày.
     *
     * Một ngày chạy nhiều chuyến khác giờ là chuyện bình thường - tour trong ngày có ca sáng ca
     * chiều, tour dài ngày có xe 5h và xe 8h. Cảnh báo theo ngày thì mọi tour bán nhiều ca đều
     * hiện một dòng đỏ không sai chỗ nào mà cũng không sửa được.
     *
     * Còn hai chuyến khởi hành đúng cùng một phút thì thật sự là gõ nhầm: khách nhìn hai dòng y
     * hệt nhau trong ô chọn ngày và không biết chọn cái nào.
     */
    const trung = items.filter(
      (khac) => khac.uid !== item.uid && khac.start_date === item.start_date,
    );
    if (trung.length > 0) return "Đã có chuyến khác khởi hành đúng ngày giờ này.";

    return null;
  };

  const o = luoiThang(thangDangXem.getFullYear(), thangDangXem.getMonth());
  const soChon = dangChon.length;

  return (
    <div className="space-y-4">
      <div className="grid gap-4 xl:grid-cols-[298px_minmax(0,1fr)]">
        {/* ── Lịch tháng ─────────────────────────────────────────────────── */}
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div className="mb-3 flex items-center justify-between">
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
            <button
              type="button"
              onClick={() => setThangDangXem(new Date(homNay.getFullYear(), homNay.getMonth(), 1))}
              title="Về tháng này"
              className="rounded-lg px-2 py-1 text-sm font-bold text-gray-900 transition-colors hover:bg-gray-100"
            >
              {TEN_THANG[thangDangXem.getMonth()]} {thangDangXem.getFullYear()}
            </button>
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

          <div className="grid grid-cols-7 gap-1">
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

              const khoa = khoaNgay(d);
              const chuyen = theoNgay.get(khoa) ?? [];
              const daMo = chuyen.length > 0;
              const duocChon = chuyen.some((item) => dangChon.includes(item.uid));
              const daQua = d < homNay;

              return (
                <button
                  key={khoa}
                  type="button"
                  disabled={daQua}
                  onClick={() => bamO(d)}
                  aria-pressed={daMo}
                  title={daMo ? "Chuyến đã mở — bấm để chọn sửa hàng loạt" : "Bấm để mở chuyến"}
                  className={[
                    "relative h-9 rounded-lg text-xs font-semibold transition-colors",
                    daQua
                      ? "cursor-not-allowed text-gray-300"
                      : daMo
                        ? "bg-primary-600 text-white hover:bg-primary-700"
                        : "text-gray-700 hover:bg-primary-50 hover:text-primary-700",
                    duocChon ? "ring-2 ring-primary-300 ring-offset-1" : "",
                  ].join(" ")}
                >
                  {d.getDate()}
                  {daMo && chuyen.length > 1 && (
                    <span className="absolute right-1 top-0.5 text-[9px] font-bold">
                      ×{chuyen.length}
                    </span>
                  )}
                </button>
              );
            })}
          </div>

          <p className="mt-3 flex items-center gap-1.5 text-[11px] text-gray-500">
            <span className="inline-block h-2.5 w-2.5 rounded-sm bg-primary-600" />
            Ngày đã mở chuyến. Bấm ngày trống để mở thêm.
          </p>

          {/* Mặc định áp cho chuyến mở từ lịch */}
          <div className="mt-4 space-y-3 border-t border-gray-100 pt-4">
            <p className="text-[11px] font-bold uppercase tracking-wide text-gray-500">
              Mặc định cho chuyến mới
            </p>
            <div className="grid grid-cols-2 gap-2">
              <label className="block">
                <span className="mb-1 block text-[10px] font-semibold text-gray-500">Giờ đi</span>
                <input
                  type="time"
                  value={gioMacDinh}
                  onChange={(e) => setGioMacDinh(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800 focus:border-primary-500 focus:outline-none"
                />
              </label>
              <label className="block">
                <span className="mb-1 block text-[10px] font-semibold text-gray-500">Giờ về</span>
                <input
                  type="time"
                  value={gioVeMacDinh}
                  onChange={(e) => setGioVeMacDinh(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800 focus:border-primary-500 focus:outline-none"
                />
              </label>
              <label className="block">
                <span className="mb-1 block text-[10px] font-semibold text-gray-500">Tối thiểu</span>
                <input
                  type="number"
                  min={1}
                  value={toiThieuMacDinh}
                  onChange={(e) => setToiThieuMacDinh(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800 focus:border-primary-500 focus:outline-none"
                />
              </label>
              <label className="block">
                <span className="mb-1 block text-[10px] font-semibold text-gray-500">Tối đa</span>
                <input
                  type="number"
                  min={1}
                  value={toiDaMacDinh}
                  onChange={(e) => setToiDaMacDinh(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-800 focus:border-primary-500 focus:outline-none"
                />
              </label>
            </div>

            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => themTheoThu(6)}
                className="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-200"
              >
                <CalendarPlus className="h-3.5 w-3.5" />
                Mọi thứ Bảy
              </button>
              <button
                type="button"
                onClick={() => themTheoThu(0)}
                className="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-200"
              >
                <CalendarPlus className="h-3.5 w-3.5" />
                Mọi Chủ nhật
              </button>
            </div>
          </div>
        </div>

        {/* ── Danh sách chuyến ───────────────────────────────────────────── */}
        <div className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm font-semibold text-gray-800">
              {items.length > 0 ? `${items.length} chuyến khởi hành` : "Chưa có chuyến nào"}
              {soChon > 0 && (
                <span className="ml-2 text-primary-600">· đang chọn {soChon}</span>
              )}
            </p>
            {items.length > 0 && (
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() =>
                    setDangChon(soChon === items.length ? [] : items.map((item) => item.uid))
                  }
                  className="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 hover:bg-gray-50"
                >
                  {soChon === items.length ? "Bỏ chọn hết" : "Chọn tất cả"}
                </button>
              </div>
            )}
          </div>

          {/* Sửa hàng loạt — chỉ hiện khi có chuyến đang chọn, vì ngoài lúc đó nó không có nghĩa. */}
          {soChon > 0 && (
            <div className="rounded-xl border border-primary-200 bg-primary-50/60 p-3">
              <p className="mb-2.5 text-xs font-bold text-primary-800">
                Áp cho {soChon} chuyến đang chọn
              </p>
              <div className="flex flex-wrap items-end gap-2.5">
                <label className="block">
                  <span className="mb-1 block text-[10px] font-semibold text-gray-600">Giờ đi</span>
                  <input
                    type="time"
                    defaultValue={gioMacDinh}
                    onChange={(e) => e.target.value && apGioHangLoat(e.target.value)}
                    className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs"
                  />
                </label>
                <label className="block">
                  <span className="mb-1 block text-[10px] font-semibold text-gray-600">Giờ về</span>
                  <input
                    type="time"
                    defaultValue={gioVeMacDinh}
                    onChange={(e) => e.target.value && apGioVeHangLoat(e.target.value)}
                    className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs"
                  />
                </label>
                <label className="block">
                  <span className="mb-1 block text-[10px] font-semibold text-gray-600">
                    Tối thiểu
                  </span>
                  <input
                    type="number"
                    min={1}
                    placeholder="—"
                    onChange={(e) =>
                      e.target.value && apDungHangLoat({ min_people: e.target.value })
                    }
                    className="w-20 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs"
                  />
                </label>
                <label className="block">
                  <span className="mb-1 block text-[10px] font-semibold text-gray-600">Tối đa</span>
                  <input
                    type="number"
                    min={1}
                    placeholder="—"
                    onChange={(e) =>
                      e.target.value && apDungHangLoat({ max_people: e.target.value })
                    }
                    className="w-20 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs"
                  />
                </label>
                <label className="block">
                  <span className="mb-1 block text-[10px] font-semibold text-gray-600">
                    Trạng thái
                  </span>
                  <select
                    onChange={(e) => e.target.value && apDungHangLoat({ status: e.target.value })}
                    defaultValue=""
                    className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs"
                  >
                    <option value="">Giữ nguyên</option>
                    <option value="open">Mở bán</option>
                    <option value="closed">Tạm đóng</option>
                  </select>
                </label>
                <button
                  type="button"
                  onClick={() => xoaChuyen(dangChon)}
                  className="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  Xóa {soChon} chuyến
                </button>
              </div>
            </div>
          )}

          {items.length === 0 ? (
            <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50/60 px-6 py-10 text-center">
              <CalendarDays className="mx-auto h-8 w-8 text-gray-300" />
              <p className="mt-3 text-sm font-semibold text-gray-700">
                Tour chưa có ngày khởi hành nào
              </p>
              <p className="mt-1 text-xs text-gray-500">
                Bấm một ngày trên lịch bên cạnh để mở chuyến đầu tiên. Khách chỉ đặt được những
                ngày có chuyến.
              </p>
            </div>
          ) : (
            <div className="max-h-[620px] space-y-2.5 overflow-y-auto pr-1">
              {danhSach.map((item) => {
                const moRong = dangMo === item.uid;
                const duocChon = dangChon.includes(item.uid);
                const loi = canhBao(item);
                const chonDuoc = huongDanVienChonDuoc(item);
                const daChonNhungHetRanh = item.guide_ids.filter(
                  (id) => !chonDuoc.some((guide) => String(guide.id) === id),
                );
                const trangThai = NHAN_TRANG_THAI[item.status] ?? NHAN_TRANG_THAI.open;

                const doiHuongDanVien = (guideId: string) =>
                  capNhat(item.uid, {
                    guide_ids: item.guide_ids.includes(guideId)
                      ? item.guide_ids.filter((id) => id !== guideId)
                      : [...item.guide_ids, guideId],
                  });

                return (
                  <div
                    key={item.uid}
                    className={`rounded-xl border bg-white shadow-sm transition-colors ${
                      loi
                        ? "border-red-200"
                        : duocChon
                          ? "border-primary-300 ring-1 ring-primary-200"
                          : "border-gray-200"
                    }`}
                  >
                    <div className="flex items-center gap-3 px-3 py-2.5">
                      <input
                        type="checkbox"
                        checked={duocChon}
                        onChange={() =>
                          setDangChon((cu) =>
                            cu.includes(item.uid)
                              ? cu.filter((uid) => uid !== item.uid)
                              : [...cu, item.uid],
                          )
                        }
                        aria-label={`Chọn chuyến ${nhanNgay(item.start_date)}`}
                        className="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                      />

                      <button
                        type="button"
                        onClick={() => setDangMo(moRong ? null : item.uid)}
                        className="flex min-w-0 flex-1 items-center gap-3 text-left"
                      >
                        <span className="min-w-0">
                          <span className="block truncate text-sm font-bold text-gray-900">
                            {nhanNgay(item.start_date)}
                            {item.start_date.length >= 16 && (
                              <span className="ml-1.5 font-semibold text-gray-500">
                                {item.start_date.slice(11, 16)}
                              </span>
                            )}
                          </span>
                          <span className="mt-0.5 block truncate text-[11px] text-gray-500">
                            {item.min_people}–{item.max_people} khách
                            {item.booking_deadline &&
                              ` · chốt ${hienThiNgay(item.booking_deadline, true)}`}
                            {item.guide_ids.length > 0 && ` · ${item.guide_ids.length} HDV`}
                          </span>
                        </span>

                        <span
                          className={`ml-auto shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ${trangThai.lop}`}
                        >
                          {trangThai.chu}
                        </span>
                        <ChevronDown
                          className={`h-4 w-4 shrink-0 text-gray-400 transition-transform ${
                            moRong ? "rotate-180" : ""
                          }`}
                        />
                      </button>

                      {/* Mở thêm một ca nữa trong cùng ngày. Lịch bên trái không làm được việc
                          này vì bấm vào ngày đã có chuyến là chọn để sửa hàng loạt. */}
                      <button
                        type="button"
                        onClick={() => themCungNgay(item)}
                        aria-label={`Thêm chuyến khác trong ngày ${nhanNgay(item.start_date)}`}
                        title="Thêm chuyến khác trong ngày này"
                        className="shrink-0 rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-primary-50 hover:text-primary-600"
                      >
                        <CalendarPlus className="h-4 w-4" />
                      </button>

                      <button
                        type="button"
                        onClick={() => xoaChuyen([item.uid])}
                        aria-label={`Xóa chuyến ${nhanNgay(item.start_date)}`}
                        className="shrink-0 rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>

                    {loi && (
                      <p className="flex items-center gap-1.5 border-t border-red-100 bg-red-50 px-3 py-1.5 text-[11px] font-medium text-red-700">
                        <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                        {loi}
                      </p>
                    )}

                    {moRong && (
                      <div className="space-y-4 border-t border-gray-100 p-4">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                          <div>
                            <label className="mb-1 block text-[11px] font-bold text-gray-600">
                              Khởi hành <span className="text-red-500">*</span>
                            </label>
                            <DateTimePicker
                              withTime
                              required
                              minDate={homNay}
                              value={item.start_date}
                              onChange={(giaTri) => doiNgayKhoiHanh(item, giaTri)}
                              placeholder="Chọn ngày giờ"
                              buttonClassName={`${fieldClass} flex items-center gap-2 !py-2 text-left text-xs`}
                            />
                          </div>

                          <div>
                            <label className="mb-1 block text-[11px] font-bold text-gray-600">
                              Kết thúc <span className="text-red-500">*</span>
                            </label>
                            {/*
                              Cả ngày lẫn giờ, do điều hành đặt. Ô mở sẵn một gợi ý theo số ngày
                              của tour, nhưng gợi ý ấy không ràng buộc gì: xe đêm trả khách sáng
                              hôm sau là chuyện thường, và số ngày trong mô tả tour không nói được.
                            */}
                            <DateTimePicker
                              withTime
                              minDate={
                                item.start_date ? doiSangNgay(item.start_date) ?? undefined : undefined
                              }
                              value={item.end_date}
                              onChange={(giaTri) => capNhat(item.uid, { end_date: giaTri })}
                              placeholder="Chọn ngày giờ về"
                              buttonClassName={`${fieldClass} flex items-center gap-2 !py-2 text-left text-xs`}
                            />
                          </div>

                          <div>
                            <label className="mb-1 block text-[11px] font-bold text-gray-600">
                              Tới điểm đến
                            </label>
                            <DateTimePicker
                              withTime
                              value={item.arrival_at}
                              onChange={(giaTri) => capNhat(item.uid, { arrival_at: giaTri })}
                              placeholder="Giờ áng chừng"
                              buttonClassName={`${fieldClass} flex items-center gap-2 !py-2 text-left text-xs`}
                            />
                          </div>

                          <div>
                            <label className="mb-1 block text-[11px] font-bold text-gray-600">
                              Rời điểm đến
                            </label>
                            <DateTimePicker
                              withTime
                              value={item.return_departure_at}
                              onChange={(giaTri) =>
                                capNhat(item.uid, { return_departure_at: giaTri })
                              }
                              placeholder="Giờ áng chừng"
                              buttonClassName={`${fieldClass} flex items-center gap-2 !py-2 text-left text-xs`}
                            />
                          </div>

                          <div>
                            <label className="mb-1 block text-[11px] font-bold text-gray-600">
                              Hạn chốt đặt
                            </label>
                            <DateTimePicker
                              withTime
                              maxDate={
                                item.start_date ? doiSangNgay(item.start_date) ?? undefined : undefined
                              }
                              value={item.booking_deadline}
                              onChange={(giaTri) =>
                                capNhat(item.uid, { booking_deadline: giaTri })
                              }
                              placeholder="Chọn hạn chốt"
                              buttonClassName={`${fieldClass} flex items-center gap-2 !py-2 text-left text-xs`}
                            />
                          </div>

                          <div className="grid grid-cols-2 gap-2">
                            <div>
                              <label className="mb-1 block text-[11px] font-bold text-gray-600">
                                Tối thiểu <span className="text-red-500">*</span>
                              </label>
                              <input
                                type="number"
                                min={1}
                                value={item.min_people}
                                onChange={(e) =>
                                  capNhat(item.uid, { min_people: e.target.value })
                                }
                                className={`${fieldClass} !py-2 text-xs`}
                              />
                            </div>
                            <div>
                              <label className="mb-1 block text-[11px] font-bold text-gray-600">
                                Tối đa <span className="text-red-500">*</span>
                              </label>
                              <input
                                type="number"
                                min={1}
                                value={item.max_people}
                                onChange={(e) =>
                                  capNhat(item.uid, { max_people: e.target.value })
                                }
                                className={`${fieldClass} !py-2 text-xs`}
                              />
                            </div>
                          </div>

                          <div>
                            <label className="mb-1 block text-[11px] font-bold text-gray-600">
                              Trạng thái mở bán
                            </label>
                            {/*
                              Chuyến đã qua giai đoạn bán thì chỉ đọc.

                              Vòng đời sau khi chốt do nơi khác điều khiển — lệnh nền, màn quản lý
                              chuyến, luồng hủy chuyến — nên bày một ô chọn hai giá trị ở đây là hứa
                              một thứ máy chủ sẽ không làm. Trước đây ô này còn hiện trống trơn vì
                              "confirmed" không khớp option nào.
                            */}
                            {conDangBan(item.status) ? (
                              <select
                                value={item.status}
                                onChange={(e) => capNhat(item.uid, { status: e.target.value })}
                                className={`${fieldClass} !py-2 text-xs`}
                              >
                                <option value="open">Đang mở bán</option>
                                <option value="closed">Tạm đóng bán</option>
                              </select>
                            ) : (
                              <>
                                <div
                                  className={`${fieldClass} !py-2 text-xs bg-gray-50 text-gray-500`}
                                >
                                  {statusLabel[item.status as keyof typeof statusLabel] ?? item.status}
                                </div>
                                <p className="mt-1 text-[11px] text-gray-400">
                                  Đổi trạng thái ở màn quản lý chuyến.
                                </p>
                              </>
                            )}
                          </div>
                        </div>

                        {/*
                          Dời hạn chốt của một chuyến đã tồn tại là thao tác phải giải trình.

                          Mốc ấy điều khiển năm quy tắc khác nhau — bán chỗ, sửa tên hành khách,
                          chuyển chuyến, ghép chuyến, và chỗ có về kho khi khách hủy hay không —
                          nên máy chủ từ chối lưu nếu không kèm lý do. Ô này chỉ hiện đúng lúc đó,
                          không hỏi những chuyến không đổi gì.
                        */}
                        {daDoiHanChot(item) && (
                          <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <label className="mb-1 flex items-center gap-1.5 text-[11px] font-bold text-amber-800">
                              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                              Lý do dời hạn chốt <span className="text-red-500">*</span>
                            </label>
                            <textarea
                              rows={2}
                              value={item.booking_deadline_reason ?? ""}
                              onChange={(e) =>
                                capNhat(item.uid, { booking_deadline_reason: e.target.value })
                              }
                              placeholder="VD: Nhà xe chốt sớm hơn một ngày so với thỏa thuận cũ."
                              className="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs outline-none focus:border-amber-400"
                            />
                            <p className="mt-1 text-[11px] text-amber-700">
                              Chuyến này đã có trên hệ thống, hạn chốt đang đổi từ{" "}
                              <strong>
                                {item.booking_deadline_goc
                                  ? hienThiNgay(item.booking_deadline_goc, true)
                                  : "mốc mặc định"}
                              </strong>
                              . Ghi ít nhất {LY_DO_DOI_HAN_TOI_THIEU} ký tự — nhật ký hệ thống giữ
                              lại đúng câu này.
                            </p>
                          </div>
                        )}

                        {/*
                          Hướng dẫn viên — chọn được nhiều người.

                          Đoàn đông thì một người không kham nổi. Bao nhiêu người là đủ thì điều
                          hành quyết, hệ thống không suy ra hộ từ số khách.
                        */}
                        <div>
                          <div className="mb-1.5 flex items-center gap-1.5">
                            <Users className="h-3.5 w-3.5 text-primary-600" />
                            <span className="text-[11px] font-bold text-gray-600">
                              Hướng dẫn viên
                              {item.guide_ids.length > 0 && (
                                <span className="ml-1 font-semibold text-primary-600">
                                  (đã chọn {item.guide_ids.length})
                                </span>
                              )}
                            </span>
                          </div>

                          {!item.start_date ? (
                            <p className="rounded-lg border border-dashed border-gray-200 bg-gray-50/60 px-3 py-2 text-[11px] text-gray-500">
                              Chọn ngày khởi hành trước để biết ai đang trống lịch.
                            </p>
                          ) : availabilityLoading ? (
                            <p className="text-[11px] text-gray-500">
                              Đang tìm hướng dẫn viên rảnh...
                            </p>
                          ) : (
                            <div className="flex max-h-32 flex-wrap gap-1.5 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50/50 p-2">
                              {chonDuoc.length === 0 && daChonNhungHetRanh.length === 0 && (
                                <span className="flex items-center gap-1 text-[11px] text-amber-700">
                                  <AlertTriangle className="h-3 w-3 shrink-0" />
                                  Không có hướng dẫn viên rảnh trong khoảng này.
                                </span>
                              )}

                              {chonDuoc.map((guide) => {
                                const chon = item.guide_ids.includes(String(guide.id));
                                return (
                                  <label
                                    key={guide.id}
                                    className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition-colors ${
                                      chon
                                        ? "border-primary-500 bg-primary-50 text-primary-700"
                                        : "border-gray-200 bg-white text-gray-600 hover:border-primary-200"
                                    }`}
                                  >
                                    <input
                                      type="checkbox"
                                      checked={chon}
                                      onChange={() => doiHuongDanVien(String(guide.id))}
                                      className="h-3.5 w-3.5 rounded border-gray-300 text-primary-600"
                                    />
                                    {guide.name}
                                  </label>
                                );
                              })}

                              {/* Người đã chọn trước đó nay vướng lịch: vẫn hiện để bỏ chọn được. */}
                              {daChonNhungHetRanh.map((id) => (
                                <label
                                  key={id}
                                  className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700"
                                >
                                  <input
                                    type="checkbox"
                                    checked
                                    onChange={() => doiHuongDanVien(id)}
                                    className="h-3.5 w-3.5 rounded border-gray-300 text-amber-600"
                                  />
                                  Người đã chọn nay vướng lịch khác
                                </label>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
