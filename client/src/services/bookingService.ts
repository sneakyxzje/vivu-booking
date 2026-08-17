import type { Booking } from "@/types";
import api from "./api";

/**
 * Một hành khách gửi lên máy chủ.
 *
 * Dùng chung cho cả lúc đặt tour lẫn lúc sửa danh sách về sau, vì máy chủ nhận đúng một bộ
 * trường và áp đúng một bộ luật cho cả hai đường. Khai hai kiểu riêng thì sớm muộn một bên mọc
 * thêm trường mà bên kia không có, và khách phải đặt xong mới điền nốt được.
 */
export interface PassengerPayload {
  name: string;
  type: "adult" | "child" | "infant";
  gender?: string | null;
  date_of_birth?: string | null;
  identity_number?: string | null;
  id_type?: string | null;
  phone?: string | null;
  /** Ăn chay, dị ứng, cần hỗ trợ di chuyển. Gửi cho nhà hàng và khách sạn trước ngày đi. */
  special_request?: string | null;
  /** Người hướng dẫn viên gọi khi cần liên hệ nhóm khách này. */
  is_contact?: boolean;
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

export type ChangeRequestStatus =
  | "pending"
  | "approved"
  | "rejected"
  | "cancelled_by_customer";

/** Yêu cầu thay đổi khách gửi lên, hiện mới dùng cho loại xin hủy. */
export interface BookingChangeRequest {
  id: number;
  booking_id: number;
  type: "cancel" | "transfer" | "change_guests" | "change_passenger";
  status: ChangeRequestStatus;
  /** Mức hoàn chốt tại thời điểm gửi, không tính lại lúc duyệt. */
  estimated_refund: string | number | null;
  estimated_refund_percent: number | null;
  request_note: string | null;
  review_note: string | null;
  reviewed_at: string | null;
  created_at: string;
}

/** Mức hoàn dự kiến của đơn đã thanh toán, kèm yêu cầu đang chờ nếu có. */
export interface CancelRequestPreview extends RefundQuote {
  seats_will_be_released: boolean;
  pending_request: BookingChangeRequest | null;
}

/** Cùng một hành khách, dù đang đặt tour hay đang sửa danh sách. */
export type PassengerInput = PassengerPayload;

export interface PassengerListResponse {
  passengers: BookingPassengerRow[];
  guests: number;
  /** false khi đã qua hạn chốt danh sách hoặc đoàn đã lên đường. */
  can_edit: boolean;
  locked_reason: string | null;
  /** Khai thiếu người, thiếu giấy tờ, chưa chọn người liên hệ. */
  warnings: string[];
}

export interface BookingPassengerRow extends PassengerInput {
  id: number;
  booking_id: number;
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
   * Khách tự sửa thông tin liên hệ đã nhập nhầm.
   *
   * Không bị hạn chốt danh sách khóa, khác với sửa danh sách hành khách: đây là số công ty và
   * hướng dẫn viên gọi khách, càng sát ngày càng cần đúng.
   *
   * Số lượng khách thì không sửa được - đổi số người là đổi cả chỗ lẫn tiền, phải hủy và đặt lại.
   */
  updateMyBookingContact: (
    id: number,
    payload: { customer_name: string; customer_email: string; customer_phone?: string | null },
  ) =>
    api.put<{ success: boolean; message: string }>(`/my-bookings/${id}/contact`, payload),

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

  /*
   * Đơn ĐÃ thanh toán đi đường khác hẳn: khách không tự hủy mà gửi yêu cầu, điều hành duyệt
   * rồi hệ thống mới thực thi. Xem docs/nghiep-vu/03-luong-huy-va-hoan-tien.md mục 5.
   */

  /** Mức hoàn khách sẽ nhận nếu gửi yêu cầu ngay bây giờ. Bắt buộc cho khách xem trước khi gửi. */
  getCancelRequestPreview: (bookingId: number) =>
    api.get<{ success: boolean; data: CancelRequestPreview }>(
      `/my-bookings/${bookingId}/cancel-preview`,
    ),

  requestCancellation: (bookingId: number, reason: string) =>
    api.post<{ success: boolean; message: string; data: BookingChangeRequest }>(
      `/my-bookings/${bookingId}/cancel-request`,
      { reason },
    ),

  withdrawChangeRequest: (id: number) =>
    api.put<{ success: boolean; message: string }>(`/my-change-requests/${id}/withdraw`),

  /*
   * Danh sách hành khách.
   *
   * Quyền sửa phụ thuộc THỜI ĐIỂM chứ không phải vai trò: trước hạn chốt danh sách khách tự
   * sửa, sau đó danh sách đã gửi nhà cung cấp nên chỉ điều hành sửa được. Máy chủ trả sẵn
   * can_edit để màn hình khỏi tự suy ra và suy sai.
   */
  getPassengers: (bookingId: number) =>
    api.get<{ success: boolean; data: PassengerListResponse }>(
      `/my-bookings/${bookingId}/passengers`,
    ),

  updatePassengers: (bookingId: number, passengers: PassengerInput[]) =>
    api.put<{ success: boolean; message: string; data: { warnings: string[] } }>(
      `/my-bookings/${bookingId}/passengers`,
      { passengers },
    ),

  // Task X06b - Gửi lại mã tra cứu về email cho khách vãng lai (Edge Case A16)
  resendLookupCode: (payload: { email: string; phone?: string }) =>
    api.post<{ success: boolean; message: string }>("/bookings/resend-code", payload),
};

export default bookingService;
