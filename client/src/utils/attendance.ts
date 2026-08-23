import type { PassengerCheckinStatus } from "@/types/guide";

/**
 * Cách hiển thị năm trạng thái điểm danh, dùng chung cho màn hướng dẫn viên và màn quản trị.
 *
 * Để một chỗ vì hai màn hình phải nói cùng một thứ tiếng. Nhãn lệch nhau giữa hai màn là kiểu
 * lỗi không ai nhận ra cho tới lúc đối chiếu khiếu nại, khi đó thì đã muộn.
 *
 * Danh sách này phải khớp với App\Enums\PassengerCheckinStatus phía máy chủ.
 */

/*
 * Không còn trường `icon`. Năm trạng thái này từng đi kèm một biểu tượng cảm xúc, nhưng nhãn đã
 * nói đủ và biểu tượng chỉ thêm một thứ phải giải mã — hình người đang chạy cho "rời đoàn sớm"
 * và bong bóng thoại cho "vắng có phép" là hai cái không ai đoán ra nếu chưa được kể.
 *
 * Cả hai màn dùng bảng này đều đã bỏ biểu tượng, nên bỏ luôn ở đây để không còn đường quay lại
 * lệch nhau.
 */
export interface AttendanceStatusConfig {
  label: string;
  badgeClass: string;
  buttonClass: string;
}

export const ATTENDANCE_STATUSES: Record<PassengerCheckinStatus, AttendanceStatusConfig> = {
  present: {
    label: "Có mặt",
    badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    buttonClass: "bg-emerald-600 text-white shadow-sm",
  },
  absent: {
    label: "Vắng mặt",
    badgeClass: "bg-rose-50 text-rose-700 border-rose-200",
    buttonClass: "bg-rose-600 text-white shadow-sm",
  },
  late: {
    label: "Đến muộn",
    badgeClass: "bg-amber-50 text-amber-700 border-amber-200",
    buttonClass: "bg-amber-500 text-white shadow-sm",
  },
  left_early: {
    label: "Rời đoàn sớm",
    badgeClass: "bg-purple-50 text-purple-700 border-purple-200",
    buttonClass: "bg-purple-600 text-white shadow-sm",
  },
  excused: {
    label: "Vắng có phép",
    badgeClass: "bg-blue-50 text-blue-700 border-blue-200",
    buttonClass: "bg-blue-600 text-white shadow-sm",
  },
};

export const ATTENDANCE_STATUS_ORDER: PassengerCheckinStatus[] = [
  "present",
  "absent",
  "late",
  "left_early",
  "excused",
];

/**
 * Quy tắc 7 của AttendanceService: mọi trạng thái khác "có mặt" phải kèm ghi chú đủ dài.
 *
 * Kiểm luôn ở đây để hướng dẫn viên biết ngay tại chỗ thay vì bấm lưu rồi nhận 422. Đây là bản
 * sao cho tiện dùng, không phải chốt chặn - chốt chặn thật nằm ở máy chủ và vẫn phải giữ, vì
 * kiểm tra phía trình duyệt thì ai cũng bỏ qua được.
 */
export const MIN_ATTENDANCE_NOTE_LENGTH = 10;

export const requiresNote = (status: PassengerCheckinStatus): boolean => status !== "present";

export const noteIsValid = (status: PassengerCheckinStatus, note: string): boolean =>
  !requiresNote(status) || note.trim().length >= MIN_ATTENDANCE_NOTE_LENGTH;

export const SUGGESTED_REASONS = [
  "Khách báo tự đi riêng theo lịch cá nhân",
  "Khách mệt, nghỉ ngơi tại khách sạn",
  "Khách chưa đến kịp, đang trên đường tới điểm tập trung",
  "Không liên lạc được qua số điện thoại khách",
  "Khách rời đoàn về sớm theo nguyện vọng",
];
