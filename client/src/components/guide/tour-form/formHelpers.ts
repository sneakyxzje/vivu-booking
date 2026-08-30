import type { CheckpointItem, ItineraryFormItem, ScheduleFormItem } from "./types";
import { dauNgay, doiSangNgay, khoaNgay, layGio } from "@/components/date/dateHelpers";

/**
 * Những giá trị dựng sẵn và phép tính nhỏ dùng chung cho biểu mẫu tour.
 *
 * Để riêng khỏi các tệp component vì hai lẽ: nhiều bước cùng cần chúng, và một tệp vừa xuất
 * component vừa xuất hằng số thì mất khả năng thay nóng khi đang chạy (`react-refresh`).
 */

/** Giờ khởi hành gợi ý. Tour đường bộ ở Việt Nam gần như luôn xuất phát sáng sớm. */
export const GIO_MAC_DINH = "08:00";

/** Hạn chốt danh sách mặc định: trước ngày đi ba ngày, đủ để chốt phòng và suất ăn. */
export const SO_NGAY_HAN_CHOT = 3;

export const SO_KHACH_TOI_THIEU_MAC_DINH = "5";
export const SO_KHACH_TOI_DA_MAC_DINH = "10";

export const khoaChuyenMoi = () =>
  `ch-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;

/**
 * Hạn chốt gợi ý cho một mốc khởi hành.
 *
 * Kẹp không cho lùi về trước hôm nay: một hạn chốt đã trôi qua ngay lúc tạo thì tour mở ra là
 * đóng luôn, mà người tạo không hiểu vì sao.
 */
export const hanChotMacDinh = (batDau: string): string => {
  const ngay = doiSangNgay(batDau);
  if (!ngay) return "";

  const gio = layGio(batDau, GIO_MAC_DINH);
  ngay.setDate(ngay.getDate() - SO_NGAY_HAN_CHOT);

  const homNay = dauNgay(new Date());
  if (ngay < homNay) return `${khoaNgay(homNay)}T00:00`;

  return `${khoaNgay(ngay)}T${gio}`;
};

export const taoChuyen = (
  batDau: string,
  macDinh?: { toiThieu?: string; toiDa?: string; trangThai?: string },
): ScheduleFormItem => ({
  uid: khoaChuyenMoi(),
  start_date: batDau,
  min_people: macDinh?.toiThieu ?? SO_KHACH_TOI_THIEU_MAC_DINH,
  max_people: macDinh?.toiDa ?? SO_KHACH_TOI_DA_MAC_DINH,
  booking_deadline: hanChotMacDinh(batDau),
  status: macDinh?.trangThai ?? "open",
  guide_ids: [],
});

export const ngayRong = (thuTu: number): ItineraryFormItem => ({
  day_number: String(thuTu),
  title: "",
  start_point: "",
  end_point: "",
  route_points: [],
  rest_stops: "",
  content: "",
  checkpoints: [],
});

/** Đánh lại số ngày theo vị trí, để danh sách và `day_number` không bao giờ nói khác nhau. */
export const danhSoLai = (danhSach: ItineraryFormItem[]): ItineraryFormItem[] =>
  danhSach.map((item, i) => ({ ...item, day_number: String(i + 1) }));

export const checkpointRong = (): CheckpointItem => ({
  name: "",
  description: "",
  is_required_photo: false,
});
