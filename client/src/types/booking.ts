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
  date_of_birth?: string | null;
  identity_number?: string | null;
  note?: string | null;
}

