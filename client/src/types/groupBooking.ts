/**
 * Booking theo đoàn — điểm 14.
 *
 * Một yêu cầu đoàn KHÔNG phải một đơn hàng: nó là giai đoạn thương lượng. Chỉ khi điều hành chốt
 * thì mới sinh `Booking` thật chiếm chỗ thật, và từ đó mọi nghiệp vụ đi trên đơn.
 */

export type GroupRequestStatus =
  | "pending_quote"
  | "quoted"
  | "confirmed"
  | "rejected"
  | "withdrawn";

export interface GroupQuote {
  price_per_person: number;
  /** Suất miễn phí (thường cho trưởng đoàn) — miễn tiền nhưng vẫn chiếm ghế. */
  free_slots: number;
  note: string | null;
  expires_at: string | null;
  /** Suy ra lúc đọc từ expires_at, không phải một trạng thái được lưu. */
  expired: boolean;
  quoted_by?: string | null;
}

/** Một dòng trong bảng của điều hành. */
export interface GroupBookingRequestRow {
  id: number;
  status: GroupRequestStatus;
  status_label: string;
  tour_title: string | null;
  schedule_id: number;
  start_date: string | null;
  /** Hiện ngay trên danh sách: yêu cầu 40 người vào chuyến còn 5 chỗ phải thấy trước khi báo giá. */
  remaining_seats: number | null;
  booking_deadline: string | null;
  contact_name: string;
  contact_email: string;
  contact_phone: string;
  estimated_guests: number;
  company_name: string | null;
  tax_code: string | null;
  invoice_address: string | null;
  note: string | null;
  quote: GroupQuote | null;
  rejected_reason: string | null;
  booking: {
    id: number;
    guests: number;
    total_amount: number;
    status: string;
    paid_in_full: boolean;
  } | null;
  created_at: string;
}

/** Những gì khách thấy khi tra cứu bằng mã. */
export interface GroupBookingPublicView {
  public_token: string;
  status: GroupRequestStatus;
  status_label: string;
  tour_title: string | null;
  start_date: string | null;
  contact_name: string;
  estimated_guests: number;
  quote: GroupQuote | null;
  rejected_reason: string | null;
  booking: {
    public_token: string;
    guests: number;
    total_amount: number;
    status: string;
    paid_in_full: boolean;
  } | null;
}

/** Một bút toán trên sổ giao dịch. Số tiền luôn dương, `kind` quyết định dấu. */
export interface PaymentEntry {
  id: number;
  kind: "deposit" | "balance" | "refund";
  kind_label: string;
  amount: number;
  method: string | null;
  reference: string | null;
  note: string | null;
  paid_at: string;
  recorded_by: string | null;
}

/** Tài khoản khách khai lúc gửi yêu cầu hủy. null khi đơn hủy qua đường khác. */
export interface RefundBankInfo {
  account_number: string;
  bank_name: string | null;
  account_holder: string | null;
}

/**
 * Sổ giao dịch của một đơn: số đã thu là TỔNG của sổ, không phải một cột bị ghi đè.
 *
 * Sổ này ra đời cùng booking đoàn nên kiểu vẫn nằm ở tệp này, nhưng từ khi đơn lẻ cũng trả nhiều
 * đợt thì nó áp cho mọi đơn.
 */
export interface BookingLedger {
  total_amount: number;
  net_paid: number;
  /** Còn thiếu bao nhiêu so với giá trị đơn. */
  balance_due: number;
  paid_in_full: boolean;
  /** Nghĩa vụ hoàn chốt tại thời điểm hủy; 0 khi đơn chưa hủy. */
  refund_due: number;
  refunded: number;
  refund_outstanding: number;
  refund_bank: RefundBankInfo | null;
  entries: PaymentEntry[];
}
