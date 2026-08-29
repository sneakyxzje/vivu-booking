import api from "./api";
import { extractObject } from "@/utils/apiHelpers";

/**
 * Hộp thông báo, dùng chung cho điều hành và hướng dẫn viên.
 *
 * Tách khỏi `adminService` vì hai vai cùng gọi bốn điểm cuối này. Để nguyên bên đó thì màn hình
 * của hướng dẫn viên phải `import adminService` — đọc code một lúc sẽ tưởng họ gọi được API quản
 * trị, và người sửa sau sẽ tưởng bốn hàm ấy chỉ điều hành mới chạm tới.
 *
 * Máy chủ tự lọc theo người đăng nhập: mỗi người chỉ thấy thông báo gửi cho chính mình. Phía giao
 * diện không cần truyền id, và cũng không có cách nào xin hộp của người khác.
 */

/**
 * Một việc cần biết.
 *
 * `kind` chỉ quyết định màu — nội dung thật nằm ở `title` và `body`, và cả hai đã được máy chủ
 * dựng sẵn thành câu đọc được. Giao diện không ghép chuỗi từ mã loại.
 */
export interface AppNotification {
  id: string;
  kind:
    /* Gửi cho điều hành. */
    | "guide_declined"
    | "handover_requested"
    | "incident_reported"
    /* Gửi cho hướng dẫn viên. */
    | "assigned"
    | "handover_received"
    | "handover_closed"
    | "incident_resolved"
    | string;
  title: string;
  body: string;
  /** Màn hình xử lý việc này. Null nghĩa là chỉ để đọc. */
  url: string | null;
  read_at: string | null;
  created_at: string | null;
}

export interface NotificationList {
  notifications: AppNotification[];
  unread_count: number;
}

export const notificationService = {
  getNotifications: async (): Promise<NotificationList | null> => {
    const response = await api.get("/notifications");
    return extractObject<NotificationList>(response);
  },

  /*
   * Chỉ lấy con số, không kéo danh sách.
   *
   * Đây là thứ màn hình hỏi mỗi 30 giây khi không có WebSocket. Kéo cả danh sách về chỉ để đếm
   * là lãng phí đúng vào lúc dễ thấy nhất.
   */
  getUnreadCount: async (): Promise<number> => {
    const response = await api.get("/notifications/unread-count");
    return Number(response.data?.data?.unread_count ?? 0);
  },

  markRead: async (id: string) => {
    await api.put(`/notifications/${id}/read`);
  },

  markAllRead: async () => {
    await api.put("/notifications/read-all");
  },
};

export default notificationService;
