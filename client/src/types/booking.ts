import type { Tour } from "./tour";

export type BookingStatus = "pending" | "confirmed" | "cancelled";

export interface Booking {
  id: number;
  tour_id: number;
  customer_id: number | null;
  guest_id: string | null;
  tour_schedule_id: number | null;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  departure_date: string;
  guests: number;
  total_amount: number;
  status: BookingStatus;
  note: string | null;
  vnpay_transaction_no: string | null;
  paid_at: string | null;
  confirmed_at: string | null;
  created_at: string;
  updated_at: string;
  tour?: Tour;
}
