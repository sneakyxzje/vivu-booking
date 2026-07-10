import type { Booking } from "@/types";
import api from "./api";

export interface CreateBookingPayload {
  tour_id: number;
  tour_schedule_id: number;
  customer_name: string;
  customer_email: string;
  customer_phone?: string;
  guests: number;
  note?: string;
}

export interface CreateBookingResponse {
  success: boolean;
  message: string;
  data: {
    payment_url?: string;
    booking: Booking;
  };
}

const bookingService = {
  create: (payload: CreateBookingPayload) =>
    api.post<CreateBookingResponse>("/bookings", payload),

  getMyBookings: () => api.get("/my-bookings"),
};

export default bookingService;
