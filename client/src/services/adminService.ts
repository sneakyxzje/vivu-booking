import api from "./api";
import { extractObject } from "@/utils/apiHelpers";
import type { Booking, Guide, Tour, DiscountCode, DiscountCodePayload, Service, ServicePayload } from "@/types";
import { buildTourPayload } from "@/services/guideService";

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  total: number;
  per_page: number;
  last_page: number;
}

const adminService = {
  // --- TOURS ---
  getTours: async (): Promise<Tour[]> => {
    const response = await api.get("/admin/tours");
    return response.data?.data ?? [];
  },
  getTourById: async (id: number): Promise<Tour | null> => {
    const response = await api.get(`/admin/tours/${id}`);
    return response.data?.data ?? null;
  },

  updateTour: async (id: number, payload: unknown): Promise<{ success: boolean }> => {
    const data = buildTourPayload(payload);
    data.append("_method", "PUT");

    const response = await api.post(`/admin/tours/${id}`, data, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return { success: response.data?.success !== false };
  },

  getAvailableGuides: async (startDate: string, numberOfDays: number): Promise<Guide[]> => {
    const response = await api.get("/admin/available-guides", {
      params: { start_date: startDate, number_of_days: numberOfDays },
    });
    return response.data?.data ?? [];
  },

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

  // --- DISCOUNT CODES ---
  getDiscountCodes: async (page = 1): Promise<PaginatedResponse<DiscountCode> | null> => {
    const response = await api.get(`/admin/discount-codes?page=${page}`);
    return extractObject<PaginatedResponse<DiscountCode>>(response);
  },

  createDiscountCode: async (payload: DiscountCodePayload): Promise<DiscountCode | null> => {
    const response = await api.post("/admin/discount-codes", payload);
    return extractObject<DiscountCode>(response);
  },

  updateDiscountCode: async (id: number, payload: DiscountCodePayload): Promise<DiscountCode | null> => {
    const response = await api.put(`/admin/discount-codes/${id}`, payload);
    return extractObject<DiscountCode>(response);
  },

  deleteDiscountCode: async (id: number): Promise<boolean> => {
    const response = await api.delete(`/admin/discount-codes/${id}`);
    return response.data?.success !== false;
  },

  // --- ASSIGN GUIDE TO SCHEDULE ---
  assignGuideToSchedule: async (scheduleId: number, guideId: number | null) => {
    const response = await api.put(`/admin/tour-schedules/${scheduleId}/assign-guide`, {
      guide_id: guideId,
    });
    return response.data?.success !== false;
  },

  // --- EXTRA SERVICES (Dịch vụ phát sinh theo tour) ---
  getServices: async (page = 1): Promise<PaginatedResponse<Service> | null> => {
    const response = await api.get(`/admin/services?page=${page}`);
    return extractObject<PaginatedResponse<Service>>(response);
  },

  createService: async (payload: ServicePayload): Promise<Service | null> => {
    const response = await api.post("/admin/services", payload);
    return extractObject<Service>(response);
  },

  updateService: async (id: number, payload: Partial<ServicePayload>): Promise<Service | null> => {
    const response = await api.put(`/admin/services/${id}`, payload);
    return extractObject<Service>(response);
  },

  deleteService: async (id: number): Promise<boolean> => {
    const response = await api.delete(`/admin/services/${id}`);
    return response.data?.success !== false;
  },
};

export default adminService;



