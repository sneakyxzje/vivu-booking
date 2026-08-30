/**
 * Phép tính ngày dùng chung cho các bộ chọn thời gian.
 *
 * ## Một quy tắc xuyên suốt: chỉ đọc và ghi bằng thành phần GIỜ ĐỊA PHƯƠNG
 *
 * Không hàm nào ở đây gọi `toISOString()`. Hàm đó đổi sang UTC, nên với giờ Việt Nam mọi thời
 * điểm trước 7 giờ sáng đều lùi về ngày hôm trước — lỗi đã có thật trong dự án này ở bộ lọc ngày
 * khởi hành, nơi mở trang lúc 6 giờ sáng thì lịch cho chọn một ngày mà máy chủ từ chối.
 *
 * ## Giá trị trao đổi là CHUỖI
 *
 * `YYYY-MM-DD`, hoặc `YYYY-MM-DDTHH:mm` khi có giờ — đúng dạng máy chủ nhận, đúng dạng đặt vào
 * chuỗi truy vấn, và đúng dạng `<input type="datetime-local">` từng dùng. Trả về `Date` thì mỗi
 * nơi gọi lại tự định dạng một kiểu.
 */

export const hai = (n: number) => String(n).padStart(2, "0");

/** `Date` → `YYYY-MM-DD`. */
export const khoaNgay = (d: Date) =>
  `${d.getFullYear()}-${hai(d.getMonth() + 1)}-${hai(d.getDate())}`;

/** `YYYY-MM-DD...` → `Date` lúc 0 giờ địa phương. Chuỗi rỗng hoặc hỏng trả về null. */
export const doiSangNgay = (s: string): Date | null => {
  if (!s) return null;
  const [nam, thang, ngay] = s.slice(0, 10).split("-").map(Number);
  if (!nam || !thang || !ngay) return null;
  return new Date(nam, thang - 1, ngay);
};

/** Phần giờ `HH:mm` của một giá trị, hoặc mặc định khi giá trị chưa có giờ. */
export const layGio = (s: string, macDinh: string) =>
  s.length >= 16 ? s.slice(11, 16) : macDinh;

export const ghepGio = (ngay: string, gio: string, coGio: boolean) =>
  coGio && ngay ? `${ngay}T${gio}` : ngay;

export const cungNgay = (a: Date, b: Date) => khoaNgay(a) === khoaNgay(b);

/** Dạng người đọc: `30/08/2026` hoặc `30/08/2026 14:30`. */
export const hienThiNgay = (s: string, coGio: boolean) => {
  const d = doiSangNgay(s);
  if (!d) return "";
  const ngay = `${hai(d.getDate())}/${hai(d.getMonth() + 1)}/${d.getFullYear()}`;
  return coGio && s.length >= 16 ? `${ngay} ${s.slice(11, 16)}` : ngay;
};

/** Bỏ phần giờ của một `Date`, để so sánh theo ngày mà không lệch vì mấy tiếng chênh. */
export const dauNgay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

export const THU = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];

export const TEN_THANG = [
  "Tháng 1", "Tháng 2", "Tháng 3", "Tháng 4", "Tháng 5", "Tháng 6",
  "Tháng 7", "Tháng 8", "Tháng 9", "Tháng 10", "Tháng 11", "Tháng 12",
];

/**
 * Lưới ngày của một tháng, gồm cả ô trống đầu và cuối để đủ hàng 7 cột.
 *
 * Tuần bắt đầu từ Thứ Hai theo cách người Việt đọc lịch, nên phải xoay `getDay()` — hàm ấy trả
 * 0 cho Chủ nhật.
 */
export function luoiThang(nam: number, thang: number): (Date | null)[] {
  const dauThang = new Date(nam, thang, 1);
  const soNgay = new Date(nam, thang + 1, 0).getDate();
  const dem = (dauThang.getDay() + 6) % 7;

  const o: (Date | null)[] = Array(dem).fill(null);
  for (let i = 1; i <= soNgay; i++) o.push(new Date(nam, thang, i));
  while (o.length % 7 !== 0) o.push(null);

  return o;
}
