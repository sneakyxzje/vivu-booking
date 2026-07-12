import api from "./api";
import { extractArray, extractObject } from "@/utils/apiHelpers";
import type { GuideBooking, GuideDashboardStats, Tour } from "@/types/guide";

const defaultStats: GuideDashboardStats = {
  totalTours: 0,
  activeTours: 0,
  fullTours: 0,
  totalBookings: 0,
  pendingBookings: 0,
  revenue: 0,
};

const mapDashboardStats = (
  raw: Record<string, unknown> | null,
): GuideDashboardStats => {
  if (!raw) return { ...defaultStats };

  return {
    totalTours: Number(raw.totalTours ?? raw.total_tours ?? 0),
    activeTours: Number(raw.activeTours ?? raw.active_tours ?? 0),
    fullTours: Number(raw.fullTours ?? raw.full_tours ?? 0),
    totalBookings: Number(raw.totalBookings ?? raw.total_bookings ?? 0),
    pendingBookings: Number(raw.pendingBookings ?? raw.pending_bookings ?? 0),
    revenue: Number(raw.revenue ?? 0),
  };
};

const mapBooking = (raw: Record<string, unknown>): GuideBooking => ({
  id: Number(raw.id),
  tour_id: Number(raw.tour_id),
  tour_title: String(raw.tour_title ?? raw.tourTitle ?? ""),
  customer_name: String(raw.customer_name ?? raw.customerName ?? ""),
  customer_email: String(raw.customer_email ?? raw.customerEmail ?? ""),
  customer_phone: String(raw.customer_phone ?? raw.customerPhone ?? ""),
  departure_date: String(raw.departure_date ?? raw.departureDate ?? ""),
  guests: Number(raw.guests ?? 0),
  total_amount: Number(raw.total_amount ?? raw.totalAmount ?? 0),
  status: (raw.status as GuideBooking["status"]) ?? "pending",
  created_at: String(raw.created_at ?? raw.createdAt ?? ""),
});

export const buildTourPayload = (form: unknown) => {
  const f = form as Record<string, unknown>;
  const data = new FormData();

  data.append("title", String(f.title ?? ""));
  data.append("description", String(f.description ?? ""));
  data.append("price", String(Number(f.price)));
  if (f.discount_price) {
    data.append("discount_price", String(Number(f.discount_price)));
  }
  data.append("number_of_days", String(Number(f.number_of_days)));
  data.append("number_of_nights", String(Number(f.number_of_nights)));
  data.append("start_location", String(f.start_location ?? ""));
  data.append("end_location", String(f.end_location ?? ""));

  if (f.thumbnail) {
    data.append("thumbnail", String(f.thumbnail));
  }

  if (f.thumbnail_file instanceof File) {
    data.append("thumbnail_file", f.thumbnail_file);
  }

  ((f.images as File[] | undefined) ?? []).forEach((file) => {
    data.append("images[]", file);
  });

  ((f.category_ids as number[] | undefined) ?? []).forEach((id) => {
    data.append("category_ids[]", String(id));
  });

  ((f.service_ids as number[] | undefined) ?? []).forEach((id) => {
    data.append("service_ids[]", String(id));
  });

  (
    (f.itineraries as
      | { day_number: string; title: string; content: string }[]
      | undefined) ?? []
  ).forEach((item, index) => {
    data.append(`itineraries[${index}][day_number]`, String(item.day_number));
    data.append(`itineraries[${index}][title]`, item.title);
    data.append(`itineraries[${index}][content]`, item.content);
  });

  (
    (f.schedules as { start_date: string; max_people: string }[] | undefined) ??
    []
  ).forEach((item, index) => {
    data.append(`schedules[${index}][start_date]`, item.start_date);
    data.append(`schedules[${index}][max_people]`, String(item.max_people));
  });

  return data;
};

const guideService = {
  getDashboardStats: async (): Promise<GuideDashboardStats> => {
    const response = await api.get("/guide/dashboard");
    const raw = extractObject<Record<string, unknown>>(response);
    return mapDashboardStats(raw);
  },

  getMyTours: async (): Promise<Tour[]> => {
    const response = await api.get("/guide/my-tours");
    return extractArray<Tour>(response);
  },

  getTourById: async (id: number): Promise<Tour | undefined> => {
    const response = await api.get(`/guide/my-tours/${id}`);
    const tour = extractObject<Tour>(response);
    return tour ?? undefined;
  },

  getBookings: async (): Promise<GuideBooking[]> => {
    const response = await api.get("/guide/bookings");
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

  updateTour: async (
    id: number,
    payload: unknown,
  ): Promise<{ success: boolean }> => {
    const response = await api.put(
      `/guide/my-tours/${id}`,
      buildTourPayload(payload),
      { headers: { "Content-Type": "multipart/form-data" } },
    );
    return { success: response.data?.success !== false };
  },

  confirmBooking: async (id: number): Promise<{ success: boolean }> => {
    const response = await api.put(`/guide/bookings/${id}/confirm`);
    return { success: response.data?.success !== false };
  },
};

export default guideService;

