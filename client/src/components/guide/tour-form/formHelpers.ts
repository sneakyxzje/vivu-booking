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

/**
 * Giờ về gợi ý.
 *
 * Chỉ là gợi ý, và điều hành sửa được từng chuyến — nhà xe mỗi tuyến trả khách một giờ khác nhau.
 * Có mặc định vì bỏ trống thì máy chủ lấy giờ về bằng giờ đi, mà con số ấy không nói lên điều gì
 * và giao diện khách phải giấu đi.
 */
export const GIO_VE_MAC_DINH = "18:00";

/**
 * Mốc kết thúc gợi ý cho một mốc khởi hành.
 *
 * Chỉ là **giá trị mở sẵn trong ô**, không phải luật: điều hành sửa được cả ngày lẫn giờ. Đây là
 * điểm khác với hạn chốt — hạn chốt có mốc mặc định của hệ thống, còn ngày về thì tùy chuyến, và
 * gợi ý theo số ngày của tour chỉ để người nhập đỡ phải gõ lại con số họ vừa khai ở trên.
 */
export const ketThucMacDinh = (
  batDau: string,
  soNgay: number,
  gioVe: string = GIO_VE_MAC_DINH,
): string => {
  const ngay = doiSangNgay(batDau);
  if (!ngay) return "";

  ngay.setDate(ngay.getDate() + Math.max(0, soNgay - 1));

  return `${khoaNgay(ngay)}T${gioVe}`;
};

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
  macDinh?: {
    toiThieu?: string;
    toiDa?: string;
    trangThai?: string;
    gioVe?: string;
    soNgay?: number;
  },
): ScheduleFormItem => ({
  uid: khoaChuyenMoi(),
  start_date: batDau,
  end_date: ketThucMacDinh(batDau, macDinh?.soNgay ?? 1, macDinh?.gioVe),
  /*
   * Hai mốc giữa để TRỐNG, không gợi ý gì.
   *
   * Khác mốc kết thúc: mốc ấy còn suy được từ số ngày của tour, còn giờ tới nơi thì phụ thuộc
   * quãng đường và loại xe — không có con số nào đoán hộ mà không sai. Điền sẵn một giờ bịa rồi
   * in lên trang cho khách đọc tệ hơn hẳn để trống.
   */
  arrival_at: "",
  return_departure_at: "",
  min_people: macDinh?.toiThieu ?? SO_KHACH_TOI_THIEU_MAC_DINH,
  max_people: macDinh?.toiDa ?? SO_KHACH_TOI_DA_MAC_DINH,
  booking_deadline: hanChotMacDinh(batDau),
  booking_deadline_reason: "",
  status: macDinh?.trangThai ?? "open",
  guide_ids: [],
});

/**
 * Chuyến đã có trên máy chủ và hạn chốt của nó vừa bị dời.
 *
 * Máy chủ đòi lý do đúng lúc này chứ không phải mọi lần lưu: biểu mẫu gửi lại toàn bộ danh sách
 * chuyến mỗi lần bấm lưu, mà phần lớn chúng không đổi gì. Hỏi lý do cho cả những chuyến không đổi
 * thì người dùng sẽ gõ bừa cho xong, và cột lý do trong nhật ký thành vô nghĩa.
 */
export const daDoiHanChot = (item: ScheduleFormItem): boolean =>
  Boolean(item.id) && (item.booking_deadline ?? "") !== (item.booking_deadline_goc ?? "");

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
