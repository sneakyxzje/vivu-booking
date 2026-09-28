import type { ScheduleFormItem } from "./types";

export type ScheduleErrors = Partial<Record<keyof ScheduleFormItem, string>>;

export function scheduleErrors(item: ScheduleFormItem, others: ScheduleFormItem[] = []): ScheduleErrors {
  const errors: ScheduleErrors = {};
  if (!item.start_date) errors.start_date = "Chọn ngày và giờ khởi hành.";
  if (!item.end_date) errors.end_date = "Chọn ngày và giờ về tới nơi.";
  if (item.start_date && others.some(other => other.uid !== item.uid && other.start_date === item.start_date)) {
    errors.start_date = "Đã có chuyến khởi hành đúng ngày giờ này.";
  }
  const milestones = [
    ["start_date", "giờ khởi hành"],
    ["arrival_at", "giờ tới điểm đến"],
    ["return_departure_at", "giờ khởi hành về"],
    ["end_date", "giờ về tới nơi"],
  ] as const;
  let previous: { time: number; label: string } | undefined;
  for (const [field, label] of milestones) {
    if (!item[field]) continue;
    const time = Date.parse(item[field]);
    if (!Number.isFinite(time)) errors[field] = "Ngày giờ không hợp lệ.";
    else if (previous && time < previous.time) errors[field] = `${label[0].toUpperCase()}${label.slice(1)} phải bằng hoặc sau ${previous.label}.`;
    if (Number.isFinite(time)) previous = { time, label };
  }
  if (item.booking_deadline && Date.parse(item.booking_deadline) >= Date.parse(item.start_date)) {
    errors.booking_deadline = "Hạn chốt phải trước giờ khởi hành.";
  }
  for (const field of ["min_people", "max_people"] as const) {
    if (!Number.isInteger(Number(item[field])) || Number(item[field]) < 1) errors[field] = "Nhập số nguyên từ 1 trở lên.";
  }
  if (Number(item.min_people) > Number(item.max_people)) errors.min_people = "Số khách mục tiêu không được vượt quá số chỗ tối đa.";
  if (item.id && (item.booking_deadline ?? "") !== (item.booking_deadline_goc ?? "") && (item.booking_deadline_reason ?? "").trim().length < 10) {
    errors.booking_deadline_reason = "Nhập lý do đổi hạn chốt, ít nhất 10 ký tự.";
  }
  return errors;
}
