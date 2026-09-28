import type { ItineraryFormItem } from "./types";

export const ngayRong = (day: number): ItineraryFormItem => ({
  day_number: String(day), title: "", content: "", start_point: "", end_point: "",
  route_points: [], rest_stops: "", checkpoints: [],
});

/** Fill missing days without dropping existing content or changing saved identities. */
export function fillItineraryDays(items: ItineraryFormItem[], days: number): ItineraryFormItem[] {
  if (!Number.isInteger(days) || days < 1) return items;
  const result = [...items];
  const present = new Set(items.map(item => Number(item.day_number)));
  for (let day = 1; day <= days; day++) {
    if (!present.has(day)) result.push(ngayRong(day));
  }
  return result.sort((a, b) => Number(a.day_number) - Number(b.day_number));
}

export function itineraryErrors(items: ItineraryFormItem[], days: number): string[] {
  const errors: string[] = [];
  if (Number.isInteger(days) && days > 0) {
    const numbers = items.map(item => Number(item.day_number));
    const missing = Array.from({ length: days }, (_, index) => index + 1).filter(day => !numbers.includes(day));
    if (missing.length) errors.push(`Chưa có lịch trình ngày ${missing.join(", ")}`);
    if (items.length > days) errors.push(`Tour ${days} ngày đang có ${items.length} ngày lịch trình. Xóa ngày thừa hoặc sửa thời lượng tour.`);
    if (new Set(numbers).size !== numbers.length || numbers.some(day => !Number.isInteger(day) || day < 1 || day > days)) {
      errors.push(`Lịch trình phải gồm các ngày từ 1 đến ${days}, không trùng ngày`);
    }
  }
  for (const item of items) {
    const missing = [!item.title.trim() && "tiêu đề", !item.content.trim() && "hoạt động"].filter(Boolean);
    if (missing.length) errors.push(`Ngày ${item.day_number} chưa có ${missing.join(" và ")}`);
  }
  return errors;
}
