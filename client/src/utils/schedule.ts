import type { Tour, TourSchedule } from "@/types/tour";

type ScheduleStatus = TourSchedule["status"];

// Sáu trạng thái của vòng đời chuyến khởi hành, khớp với App\Enums\ScheduleStatus phía máy chủ.
// Không còn active / inactive / full: đó là giá trị của cột tours.status, không phải của chuyến.
export const statusLabel: Record<ScheduleStatus, string> = {
  open: "Đang mở bán",
  closed: "Đã đóng bán",
  confirmed: "Đã chốt chạy",
  in_progress: "Đang di chuyển",
  completed: "Đã hoàn thành",
  cancelled: "Đã hủy",
};

export const statusClasses: Record<ScheduleStatus, string> = {
  open: "bg-emerald-50 border-emerald-200 border text-emerald-800",
  closed: "bg-gray-100 border-gray-200 border text-gray-700",
  confirmed: "bg-blue-50 border-blue-200 border text-blue-800",
  in_progress: "bg-amber-50 border-amber-200 border text-amber-800",
  completed: "bg-indigo-50 border-indigo-200 border text-indigo-800",
  cancelled: "bg-rose-50 border-rose-200 border text-rose-800",
};

// Trạng thái của TOUR là ba giá trị khác hẳn, đừng dùng chung bảng nhãn với chuyến khởi hành.
// Một tour đang bán vẫn có chuyến đã chạy xong và chuyến bị hủy cùng lúc.
export const tourStatusLabel: Record<Tour["status"], string> = {
  active: "Đang hoạt động",
  inactive: "Tạm dừng",
  full: "Hết chỗ",
};
