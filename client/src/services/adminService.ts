import api from "./api";
import { extractObject } from "@/utils/apiHelpers";
import type { Booking, Guide } from "@/types";

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  total: number;
  per_page: number;
  last_page: number;
}

const adminService = {
  // --- BOOKINGS ---
  getBookings: async (page = 1): Promise<PaginatedResponse<Booking> | null> => {
    const response = await api.get(`/admin/bookings?page=${page}`);
    return extractObject<PaginatedResponse<Booking>>(response);
  },

  getBookingById: async (id: number): Promise<Booking | null> => {
    const response = await api.get(`/admin/bookings/${id}`);
    return extractObject<Booking>(response);
  },

  // --- GUIDES ---
  getGuides: async (page = 1): Promise<PaginatedResponse<Guide> | null> => {
    const response = await api.get(`/admin/guides?page=${page}`);
    return extractObject<PaginatedResponse<Guide>>(response);
  },

  getGuideById: async (id: number): Promise<Guide | null> => {
    const response = await api.get(`/admin/guides/${id}`);
    return extractObject<Guide>(response);
  },

  createGuide: async (payload: Omit<Guide, "id" | "created_at" | "assigned_tours_count"> & { password?: string }) => {
    const response = await api.post("/admin/guides", payload);
    return extractObject<Guide>(response);
  },

  updateGuide: async (id: number, payload: Partial<Guide>) => {
    const response = await api.put(`/admin/guides/${id}`, payload);
    return extractObject<Guide>(response);
  },

  deleteGuide: async (id: number): Promise<boolean> => {
    const response = await api.delete(`/admin/guides/${id}`);
    return response.data?.success !== false;
  },

  // --- ASSIGN GUIDE TO TOUR ---
  assignGuideToTour: async (tourId: number, guideId: number | null) => {
    const response = await api.put(`/admin/tours/${tourId}/assign-guide`, {
      guide_id: guideId,
    });
    return response.data?.success !== false;
  },
};

export default adminService;
