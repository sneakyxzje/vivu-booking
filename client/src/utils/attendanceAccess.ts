import type { AttendanceCheckpoint, AttendanceData } from "../types/guide";

export type AttendanceClock = { timestamp: number; receivedAt: number };

// Elapsed time is monotonic; changing the device clock does not unlock a day.
export const attendanceNow = (clock: AttendanceClock | null, elapsed: number): number =>
  clock ? clock.timestamp + Math.max(0, elapsed - clock.receivedAt) : NaN;

export const attendanceDate = (timestamp: number): string => {
  if (!Number.isFinite(timestamp)) return "";
  const parts = new Intl.DateTimeFormat("en-GB", { timeZone: "Asia/Ho_Chi_Minh", year: "numeric", month: "2-digit", day: "2-digit" }).formatToParts(timestamp);
  const value = (type: string) => parts.find(part => part.type === type)?.value;
  return `${value("year")}-${value("month")}-${value("day")}`;
};

export function attendanceAccess(schedule: AttendanceData["schedule"] | undefined, checkpoint: AttendanceCheckpoint | null, now: number): string | null {
  if (!schedule || !Number.isFinite(now)) return "Đang kiểm tra quyền điểm danh. Vui lòng chờ hoặc tải lại.";
  if (schedule.status === "cancelled") return "Chuyến đã hủy. Dữ liệu chỉ được xem lại.";
  if (schedule.status === "completed" || now > Date.parse(schedule.recording_ends_at)) return "Chuyến đã kết thúc. Điểm danh, ghi chú và ảnh chỉ được xem lại.";
  if (!schedule.can_record) return "Chuyến chưa khởi hành. Chưa thể điểm danh hoặc thêm ảnh.";
  if (!checkpoint?.attendance_date) return "Chọn điểm dừng để xem điểm danh.";
  const today = attendanceDate(now);
  if (checkpoint.attendance_date < today) return "Ngày này đã qua, chỉ được xem. Nếu cần đính chính, vui lòng báo điều hành.";
  if (checkpoint.attendance_date > today) return "Ngày này chưa tới. Chỉ được điểm danh và thêm ảnh trong đúng ngày của điểm dừng.";
  return null;
}
