import type { Tour } from "./tour";

export interface GuideDashboardStats {
  totalTours: number;
  activeTours: number;
  pendingTours: number;
  totalBookings: number;
  pendingBookings: number;
  revenue: number;
}

export type BookingStatus = "pending" | "confirmed" | "cancelled";

export interface GuideBooking {
  id: number;
  tour_id: number;
  tour_title: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  departure_date: string;
  guests: number;
  total_amount: number;
  status: BookingStatus;
  created_at: string;
}

export type { Tour };




