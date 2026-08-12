import api from "./api";
import { extractObject } from "@/utils/apiHelpers";
import type { Booking, Guide, Tour, TourSchedule, DiscountCode, DiscountCodePayload, Service, ServicePayload, Category, CategoryPayload } from "@/types";
import { buildTourPayload } from "@/services/guideService";

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  total: number;
  per_page: number;
  last_page: number;
}

export interface AdminDashboardData {
  summary: Record<string, number>;
  booking_summary: {
    total_bookings: number;
    pending_bookings: number;
    confirmed_bookings: number;
    cancelled_bookings: number;
    total_revenue: number;
    revenue_this_month: number;
    new_customers_this_month: number;
    occupancy_rate: number;
  };
  monthly_performance: { name: string; revenue: number; bookings: number }[];
  destinations: { name: string; value: number }[];
  recent_bookings: {
    id: number;
    customer: string;
    tour: string;
    price: number;
    status: string;
    date: string | null;
  }[];
}

export interface CancellationPolicyRule {
  id?: number;
  min_hours_before: number;
  max_hours_before: number | null;
  refund_percent: number;
  note?: string | null;
}

export interface CancellationPolicy {
  id: number;
  name: string;
  description: string | null;
  is_default: boolean;
  tours_count?: number;
  rules: CancellationPolicyRule[];
}

export interface CancellationPolicyPayload {
  name: string;
  description?: string | null;
  is_default?: boolean;
  rules: CancellationPolicyRule[];
}

export interface HeldSeatsResponse {
  bookings: PaginatedResponse<Booking>;
  total_held_seats: number;
}

const adminService = {
  // --- DASHBOARD ---
  getDashboard: async (): Promise<AdminDashboardData | null> => {
    const response = await api.get("/admin/dashboard");
    return extractObject<AdminDashboardData>(response);
  },

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

  updateScheduleStatus: async (
    scheduleId: number,
    status: "open" | "closed" | "confirmed" | "cancelled",
    reason?: string,
  ): Promise<TourSchedule | null> => {
    const response = await api.patch(`/admin/schedules/${scheduleId}/status`, {
      status,
      ...(status === "cancelled" ? { reason } : {}),
    });
    return extractObject<TourSchedule>(response);
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

  confirmBooking: async (id: number): Promise<Booking | null> => {
    const response = await api.put(`/admin/bookings/${id}/confirm`);
    return extractObject<Booking>(response);
  },

  cancelBooking: async (id: number, reason: string): Promise<Booking | null> => {
    const response = await api.put(`/admin/bookings/${id}/cancel`, {
      cancel_reason: reason,
    });
    return extractObject<Booking>(response);
  },

  // Task X07b - Mở lại đơn đã hủy nhầm trong 24 giờ (Edge Case C06)
  reopenBooking: async (id: number, reopen_reason: string): Promise<Booking | null> => {
    const response = await api.put(`/admin/bookings/${id}/reopen`, {
      reopen_reason,
    });
    return extractObject<Booking>(response);
  },

  // Ghế chết: đơn đã hủy sau hạn chốt nên chỗ chưa được trả về kho để bán lại.
  getHeldSeats: async (page = 1): Promise<HeldSeatsResponse | null> => {
    const response = await api.get(`/admin/bookings/held-seats?page=${page}`);
    return extractObject<HeldSeatsResponse>(response);
  },

  releaseHeldSeats: async (id: number): Promise<Booking | null> => {
    const response = await api.put(`/admin/bookings/${id}/release-seats`);
    return extractObject<Booking>(response);
  },

  // --- CHÍNH SÁCH HỦY ---
  getCancellationPolicies: async (): Promise<CancellationPolicy[]> => {
    const response = await api.get("/admin/cancellation-policies");
    return response.data?.data ?? [];
  },

  createCancellationPolicy: async (
    payload: CancellationPolicyPayload,
  ): Promise<CancellationPolicy | null> => {
    const response = await api.post("/admin/cancellation-policies", payload);
    return extractObject<CancellationPolicy>(response);
  },

  updateCancellationPolicy: async (
    id: number,
    payload: CancellationPolicyPayload,
  ): Promise<CancellationPolicy | null> => {
    const response = await api.put(`/admin/cancellation-policies/${id}`, payload);
    return extractObject<CancellationPolicy>(response);
  },

  deleteCancellationPolicy: async (id: number): Promise<boolean> => {
    const response = await api.delete(`/admin/cancellation-policies/${id}`);
    return response.data?.success !== false;
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

  // --- ATTENDANCE (xem lại điểm danh của guide) ---
  getScheduleAttendance: async (scheduleId: number) => {
    const response = await api.get(`/admin/tour-schedules/${scheduleId}/attendance`);
    return response.data?.data ?? null;
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

  // --- CATEGORIES (Danh mục tour) ---
  getCategories: async (page = 1): Promise<PaginatedResponse<Category> | null> => {
    const response = await api.get(`/admin/categories?page=${page}`);
    return extractObject<PaginatedResponse<Category>>(response);
  },

  createCategory: async (payload: CategoryPayload): Promise<Category | null> => {
    const response = await api.post("/admin/categories", payload);
    return extractObject<Category>(response);
  },

  updateCategory: async (id: number, payload: Partial<CategoryPayload>): Promise<Category | null> => {
    const response = await api.put(`/admin/categories/${id}`, payload);
    return extractObject<Category>(response);
  },

  deleteCategory: async (id: number): Promise<boolean> => {
    const response = await api.delete(`/admin/categories/${id}`);
    return response.data?.success !== false;
  },

  // --- BÁO CÁO ĐIỂM DANH ---
  getAttendanceReport: async (params?: {
    from_date?: string;
    to_date?: string;
    search?: string;
    status?: string;
    page?: number;
    per_page?: number;
  }): Promise<{
    kpis: {
      overall_presence_rate: number;
      total_checkins: number;
      total_present: number;
      total_absent: number;
      missing_photos_count: number;
    };
    schedules: {
      data: {
        id: number;
        start_date: string;
        status: string;
        booked_people: number;
        tour_id: number | null;
        tour_title: string;
        number_of_days: number;
        guide: { id: number; name: string; phone?: string | null } | null;
        present_count: number;
        absent_count: number;
        total_checkins: number;
        presence_rate: number;
        photo_count: number;
      }[];
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
    absence_logs: {
      id: number;
      booking_id: number;
      customer_name: string;
      customer_phone: string;
      day_number: number;
      itinerary_title: string;
      checked_at: string | null;
      guide_name: string;
    }[];
  } | null> => {
    const response = await api.get("/admin/attendance-reports", { params });
    return extractObject(response);
  },
};

export default adminService;



