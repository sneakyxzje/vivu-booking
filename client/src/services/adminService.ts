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

/*
 * Báo cáo điểm danh tổng hợp.
 *
 * Khai một lần ở đây và cho màn hình dùng lại. Trước đó cùng một hợp đồng được viết hai lần,
 * một ở service một ở trang báo cáo, rồi lệch nhau: trang khai absence_logs còn máy chủ không
 * trả, TypeScript vẫn xanh và màn hình vỡ ngay khi mở.
 */

export interface AttendanceScheduleRow {
  id: number;
  start_date: string;
  /** Sáu trạng thái của vòng đời chuyến, khớp App\Enums\ScheduleStatus phía máy chủ. */
  status: TourSchedule["status"];
  booked_people: number;
  tour_id: number | null;
  tour_title: string;
  number_of_days: number;
  guides: { id: number; name: string; phone?: string | null }[];
  present_count: number;
  absent_count: number;
  total_checkins: number;
  presence_rate: number;
  late_entry_count: number;
  photo_count: number;
}

/** Một lần khách không có mặt, gộp từ mọi chuyến trong bộ lọc. */
export interface AttendanceAbsenceLog {
  id: number;
  booking_id: number;
  passenger_name: string;
  customer_name: string;
  customer_phone: string;
  day_number: number;
  itinerary_title: string;
  checkpoint_name: string;
  status: string;
  status_label: string;
  note: string | null;
  checked_at: string | null;
  guide_name: string;
}

export interface AttendanceReportData {
  kpis: {
    overall_presence_rate: number;
    total_checkins: number;
    total_present: number;
    total_absent: number;
    late_entry_count: number;
    missing_photos_count: number;
  };
  schedules: {
    data: AttendanceScheduleRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  absence_logs: AttendanceAbsenceLog[];
}

/** Một hành khách đã khai trong nhóm. */
export interface ManifestPassenger {
  id: number;
  name: string;
  type: "adult" | "child" | "infant";
  gender: string | null;
  date_of_birth: string | null;
  identity_number: string | null;
  id_type: string | null;
  nationality: string | null;
  phone: string | null;
  /** Ăn chay, dị ứng, cần hỗ trợ di chuyển: thứ nhà cung cấp phải biết trước. */
  special_request: string | null;
  /** Người hướng dẫn viên gọi khi cần liên lạc với nhóm này. */
  is_contact: boolean;
  note: string | null;
}

/**
 * Một nhóm trong đoàn.
 *
 * Nhóm chính là một đơn đặt: thường có một người đứng ra đăng ký cho cả nhà hoặc cả phòng ban,
 * nên customer_name là người đặt, còn passengers mới là người đi.
 */
export interface ManifestGroup {
  booking_id: number;
  customer_name: string;
  customer_phone: string | null;
  customer_email: string | null;
  status: string;
  guests: number;
  declared: number;
  missing: number;
  warnings: string[];
  passengers: ManifestPassenger[];
}

export interface ScheduleManifestResponse {
  groups: ManifestGroup[];
  total_groups: number;
  total_guests: number;
  total_declared: number;
  total_missing: number;
  /** Danh sách đoàn chỉ gửi nhà cung cấp được khi mọi nhóm đã khai đủ người. */
  can_export_manifest: boolean;
}

export type ChangeRequestStatus =
  | "pending"
  | "approved"
  | "rejected"
  | "cancelled_by_customer";

/** Yêu cầu thay đổi do khách gửi, hiện mới có loại xin hủy. */
export interface ChangeRequest {
  id: number;
  booking_id: number;
  type: "cancel" | "transfer" | "change_guests" | "change_passenger";
  status: ChangeRequestStatus;
  /** Mức hoàn chốt lúc khách gửi. Đây là số hệ thống sẽ trả, không phải số tính lại. */
  estimated_refund: string | number | null;
  estimated_refund_percent: number | null;
  request_note: string | null;
  review_note: string | null;
  reviewed_at: string | null;
  created_at: string;
  booking?: Booking | null;
  /** Quan hệ đặt tên khác cột khóa ngoại, nếu không object sẽ đè lên chính cột id. */
  requester?: { id: number; name: string; email: string } | null;
  reviewer?: { id: number; name: string } | null;
}

export interface ChangeRequestListResponse {
  requests: PaginatedResponse<ChangeRequest>;
  pending_count: number;
}

export interface ChangeRequestDetail {
  request: ChangeRequest;
  /** false nghĩa là duyệt xong sẽ thành ghế chết. */
  seats_will_be_released: boolean;
  /** false khi chuyến đã khởi hành trong lúc yêu cầu nằm chờ. */
  can_approve: boolean;
  blocked_reason: string | null;
}

/** Một chuyến có thể ghép vào, kèm tác động đã tính sẵn. */
export interface MergeCandidate {
  schedule_id: number;
  start_date: string;
  booked_people: number;
  max_people: number;
  can_merge: boolean;
  blocked_reason: string | null;
  /** Số đơn đã thanh toán sẽ được chuyển sang. */
  transferring: number;
  transferring_guests: number;
  /** Số đơn chưa thanh toán sẽ bị hủy và mời đặt lại. */
  cancelling: number;
  remaining_seats: number;
}

export interface MergeCandidatesResponse {
  schedule: {
    id: number;
    start_date: string;
    booked_people: number;
    tour_title: string | null;
    tour_type: string | null;
  };
  candidates: MergeCandidate[];
}

/** Một đơn đã thanh toán của chuyến sắp hủy. Mỗi đơn phải có một phương án. */
export interface CancelPaidBooking {
  booking_id: number;
  customer_name: string;
  customer_email: string | null;
  guests: number;
  paid_amount: number;
}

export interface ScheduleCancelImpact {
  can_cancel: boolean;
  blocked_reason: string | null;
  start_date: string | null;
  hours_until_departure: number | null;
  paid_bookings: CancelPaidBooking[];
  /** Đơn chưa thanh toán: hệ thống tự hủy, không cần hỏi phương án. */
  unpaid_bookings: number;
  unpaid_guests: number;
  total_paid_bookings: number;
  total_paid_guests: number;
  total_refund_if_all_refunded: number;
  transfer_options: {
    schedule_id: number;
    start_date: string;
    remaining_seats: number;
  }[];
}

export interface ScheduleCancelPreviewResponse {
  schedule: {
    id: number;
    tour_title: string | null;
    start_date: string;
    booked_people: number;
    max_people: number;
  };
  impact: ScheduleCancelImpact;
}

/** Phương án cho một đơn khi hủy chuyến: hoàn đủ, hoặc chuyển sang chuyến khác. */
export interface CancelPlan {
  booking_id: number;
  action: "refund" | "transfer";
  to_schedule_id?: number | null;
}

/** Một dòng trong nhật ký hệ thống, gộp từ nhật ký đơn và nhật ký chuyến. */
export interface AuditLogEntry {
  /** Khóa dựng sẵn để React phân biệt hai nguồn, ví dụ "booking-12" và "schedule-12". */
  id: string;
  source: "booking" | "schedule";
  subject_id: number;
  /** Nhãn đúng như hiện trên các màn hình khác: "BK-19" hoặc "Chuyến #8". */
  subject_label: string;
  subject_note: string | null;
  action: string;
  action_label: string;
  touches_money: boolean;
  actor_name: string | null;
  actor_role: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  reason: string | null;
  ip_address: string | null;
  created_at: string | null;
}

export interface AuditLogFilters {
  scope?: "all" | "booking" | "schedule";
  action?: string;
  actor_id?: number;
  booking_id?: number;
  schedule_id?: number;
  from?: string;
  to?: string;
  money_only?: boolean;
  page?: number;
  per_page?: number;
}

export interface AuditLogResponse {
  data: AuditLogEntry[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
  filters: {
    booking_actions: { value: string; label: string; touches_money: boolean }[];
    schedule_actions: { value: string; label: string; touches_money: boolean }[];
  };
}

/**
 * Tác động của việc dời hạn chốt danh sách, máy chủ tính sẵn.
 *
 * Hạn chốt là cái vạch chia trước và sau khi gửi danh sách cho nhà cung cấp. Kéo cái vạch ấy đổi
 * cùng lúc quyền bán chỗ, sửa tên hành khách, chuyển chuyến, ghép chuyến, và việc chỗ có quay về
 * kho khi khách hủy hay không - nên phải nói ra trước khi lưu.
 */
export interface DeadlineImpact {
  current_deadline: string | null;
  new_deadline: string | null;
  /** Mốc thực sự có hiệu lực: hạn chốt riêng của chuyến, hoặc mốc mặc định của hệ thống. */
  effective_current_deadline: string | null;
  effective_new_deadline: string | null;
  direction: "earlier" | "later" | "unchanged" | "unknown";
  currently_past: boolean;
  will_be_past: boolean;
  /** Số đơn đã vào danh sách đoàn, tức nhóm mất quyền sửa tên và chuyển chuyến. */
  manifest_bookings: number;
  manifest_guests: number;
  pending_bookings: number;
  /** Ghế chết: đơn đã hủy nhưng chỗ chưa trả về kho. Dời hạn chốt không làm nó sống lại. */
  held_seat_bookings: number;
  held_seats: number;
  /** Chuyến đang đóng bán và sẽ không tự mở lại sau khi gia hạn. */
  needs_manual_reopen: boolean;
  can_change: boolean;
  blocked_reason: string | null;
  warnings: string[];
}

export interface DeadlineImpactResponse {
  schedule: {
    id: number;
    tour_title: string | null;
    start_date: string;
    status: string;
    booked_people: number;
    max_people: number;
  };
  impact: DeadlineImpact;
}

/** Một chuyến có thể chuyển đơn sang, kèm chênh lệch đã tính sẵn. */
export interface TransferOption {
  schedule_id: number;
  tour_id: number;
  tour_title: string | null;
  start_date: string;
  remaining_seats: number;
  can_transfer: boolean;
  blocked_reason: string | null;
  /** Dương là chuyến đích đắt hơn, âm là rẻ hơn. */
  price_difference: number;
  /** Phí đổi lịch, chỉ phát sinh từ lần chuyển thứ hai và khi khách khởi xướng. */
  fee: number;
  new_total: number;
  transfer_count: number;
}

export interface TransferOptionsResponse {
  booking: {
    id: number;
    guests: number;
    total_amount: number;
    transfer_count: number;
  };
  options: TransferOption[];
}

/** Một mục trong dòng thời gian thay đổi của đơn. */
export interface BookingAuditEntry {
  id: number;
  action: string;
  action_label: string;
  /** Thao tác này có làm thay đổi tiền hay không, dùng để làm nổi khi đối soát. */
  touches_money: boolean;
  actor_name: string | null;
  /** Vai trò chép lại tại thời điểm thao tác, không phải vai trò hiện tại của tài khoản. */
  actor_role: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  reason: string | null;
  ip_address: string | null;
  created_at: string;
}

/** Hậu quả của việc hủy một đơn, tính trước khi thực hiện. */
export interface CancelPreview {
  /** Số giờ còn lại tới khởi hành. Âm nghĩa là đã qua giờ đi. */
  hours_before: number | null;
  refund_percent: number;
  total_amount: number;
  paid_amount: number;
  cancellation_fee: number;
  refund_amount: number;
  can_cancel: boolean;
  /** Câu giải thích khi không hủy được, lấy nguyên từ tầng dịch vụ. */
  blocked_reason: string | null;
  /** false nghĩa là hủy xong sẽ thành ghế chết. */
  seats_will_be_released: boolean;
  policy_name: string | null;
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

  /**
   * Hậu quả của việc hủy, lấy trước khi bấm xác nhận.
   *
   * Hai con số quan trọng nhất là mức hoàn và chỗ có về kho hay không. Người hủy phải biết
   * trước, không phải phát hiện ra sau khi thấy số chỗ không nhúc nhích.
   */
  /**
   * G05 - Các đơn của một chuyến còn khai thiếu hành khách.
   *
   * Điều hành cần biết trước khi gửi danh sách đoàn cho nhà cung cấp, thay vì mở từng đơn đếm.
   */
  getScheduleManifest: async (
    scheduleId: number,
  ): Promise<ScheduleManifestResponse | null> => {
    const response = await api.get(`/admin/schedules/${scheduleId}/manifest`);
    return extractObject<ScheduleManifestResponse>(response);
  },

  // --- YÊU CẦU THAY ĐỔI CỦA KHÁCH ---
  getChangeRequests: async (
    status = "pending",
    page = 1,
  ): Promise<ChangeRequestListResponse | null> => {
    const response = await api.get(`/admin/change-requests?status=${status}&page=${page}`);
    return extractObject<ChangeRequestListResponse>(response);
  },

  getChangeRequest: async (id: number): Promise<ChangeRequestDetail | null> => {
    const response = await api.get(`/admin/change-requests/${id}`);
    return extractObject<ChangeRequestDetail>(response);
  },

  approveChangeRequest: async (id: number, reviewNote?: string) => {
    const response = await api.put(`/admin/change-requests/${id}/approve`, {
      review_note: reviewNote || null,
    });
    return response.data?.message ?? "Đã duyệt yêu cầu.";
  },

  rejectChangeRequest: async (id: number, reviewNote: string) => {
    const response = await api.put(`/admin/change-requests/${id}/reject`, {
      review_note: reviewNote,
    });
    return response.data?.message ?? "Đã từ chối yêu cầu.";
  },

  /**
   * L03 - Các chuyến có thể ghép chuyến này vào.
   *
   * Máy chủ tính sẵn số đơn sẽ chuyển và số đơn sẽ bị hủy cho từng lựa chọn, để điều hành thấy
   * hậu quả trước khi chọn chứ không phải chọn rồi mới biết.
   */
  getMergeCandidates: async (scheduleId: number): Promise<MergeCandidatesResponse | null> => {
    const response = await api.get(`/admin/schedules/${scheduleId}/merge-candidates`);
    return extractObject<MergeCandidatesResponse>(response);
  },

  mergeSchedule: async (scheduleId: number, toScheduleId: number, reason: string) => {
    const response = await api.post(`/admin/schedules/${scheduleId}/merge`, {
      to_schedule_id: toScheduleId,
      reason,
    });
    return response.data?.message ?? "Đã ghép chuyến.";
  },

  /**
   * K - Tác động của việc hủy chuyến: ai đã trả tiền, bao nhiêu, chuyển sang đâu được.
   *
   * Phải xem trước rồi mới hủy được, vì mỗi đơn đã thanh toán cần một phương án cụ thể.
   */
  getScheduleCancelPreview: async (
    scheduleId: number,
  ): Promise<ScheduleCancelPreviewResponse | null> => {
    const response = await api.get(`/admin/schedules/${scheduleId}/cancel-preview`);
    return extractObject<ScheduleCancelPreviewResponse>(response);
  },

  cancelSchedule: async (scheduleId: number, reason: string, plans: CancelPlan[]) => {
    const response = await api.post(`/admin/schedules/${scheduleId}/cancel`, {
      reason,
      plans,
    });
    return response.data?.message ?? "Đã hủy chuyến.";
  },

  /**
   * Tác động của hạn chốt mới, lấy trước khi lưu.
   *
   * Tính ở máy chủ chứ không ở trình duyệt: cùng một phép tính mà làm hai nơi thì sớm muộn con
   * số hiện cho điều hành sẽ lệch với luật máy chủ thực sự áp.
   */
  getDeadlineImpact: async (
    scheduleId: number,
    deadline: string | null,
  ): Promise<DeadlineImpactResponse | null> => {
    const response = await api.get(`/admin/schedules/${scheduleId}/deadline-impact`, {
      params: deadline ? { booking_deadline: deadline } : {},
    });
    return extractObject<DeadlineImpactResponse>(response);
  },

  updateScheduleDeadline: async (
    scheduleId: number,
    deadline: string | null,
    reason: string,
  ) => {
    const response = await api.patch(`/admin/schedules/${scheduleId}/deadline`, {
      booking_deadline: deadline,
      reason: reason || null,
    });
    return response.data?.message ?? "Đã đổi hạn chốt danh sách.";
  },

  /**
   * I05 - Các chuyến có thể chuyển đơn sang.
   *
   * Máy chủ tính sẵn chênh lệch cho từng lựa chọn và loại bỏ chuyến không chuyển được, nên màn
   * hình chỉ việc hiển thị. Tính lại ở trình duyệt thì sớm muộn cũng lệch với con số máy chủ áp.
   */
  getTransferOptions: async (
    bookingId: number,
    sameTour = true,
    initiatedBy: "customer" | "company" = "customer",
  ): Promise<TransferOptionsResponse | null> => {
    const response = await api.get(
      `/admin/bookings/${bookingId}/transfer-options?same_tour=${sameTour ? 1 : 0}`
        + `&initiated_by=${initiatedBy}`,
    );
    return extractObject<TransferOptionsResponse>(response);
  },

  /**
   * `initiatedBy` quyết định hai luật: hạn báo trước 7 ngày và phí đổi lịch.
   *
   * Khách gọi lên xin đổi thì vẫn là `customer`, dù người bấm nút là điều hành. Gửi `company`
   * cho mọi trường hợp là nói dối hệ thống để lách hai luật đó.
   */
  transferBooking: async (
    bookingId: number,
    toScheduleId: number,
    reason: string,
    initiatedBy: "customer" | "company" = "customer",
  ) => {
    const response = await api.post(`/admin/bookings/${bookingId}/transfer`, {
      to_schedule_id: toScheduleId,
      reason,
      initiated_by: initiatedBy,
    });
    return response.data?.message ?? "Đã chuyển chuyến.";
  },

  /** E04 - Dòng thời gian thay đổi của một đơn: ai làm gì, lúc nào, vì sao. */
  /**
   * Nhật ký hệ thống: mọi can thiệp vào đơn và vào chuyến trên một dòng thời gian.
   *
   * Khác getBookingHistory ở chiều tra cứu. Hàm kia trả lời "đơn này đã trải qua những gì" và
   * đòi biết trước cần xem đơn nào; hàm này trả lời "hôm qua ai đụng vào tiền".
   */
  getAuditLogs: async (filters: AuditLogFilters = {}): Promise<AuditLogResponse | null> => {
    const params: Record<string, string | number> = {};

    Object.entries(filters).forEach(([khoa, giaTri]) => {
      if (giaTri === undefined || giaTri === "" || giaTri === false) return;
      params[khoa] = typeof giaTri === "boolean" ? 1 : giaTri;
    });

    const response = await api.get("/admin/audit-logs", { params });
    return extractObject<AuditLogResponse>(response);
  },

  getBookingHistory: async (id: number): Promise<BookingAuditEntry[]> => {
    const response = await api.get(`/admin/bookings/${id}/history`);
    return response.data?.data ?? [];
  },

  getCancelPreview: async (id: number): Promise<CancelPreview | null> => {
    const response = await api.get(`/admin/bookings/${id}/cancel-preview`);
    return extractObject<CancelPreview>(response);
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

  /**
   * Đặt lại danh sách hướng dẫn viên của một chuyến.
   *
   * Nhận cả danh sách vì đoàn đông thì cần nhiều người dẫn. Mảng rỗng nghĩa là bỏ hết phân công.
   * Máy chủ được ăn cả ngã về không: một người vướng lịch thì cả lần phân công bị từ chối.
   */
  assignGuidesToSchedule: async (scheduleId: number, guideIds: number[]) => {
    const response = await api.put(`/admin/tour-schedules/${scheduleId}/assign-guide`, {
      guide_ids: guideIds,
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
  }): Promise<AttendanceReportData | null> => {
    const response = await api.get("/admin/attendance-reports", { params });
    return extractObject<AttendanceReportData>(response);
  },
};

export default adminService;



