import api from "./api";
import { extractArray, extractObject } from "@/utils/apiHelpers";
import type {
  AttendanceCheckinInput,
  AttendanceData,
  GuideBooking,
  GuideDashboardStats,
  SaveAttendanceResult,
  Tour,
  UploadCheckinPhotoResult,
} from "@/types/guide";

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
  data.append("adult_price", String(Number(f.adult_price)));
  data.append("child_price", String(Number(f.child_price)));
  data.append("infant_price", String(Number(f.infant_price)));
  data.append("number_of_days", String(Number(f.number_of_days)));
  data.append("number_of_nights", String(Number(f.number_of_nights)));
  data.append("start_location", String(f.start_location ?? ""));
  data.append("end_location", String(f.end_location ?? ""));
  data.append("vehicle_info", String(f.vehicle_info ?? ""));
  data.append("pickup_location", String(f.pickup_location ?? ""));

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
      | { day_number: string; title: string; start_point?: string; end_point?: string; route_points?: string[]; rest_stops?: string; content: string }[]
      | undefined) ?? []
  ).forEach((item, index) => {
    if ("id" in item && item.id) {
      data.append(`itineraries[${index}][id]`, String(item.id));
    }
    data.append(`itineraries[${index}][day_number]`, String(item.day_number));
    data.append(`itineraries[${index}][title]`, item.title);
    data.append(`itineraries[${index}][start_point]`, item.start_point ?? "");
    data.append(`itineraries[${index}][end_point]`, item.end_point ?? "");
    data.append(
      `itineraries[${index}][route_points]`,
      (item.route_points ?? [])
        .map((point) => point.trim())
        .filter(Boolean)
        .join(", "),
    );
    data.append(`itineraries[${index}][rest_stops]`, item.rest_stops ?? "");
    data.append(`itineraries[${index}][content]`, item.content);
  });

  (
    (f.schedules as
      | {
          id?: number;
          start_date: string;
          max_people: string;
          min_people?: string;
          booking_deadline?: string;
          status?: string;
          guide_id?: string;
        }[]
      | undefined) ??
    []
  ).forEach((item, index) => {
    if (item.id) {
      data.append(`schedules[${index}][id]`, String(item.id));
    }
    data.append(`schedules[${index}][start_date]`, item.start_date);
    data.append(`schedules[${index}][max_people]`, String(item.max_people));
    data.append(`schedules[${index}][min_people]`, String(item.min_people ?? 1));
    if (item.booking_deadline) {
      data.append(`schedules[${index}][booking_deadline]`, item.booking_deadline);
    }
    if (item.status) {
      data.append(`schedules[${index}][status]`, item.status);
    }
    if (item.guide_id) {
      data.append(`schedules[${index}][guide_id]`, item.guide_id);
    }
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

  getAttendance: async (scheduleId: number): Promise<AttendanceData | null> => {
    const response = await api.get(`/guide/schedules/${scheduleId}/attendance`);
    return extractObject<AttendanceData>(response);
  },

  /**
   * Lưu điểm danh của một loạt hành khách tại một điểm dừng.
   *
   * Máy chủ áp chín quy tắc ở AttendanceService và trả 422 kèm thông báo tiếng Việt khi vi phạm,
   * nên lỗi phải để nguyên cho màn hình đọc `message`, không nuốt đi.
   */
  saveAttendance: async (
    scheduleId: number,
    checkpointId: number,
    checkins: AttendanceCheckinInput[],
  ): Promise<SaveAttendanceResult | null> => {
    const response = await api.put(
      `/guide/schedules/${scheduleId}/checkpoints/${checkpointId}/attendance`,
      { checkins },
    );
    return extractObject<SaveAttendanceResult>(response);
  },

  /**
   * Ảnh check-in phải kèm tọa độ nơi chụp. Máy chủ so với tọa độ điểm dừng và cảnh báo khi
   * cách quá 200m; thiếu tọa độ thì bị từ chối chứ không lưu suông.
   */
  uploadCheckinPhoto: async (
    scheduleId: number,
    checkpointId: number,
    photo: File,
    coords: { latitude: number; longitude: number },
  ): Promise<UploadCheckinPhotoResult | null> => {
    const data = new FormData();
    data.append("photo", photo);
    data.append("latitude", String(coords.latitude));
    data.append("longitude", String(coords.longitude));

    const response = await api.post(
      `/guide/schedules/${scheduleId}/checkpoints/${checkpointId}/checkin-photo`,
      data,
      { headers: { "Content-Type": "multipart/form-data" } },
    );
    return extractObject<UploadCheckinPhotoResult>(response);
  },
};

export default guideService;

