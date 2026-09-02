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

  /*
   * Gửi cả khi rỗng. Bỏ qua trường này lúc rỗng thì máy chủ không thấy khóa `thumbnail` trong
   * payload và giữ nguyên ảnh cũ, nên nút "Bỏ ảnh bìa" ở màn sửa tour không bao giờ có tác
   * dụng — ảnh biến mất khỏi biểu mẫu rồi hiện lại nguyên vẹn sau khi lưu.
   */
  data.append("thumbnail", String(f.thumbnail ?? ""));

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

  // Không gửi `cancellation_policy_id` nữa: cả hệ thống dùng chung một bảng phí hủy.

  (
    (f.itineraries as
      | {
          day_number: string;
          title: string;
          start_point?: string;
          end_point?: string;
          route_points?: string[];
          rest_stops?: string;
          content: string;
          checkpoints?: {
            id?: number;
            name: string;
            description?: string;
            is_required_photo?: boolean;
          }[];
        }[]
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

    /*
     * Điểm dừng. Trước đây payload bỏ qua hẳn phần này, nên mọi điểm dừng khai ở biểu mẫu tạo
     * tour đều biến mất lúc lưu — trong khi máy chủ vẫn nhận và vẫn có bảng để ghi.
     *
     * `sequence` suy ra từ thứ tự hiển thị, không hỏi người dùng: thứ tự đã thấy trên màn hình
     * rồi, hỏi lại một con số nữa chỉ tạo cơ hội cho hai chỗ nói khác nhau.
     */
    (item.checkpoints ?? [])
      .filter((cp) => cp.name?.trim())
      .forEach((cp, thuTu) => {
        const goc = `itineraries[${index}][checkpoints][${thuTu}]`;

        if (cp.id) data.append(`${goc}[id]`, String(cp.id));
        data.append(`${goc}[name]`, cp.name.trim());
        data.append(`${goc}[description]`, cp.description?.trim() ?? "");
        data.append(`${goc}[sequence]`, String(thuTu + 1));
        data.append(`${goc}[is_required_photo]`, cp.is_required_photo ? "1" : "0");
      });
  });

  (
    (f.schedules as
      | {
          id?: number;
          start_date: string;
          max_people: string;
          min_people?: string;
          booking_deadline?: string;
          booking_deadline_reason?: string;
          status?: string;
          guide_ids?: string[];
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
    // Chỉ có mặt khi người dùng thực sự dời hạn chốt của một chuyến đã tồn tại; máy chủ đòi nó
    // đúng lúc ấy và bỏ qua ở mọi lúc khác.
    if (item.booking_deadline_reason?.trim()) {
      data.append(
        `schedules[${index}][booking_deadline_reason]`,
        item.booking_deadline_reason.trim(),
      );
    }
    if (item.status) {
      data.append(`schedules[${index}][status]`, item.status);
    }
    // Một chuyến có thể có nhiều hướng dẫn viên, nên gửi cả mảng.
    (item.guide_ids ?? []).forEach((guideId) => {
      data.append(`schedules[${index}][guide_ids][]`, guideId);
    });
  });

  return data;
};

/** Một sự cố dọc đường, nhìn từ phía hướng dẫn viên. */
export interface GuideIncident {
  id: number;
  tour_schedule_id: number;
  tour_title: string | null;
  start_date: string | null;
  type: string;
  type_label: string;
  severity: string;
  severity_label: string;
  status: string;
  status_label: string;
  occurred_at: string | null;
  /** Báo muộn hơn 6 tiếng so với lúc xảy ra: mất sóng giữa biển là chuyện thường. */
  reported_late: boolean;
  description: string;
  /** Phương án do điều hành quyết. Hướng dẫn viên chỉ đọc, không sửa. */
  resolution: string | null;
  photos: { id: number; image_path: string; caption: string | null }[];
}

/** Một chuyến được phân công cho hướng dẫn viên này. */
export interface GuideAssignment {
  schedule_id: number;
  tour_title: string | null;
  start_date: string;
  end_date: string | null;
  number_of_days: number;
  status: string;
  status_label: string;
  /**
   * Đã xác nhận nhận chuyến chưa.
   *
   * Chưa xác nhận **vẫn là đã được phân công** — điều hành đang trông vào bạn. Xác nhận chỉ là
   * bằng chứng bạn đã biết; từ chối mới là thứ thay đổi danh sách.
   */
  accepted_at: string | null;
  /** Ai cùng dẫn chuyến này. */
  co_guides: string[];
  /** Chuyến đã lên đường thì rút lui là bàn giao, không phải từ chối. */
  can_decline: boolean;
}

/** Biên bản bàn giao, nhìn từ phía hướng dẫn viên. */
export interface GuideHandoverNote {
  id: number;
  tour_schedule_id: number;
  tour_title: string | null;
  start_date: string | null;
  /** received: mình nhận đoàn. given: mình đã giao đoàn đi. */
  direction: "received" | "given";
  from_guide_name: string | null;
  to_guide_name: string | null;
  to_guide_phone: string | null;
  handed_over_at: string | null;
  reason: string;
  handover_note: string;
}

/** Yêu cầu bàn giao do chính hướng dẫn viên gửi. Không có người thay: đó là việc của điều hành. */
export interface GuideHandoverRequestRow {
  id: number;
  tour_schedule_id: number;
  tour_title: string | null;
  start_date: string | null;
  status: "pending" | "approved" | "rejected" | "withdrawn";
  status_label: string;
  reason: string;
  group_state: string;
  /** Lý do điều hành từ chối, nếu có. */
  review_note: string | null;
  created_at: string | null;
}

export const INCIDENT_TYPES = [
  { value: "weather", label: "Thời tiết" },
  { value: "vehicle", label: "Phương tiện" },
  { value: "health", label: "Sức khỏe khách" },
  { value: "supplier", label: "Nhà cung cấp" },
  { value: "security", label: "An ninh, an toàn" },
  { value: "other", label: "Khác" },
] as const;

export const INCIDENT_SEVERITIES = [
  { value: "low", label: "Nhẹ" },
  { value: "medium", label: "Vừa" },
  { value: "high", label: "Nghiêm trọng" },
] as const;

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

  /**
   * Dữ liệu để dựng biểu mẫu tour: danh mục và dịch vụ.
   *
   * `allSettled` để một nguồn hỏng không kéo cả biểu mẫu chết theo — mất danh sách dịch vụ thì
   * vẫn tạo được tour, chỉ là không tick được dịch vụ đi kèm.
   */
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

  /**
   * Xác nhận một đơn tại điểm tập trung, kèm khoản tiền vừa thu.
   *
   * Hướng dẫn viên cầm tiền mặt của khách thì phải khai đã cầm bao nhiêu — máy chủ ghi thẳng vào sổ
   * giao dịch trong cùng thao tác. Để trống chỉ được khi sổ đã có khoản thu từ trước (khách chuyển
   * khoản cho văn phòng rồi mới ra bến).
   */
  confirmBooking: async (
    id: number,
    thuTien?: { amount: number; method: "cash" | "bank_transfer"; note?: string },
  ): Promise<{ success: boolean }> => {
    const response = await api.put(`/guide/bookings/${id}/confirm`, thuTien ?? {});
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

  // --- O: Sự cố dọc đường ---
  // Cố ý không có tham số tiền nào. Hướng dẫn viên báo cáo những gì nhìn thấy; điều hành quyết
  // ai trả bao nhiêu. Người đang đứng giữa đoàn khách mệt không nên là người quyết mức thu.

  /**
   * Biên bản bàn giao liên quan tới hướng dẫn viên này, cả hai chiều.
   *
   * Chiều nhận là thứ cần nhất: bắt nhịp với đoàn bằng đúng đoạn ghi chú tình trạng. Chiều giao
   * giữ lại để người cũ còn đối chiếu được mình đã giao gì, lúc nào — họ mất quyền ghi nhưng
   * không mất vết.
   */
  getMyHandovers: async (): Promise<GuideHandoverNote[]> => {
    const response = await api.get("/guide/handovers");
    return extractArray<GuideHandoverNote>(response);
  },

  // --- Chuyến được phân công ---

  getMyAssignments: async (): Promise<GuideAssignment[]> => {
    const response = await api.get("/guide/assignments");
    return extractArray<GuideAssignment>(response);
  },

  acceptAssignment: async (scheduleId: number) => {
    const response = await api.put(`/guide/assignments/${scheduleId}/accept`);
    return response.data?.message ?? "Đã xác nhận nhận chuyến.";
  },

  /** Từ chối phải kèm lý do: điều hành cần biết để xếp người khác. */
  declineAssignment: async (scheduleId: number, reason: string) => {
    const response = await api.put(`/guide/assignments/${scheduleId}/decline`, { reason });
    return response.data?.message ?? "Đã từ chối chuyến này.";
  },

  // Không còn `acknowledgeHandover`: bước xác nhận đã đọc không chặn gì, gọi điện hỏi nhanh hơn.

  getMyHandoverRequests: async (): Promise<GuideHandoverRequestRow[]> => {
    const response = await api.get("/guide/handover-requests");
    return extractArray<GuideHandoverRequestRow>(response);
  },

  /**
   * Xin được bàn giao đoàn.
   *
   * Cố ý không nhận người thay: tìm ai đang rảnh cần nhìn toàn bộ lịch công ty. Ở đây chỉ nói
   * "tôi cần được thay" kèm hai thứ chỉ người đang dẫn mới biết.
   */
  requestHandover: async (
    scheduleId: number,
    payload: { reason: string; group_state: string },
  ) => {
    const response = await api.post(`/guide/schedules/${scheduleId}/handover-request`, payload);
    return response.data?.message ?? "Đã gửi yêu cầu.";
  },

  // Không còn `withdrawHandoverRequest`: đỡ rồi thì gọi điều hành, họ đóng phiếu kèm ghi chú.

  getMyIncidents: async (): Promise<GuideIncident[]> => {
    const response = await api.get("/guide/incidents");
    return extractArray<GuideIncident>(response);
  },

  reportIncident: async (
    scheduleId: number,
    payload: {
      type: string;
      severity: string;
      occurred_at: string;
      description: string;
      tour_itinerary_id?: number | null;
    },
  ): Promise<{ incident: GuideIncident | null; message: string }> => {
    const response = await api.post(`/guide/schedules/${scheduleId}/incidents`, payload);
    return {
      incident: extractObject<GuideIncident>(response),
      message: response.data?.message ?? "Đã gửi báo cáo.",
    };
  },

  uploadIncidentPhoto: async (incidentId: number, photo: File, caption?: string) => {
    const data = new FormData();
    data.append("photo", photo);
    if (caption) data.append("caption", caption);

    const response = await api.post(`/guide/incidents/${incidentId}/photos`, data, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return response.data?.success !== false;
  },
};

export default guideService;

