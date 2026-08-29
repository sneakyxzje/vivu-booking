import api from "./api";
import { extractObject } from "@/utils/apiHelpers";

/**
 * Chính sách công ty, đọc được mà không cần đăng nhập.
 *
 * Bắt đăng nhập mới xem được điều khoản hoàn tiền là giấu điều khoản cho tới khi người ta đã cam
 * kết. Khách phải đọc được trước khi đặt, tức trước khi có tài khoản.
 */

/** Một bậc trong bảng phí hủy. `window` là nhãn máy chủ đã dựng sẵn, giao diện không ghép chuỗi. */
export interface PolicyTier {
  window: string;
  refund_percent: number;
  note: string | null;
}

export interface PolicyResponse {
  cancellation: {
    name: string;
    description: string | null;
    /** "YYYY-MM-DD HH:mm:ss" giờ Việt Nam. Null khi đang dùng bảng phí viết trong mã. */
    effective_from: string | null;
    rules: PolicyTier[];
  };
  transfer: {
    notice_days: number;
    free_transfers: number;
    fee: number;
  };
  booking: {
    payment_ttl_minutes: number;
    deadline_days: number;
  };
}

export const policyService = {
  get: async (): Promise<PolicyResponse | null> => {
    const response = await api.get("/policies");
    return extractObject<PolicyResponse>(response);
  },
};

export default policyService;
