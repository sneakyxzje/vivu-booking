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

export interface AttendanceItinerary {
  id: number;
  day_number: number;
  title: string;
  start_point?: string | null;
  end_point?: string | null;
  route_points?: string | null;
}

export interface AttendanceGuest {
  id: number;
  customer_name: string;
  customer_phone: string | null;
  guests: number;
  adult_count?: number;
  child_count?: number;
  infant_count?: number;
  passengers?: {
    id: number;
    name: string;
    type: "adult" | "child" | "infant";
    note?: string | null;
  }[];
}

export interface AttendanceCheckin {
  booking_id: number;
  tour_itinerary_id: number;
  present: boolean;
  checked_at?: string | null;
}

export interface CheckpointPhoto {
  id: number;
  tour_itinerary_id: number;
  image_path: string;
  created_at?: string;
}

export interface AttendanceData {
  schedule: {
    id: number;
    start_date: string;
    max_people: number;
    booked_people: number;
  };
  tour: {
    id: number;
    title: string;
    number_of_days: number;
  };
  itineraries: AttendanceItinerary[];
  guests: AttendanceGuest[];
  checkins: AttendanceCheckin[];
  photos: CheckpointPhoto[];
}

export type { Tour };





