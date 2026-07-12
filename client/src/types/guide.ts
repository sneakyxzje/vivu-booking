import type { Tour } from "./tour";

export interface GuideDashboardStats {
  totalTours: number;
  activeTours: number;
  fullTours: number;
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

export interface Guide {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  address: string | null;
  avatar: string | null;
  status: "active" | "inactive" | "blocked";
  assigned_tours_count: number;
  created_at: string;
}

export type { Tour };





