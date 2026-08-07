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

const bookingService = {
  create: (payload: CreateBookingPayload) =>
    api.post<CreateBookingResponse>("/bookings", payload),

  getById: (publicToken: string) =>
    api.get<{ success: boolean; data: Booking }>(`/bookings/${publicToken}`),
  validateDiscountCode: (payload: { code: string; order_amount: number }) =>
    api.post<ValidateDiscountResponse>("/discount-codes/validate", payload),

  getMyBookings: () => api.get("/my-bookings"),
};

export default bookingService;
