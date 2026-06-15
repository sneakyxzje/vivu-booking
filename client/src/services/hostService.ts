import api from "./api";
import { extractArray, extractObject } from "@/utils/apiHelpers";
import type { HostBooking, HostDashboardStats, Tour } from "@/types/host";

const defaultStats: HostDashboardStats = {
  totalTours: 0,
  activeTours: 0,
  pendingTours: 0,
  totalBookings: 0,
  pendingBookings: 0,
  revenue: 0,
};

const mapDashboardStats = (
  raw: Record<string, unknown> | null,
): HostDashboardStats => {
  if (!raw) return { ...defaultStats };

  return {
    totalTours: Number(raw.totalTours ?? raw.total_tours ?? 0),
    activeTours: Number(raw.activeTours ?? raw.active_tours ?? 0),
    pendingTours: Number(raw.pendingTours ?? raw.pending_tours ?? 0),
    totalBookings: Number(raw.totalBookings ?? raw.total_bookings ?? 0),
    pendingBookings: Number(
      raw.pendingBookings ?? raw.pending_bookings ?? 0,
    ),
    revenue: Number(raw.revenue ?? 0),
  };
};

const mapBooking = (raw: Record<string, unknown>): HostBooking => ({
  id: Number(raw.id),
  tour_id: Number(raw.tour_id),
  tour_title: String(raw.tour_title ?? raw.tourTitle ?? ""),
  customer_name: String(raw.customer_name ?? raw.customerName ?? ""),
  customer_email: String(raw.customer_email ?? raw.customerEmail ?? ""),
  customer_phone: String(raw.customer_phone ?? raw.customerPhone ?? ""),
  departure_date: String(raw.departure_date ?? raw.departureDate ?? ""),
  guests: Number(raw.guests ?? 0),
  total_amount: Number(raw.total_amount ?? raw.totalAmount ?? 0),
  status: (raw.status as HostBooking["status"]) ?? "pending",
  created_at: String(raw.created_at ?? raw.createdAt ?? ""),
});

const buildTourPayload = (form: unknown) => {
  const f = form as Record<string, unknown>;
  return {
    title: f.title,
    description: f.description || null,
    price: Number(f.price),
    discount_price: f.discount_price ? Number(f.discount_price) : null,
    thumbnail: f.thumbnail || null,
    number_of_days: Number(f.number_of_days),
    number_of_nights: Number(f.number_of_nights),
    start_location: f.start_location,
    end_location: f.end_location || null,
    category_ids: f.category_ids ?? [],
    service_ids: f.service_ids ?? [],
  };
};

const hostService = {
  getDashboardStats: async (): Promise<HostDashboardStats> => {
    const response = await api.get("/host/dashboard");
    const raw = extractObject<Record<string, unknown>>(response);
    return mapDashboardStats(raw);
  },

  getMyTours: async (): Promise<Tour[]> => {
    const response = await api.get("/host/my-tours");
    return extractArray<Tour>(response);
  },

  getTourById: async (id: number): Promise<Tour | undefined> => {
    const response = await api.get(`/host/my-tours/${id}`);
    const tour = extractObject<Tour>(response);
    return tour ?? undefined;
  },

  getBookings: async (): Promise<HostBooking[]> => {
    const response = await api.get("/host/bookings");
    return extractArray<Record<string, unknown>>(response).map(mapBooking);
  },

  getFormData: async () => {
    const [categoriesRes, servicesRes] = await Promise.allSettled([
      api.get("/categories"),
      api.get("/services"),
    ]);

    const categories =
      categoriesRes.status === "fulfilled"
        ? extractArray<{ id: number; name: string }>(categoriesRes.value)
        : [];

    const services =
      servicesRes.status === "fulfilled"
        ? extractArray<{ id: number; name: string }>(servicesRes.value)
        : [];

    return { categories, services };
  },

  createTour: async (payload: unknown): Promise<{ success: boolean }> => {
    const response = await api.post("/host/my-tours", buildTourPayload(payload));
    return { success: response.data?.success !== false };
  },

  updateTour: async (
    id: number,
    payload: unknown,
  ): Promise<{ success: boolean }> => {
    const response = await api.put(
      `/host/my-tours/${id}`,
      buildTourPayload(payload),
    );
    return { success: response.data?.success !== false };
  },

  confirmBooking: async (id: number): Promise<{ success: boolean }> => {
    const response = await api.put(`/host/bookings/${id}/confirm`);
    return { success: response.data?.success !== false };
  },
};

export default hostService;
