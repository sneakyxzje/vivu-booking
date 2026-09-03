import api from "./api";
import { extractArray, extractObject } from "@/utils/apiHelpers";
import type { Booking, BookingLedger, RefundBankInfo, GroupBookingRequestRow, Guide, GuideDecline, GuideProfilePayload, GuideSuitability, Tour, TourSchedule, DiscountCode, DiscountCodePayload, Service, ServicePayload, Category, CategoryPayload } from "@/types";
import { buildTourPayload } from "@/services/guideService";

/**
 * Kết quả xem trước việc xóa tour.
 *
 * Bên dưới là **xóa mềm**: tour biến mất khỏi mọi danh sách nhưng đơn hàng, đánh giá và chuyến
 * vẫn còn nguyên, và khôi phục lại được. `preserved` là danh sách những thứ **không** mất —
 * quan trọng ngang phần chặn, vì người bấm cần biết mình không phá gì.
 */
export interface TourDeletePreview {
  tour_id: number;
  tour_title: string;
  can_delete: boolean;
  blockers: { key: string; count: number; message: string }[];
  preserved: {
    bookings: number;
    reviews: number;
    group_requests: number;
    schedules: number;
  };
  already_retired: boolean;
}

/** Một tour đã xóa, chờ khôi phục. */
export interface TrashedTour {
  id: number;
  title: string;
  start_location: string | null;
  deleted_at: string | null;
  bookings_count: number;
}

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  total: number;
  per_page: number;
  last_page: number;
}

/** Bộ lọc của danh sách đơn phía điều hành. Rỗng nghĩa là không lọc theo tiêu chí đó. */
export interface BookingListFilters {
  page?: number;
  q?: string;
  status?: string;
  payment?: string;
  sort?: string;
}

/**
 * Các con số trên đầu trang danh sách đơn.
 *
 * Tính trên TOÀN BỘ bộ lọc đang xem chứ không riêng trang hiện tại — lọc ra 40 đơn đã hủy mà ô
 * thống kê ghi 3 thì con số ấy không dùng được vào việc gì.
 */
export interface BookingListSummary {
  total: number;
  pending: number;
  confirmed: number;
  cancelled: number;
  paid: number;
  revenue: number;
}

export type BookingListResponse = PaginatedResponse<Booking> & {
  summary: BookingListSummary;
};

/** Khoảng ngày gửi lên bảng điều khiển. Bỏ trống cả hai là xem toàn thời gian. */
export interface DashboardRange {
  from?: string | null;
  to?: string | null;
}

export interface AdminDashboardData {
  /**
   * Khoảng máy chủ thực sự áp dụng, kèm đơn vị nó chọn cho biểu đồ.
   *
   * Không tự suy ở phía giao diện được: máy chủ mới là nơi quyết gom theo ngày hay theo tháng, và
   * nơi nới hai đầu ra trọn ngày.
   */
  range: {
    from: string | null;
    to: string | null;
    granularity: "day" | "month";
    filtered: boolean;
  };
  summary: Record<string, number>;
  booking_summary: {
    total_bookings: number;
    pending_bookings: number;
    confirmed_bookings: number;
    cancelled_bookings: number;
    total_revenue: number;
    revenue_this_month: number;
    contracted_value: number;
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

/**
 * Một bậc phí, đếm bằng NGÀY còn lại tới lúc khởi hành.
 *
 * Khoảng đóng ở dưới, mở ở trên: bậc 8-15 nhận đúng mốc 8 ngày và nhường mốc 15 cho bậc trên, nên
 * các bậc nối liền nhau mà không chồng nhau. `max_days_before` để trống là bậc xa nhất.
 */
export interface CancellationPolicyRule {
  id?: number;
  min_days_before: number;
  max_days_before: number | null;
  refund_percent: number;
  note?: string | null;
}

/** Bảng phí hủy. Hệ thống chỉ có đúng một bản đang áp dụng tại mỗi thời điểm. */
export interface CancellationPolicy {
  id: number;
  name: string;
  description: string | null;
  /** Mốc bắt đầu áp dụng, giờ Việt Nam dạng "YYYY-MM-DD HH:mm:ss". Có thể nằm ở tương lai. */
  effective_from: string;
  rules: CancellationPolicyRule[];
}

export interface CancellationPolicyPayload {
  name: string;
  description?: string | null;
  effective_from: string;
  rules: CancellationPolicyRule[];
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

/* Thông báo đã chuyển sang `notificationService`: hướng dẫn viên cũng có hộp riêng nên nó không
 * còn là việc của điều hành. */

/** Hợp đồng du lịch của một đơn. `null` ở phía gọi nghĩa là đơn chưa được cấp hợp đồng. */
export interface BookingContractInfo {
  id: number;
  booking_id: number;
  contract_number: string;
  issued_at: string | null;
  issued_by_name: string | null;
  signed_at: string | null;
  signed_note: string | null;
  /** Liên kết có chữ ký, hết hạn sau 24 giờ — sinh mới mỗi lần đọc, đừng lưu lại. */
  print_url: string;
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

/** Bộ lọc của sổ giao dịch tổng. Bỏ trống trường nào là không lọc theo trường đó. */
export interface TransactionFilters {
  from?: string;
  to?: string;
  /** `in` = tiền vào, `out` = tiền hoàn ra. */
  direction?: "in" | "out" | "";
  /**
   * Loại bút toán — hẹp hơn `direction`.
   *
   * Chiều tiền trả lời "vào hay ra", loại trả lời "vào bằng đường nào": tiền cọc khác thanh toán
   * phần còn lại, và phụ thu sự cố là túi tiền khác hẳn giá tour.
   */
  kind?: "deposit" | "balance" | "refund" | "surcharge" | "surcharge_refund" | "";
  method?: "bank_transfer" | "cash" | "gateway" | "";
  /** Tìm theo mã chứng từ hoặc tên khách. */
  q?: string;
}

export interface TransactionRow {
  id: number;
  booking_id: number;
  public_token: string | null;
  customer_name: string | null;
  tour_title: string | null;
  booking_status: string | null;
  kind: string;
  kind_label: string;
  /** Chiều tiền do máy chủ quyết, giao diện không tự đoán từ `kind`. */
  direction: "in" | "out";
  amount: number;
  method: string | null;
  method_label: string | null;
  reference: string | null;
  note: string | null;
  paid_at: string | null;
  recorded_by: string | null;
}

export interface TransactionListResponse extends PaginatedResponse<TransactionRow> {
  /** Tính trên TOÀN BỘ bộ lọc, không riêng trang đang xem. */
  totals: { in: number; out: number; net: number; count: number };
}

export interface RefundQueueRow {
  id: number;
  public_token: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  tour_title: string | null;
  start_date: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  /** Nghĩa vụ chốt tại thời điểm hủy. */
  refund_due: number;
  /** Phần trong nghĩa vụ đó đã thực trả. */
  refunded: number;
  refund_outstanding: number;
  refund_bank: RefundBankInfo | null;
}

export interface RefundQueueResponse {
  data: RefundQueueRow[];
  current_page: number;
  last_page: number;
  /** Tổng còn nợ khách trên toàn bộ, không riêng trang đang xem. */
  outstanding_total: number;
}

/**
 * Một đơn khách còn nợ công ty — chiều ngược lại của `RefundQueueRow`.
 *
 * Hai màn cùng đọc một sổ giao dịch, chỉ khác chiều tiền. Có cả hai thì câu "ai còn nợ ai" mới trả
 * lời được đủ; trước đó hệ thống chỉ có nửa công ty nợ khách.
 */
export interface ReceivableRow {
  id: number;
  public_token: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  tour_title: string | null;
  start_date: string | null;
  total_amount: number;
  net_paid: number;
  balance_due: number;
  /** Hạn chốt danh sách làm hạn thu tiền: đó là lúc công ty phải trả nhà cung cấp. */
  due_by: string | null;
  overdue: boolean;
  status: string;
}

export interface ReceivableResponse {
  data: ReceivableRow[];
  current_page: number;
  last_page: number;
  total: number;
  /** Tổng còn phải thu trên toàn bộ bộ lọc, không riêng trang đang xem. */
  outstanding_total: number;
}

export type ContactMessageStatus = "new" | "handled";

export interface ContactMessage {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  subject: string | null;
  message: string;
  status: ContactMessageStatus;
  handled_at: string | null;
  handled_by: string | null;
  handling_note: string | null;
  created_at: string | null;
}

export interface ContactMessageListResponse extends PaginatedResponse<ContactMessage> {
  new_count: number;
}

export type AdminUserRole = "admin" | "guide" | "customer";
export type AdminUserStatus = "active" | "inactive" | "blocked";

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: AdminUserRole;
  status: AdminUserStatus;
  avatar: string | null;
  /** Số đơn đã đặt — con số quyết định người bấm có dám khóa hay không. */
  bookings_count: number;
  created_at: string | null;
}

export interface AdminUserListResponse extends PaginatedResponse<AdminUser> {
  counts: { admin: number; guide: number; customer: number; blocked: number };
}

export type AdminReviewStatus = "pending" | "approved" | "rejected";

export interface AdminReview {
  id: number;
  rating: number;
  comment: string;
  status: AdminReviewStatus;
  status_label: string;
  moderation_note: string | null;
  moderated_at: string | null;
  moderated_by: string | null;
  reply: string | null;
  replied_at: string | null;
  replied_by: string | null;
  created_at: string | null;
  user: { id: number; name: string; email: string } | null;
  tour: { id: number; title: string; slug: string } | null;
}

/**
 * Máy chủ trả về đối tượng phân trang có thêm `pending_count`, nên hình dạng ở đây là
 * `PaginatedResponse` mở rộng chứ không phải một khóa `reviews` lồng bên trong.
 */
export interface AdminReviewListResponse extends PaginatedResponse<AdminReview> {
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

/** Biên bản bàn giao hướng dẫn viên giữa chừng chuyến. */
export interface GuideHandoverRow {
  id: number;
  tour_schedule_id: number;
  from_guide: { id: number; name: string; phone?: string | null } | null;
  to_guide: { id: number; name: string; phone?: string | null } | null;
  handed_over_at: string | null;
  reason: string;
  /** Tình trạng đoàn tại thời điểm bàn giao. Phần có giá trị nhất của biên bản. */
  handover_note: string;
  /** Nhờ hướng dẫn viên của đoàn khác trông hộ: người nhận đang giữ hai đoàn, còn việc dở. */
  /** Người nhận đã xác nhận đọc chưa. Không chặn gì, chỉ để biết có cần gọi điện không. */
  /** Bao nhiêu phút trôi qua mà chưa ai xác nhận. Null khi đã xác nhận. */
  minutes_waiting: number | null;
  created_by_name: string | null;
  created_at: string | null;
  /** Ghi vào máy muộn hơn lúc bàn giao thật, vì bàn giao xảy ra trên đường. */
  recorded_late: boolean;
}

export interface HandoverHistoryResponse {
  handovers: (GuideHandoverRow & {
    tour_title: string | null;
    start_date: string | null;
  })[];
}

/** Phiếu bàn giao đang chờ điều hành xử lý. */
export interface PendingHandoverRequest {
  id: number;
  tour_schedule_id: number;
  tour_title: string | null;
  start_date: string | null;
  requester_name: string | null;
  requester_phone: string | null;
  reason: string;
  /** Chữ của người đang đứng cùng đoàn, không phải của người ngồi văn phòng. */
  group_state: string;
  created_at: string | null;
}

export interface HandoverPanelResponse {
  schedule: {
    id: number;
    tour_title: string | null;
    start_date: string;
    status: string;
  };
  current_guides: { id: number; name: string; phone?: string | null }[];
  /**
   * Đoàn đang trên đường mà chỉ còn một người phụ trách — chưa bàn giao được.
   *
   * Phải phân công thêm một người cho chuyến trước, để sau khi một người rời đi vẫn còn ai đó
   * bên đoàn.
   */
  blocked_needs_second_guide: boolean;
  /** Còn bao nhiêu giờ nữa đoàn về. Cử người ở xa cho chuyến sắp kết thúc là vô nghĩa. */
  hours_remaining: number | null;
  available_guides: {
    id: number;
    name: string;
    phone?: string | null;
    /**
     * Đang dẫn một đoàn khác cũng trên đường.
     *
     * Dấu hiệu gần đúng cho "ở gần đoàn": họ đã ra ngoài rồi. Người không có cờ này có thể đang
     * ở nhà cách đoàn nửa ngày đường — hệ thống không biết ai ở đâu, nhưng phân biệt được thế
     * này vẫn hơn không phân biệt gì.
     */
    leading_other_group: boolean;
  }[];
  handovers: GuideHandoverRow[];
}

/** Khoản tiền sinh ra từ một sự cố, gắn với một đơn cụ thể. */
export interface IncidentSurcharge {
  id: number;
  booking_id: number;
  customer_name: string | null;
  kind: "surcharge" | "refund";
  kind_label: string;
  /** Ai chịu KHOẢN NÀY. Một cơn bão sinh ra cả khoản hãng chịu lẫn khoản khách chịu. */
  who_bears: string | null;
  who_bears_label: string | null;
  amount: number;
  reason: string;
  status: string;
  status_label: string;
  /** Chờ duyệt thì chưa có hiệu lực với khách. */
  in_effect: boolean;
  customer_consent_at: string | null;
  consent_note: string | null;
  /* Ba câu hỏi máy chủ trả lời sẵn, để giao diện không tự suy từ trạng thái rồi suy sai. */
  needs_consent: boolean;
  can_settle: boolean;
  settled: boolean;
}

export interface AdminIncident {
  id: number;
  tour_schedule_id: number;
  tour_title: string | null;
  start_date: string | null;
  type: string;
  type_label: string;
  severity: string;
  severity_label: string;
  /** Mức nghiêm trọng và chưa ai xử lý: đẩy lên đầu danh sách. */
  needs_attention: boolean;
  status: string;
  status_label: string;
  occurred_at: string | null;
  reported_late: boolean;
  description: string;
  reporter_name: string | null;
  resolution: string | null;
  cost_delta: number | null;
  who_bears: string | null;
  who_bears_label: string | null;
  reviewed_at: string | null;
  photos: { id: number; image_path: string; caption: string | null }[];
  surcharges: IncidentSurcharge[];
}

export interface IncidentListResponse {
  incidents: AdminIncident[];
  options: {
    bearers: { value: string; label: string; customer_pays: boolean }[];
    kinds: { value: string; label: string }[];
  };
}

export interface IncidentDetailResponse {
  incident: AdminIncident;
  bookings: {
    booking_id: number;
    customer_name: string;
    customer_phone: string | null;
    guests: number;
  }[];
}

/** Một khoản điều hành lập cho một đơn khi xử lý sự cố. */
export interface IncidentChargeInput {
  booking_id: number;
  kind: "surcharge" | "refund";
  /** Bỏ trống thì máy chủ lùi về `who_bears` của phương án. */
  who_bears: string | null;
  amount: number;
  reason: string;
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
  /**
   * Còn thiếu bao nhiêu SAU khi chuyển, tính theo giá chuyến đích.
   *
   * Hạn trả nốt suy ra từ ngày khởi hành, nên chuyển sang chuyến sớm hơn kéo cái hạn ấy lùi lại —
   * có khi lùi vào quá khứ, biến một đơn đang yên lành thành đơn quá hạn. Người bấm nút không có
   * cách nào tự nhìn ra: màn hình chọn chuyến theo chỗ trống và ngày đi, không ai nhẩm "ngày đi
   * trừ mười" trong đầu.
   */
  balance_due: number;
  balance_due_at: string | null;
  /** Chuyển xong là quá hạn ngay — khách sẽ nhận thư đòi trả nốt trong vài ngày. */
  balance_overdue_after: boolean;
  /** Nặng hơn: quá sát ngày để quy trình nhắc rồi hủy kịp chạy, buộc phải thu tay. */
  auto_collect_too_late: boolean;
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

/**
 * Nhóm căn cứ của một lần chuyển chuyến.
 *
 * Ba nhóm đầu là bất khả kháng nên không thu phí đổi lịch của khách; chỉ nhóm cuối chịu quy tắc
 * phí. Phía máy chủ quyết con số, đây chỉ là danh sách để chọn.
 */
export const NHOM_LY_DO_CHUYEN = [
  { value: "force_majeure", label: "Thiên tai, thời tiết" },
  { value: "authority", label: "Quyết định của cơ quan nhà nước" },
  { value: "supplier", label: "Nhà cung cấp không thực hiện được" },
  { value: "customer_request", label: "Khách xin đổi vì việc riêng" },
] as const;

export type TransferReasonCategory = (typeof NHOM_LY_DO_CHUYEN)[number]["value"];

export const KENH_LIEN_HE = [
  { value: "phone", label: "Gọi điện" },
  { value: "zalo", label: "Nhắn Zalo" },
  { value: "email", label: "Gửi email" },
  { value: "in_person", label: "Gặp trực tiếp" },
] as const;

export const KET_QUA_LIEN_HE = [
  { value: "agreed", label: "Khách đồng ý" },
  { value: "refused", label: "Khách không đồng ý" },
  { value: "unreachable", label: "Không liên lạc được" },
] as const;

/** Một lần công ty liên hệ khách về một đơn. Ghi rồi thì không sửa, không xóa. */
export interface ContactLog {
  id: number;
  channel: string;
  channel_label: string;
  purpose: string;
  purpose_label: string;
  outcome: string;
  outcome_label: string;
  note: string;
  contacted_at: string;
  contacted_by: string | null;
  /** Đã tiêu vào một lần chuyển chuyến rồi. */
  da_dung_lam_can_cu: boolean;
  /** Khách đồng ý, đúng mục đích, và chưa dùng lần nào — tức chọn làm căn cứ được. */
  dung_lam_can_cu_duoc: boolean;
}

export interface ContactLogPayload {
  channel: string;
  purpose: string;
  outcome: string;
  note: string;
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

/**
 * Ai hủy đơn — và đây là câu hỏi về TIỀN, không phải một nhãn phân loại.
 *
 * `by_customer`: khách đổi ý, gọi lên nhờ điều hành bấm hộ. Áp bảng phí hủy.
 * `by_company`: công ty đơn phương không thực hiện đơn này. Hoàn đủ số đã thu.
 *
 * Trước đây màn hủy ghi cứng `by_company` nhưng vẫn áp bảng phí, và mẫu thư báo hủy đọc đúng cột
 * ấy rồi khẳng định với khách "không áp dụng phí hủy — hoàn đủ 100%" trong khi hệ thống vừa giữ
 * lại 30%.
 */
export type CancelType = "by_customer" | "by_company";

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
  /**
   * Đơn đang nằm trên một chuyến do công ty dời tới (ghép chuyến, hoặc hãng tự đổi lịch).
   *
   * Khi true thì bảng phí không áp: khách từ chối một thay đổi họ không hề chọn, nên hoàn đủ.
   */
  moved_by_company: boolean;
  /** Người bấm đã chọn "công ty hủy" — lý do miễn phí hủy thứ hai, do người quyết chứ không suy ra. */
  company_initiated: boolean;
  /** Gộp cả hai lý do trên: có được miễn bảng phí hay không. */
  fee_waived: boolean;
}

const adminService = {
  // --- DASHBOARD ---
  /**
   * Số liệu tổng quan, tùy chọn giới hạn trong một khoảng ngày.
   *
   * Bỏ trống cả hai đầu thì máy chủ giữ nguyên hành vi cũ: tổng toàn thời gian, biểu đồ mười hai
   * tháng của năm nay. Chỉ gửi tham số thật sự có giá trị, để một chuỗi rỗng không bị hiểu thành
   * một ngày.
   */
  getDashboard: async (
    range?: DashboardRange,
  ): Promise<AdminDashboardData | null> => {
    const params: Record<string, string> = {};
    if (range?.from) params.from = range.from;
    if (range?.to) params.to = range.to;

    const response = await api.get("/admin/dashboard", { params });
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

  /** K06 — xóa tour được chưa, và những gì vẫn ở lại sau khi xóa. */
  getTourDeletePreview: async (id: number): Promise<TourDeletePreview | null> => {
    const response = await api.get(`/admin/tours/${id}/delete-preview`);
    return extractObject<TourDeletePreview>(response);
  },

  /** Xóa tour — bên dưới là xóa mềm nên khôi phục lại được. */
  deleteTour: async (id: number) => {
    const response = await api.delete(`/admin/tours/${id}`);
    return response.data?.message ?? "Đã xóa tour.";
  },

  getTrashedTours: async (): Promise<TrashedTour[]> => {
    const response = await api.get("/admin/tours/trashed");
    return extractArray<TrashedTour>(response);
  },

  restoreTour: async (id: number) => {
    const response = await api.put(`/admin/tours/${id}/restore`);
    return response.data?.message ?? "Đã khôi phục tour.";
  },

  /** Ngừng bán — lối đi cho tour đã có lịch sử. Chuyến đã chốt vẫn chạy. */
  retireTour: async (id: number) => {
    const response = await api.put(`/admin/tours/${id}/retire`);
    return response.data?.message ?? "Đã chuyển tour sang ngừng bán.";
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
  /**
   * Danh sách đơn. Tìm, lọc và sắp xếp đều do máy chủ làm.
   *
   * Trước đây hàm này chỉ nhận số trang, còn màn hình tự lọc mười dòng vừa tải về — nên gõ mã một
   * đơn ở trang sau là "không tìm thấy". Mọi tham số dưới đây phải đi kèm mỗi lần gọi, kể cả khi
   * người dùng chỉ bấm sang trang khác.
   */
  getBookings: async (
    boLoc: BookingListFilters = {},
  ): Promise<BookingListResponse | null> => {
    const response = await api.get("/admin/bookings", {
      params: {
        page: boLoc.page ?? 1,
        // Bỏ hẳn tham số rỗng thay vì gửi chuỗi trống: máy chủ coi "không có" là không lọc, còn
        // chuỗi trống thì lọt vào luật validate và thành một bộ lọc khớp mọi thứ.
        ...(boLoc.q ? { q: boLoc.q } : {}),
        ...(boLoc.status && boLoc.status !== "all" ? { status: boLoc.status } : {}),
        ...(boLoc.payment && boLoc.payment !== "all" ? { payment: boLoc.payment } : {}),
        ...(boLoc.sort ? { sort: boLoc.sort } : {}),
      },
    });
    return extractObject<BookingListResponse>(response);
  },

  getBookingById: async (id: number): Promise<Booking | null> => {
    const response = await api.get(`/admin/bookings/${id}`);
    return extractObject<Booking>(response);
  },

  /**
   * Xác nhận một đơn đang chờ, kèm khoản tiền vừa thu.
   *
   * Xác nhận là tuyên bố "khách này đã trả tiền", nên máy chủ đòi số tiền và hình thức thu rồi ghi
   * thẳng vào sổ giao dịch. Bỏ qua được đúng một trường hợp: kế toán đã ghi khoản thu từ trước, lúc
   * ấy sổ không còn nợ gì và `thuTien` để trống.
   */
  confirmBooking: async (
    id: number,
    thuTien?: { amount: number; method: "cash" | "bank_transfer" | "gateway"; reference?: string; note?: string },
  ): Promise<Booking | null> => {
    const response = await api.put(`/admin/bookings/${id}/confirm`, thuTien ?? {});
    return extractObject<Booking>(response);
  },

  /** Nhập hộ tài khoản nhận tiền hoàn khi khách đọc qua điện thoại. */
  updateRefundAccount: async (
    bookingId: number,
    taiKhoan: { refund_bank_account: string; refund_bank_name: string; refund_account_holder: string },
  ): Promise<{ success: boolean }> => {
    const response = await api.put(`/admin/bookings/${bookingId}/refund-account`, taiKhoan);
    return { success: response.data?.success !== false };
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

  /*
   * Tải danh sách đoàn về máy.
   *
   * Phải đi qua axios chứ không phải một thẻ <a href> thẳng: tuyến này nằm sau Sanctum, mà thẻ
   * <a> không gắn được tiêu đề Authorization. Nên lấy về dạng blob rồi tự dựng liên kết tạm.
   *
   * Tên tệp lấy từ tiêu đề Content-Disposition máy chủ gửi kèm, để cả hai bên gọi tệp giống nhau.
   */
  exportScheduleManifest: async (scheduleId: number): Promise<void> => {
    const response = await api.get(`/admin/schedules/${scheduleId}/manifest/export`, {
      responseType: "blob",
    });

    const disposition = String(response.headers?.["content-disposition"] ?? "");
    const khop = disposition.match(/filename="?([^"]+)"?/);

    const url = URL.createObjectURL(response.data as Blob);
    const the = document.createElement("a");
    the.href = url;
    the.download = khop?.[1] ?? `danh-sach-doan-${scheduleId}.csv`;
    document.body.appendChild(the);
    the.click();

    // Dọn ngay: liên kết blob giữ nguyên tệp trong bộ nhớ cho tới khi bị thu hồi.
    document.body.removeChild(the);
    URL.revokeObjectURL(url);
  },

  // --- Q - HỢP ĐỒNG DU LỊCH ---

  getBookingContract: async (bookingId: number): Promise<BookingContractInfo | null> => {
    const response = await api.get(`/admin/bookings/${bookingId}/contract`);
    return (response.data?.data ?? null) as BookingContractInfo | null;
  },

  /** Cấp hợp đồng, hoặc lấy lại bản đã cấp. Không sinh số mới cho đơn đã có. */
  issueBookingContract: async (bookingId: number): Promise<BookingContractInfo> => {
    const response = await api.post(`/admin/bookings/${bookingId}/contract`);
    return response.data.data as BookingContractInfo;
  },

  markContractSigned: async (contractId: number, note?: string) => {
    const response = await api.put(`/admin/contracts/${contractId}/signed`, {
      note: note ?? null,
    });
    return response.data?.message ?? "Đã ghi nhận ký.";
  },

  // --- YÊU CẦU THAY ĐỔI CỦA KHÁCH ---
  // --- Sổ giao dịch tổng -----------------------------------------------------------------------

  getTransactions: async (
    params: TransactionFilters & { page?: number },
  ): Promise<TransactionListResponse | null> => {
    const response = await api.get("/admin/transactions", { params });
    return extractObject<TransactionListResponse>(response);
  },

  /**
   * Tải sổ ra CSV theo đúng bộ lọc đang xem.
   *
   * Qua axios rồi dựng liên kết blob, không đặt `href` thẳng: thẻ `<a>` không mang được tiêu đề
   * Authorization nên sẽ nhận 401.
   */
  exportTransactions: async (params: TransactionFilters): Promise<void> => {
    const response = await api.get("/admin/transactions/export", {
      params,
      responseType: "blob",
    });

    const disposition = String(response.headers?.["content-disposition"] ?? "");
    const khop = disposition.match(/filename="?([^"]+)"?/);

    const url = URL.createObjectURL(response.data as Blob);
    const the = document.createElement("a");
    the.href = url;
    the.download = khop?.[1] ?? "so-giao-dich.csv";
    document.body.appendChild(the);
    the.click();

    document.body.removeChild(the);
    URL.revokeObjectURL(url);
  },

  // --- Sổ giao dịch của một đơn, và hoàn tiền ---------------------------------------------------

  getRefundQueue: async (settled = false): Promise<RefundQueueResponse | null> => {
    const response = await api.get("/admin/refunds", { params: { settled: settled ? 1 : 0 } });
    return extractObject<RefundQueueResponse>(response);
  },

  /** Những đơn khách còn nợ công ty. `withinDays` lọc theo số ngày tới lúc khởi hành. */
  getReceivables: async (params: {
    q?: string;
    withinDays?: number;
    page?: number;
  } = {}): Promise<ReceivableResponse | null> => {
    const response = await api.get("/admin/receivables", {
      params: {
        q: params.q || undefined,
        within_days: params.withinDays || undefined,
        page: params.page ?? 1,
      },
    });
    return extractObject<ReceivableResponse>(response);
  },

  // --- Hộp thư liên hệ và bản tin --------------------------------------------------------------

  getContactMessages: async (
    status = "",
    page = 1,
  ): Promise<ContactMessageListResponse | null> => {
    const response = await api.get("/admin/contact-messages", { params: { status, page } });
    return extractObject<ContactMessageListResponse>(response);
  },

  /** Đảo trạng thái: chưa xử lý ↔ đã xử lý. Máy chủ quyết chiều. */
  toggleContactHandled: async (id: number, note?: string) => {
    const response = await api.put(`/admin/contact-messages/${id}/handled`, { note });
    return response.data?.message ?? "Đã cập nhật.";
  },

  getNewsletterSubscribers: async (page = 1) => {
    const response = await api.get("/admin/newsletter-subscribers", { params: { page } });
    return extractObject<{
      data: { id: number; email: string; created_at: string }[];
      total: number;
      current_page: number;
      last_page: number;
    }>(response);
  },

  /**
   * Tải danh sách email ra CSV.
   *
   * Đi qua axios rồi dựng liên kết blob, không đặt `href` thẳng tới điểm cuối: thẻ `<a>` không
   * mang được tiêu đề Authorization, nên đường ấy sẽ trả 401. Cùng cách với xuất danh sách đoàn.
   */
  exportNewsletterSubscribers: async (): Promise<void> => {
    const response = await api.get("/admin/newsletter-subscribers/export", {
      responseType: "blob",
    });

    const disposition = String(response.headers?.["content-disposition"] ?? "");
    const khop = disposition.match(/filename="?([^"]+)"?/);

    const url = URL.createObjectURL(response.data as Blob);
    const the = document.createElement("a");
    the.href = url;
    the.download = khop?.[1] ?? "nguoi-dang-ky-nhan-tin.csv";
    document.body.appendChild(the);
    the.click();

    document.body.removeChild(the);
    URL.revokeObjectURL(url);
  },

  // --- Tài khoản ------------------------------------------------------------------------------

  getUsers: async (params: {
    q?: string;
    role?: string;
    status?: string;
    page?: number;
  }): Promise<AdminUserListResponse | null> => {
    const response = await api.get("/admin/users", { params });
    return extractObject<AdminUserListResponse>(response);
  },

  /** Khóa nếu đang mở, mở nếu đang khóa. Máy chủ quyết chiều, không nhận tham số trạng thái. */
  toggleUserStatus: async (id: number) => {
    const response = await api.put(`/admin/users/${id}/status`);
    return {
      message: response.data?.message ?? "Đã cập nhật trạng thái tài khoản.",
      status: response.data?.data?.status as AdminUserStatus,
    };
  },

  // --- Kiểm duyệt đánh giá ------------------------------------------------------------------

  getReviews: async (
    status = "pending",
    page = 1,
  ): Promise<AdminReviewListResponse | null> => {
    const response = await api.get(`/admin/reviews?status=${status}&page=${page}`);
    return extractObject<AdminReviewListResponse>(response);
  },

  approveReview: async (id: number) => {
    const response = await api.put(`/admin/reviews/${id}/approve`);
    return response.data?.message ?? "Đã duyệt đánh giá.";
  },

  rejectReview: async (id: number, reason: string) => {
    const response = await api.put(`/admin/reviews/${id}/reject`, { reason });
    return response.data?.message ?? "Đã từ chối đánh giá.";
  },

  /** Gửi chuỗi rỗng để gỡ câu trả lời đang có. */
  replyToReview: async (id: number, reply: string) => {
    const response = await api.put(`/admin/reviews/${id}/reply`, { reply });
    return response.data?.message ?? "Đã lưu câu trả lời.";
  },

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
   * Bàn giao hướng dẫn viên giữa chừng.
   *
   * Tách khỏi phân công thường vì bắt buộc kèm lý do và tình trạng đoàn. Gộp chung thì sớm muộn
   * sẽ có người đổi người dẫn bằng màn phân công và bỏ qua biên bản.
   */
  /**
   * Lịch sử bàn giao toàn công ty: gần đây có bao nhiêu lần đổi người.
   */
  getHandoverHistory: async (): Promise<HandoverHistoryResponse | null> => {
    const response = await api.get("/admin/handovers");
    return extractObject<HandoverHistoryResponse>(response);
  },

  /** Phiếu bàn giao hướng dẫn viên gửi lên, chờ điều hành chọn người thay. */
  getPendingHandoverRequests: async (): Promise<PendingHandoverRequest[]> => {
    const response = await api.get("/admin/handover-requests");
    return extractArray<PendingHandoverRequest>(response);
  },

  /*
   * Hai cách xử lý một phiếu, không có luồng duyệt nhiều bước.
   *
   * `resolve` chỉ định người mới và đi qua đúng đường bàn giao chung của máy chủ.
   * `close` đóng phiếu mà không đổi ai — gộp cả "không đồng ý" lẫn "hướng dẫn viên đỡ rồi",
   * khác nhau ở câu ghi chú chứ không cần thành hai trạng thái.
   */
  resolveHandoverRequest: async (id: number, toGuideId: number, reviewNote?: string) => {
    const response = await api.put(`/admin/handover-requests/${id}/resolve`, {
      to_guide_id: toGuideId,
      review_note: reviewNote || null,
    });
    return response.data?.message ?? "Đã bàn giao.";
  },

  closeHandoverRequest: async (id: number, reviewNote: string) => {
    const response = await api.put(`/admin/handover-requests/${id}/close`, {
      review_note: reviewNote,
    });
    return response.data?.message ?? "Đã đóng phiếu.";
  },

  getHandoverPanel: async (scheduleId: number): Promise<HandoverPanelResponse | null> => {
    const response = await api.get(`/admin/schedules/${scheduleId}/handovers`);
    return extractObject<HandoverPanelResponse>(response);
  },

  handoverGuide: async (
    scheduleId: number,
    payload: {
      from_guide_id: number;
      to_guide_id: number;
      reason: string;
      handover_note: string;
    },
  ) => {
    const response = await api.post(`/admin/schedules/${scheduleId}/handover`, payload);
    return response.data?.message ?? "Đã bàn giao.";
  },

  // --- O: Sự cố dọc đường ---
  // Đây là nơi duy nhất quyết được tiền của một sự cố. Hướng dẫn viên chỉ báo cáo.

  getIncidents: async (status?: string): Promise<IncidentListResponse | null> => {
    const response = await api.get("/admin/incidents", {
      params: status ? { status } : {},
    });
    return extractObject<IncidentListResponse>(response);
  },

  getIncident: async (id: number): Promise<IncidentDetailResponse | null> => {
    const response = await api.get(`/admin/incidents/${id}`);
    return extractObject<IncidentDetailResponse>(response);
  },

  /** Ghi phương án và phân bổ chi phí. Các khoản lập ra ở trạng thái chờ duyệt. */
  resolveIncident: async (
    id: number,
    payload: {
      resolution: string;
      cost_delta?: number | null;
      who_bears?: string | null;
      charges: IncidentChargeInput[];
    },
  ) => {
    const response = await api.post(`/admin/incidents/${id}/resolve`, payload);
    return response.data?.message ?? "Đã ghi phương án.";
  },

  approveSurcharge: async (id: number) => {
    const response = await api.put(`/admin/surcharges/${id}/approve`);
    return response.data?.message ?? "Đã duyệt.";
  },

  waiveSurcharge: async (id: number, reason: string) => {
    const response = await api.put(`/admin/surcharges/${id}/waive`, { reason });
    return response.data?.message ?? "Đã miễn.";
  },

  /** Khách đã nghe giải thích và đồng ý. Bước bắt buộc trước khi thu. */
  recordSurchargeConsent: async (id: number, note?: string) => {
    const response = await api.put(`/admin/surcharges/${id}/consent`, { note: note ?? null });
    return response.data?.message ?? "Đã ghi nhận khách đồng ý.";
  },

  /** Ghi nhận đã thu (hoặc đã hoàn). Đây là bước đưa khoản vào sổ giao dịch của đơn. */
  settleSurcharge: async (
    id: number,
    payload: { method?: string | null; reference?: string | null; note?: string | null },
  ) => {
    const response = await api.put(`/admin/surcharges/${id}/settle`, payload);
    return response.data?.message ?? "Đã ghi nhận.";
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
    reasonCategory?: TransferReasonCategory,
  ): Promise<TransferOptionsResponse | null> => {
    const response = await api.get(
      `/admin/bookings/${bookingId}/transfer-options?same_tour=${sameTour ? 1 : 0}`
        + `&initiated_by=${initiatedBy}`
        + (reasonCategory ? `&reason_category=${reasonCategory}` : ""),
    );
    return extractObject<TransferOptionsResponse>(response);
  },

  /**
   * `initiatedBy` quyết định hai luật: hạn báo trước 7 ngày và phí đổi lịch.
   *
   * Khách gọi lên xin đổi thì vẫn là `customer`, dù người bấm nút là điều hành. Gửi `company`
   * cho mọi trường hợp là nói dối hệ thống để lách hai luật đó.
   *
   * `contactLogId` là cuộc trao đổi với khách làm căn cứ — máy chủ từ chối nếu thiếu, nếu bản ghi
   * không phải "khách đồng ý", hoặc nếu nó đã dùng cho một lần chuyển trước.
   */
  transferBooking: async (
    bookingId: number,
    toScheduleId: number,
    reason: string,
    contactLogId: number,
    reasonCategory: TransferReasonCategory,
    initiatedBy: "customer" | "company" = "customer",
  ) => {
    const response = await api.post(`/admin/bookings/${bookingId}/transfer`, {
      to_schedule_id: toScheduleId,
      contact_log_id: contactLogId,
      reason_category: reasonCategory,
      reason,
      initiated_by: initiatedBy,
    });
    return response.data?.message ?? "Đã chuyển chuyến.";
  },

  /** Nhật ký liên hệ của một đơn, mới trước cũ sau. */
  getContactLogs: async (bookingId: number): Promise<ContactLog[]> => {
    const response = await api.get(`/admin/bookings/${bookingId}/contact-logs`);
    return extractArray<ContactLog>(response);
  },

  createContactLog: async (bookingId: number, payload: ContactLogPayload) => {
    const response = await api.post(`/admin/bookings/${bookingId}/contact-logs`, payload);
    return response.data?.message ?? "Đã ghi nhận cuộc liên hệ.";
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

  /**
   * Sửa thông tin liên hệ của người đặt.
   *
   * Không bị hạn chốt danh sách khóa: đây là số hướng dẫn viên gọi khách, sát ngày mới càng cần
   * đúng. Chỉ dừng khi đơn đã kết thúc vòng đời.
   */
  updateBookingContact: async (
    id: number,
    payload: { customer_name: string; customer_email: string; customer_phone?: string | null },
  ) => {
    const response = await api.put(`/admin/bookings/${id}/contact`, payload);
    return response.data?.message ?? "Đã cập nhật thông tin liên hệ.";
  },

  getBookingHistory: async (id: number): Promise<BookingAuditEntry[]> => {
    const response = await api.get(`/admin/bookings/${id}/history`);
    return response.data?.data ?? [];
  },

  /**
   * Dự báo hủy, tính theo đúng loại hủy sắp chọn.
   *
   * Khách đổi ý thì áp bảng phí; công ty đơn phương hủy thì hoàn đủ số đã thu. Hai con số khác
   * nhau, nên màn xem trước phải hỏi máy chủ theo đúng lựa chọn — nếu không thì số hiện ra và số
   * thực chi là hai số khác nhau.
   */
  getCancelPreview: async (
    id: number,
    cancelType: CancelType = "by_customer",
  ): Promise<CancelPreview | null> => {
    const response = await api.get(`/admin/bookings/${id}/cancel-preview`, {
      params: { cancel_type: cancelType },
    });
    return extractObject<CancelPreview>(response);
  },

  cancelBooking: async (
    id: number,
    reason: string,
    cancelType: CancelType = "by_customer",
  ): Promise<Booking | null> => {
    const response = await api.put(`/admin/bookings/${id}/cancel`, {
      cancel_reason: reason,
      cancel_type: cancelType,
    });
    return extractObject<Booking>(response);
  },

  // Không còn `reopenBooking`: hủy là trạng thái kết thúc, hủy nhầm thì đặt lại đơn mới.

  // --- CHÍNH SÁCH HỦY ---
  /*
   * Một bảng phí hủy duy nhất cho toàn hệ thống — không còn danh sách, không tạo, không xóa.
   * Máy chủ tự dựng bảng mặc định nếu cơ sở dữ liệu chưa có gì, nên hàm này không trả về null.
   */
  getCancellationPolicy: async (): Promise<CancellationPolicy | null> => {
    const response = await api.get("/admin/cancellation-policies");
    return extractObject<CancellationPolicy>(response);
  },

  updateCancellationPolicy: async (
    payload: CancellationPolicyPayload,
  ): Promise<CancellationPolicy | null> => {
    const response = await api.put("/admin/cancellation-policies", payload);
    return extractObject<CancellationPolicy>(response);
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

  /**
   * Ai đã từ chối chuyến này và vì sao.
   *
   * Từ chối gỡ người khỏi danh sách, nên nhìn chuyến chỉ thấy thiếu người chứ không biết đã có
   * ai trả lời. Đọc lúc mở hộp thoại xếp người, để khỏi gán lại đúng người vừa nói không.
   */
  getScheduleGuideDeclines: async (scheduleId: number): Promise<GuideDecline[]> => {
    const response = await api.get(`/admin/tour-schedules/${scheduleId}/guide-declines`);
    return extractArray<GuideDecline>(response);
  },

  /**
   * Chấm cả đội ngũ cho một chuyến: ai phù hợp, ai bị chặn, và vì sao.
   *
   * Trả về **cả người bị chặn**. Lọc bỏ ở giao diện thì điều hành đi tìm mãi một cái tên đáng lẽ
   * phải có mà không hiểu vì sao mất.
   */
  getScheduleGuideSuitability: async (scheduleId: number): Promise<GuideSuitability[]> => {
    const response = await api.get(`/admin/tour-schedules/${scheduleId}/guide-suitability`);
    return extractArray<GuideSuitability>(response);
  },

  /** Lưu hồ sơ năng lực. Ghi đè cả hồ sơ: ô để trống nghĩa là xóa, không phải giữ nguyên. */
  updateGuideProfile: async (guideId: number, payload: GuideProfilePayload) => {
    const response = await api.put(`/admin/guides/${guideId}/profile`, payload);
    return response.data?.message ?? "Đã lưu hồ sơ năng lực.";
  },

  // --- BOOKING THEO ĐOÀN (điểm 14) ---

  getGroupBookings: async (page = 1, status?: string): Promise<PaginatedResponse<GroupBookingRequestRow> | null> => {
    const query = status ? `&status=${status}` : "";
    const response = await api.get(`/admin/group-bookings?page=${page}${query}`);
    return extractObject<PaginatedResponse<GroupBookingRequestRow>>(response);
  },

  /** Báo giá hoặc báo giá lại. Giá là quyết định của điều hành, hệ thống chỉ ghi. */
  quoteGroupBooking: async (
    id: number,
    payload: { price_per_person: number; free_slots: number; expires_at: string; note?: string },
  ) => {
    const response = await api.put(`/admin/group-bookings/${id}/quote`, payload);
    return response.data?.message ?? "Đã lưu báo giá.";
  },

  /** Chốt: bước duy nhất chiếm chỗ thật. Số khách là con số hai bên vừa thống nhất. */
  confirmGroupBooking: async (id: number, finalGuests: number) => {
    const response = await api.put(`/admin/group-bookings/${id}/confirm`, {
      final_guests: finalGuests,
    });
    return response.data?.message ?? "Đã chốt đoàn.";
  },

  rejectGroupBooking: async (id: number, reason: string) => {
    const response = await api.put(`/admin/group-bookings/${id}/reject`, { reason });
    return response.data?.message ?? "Đã từ chối yêu cầu.";
  },

  getBookingLedger: async (bookingId: number): Promise<BookingLedger | null> => {
    const response = await api.get(`/admin/bookings/${bookingId}/payments`);
    return extractObject<BookingLedger>(response);
  },

  recordBookingPayment: async (
    bookingId: number,
    payload: { kind: string; amount: number; method?: string; reference?: string; note?: string },
  ) => {
    const response = await api.post(`/admin/bookings/${bookingId}/payments`, payload);
    return response.data?.message ?? "Đã ghi vào sổ giao dịch.";
  },

  /** Đoàn giảm số khách — đơn lẻ bị máy chủ từ chối, muốn đổi số người thì hủy đặt lại. */
  reduceBookingGuests: async (bookingId: number, newGuests: number, reason?: string) => {
    const response = await api.put(`/admin/bookings/${bookingId}/reduce-guests`, {
      new_guests: newGuests,
      reason,
    });
    return response.data?.message ?? "Đã giảm số khách.";
  },

  // --- SERVICES (Dịch vụ tour đã bao gồm trong giá bán, gán theo tour) ---
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



