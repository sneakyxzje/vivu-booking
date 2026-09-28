import type { AttendanceData, UploadCheckinPhotoResult } from "../types/guide";
import { attendanceAccess, attendanceNow, type AttendanceClock } from "./attendanceAccess.ts";

export class AttendancePhotoError extends Error {}

type PhotoUploadDependencies = {
  getAttendance: (scheduleId: number) => Promise<AttendanceData | null>;
  upload: (scheduleId: number, checkpointId: number, file: File) => Promise<UploadCheckinPhotoResult | null>;
  elapsed: () => number;
  onAccess: (data: AttendanceData, clock: AttendanceClock) => void;
};

/** A file picker can trigger focus/visibility refreshes. Check fresh permission instead of
 * dropping the selected file because the page clock is temporarily being synchronized. */
export async function uploadAttendancePhoto(scheduleId: number, checkpointId: number, file: File, deps: PhotoUploadDependencies) {
  if (file.size > 5 * 1024 * 1024) throw new AttendancePhotoError("Ảnh không được vượt quá 5MB. Hãy chọn ảnh nhỏ hơn.");
  if (file.type && !file.type.startsWith("image/")) throw new AttendancePhotoError("Vui lòng chọn tệp hình ảnh.");

  const latest = await deps.getAttendance(scheduleId);
  if (!latest) throw new AttendancePhotoError("Chưa kiểm tra được quyền thêm ảnh. Vui lòng thử lại.");
  const clock = { timestamp: Date.parse(latest.schedule.server_now), receivedAt: deps.elapsed() };
  deps.onAccess(latest, clock);
  const checkpoint = latest.checkpoints.find(point => point.id === checkpointId);
  if (!checkpoint) throw new AttendancePhotoError("Điểm dừng không còn trong chuyến này. Vui lòng tải lại trang.");
  const checkAccess = () => {
    const reason = attendanceAccess(latest.schedule, checkpoint, attendanceNow(clock, deps.elapsed()));
    if (reason) throw new AttendancePhotoError(reason);
  };
  checkAccess();
  // The API also checks assignment, date and trip status before and after storing the image.
  return deps.upload(scheduleId, checkpointId, file);
}
