import { useMemo, useState } from "react";
import type { Tour, TourSchedule } from "@/types/tour";
import { formatPrice } from "@/utils/format";
import { getAvailableSlots, getScheduleUnavailableReason } from "@/utils/schedule";

/**
 * Lịch trình khởi hành — bảng chọn ngày đi trên trang chi tiết tour.
 *
 * ## Vì sao thay thẻ `select`
 *
 * Trước đây danh sách chuyến là một `<select>` gốc, mỗi dòng nhồi vào một chuỗi dài kiểu
 * "Khởi hành: 09/09/2026 (Còn 12 chỗ) - Hạn chốt: 06/09/2026". Ba thông tin quyết định việc chọn
 * ngày — còn mấy chỗ, bao giờ hết hạn, giá bao nhiêu — đều nằm sau một cú bấm và không liếc mà so
 * giữa các ngày được. Chọn ngày khởi hành là quyết định thật của khách, nó xứng đáng một khối
 * hiện sẵn chứ không phải một ô xổ xuống.
 *
 * ## Mã chuyến được suy ra, không lưu trong cơ sở dữ liệu
 *
 * `maChuyen()` dựng từ id tour, id chuyến và ngày khởi hành nên nó ổn định và không trùng, nhưng
 * đây **không phải một cột**. Nó tồn tại vì khách gọi điện cần đọc một thứ ngắn hơn "chuyến ngày 9
 * tháng 9 của tour Cao Bằng", và tổng đài tra ngược được về đúng chuyến. Ngày nào cần mã do người
 * đặt thì thêm cột thật rồi đọc từ đó, đừng sửa hàm này thành mã có ý nghĩa nghiệp vụ.
 *
 * ## Giờ đi có thật, giờ về thì không
 *
 * `start_date` và `end_date` đều là cột `dateTime` — migration `2026_08_06` đã đổi `start_date`
 * từ `date` sang — và form quản trị có ô **Giờ đi** với mặc định 08:00, sửa được từng chuyến hoặc
 * hàng loạt. Nên giờ khởi hành là dữ liệu người ta nhập thật, và bảng này hiện nó.
 *
 * `end_date` thì khác: `AdminTourController` tự tính nó bằng `start_date + (số ngày - 1)`, nên
 * phần giờ của nó **luôn bằng giờ đi** chứ không phải giờ đoàn về. In nó ra dưới nhãn "giờ về" là
 * nói với khách rằng xe về lúc 08:00 trong khi 08:00 là giờ xe chạy. Chưa có ô nhập giờ về ở đâu
 * cả, nên chiều về chỉ hiện ngày. Ngày nào thêm ô ấy thì hiện, không phải trước đó.
 *
 * ## Những gì cố ý KHÔNG hiện
 *
 * Bản mẫu của các hãng lớn còn có phụ thu phòng đơn và hạng "trẻ nhỏ" tách khỏi "trẻ em". Hệ
 * thống này không có bảng phụ thu, và bảng giá chỉ chia ba hạng. Bịa ra cho đủ ô là in lên trang
 * một con số không ai kiểm được — chỗ nào không có dữ liệu thì bỏ hẳn dòng đó.
 */

/** Thứ trong tuần viết tắt kiểu Việt: T2..T7 và CN. */
const thuTrongTuan = (ngay: Date): string => {
  const thu = ngay.getDay();

  return thu === 0 ? "CN" : `T${thu + 1}`;
};

/**
 * Đọc ngày từ chuỗi máy chủ trả về mà không để múi giờ xê dịch.
 *
 * Cột `start_date` là kiểu ngày, và máy chủ trả "2026-09-09" hoặc "2026-09-09 00:00:00".
 * `new Date("2026-09-09")` được hiểu là nửa đêm UTC; ở những múi giờ âm, ngày hiện ra lùi một
 * hôm. Cắt lấy mười ký tự đầu rồi dựng ngày theo giờ địa phương thì con số hiện ra luôn đúng
 * bằng con số trong cơ sở dữ liệu.
 */
const parseNgay = (chuoi: string): Date => {
  const [nam, thang, ngay] = chuoi.slice(0, 10).split("-").map(Number);

  return new Date(nam, (thang ?? 1) - 1, ngay ?? 1);
};

const dinhDangNgay = (ngay: Date): string =>
  `${String(ngay.getDate()).padStart(2, "0")}/${String(ngay.getMonth() + 1).padStart(2, "0")}/${ngay.getFullYear()}`;

/**
 * Giờ trong chuỗi máy chủ trả về, hoặc null nếu không có giờ đáng nói.
 *
 * Máy chủ trả "2026-09-09 08:00:00" — giờ Việt Nam dạng mộc, không hậu tố múi giờ, xem
 * `TourSchedule::serializeDate`. Cắt thẳng từ chuỗi chứ không qua `Date` để không có chỗ nào cộng
 * trừ mất bảy tiếng.
 *
 * Nửa đêm bị coi là "không đặt giờ" và không hiện. Đây là một đánh đổi có ý thức: form quản trị
 * mặc định 08:00 nên chuyến nhập đàng hoàng luôn có giờ thật, còn 00:00 gần như chỉ đến từ dữ
 * liệu cũ tạo trước khi có ô giờ. Cái giá là một chuyến khởi hành đúng nửa đêm sẽ không hiện giờ.
 */
const gioCua = (chuoi?: string | null): string | null => {
  if (!chuoi || chuoi.length < 16) return null;

  const gio = chuoi.slice(11, 16);

  return gio === "00:00" ? null : gio;
};

/** Mã đọc qua điện thoại được. Xem chú thích đầu tệp: suy ra, không phải cột. */
const maChuyen = (tour: Tour, schedule: TourSchedule): string => {
  const ngay = parseNgay(schedule.start_date);
  const phan = `${String(ngay.getDate()).padStart(2, "0")}${String(ngay.getMonth() + 1).padStart(2, "0")}${String(ngay.getFullYear()).slice(2)}`;

  return `VVB${String(tour.id).padStart(3, "0")}-${String(schedule.id).padStart(3, "0")}-${phan}`;
};

const khoaThang = (schedule: TourSchedule): string => schedule.start_date.slice(0, 7);

const nhanThang = (khoa: string): { thang: string; nam: string } => {
  const [nam, thang] = khoa.split("-");

  return { thang: `Tháng ${Number(thang)}`, nam };
};

/** Ngày về: lấy cột thật nếu có, không thì suy từ số ngày của tour. */
const ngayVeCua = (tour: Tour, schedule: TourSchedule): Date => {
  if (schedule.end_date) return parseNgay(schedule.end_date);

  const ve = parseNgay(schedule.start_date);
  ve.setDate(ve.getDate() + Math.max(0, (tour.number_of_days || 1) - 1));

  return ve;
};

type Props = {
  tour: Tour;
  selectedSchedule: TourSchedule | null;
  onScheduleChange: (schedule: TourSchedule) => void;
};

export const TourDepartures = ({ tour, selectedSchedule, onScheduleChange }: Props) => {
  const schedules = useMemo(
    () =>
      [...(tour.schedules ?? [])].sort((a, b) => a.start_date.localeCompare(b.start_date)),
    [tour.schedules],
  );

  /** Các tháng có chuyến, giữ nguyên thứ tự thời gian. */
  const thangCoChuyen = useMemo(() => {
    const khoa: string[] = [];

    schedules.forEach((schedule) => {
      const k = khoaThang(schedule);
      if (!khoa.includes(k)) khoa.push(k);
    });

    return khoa;
  }, [schedules]);

  /**
   * Tháng người dùng tự bấm. Null nghĩa là chưa bấm gì và để hệ thống tự quyết.
   *
   * Chỉ lưu đúng lựa chọn của người dùng, còn tháng đang hiện thì **tính lúc render**. Nhét cả
   * tháng đang hiện vào state rồi đồng bộ bằng effect là dựng thêm một bản sao của thứ đã suy ra
   * được, và bản sao ấy có lúc trễ hơn dữ liệu thật đúng một nhịp vẽ.
   */
  const [thangDaChon, setThangDaChon] = useState<string | null>(null);

  /*
   * Mặc định mở đúng tháng chứa chuyến đang chọn.
   *
   * Trang cha chọn sẵn chuyến đặt được gần nhất, nên nếu tháng đầu tiên đã hết chỗ thì tab phải
   * nằm ở tháng có chuyến ấy. Người dùng không phải đi tìm xem cái đang được chọn nằm ở đâu.
   */
  const thangDangXem = useMemo(() => {
    if (thangDaChon && thangCoChuyen.includes(thangDaChon)) return thangDaChon;

    const mongMuon = selectedSchedule ? khoaThang(selectedSchedule) : null;

    if (mongMuon && thangCoChuyen.includes(mongMuon)) return mongMuon;

    return thangCoChuyen[0] ?? "";
  }, [thangDaChon, thangCoChuyen, selectedSchedule]);

  const dangHien = useMemo(
    () => schedules.filter((schedule) => khoaThang(schedule) === thangDangXem),
    [schedules, thangDangXem],
  );

  if (!schedules.length) {
    return (
      <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm md:p-8">
        <h2 className="font-plus-jakarta text-xl font-bold text-gray-900 md:text-2xl">
          Lịch trình khởi hành
        </h2>
        <p className="mt-3 text-sm text-gray-500">
          Tour này chưa mở lịch khởi hành nào. Vui lòng liên hệ tổng đài để được báo ngày đi dự
          kiến.
        </p>
      </div>
    );
  }

  const bangGia = [
    { nhan: "Người lớn", tuoi: "Từ 12 tuổi trở lên", gia: tour.adult_price },
    { nhan: "Trẻ em", tuoi: "Từ 2 đến 11 tuổi", gia: tour.child_price },
    { nhan: "Em bé", tuoi: "Dưới 2 tuổi", gia: tour.infant_price },
  ];

  return (
    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm md:p-8">
      <h2 className="font-plus-jakarta text-xl font-bold text-gray-900 md:text-2xl">
        Lịch trình khởi hành
      </h2>

      {/* Tab tháng. Chỉ hiện tháng thật sự có chuyến — tab rỗng chỉ tổ làm người ta bấm hụt. */}
      <div className="mt-5 flex flex-wrap gap-3">
        {thangCoChuyen.map((khoa) => {
          const { thang, nam } = nhanThang(khoa);
          const dangXem = khoa === thangDangXem;

          return (
            <button
              key={khoa}
              type="button"
              onClick={() => setThangDaChon(khoa)}
              aria-pressed={dangXem}
              className={`min-w-[104px] rounded-xl border px-5 py-2.5 text-center transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 ${
                dangXem
                  ? "border-primary-600 bg-primary-600 text-white shadow-sm"
                  : "border-gray-200 bg-white text-gray-400 hover:border-primary-200 hover:text-primary-600"
              }`}
            >
              <span className="block text-sm font-bold leading-tight">{thang}</span>
              <span className="block text-xs leading-tight opacity-80">{nam}</span>
            </button>
          );
        })}
      </div>

      <div className="mt-5 space-y-4">
        {dangHien.map((schedule) => {
          const lyDoChan = getScheduleUnavailableReason(schedule, tour.status);
          const dangChon = selectedSchedule?.id === schedule.id;
          const ngayDi = parseNgay(schedule.start_date);
          const ngayVe = ngayVeCua(tour, schedule);
          const gioDi = gioCua(schedule.start_date);
          const gioChot = gioCua(schedule.booking_deadline);
          const conLai = getAvailableSlots(schedule);

          return (
            <div
              key={schedule.id}
              className={`overflow-hidden rounded-2xl border transition-colors ${
                dangChon
                  ? "border-primary-500 bg-white shadow-sm"
                  : "border-gray-200 bg-white hover:border-primary-200"
              } ${lyDoChan && !dangChon ? "opacity-60" : ""}`}
            >
              {/* Hàng đầu: ngày, mã chuyến, giá, nút chọn. Luôn hiện, kể cả khi chưa mở rộng. */}
              <div className="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                <span
                  className={`rounded-lg px-3.5 py-1.5 text-sm font-bold ${
                    dangChon ? "bg-primary-50 text-primary-700" : "bg-gray-100 text-gray-700"
                  }`}
                >
                  {thuTrongTuan(ngayDi)}, {dinhDangNgay(ngayDi)}
                </span>

                <span className="font-mono text-xs tracking-tight text-gray-500">
                  {maChuyen(tour, schedule)}
                </span>

                <div className="ml-auto flex items-center gap-4">
                  {!dangChon && (
                    <span className="font-plus-jakarta text-lg font-bold text-red-600">
                      {formatPrice(tour.adult_price)}
                    </span>
                  )}

                  {lyDoChan ? (
                    <span className="rounded-full bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-500">
                      {lyDoChan}
                    </span>
                  ) : (
                    <button
                      type="button"
                      onClick={() => onScheduleChange(schedule)}
                      aria-pressed={dangChon}
                      className={`rounded-full px-6 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 ${
                        dangChon
                          ? "bg-primary-600 text-white"
                          : "border border-gray-200 bg-white text-gray-600 hover:border-primary-300 hover:text-primary-600"
                      }`}
                    >
                      {dangChon ? "Đang chọn" : "Chọn"}
                    </button>
                  )}
                </div>
              </div>

              {dangChon && (
                <div className="border-t border-gray-100 px-5 pb-5 pt-4">
                  <p className="text-center text-sm font-semibold text-gray-700">Hành trình</p>

                  <div className="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 md:divide-x md:divide-gray-100">
                    <div className="md:pr-6">
                      <div className="flex items-baseline justify-between gap-3">
                        <span className="text-sm text-gray-500">
                          Ngày đi:{" "}
                          <strong className="font-semibold text-gray-900">
                            {dinhDangNgay(ngayDi)}
                          </strong>
                        </span>
                        {tour.vehicle_info && (
                          <span className="text-sm font-medium text-orange-600">
                            {tour.vehicle_info}
                          </span>
                        )}
                      </div>
                      {gioDi && (
                        <p className="mt-1 text-sm font-semibold text-gray-900">
                          Giờ khởi hành: {gioDi}
                        </p>
                      )}
                      <div className="mt-2 flex items-baseline justify-between gap-3 border-t border-dashed border-gray-200 pt-2 text-sm text-gray-700">
                        <span>{tour.start_location ?? "—"}</span>
                        <span>{tour.end_location ?? "—"}</span>
                      </div>
                    </div>

                    <div className="md:pl-6">
                      <div className="flex items-baseline justify-between gap-3">
                        <span className="text-sm text-gray-500">
                          Ngày về:{" "}
                          <strong className="font-semibold text-gray-900">
                            {dinhDangNgay(ngayVe)}
                          </strong>
                        </span>
                        {tour.vehicle_info && (
                          <span className="text-sm font-medium text-orange-600">
                            {tour.vehicle_info}
                          </span>
                        )}
                      </div>
                      <div className="mt-2 flex items-baseline justify-between gap-3 border-t border-dashed border-gray-200 pt-2 text-sm text-gray-700">
                        <span>{tour.end_location ?? "—"}</span>
                        <span>{tour.start_location ?? "—"}</span>
                      </div>
                    </div>
                  </div>

                  <p className="mt-6 text-center text-sm font-semibold text-gray-700">
                    Giá chuyến đi
                  </p>

                  <div className="mt-3 grid grid-cols-1 gap-x-10 gap-y-3 sm:grid-cols-2">
                    {bangGia.map((hang) => (
                      <div key={hang.nhan} className="flex items-baseline justify-between gap-4">
                        <span className="text-sm">
                          <strong className="font-semibold text-gray-900">{hang.nhan}</strong>
                          <span className="block text-xs text-gray-400">({hang.tuoi})</span>
                        </span>
                        <span className="font-plus-jakarta font-bold text-red-600 tabular-nums">
                          {formatPrice(hang.gia)}
                        </span>
                      </div>
                    ))}
                  </div>

                  {/*
                    Khối này không có ở bản mẫu của các hãng lớn, và đó là lợi thế chứ không phải
                    thừa: hệ thống biết chính xác còn bao nhiêu chỗ và bao giờ chốt danh sách, nên
                    nói ra được thay vì để khách đoán.
                  */}
                  <div className="mt-5 flex flex-wrap gap-x-8 gap-y-2 rounded-xl bg-gray-50 px-4 py-3 text-sm">
                    <span className="text-gray-600">
                      Số chỗ còn lại:{" "}
                      <strong className="font-semibold text-gray-900">{conLai}</strong>
                    </span>
                    {schedule.booking_deadline && (
                      <span className="text-gray-600">
                        Hạn chốt danh sách:{" "}
                        <strong className="font-semibold text-gray-900">
                          {dinhDangNgay(parseNgay(schedule.booking_deadline))}
                          {gioChot ? ` ${gioChot}` : ""}
                        </strong>
                      </span>
                    )}
                  </div>

                  {tour.pickup_location && (
                    <p className="mt-4 rounded-xl border border-orange-100 bg-orange-50/70 px-4 py-3 text-sm leading-relaxed text-orange-800">
                      Điểm đón: <strong className="font-semibold">{tour.pickup_location}</strong>.
                      Hướng dẫn viên liên hệ với quý khách trước ngày khởi hành. Vui lòng khai đủ
                      thông tin hành khách trước hạn chốt danh sách, vì sau mốc đó danh sách đã gửi
                      cho nhà cung cấp và không sửa được nữa.
                    </p>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default TourDepartures;
