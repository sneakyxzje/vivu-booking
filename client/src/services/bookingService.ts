import type { Booking } from "@/types";
import api from "./api";

export interface PassengerPayload {
  name: string;
  type: "adult" | "child" | "infant";
  date_of_birth?: string | null;
  identity_number?: string | null;
  note?: string | null;
}

export interface CreateBookingPayload {
  tour_id: number;
  tour_schedule_id: number;
  customer_name: string;
  customer_email: string;
  customer_phone?: string;
  adult_count: number;
  child_count: number;
  infant_count: number;
  note?: string;
  discount_code?: string;
  passengers?: PassengerPayload[];
}

export interface CreateBookingResponse {
  success: boolean;
  message: string;
  data: {
    payment_url?: string;
    booking: Booking;
  };
}

export interface ValidateDiscountResponse {
  success: boolean;
  message: string;
  data: {
    code: string;
    name: string;
    discount_amount: number;
    final_amount: number;
  };
}

export interface RefundQuoteRule {
  window: string;
  refund_percent: number;
  note: string | null;
}

export interface RefundQuote {
  hours_before: number | null;
  refund_percent: number;
  total_amount: number;
  paid_amount: number;
  cancellation_fee: number;
  refund_amount: number;
  policy_name: string | null;
  rules: RefundQuoteRule[] | null;
}

const bookingService = {
  create: (payload: CreateBookingPayload) =>
    api.post<CreateBookingResponse>("/bookings", payload),

  getById: (publicToken: string) =>
    api.get<{ success: boolean; data: Booking }>(`/bookings/${publicToken}`),
  validateDiscountCode: (payload: { code: string; order_amount: number }) =>
    api.post<ValidateDiscountResponse>("/discount-codes/validate", payload),

  getMyBookings: () => api.get("/my-bookings"),

  /**
   * Khách tự hủy đơn của mình.
   *
   * Máy chủ chỉ nhận đơn chưa thanh toán, và từ chối kèm lý do khi chuyến đã khởi hành. Lỗi để
   * nguyên cho màn hình đọc `message`, vì câu giải thích của tầng dịch vụ cụ thể hơn bất cứ câu
   * chung chung nào viết ở đây.
   */
  cancelMyBooking: (id: number, cancelReason: string) =>
    api.put<{ success: boolean; message: string }>(`/my-bookings/${id}/cancel`, {
      cancel_reason: cancelReason,
    }),

  // Mức hoàn dự kiến nếu hủy ngay bây giờ, xem được bằng mã tra cứu nên khách vãng lai
  // cũng đọc được mà không cần đăng nhập.
  getRefundQuote: (publicToken: string) =>
    api.get<{ success: boolean; data: RefundQuote }>(`/bookings/${publicToken}/refund-quote`),

  // Task X06b - Gửi lại mã tra cứu về email cho khách vãng lai (Edge Case A16)
  resendLookupCode: (payload: { email: string; phone?: string }) =>
    api.post<{ success: boolean; message: string }>("/bookings/resend-code", payload),
};

export default bookingService;
