import type { Tour, TourSchedule } from "./tour";

/**
 * Năm trạng thái mà luồng hiện tại sinh ra được — khớp `BookingStatus::liveValues()` ở máy chủ.
 *
 * Thiếu `completed` và `no_show` thì mọi màn hình đọc đơn của chuyến đã đi xong đều rơi vào nhánh
 * mặc định mà TypeScript không kêu một tiếng nào.
 */
export type BookingStatus =
  | "pending"
  | "confirmed"
  | "cancelled"
  | "completed"
  | "no_show";

export interface Booking {
  id: number;
  public_token: string;
  tour_id: number;
  customer_id: number | null;
  guest_id: string | null;
  tour_schedule_id: number | null;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  departure_date: string;
  guests: number;
  adult_count?: number;
  child_count?: number;
  infant_count?: number;
  total_amount: number;
  /** Tổng đã thu thực, tính từ sổ giao dịch. Chỉ có ở các điểm cuối tính sẵn nó. */
  net_paid?: number;
  balance_due?: number;
  /**
   * Số của LẦN TRẢ SẮP TỚI — khác `balance_due` ở đúng lần đầu.
   *
   * Đơn vừa đặt còn thiếu cả giá tour nhưng chỉ phải cọc một phần. Đây là con số cổng thanh toán
   * sẽ đòi, và là con số duy nhất được in lên nút; lấy `balance_due` thì nút hứa một đằng còn
   * cổng đòi một nẻo.
   */
  payment_amount?: number;
  /** Hạn trả nốt. Quá mốc này mà chưa thanh toán thì đơn bị hủy và mất cọc. */
  balance_due_at?: string | null;
  balance_overdue?: boolean;
  /** Liên kết cổng thanh toán, dựng theo `payment_amount`. Vắng mặt khi đơn đã trả đủ. */
  payment_url?: string;
  discount_code_id?: number | null;
  discount_code?: string | null;
  discount_amount?: number;
  status: BookingStatus;
  expires_at?: string | null;
  cancel_reason?: string | null;
  cancel_type?: "hold_expired" | "by_customer" | "by_company" | "force_majeure" | null;
  cancelled_at?: string | null;
  cancelled_by?: number | null;
  // false nghĩa là ghế chết: đơn đã hủy sau hạn chốt nên chỗ chưa được trả về kho.
  seats_released?: boolean;
  seats_released_at?: string | null;
  refund_amount?: string | number | null;
  note: string | null;
  vnpay_transaction_no: string | null;
  paid_at: string | null;
  confirmed_at: string | null;
  created_at: string;
  updated_at: string;
  tour?: Tour;
  schedule?: TourSchedule;
  passengers?: BookingPassenger[];
  /** Khoản sinh ra từ sự cố dọc đường. Máy chủ chỉ trả về khoản đã có hiệu lực. */
  surcharges?: BookingSurcharge[];
  /** Nhật ký cổng thanh toán. Chỉ có ở điểm cuối chi tiết đơn phía điều hành. */
  payment_logs?: PaymentLogEntry[];
}

/**
 * Một lượt cổng thanh toán trả về, ghi lại nguyên trạng — kể cả lượt thất bại.
 *
 * `is_valid_signature` là trường quan trọng nhất: nó trả lời câu "dữ liệu này có đúng do VNPay ký
 * không", tức khoản thanh toán có thật hay là ai đó tự gọi vào địa chỉ quay về.
 */
export interface PaymentLogEntry {
  id: number;
  provider: string;
  transaction_no: string | null;
  bank_code: string | null;
  response_code: string | null;
  transaction_status: string | null;
  amount: string | number | null;
  is_valid_signature: boolean;
  created_at: string;
}

/**
 * Một khoản khách phải trả thêm, hoặc được hoàn, vì một sự cố dọc đường.
 *
 * Không liên quan tới giá tour: khách trả tiền tour là đã trả xong tour. Đây là thứ xảy ra sau
 * khi đoàn lên đường mà không ai lường trước — kẹt bão phải ở thêm đêm, hoặc ngược lại, buổi
 * tham quan đã bán mà không đi được nên phải hoàn.
 */
export interface BookingSurcharge {
  id: number;
  kind: "surcharge" | "refund";
  kind_label: string;
  who_bears: string | null;
  who_bears_label: string | null;
  amount: string | number;
  reason: string;
  status: "pending" | "approved" | "paid" | "waived";
  status_label: string;
  customer_consent_at: string | null;
  created_at: string;
}

export interface BookingPassenger {
  id: number;
  booking_id: number;
  name: string;
  type: "adult" | "child" | "infant";
  gender?: "male" | "female" | "other" | null;
  date_of_birth?: string | null;
  identity_number?: string | null;
  id_type?: "cccd" | "cmnd" | "passport" | "birth_certificate" | null;
  nationality?: string | null;
  phone?: string | null;
  /** Ăn chay, dị ứng, cần hỗ trợ di chuyển. Nhà cung cấp cần biết trước. */
  special_request?: string | null;
  /** Người hướng dẫn viên gọi khi cần liên hệ đoàn nhỏ này. */
  is_contact?: boolean;
  note?: string | null;
}

