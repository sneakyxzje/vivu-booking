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

/**
 * Độ dài tối thiểu của lý do dời hạn chốt danh sách.
 *
 * Khớp với `ScheduleDeadlineService::LY_DO_TOI_THIEU` ở máy chủ. Máy chủ mới là nơi luật có hiệu
 * lực; con số ở đây chỉ để nút lưu không mời người dùng bấm vào một thứ chắc chắn bị từ chối.
 */
export const LY_DO_DOI_HAN_TOI_THIEU = 10;

export const getAvailableSlots = (schedule?: TourSchedule | null): number =>
  schedule ? Math.max(0, schedule.max_people - schedule.booked_people) : 0;

export const isDeadlineOverdue = (schedule?: TourSchedule | null): boolean =>
  schedule?.booking_deadline ? new Date(schedule.booking_deadline) < new Date() : false;

/**
 * Lý do chuyến này không đặt được, hoặc null nếu đặt được.
 *
 * Đây là nguồn duy nhất cho câu hỏi "chuyến này còn bán không". Trước đây logic này bị chép
 * ở ba chỗ (thanh bên trang chi tiết, trang đặt tour, bộ lọc tự chọn chuyến), nên sửa một chỗ
 * thì hai chỗ kia vẫn sai.
 *
 * Điểm quan trọng: phải nói rõ LÝ DO, không gộp mọi trạng thái khác open thành một câu chung.
 * Trong luồng bình thường, tác vụ nền đóng bán chuyến khi tới hạn chốt nên trạng thái thành
 * 'closed'. Nếu chỉ báo "hiện không khả dụng" thì khách không bao giờ biết là hết chỗ hay là
 * đã quá hạn đăng ký, trong khi hai chuyện đó dẫn tới hai hành động khác nhau.
 */
export const getScheduleUnavailableReason = (
  schedule: TourSchedule | null | undefined,
  tourStatus?: Tour["status"],
): string | null => {
  if (!schedule) return "Tạm hết lịch";
  if (tourStatus === "inactive") return "Tour đang tạm ngừng";

  switch (schedule.status) {
    case "closed":
      // Chuyến đóng bán vì một trong hai lý do. Phân biệt để khách biết còn cơ hội hay không:
      // hết chỗ thì chờ người hủy, quá hạn thì chuyến này coi như chốt sổ.
      return getAvailableSlots(schedule) <= 0 ? "Đã hết chỗ" : "Đã quá hạn đăng ký";
    case "confirmed":
      return "Đã chốt danh sách, ngừng nhận khách";
    case "in_progress":
      return "Đoàn đã khởi hành";
    case "completed":
      return "Chuyến đã kết thúc";
    case "cancelled":
      return "Chuyến đã bị hủy";
    case "open":
      break;
    default: {
      // Thêm trạng thái mới vào ScheduleStatus mà quên xử lý ở đây thì dòng này báo lỗi biên dịch.
      const unhandled: never = schedule.status;
      return unhandled;
    }
  }

  // Chuyến vẫn đang mở bán nhưng tác vụ nền chưa kịp đóng.
  if (isDeadlineOverdue(schedule)) return "Đã quá hạn đăng ký";
  if (getAvailableSlots(schedule) <= 0) return "Đã hết chỗ";

  return null;
};

export const isScheduleBookable = (
  schedule: TourSchedule | null | undefined,
  tourStatus?: Tour["status"],
): boolean => getScheduleUnavailableReason(schedule, tourStatus) === null;
