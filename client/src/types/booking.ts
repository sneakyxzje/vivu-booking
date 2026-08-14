import type { Tour, TourSchedule } from "./tour";

export type BookingStatus = "pending" | "confirmed" | "cancelled";

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
  discount_code_id?: number | null;
  discount_code?: string | null;
  discount_amount?: number;
  status: BookingStatus;
  expires_at?: string | null;
  cancel_reason?: string | null;
  cancel_type?: "hold_expired" | "by_customer" | "by_company" | "force_majeure" | null;
  cancelled_at?: string | null;
  cancelled_by?: number | null;
  reopen_reason?: string | null;
  reopened_at?: string | null;
  reopened_by?: number | null;
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

